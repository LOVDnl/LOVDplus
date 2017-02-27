<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-11-28
 * Modified    : 2017-02-06
 * For LOVD+   : 3.0-18
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *************/

//define('ROOT_PATH', '../');
define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__) . '/../'));
define('FORMAT_ALLOW_TEXTPLAIN', true);


$_GET['format'] = 'text/plain';
// To prevent notices when running inc-init.php.
$_SERVER = array_merge($_SERVER, array(
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => 'scripts/convert_and_merge_data_files.php',
    'QUERY_STRING' => '',
    'REQUEST_METHOD' => 'GET',
));
// Try and improve HTTP_HOST, since settings may depend on it.
$aPath = explode('/', trim(dirname($_SERVER['SCRIPT_NAME']), '/'));
foreach ($aPath as $sDirName) {
    // Stupid but effective check.
    if (preg_match('/^((([0-9a-z][-0-9a-z]*[0-9a-z]|[0-9a-z])\.)+[a-z]{2,6})$/', $sDirName)) {
        // Valid host name.
        $_SERVER['HTTP_HOST'] = $sDirName;
        break;
    }
}
require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-genes.php';
// 128MB was not enough for a 100MB file. We're already no longer using file(), now we're using fgets().
// But still, loading all the gene and transcript data, uses too much memory. After some 18000 lines, the thing dies.

// Setting to 1.5GB, but still maybe we'll run into problems. Do we need to reset the genes and transcripts arrays after each chromosome?
ini_set('memory_limit', '4294967296'); // MGHA AM - This may need to be site specific so you can adjust this to your servers available resources. We have set it to bytes here to avoid some issues with our dev environment.

// But we don't care about your session (in fact, it locks the whole LOVD if we keep this page running).
session_write_close();
set_time_limit(0);
ignore_user_abort(true);





// Initialize curl connection.
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Set proxy
if ($_CONF['proxy_host']) {
    curl_setopt($ch, CURLOPT_PROXY, 'https://' . $_CONF['proxy_host'] . ':' . $_CONF['proxy_port']);
}
if (!empty($_CONF['proxy_username']) && !empty($_CONF['proxy_password'])) {
    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $_CONF['proxy_username'] . ':' . $_CONF['proxy_password']);
}

function mutalyzer_getTranscriptsAndInfo($ref, $gene) {
    global $ch;

    $sUrl = 'https://mutalyzer.nl/json/getTranscriptsAndInfo?genomicReference=' . $ref . '&geneName=' . $gene;
    curl_setopt($ch, CURLOPT_URL, $sUrl);

    return curl_exec($ch);
}

function mutalyzer_numberConversion($build, $variant) {
    global $ch;

    $sUrl = 'https://mutalyzer.nl/json/numberConversion?build=' . $build . '&variant=' . $variant;
    curl_setopt($ch, CURLOPT_URL, $sUrl);

    return curl_exec($ch);
}

function mutalyzer_runMutalyzer($variant) {
    global $ch;

    $sUrl = 'https://mutalyzer.nl/json/runMutalyzer?variant=' . $variant;
    curl_setopt($ch, CURLOPT_URL, $sUrl);

    return curl_exec($ch);
}







if (empty($_INI['import']['exit_on_annotation_error'])) {
    $_INI['import']['exit_on_annotation_error'] = 'yes';
}

if (empty($_INI['import']['max_annotation_error_allowed'])) {
    $_INI['import']['max_annotation_error_allowed'] = 50;
}






// This script will be called from localhost by a cron job.

// Call adapter script to apply any instance specific re-formatting.
$zAdapter = lovd_initAdapter();


// Define the array of suffixes for the files names expected.
$aSuffixes = array(
    'meta' => 'meta.lovd',
    'vep' => 'directvep.data.lovd',
    'total.tmp' => 'total.data.tmp',
    'total' => 'total.data.lovd',
    'error' => 'error'
);

// Define list of genes to ignore, because they can't be found by the HGNC.
// LOC* genes are always ignored, because they never work (HGNC doesn't know them).
$aGenesToIgnore = $zAdapter->prepareGenesToIgnore();


// Define list of gene aliases. Genes not mentioned in here, are searched for in the database. If not found,
// HGNC will be queried and gene will be added. If the symbols don't match, we'll get a duplicate key error.
// Insert those genes here.
$aGeneAliases = $zAdapter->prepareGeneAliases();


// Define list of columns that we are recognizing.
$aColumnMappings = $zAdapter->prepareMappings();


// These columns will be taken out of $aVariant and stored as the VOG data.
// This array is also used to build the LOVD file.
$aColumnsForVOG = array(
    'id',
    'allele',
    'effectid',
    'chromosome',
    'position_g_start',
    'position_g_end',
    'type',
    'mapping_flags',
    'average_frequency',
    'owned_by',
    'statusid',
    'created_by',
    'created_date',
    'edited_by',
    'edited_date',
    'VariantOnGenome/DBID',
);
// These columns will be taken out of $aVariant and stored as the VOT data.
// This array is also used to build the LOVD file.
$aColumnsForVOT = array(
    'id',
    'transcriptid',
    'effectid',
    'position_c_start',
    'position_c_start_intron',
    'position_c_end',
    'position_c_end_intron'
);

$aVOTKeys = array();

// Default values.
$aDefaultValues = array(
    'effectid' => $_SETT['var_effect_default'],
    'mapping_flags' => '0',
//    'owned_by' => 0, // '0' is not a valid value, because "LOVD" is removed from the selection list. When left empty, it will default to the user running LOVD, though.
    'statusid' => STATUS_HIDDEN,
    'created_by' => 0,
    'created_date' => date('Y-m-d H:i:s'),
);







$nMutalyzerRetries = 5; // The number of times we retry the Mutalyzer API call if the connection fails.
$nFilesBeingMerged = 0; // We're counting how many files are being merged at the time, because we don't want to stress the system too much.
$nMaxFilesBeingMerged = 5; // We're allowing only five processes working concurrently on merging files (or so many failed attempts that have not been cleaned up).
$aFiles = array(); // array(ID => array(files), ...);





function lovd_initAdapter()
{

    // Run adapter script to convert input file formats.

    global $_INI;

    $sAdaptersDir = ROOT_PATH . 'scripts/adapters/';
    $sAdapterName = 'DEFAULT';

    // Even if instance name exists, still check if the actual adapter library file exists.
    // If adapter library file does not exist, we still use default adapter.
    if (!empty($_INI['instance']['name']) && file_exists($sAdaptersDir . 'conversion_adapter.lib.'. strtoupper($_INI['instance']['name']) .'.php')) {
        $sAdapterName = strtoupper($_INI['instance']['name']);
    }

    require_once $sAdaptersDir . 'conversion_adapter.lib.'. $sAdapterName .'.php';

    // Camelcase the adapter name.
    $sClassPrefix = ucwords(strtolower(str_replace('_', ' ', $sAdapterName)));
    $sClassPrefix = str_replace(' ', '', $sClassPrefix);
    $sClassName = 'LOVD_' . $sClassPrefix . 'DataConverter';

    $zAdapter = new $sClassName($sAdaptersDir);

    return $zAdapter;
}






function lovd_handleAnnotationError(&$aVariant, $sErrorMsg) {
    global $_INI, $fError, $nAnnotationErrors, $nLine, $sFileError;
    $nAnnotationErrors++;

    $sLineErrorMsg = "LINE " . $nLine . " - VariantOnTranscript data dropped: " . $sErrorMsg . "\n";
    if ($fError) {
        fwrite($fError, $sLineErrorMsg);
    }
    print($sLineErrorMsg);

    $bExitOnError = (substr(strtolower($_INI['import']['exit_on_annotation_error']), 0, 1) === 'y'? true : false);
    if ($bExitOnError) {
        die("ERROR: Please update your data and re-run this script.\n");
    }

    // We want to stop the script if there are too many lines of data with annotations issues.
    // We want users to check their data before they continue.
    if ($nAnnotationErrors > $_INI['import']['max_annotation_error_allowed']) {
        $sFileMessage = (filesize($sFileError) === 0?'' : "Please check details of dropped annotation data in " . $sFileError . "\n");
        die("ERROR: Script cannot continue because this file has too many lines of annotation data that this script cannot handle.\n"
            . $nAnnotationErrors . " lines of transcripts data was dropped.\nPlease update your data and re-run this script.\n"
            . $sFileMessage);
    }

    // Otherwise, keep the VariantOnGenome data only, and add some data in Remarks.
    if (isset($aVariant['VariantOnGenome/Remarks'])) {
        $aVariant['VariantOnGenome/Remarks'] .= $sErrorMsg;
    }

    return $nAnnotationErrors;
}





function lovd_getVariantDescription (&$aVariant, $sRef, $sAlt)
{
    // Constructs a variant description from $sRef and $sAlt and adds it to $aVariant in a new 'VariantOnGenome/DNA' key.
    // The 'position_g_start' and 'position_g_end' keys in $aVariant are adjusted accordingly and a 'type' key is added too.
    // The numbering scheme is either g. or m. and depends on the 'chromosome' key in $aVariant.

    // Make all bases uppercase.
    $sRef = strtoupper($sRef);
    $sAlt = strtoupper($sAlt);

    // Use the right prefix for the numbering scheme.
    $sHGVSPrefix = 'g.';
    if ($aVariant['chromosome'] == 'M') {
        $sHGVSPrefix = 'm.';
    }

    // Even substitutions are sometimes mentioned as longer Refs and Alts, so we'll always need to isolate the actual difference.
    $aVariant['position_g_start'] = $aVariant['position'];
    $aVariant['position_g_end'] = $aVariant['position'] + strlen($sRef) - 1;

    // 'Eat' letters from either end - first left, then right - to isolate the difference.
    $sAltOriginal = $sAlt;
    while (strlen($sRef) > 0 && strlen($sAlt) > 0 && $sRef{0} == $sAlt{0}) {
        $sRef = substr($sRef, 1);
        $sAlt = substr($sAlt, 1);
        $aVariant['position_g_start'] ++;
    }
    while (strlen($sRef) > 0 && strlen($sAlt) > 0 && $sRef[strlen($sRef) - 1] == $sAlt[strlen($sAlt) - 1]) {
        $sRef = substr($sRef, 0, -1);
        $sAlt = substr($sAlt, 0, -1);
        $aVariant['position_g_end'] --;
    }

    // Substitution, or something else?
    if (strlen($sRef) == 1 && strlen($sAlt) == 1) {
        // Substitutions.
        $aVariant['type'] = 'subst';
        $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . $sRef . '>' . $sAlt;
    } else {
        // Insertions/duplications, deletions, inversions, indels.

        // Now find out the variant type.
        if (strlen($sRef) > 0 && strlen($sAlt) == 0) {
            // Deletion.
            $aVariant['type'] = 'del';
            if ($aVariant['position_g_start'] == $aVariant['position_g_end']) {
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . 'del';
            } else {
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'del';
            }
        } elseif (strlen($sAlt) > 0 && strlen($sRef) == 0) {
            // Something has been added... could be an insertion or a duplication.
            if (substr($sAltOriginal, strrpos($sAltOriginal, $sAlt) - strlen($sAlt), strlen($sAlt)) == $sAlt) {
                // Duplicaton.
                $aVariant['type'] = 'dup';
                $aVariant['position_g_start'] -= strlen($sAlt);
                if ($aVariant['position_g_start'] == $aVariant['position_g_end']) {
                    $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . 'dup';
                } else {
                    $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'dup';
                }
            } else {
                // Insertion.
                $aVariant['type'] = 'ins';
                // Exchange g_start and g_end; after the 'letter eating' we did, start is actually end + 1!
                $aVariant['position_g_start'] --;
                $aVariant['position_g_end'] ++;
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'ins' . $sAlt;
            }
        } elseif ($sRef == strrev(str_replace(array('a', 'c', 'g', 't'), array('T', 'G', 'C', 'A'), strtolower($sAlt)))) {
            // Inversion.
            $aVariant['type'] = 'inv';
            $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'inv';
        } else {
            // Deletion/insertion.
            $aVariant['type'] = 'delins';
            if ($aVariant['position_g_start'] == $aVariant['position_g_end']) {
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . 'delins' . $sAlt;
            } else {
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'delins' . $sAlt;
            }
        }
    }
}





function lovd_getVariantPosition ($sVariant, $aTranscript = array())
{
    // Constructs an array with the position fields 'start', 'start_intron', 'end', 'end_intron', from the variant description.
    // Whether the input is chromosomal or transcriptome positions, doesn't matter.

    $aReturn = array(
        'start' => 0,
        'start_intron' => 0,
        'end' => 0,
        'end_intron' => 0,
    );

    if (preg_match('/^[cgmn]\.((?:\-|\*)?\d+)([-+]\d+)?(?:[ACGT]>[ACGT]|(?:_((?:\-|\*)?\d+)([-+]\d+)?)?(?:d(?:el(?:ins)?|up)|inv|ins)(?:[ACGT])*|\[[0-9]+\](?:[ACGT]+)?)$/', $sVariant, $aRegs)) {
        foreach (array(1, 3) as $i) {
            if (isset($aRegs[$i]) && $aRegs[$i]{0} == '*') {
                // Position in 3'UTR. Add CDS offset.
                if ($aTranscript && isset($aTranscript['position_c_cds_end'])) {
                    $aRegs[$i] = (int) substr($aRegs[$i], 1) + $aTranscript['position_c_cds_end'];
                } else {
                    // Whatever we'll do, it will be wrong anyway.
                    return $aReturn;
                }
            }
        }

        $aReturn['start'] = (int) $aRegs[1];
        if (isset($aRegs[2]) && $aRegs[2]) {
            $aReturn['start_intron'] = (int) $aRegs[2]; // (int) to get rid of the '+' if it's there.
        }
        if (isset($aRegs[4]) && $aRegs[4]) {
            $aReturn['end_intron'] = (int) $aRegs[4]; // (int) to get rid of the '+' if it's there.
        }
        if (isset($aRegs[3])) {
            $aReturn['end'] = (int) $aRegs[3];
        } else {
            $aReturn['end'] = $aReturn['start'];
            $aReturn['end_intron'] = $aReturn['start_intron'];
        }
    }

    return $aReturn;
}



$zAdapter->convertInputFiles();

// Loop through the files in the dir and try and find a meta and data file, that match but have no total data file.
$h = opendir($_INI['paths']['data_files']);
if (!$h) {
    die('Can\'t open directory.' . "\n");
}
while (($sFile = readdir($h)) !== false) {
    if ($sFile{0} == '.') {
        // Current dir, parent dir, and hidden files.
        continue;
    }


    if (preg_match('/^'. $zAdapter->getInputFilePrefixPattern() .'\.(' . implode('|', array_map('preg_quote', array_values($aSuffixes))) . ')$/', $sFile, $aRegs)) {
        // Files we need to merge.
        list(, $sID, $sFileType) = $aRegs;
        if (!isset($aFiles[$sID])) {
            $aFiles[$sID] = array();
        }
        $aFiles[$sID][] = $sFileType;
    }
}

// Die here, if we have nothing to work with.
if (!$aFiles) {
    die('No files found.' . "\n");
}

// Filter the list of files, to see which ones are already complete.
foreach ($aFiles as $sID => $aFileTypes) {
    if (in_array($aSuffixes['total'], $aFileTypes)) {
        // Already merged.
        unset($aFiles[$sID]);
        continue;
    }
}

// Die here, if we have nothing to do anymore.
if (!$aFiles) {
    die('No files found available for merging.' . "\n");
}

// Report incomplete data sets; meta data without variant data, for instance, and data sets still running (maybe split that, if this happens more often).
foreach ($aFiles as $sID => $aFileTypes) {
    if (!in_array($aSuffixes['meta'], $aFileTypes)) {
        // No meta data.
        unset($aFiles[$sID]);
        print('Meta data missing: ' . $sID . "\n");
    }
    if (!in_array($aSuffixes['vep'], $aFileTypes)) {
        // No variant data.
        unset($aFiles[$sID]);
        print('VEP data missing: ' . $sID . "\n");
    }
    if (in_array($aSuffixes['total.tmp'], $aFileTypes)) {
        // Already working on a merge. We count these, because we don't want too many processes in parallel.
        // FIXME: Should we check the timestamp on the file? Remove really old files, so we can continue?
        $nFilesBeingMerged ++;
        unset($aFiles[$sID]);
        print('Already being merged: ' . $sID . "\n");
    }
}

// Report what we have left.
$nFiles = count($aFiles);
if (!$nFiles) {
    die('No files left to merge.' . "\n");
} else {
    print(str_repeat('-', 60) . "\n" . $nFiles . ' patient' . ($nFiles == 1? '' : 's') . ' with data files ready to be merged.' . "\n");
}

// But don't run, if too many are still active...
if ($nFilesBeingMerged >= $nMaxFilesBeingMerged) {
    die('Too many files being merged at the same time, stopping here.' . "\n");
}





// We're simply taking the first one, with the lowest ID (or actually, alphabetically the lowest ID, since we have the Child|Patient prefix.
// To make sure that we don't hang if one file is messed up, we'll start parsing them one by one, and the first one with an OK header, we take.
$aFiles = array_keys($aFiles);
sort($aFiles);
define('LOG_EVENT', 'ConvertVEPToLOVD');
require ROOT_PATH . 'inc-lib-actions.php';
flush();
@ob_end_flush(); // Can generate errors on the screen if no buffer found.
foreach ($aFiles as $sID) {
    // Try and open the file, check the first line if it conforms to the standard, and start converting.
    print('Working on: ' . $sID . "...\n");
    flush();
    $sFileToConvert = $_INI['paths']['data_files'] . '/' . $sID . '.' . $aSuffixes['vep'];
    $sFileMeta = $_INI['paths']['data_files'] . '/' . $sID . '.' . $aSuffixes['meta'];
    $sFileTmp = $_INI['paths']['data_files'] . '/' . $sID . '.' . $aSuffixes['total.tmp'];
    $sFileDone = $_INI['paths']['data_files'] . '/' . $sID . '.' . $aSuffixes['total'];
    $sFileError = $_INI['paths']['data_files'] . '/' . $sID . '.' . $aSuffixes['error'];

    $fInput = fopen($sFileToConvert, 'r');
    if ($fInput === false) {
        die('Error opening file: ' . $sFileToConvert . ".\n");
    }

    $sHeaders = fgets($fInput);
    $aHeaders = explode("\t", rtrim($sHeaders, "\r\n"));
    foreach ($zAdapter->getRequiredHeaderColumns() as $sColumn) {
        if (!in_array($sColumn, $aHeaders, true)) {
            print('Ignoring file, does not conform to format: ' . $sFileToConvert . ".\n");
            continue 2; // Continue the $aFiles loop.
        }
    }

    // Start creating the output file, based on the meta file. We just add the analysis_status, so the analysis can start directly after importing.
    $aFileMeta = file($sFileMeta, FILE_IGNORE_NEW_LINES);
    foreach ($aFileMeta as $nLine => $sLine) {
        if (strpos($sLine, '{{Screening/') !== false) {
            $aFileMeta[$nLine]   .= "\t\"{{analysis_statusid}}\"";
            $aFileMeta[$nLine+1] .= "\t\"" . ANALYSIS_STATUS_READY . '"';
            break;
        }
    }
    $fOutput = @fopen($sFileTmp, 'w');
    if (!$fOutput || !fputs($fOutput, implode("\r\n", $aFileMeta))) {
        print('Error copying meta file to target: ' . $sFileTmp . ".\n");
        fclose($fOutput);
        continue; // Continue to try the next file.
    }
    fclose($fOutput);

    $fError = @fopen($sFileError, 'w');

    // Isolate the used Screening ID, so we'll connect the variants to the right ID.
    // It could just be 1 always, but they use the Miracle ID.
    // FIXME: This is quite a lot of code, for something simple as that... Can't we do this in an easier way? More assumptions, less checks?

    $aMetaData = file($sFileTmp, FILE_IGNORE_NEW_LINES);
    if (!$aMetaData) {
        print('Error reading out temporary output file: ' . $sFileTmp . ".\n");
        unlink($sFileTmp);
        continue; // Continue to try the next file.
    }
    $bParseColumns = false;
    $nColumnIndexIDMiracle = false;
    $nColumnIndexIDScreening = false;
    $nScreeningID = 0;
    $nMiracleID = 0;


    $zAdapter->readMetadata($aMetaData);
    $nScreeningID = $zAdapter->prepareScreeningID($aMetaData);
    if (empty($nScreeningID)) {
        foreach ($aMetaData as $nLine => $sLine) {
            if (!trim($sLine)) {
                continue;
            }
            $nLine ++;
            if (!$bParseColumns) {
                if (substr($sLine, 0, 17) == '## Individuals ##') {
                    $bParseColumns = 'Individuals';
                } elseif (substr($sLine, 0, 16) == '## Screenings ##') {
                    $bParseColumns = 'Screenings';
                }
            } else {
                if ($nColumnIndexIDMiracle === false && $nColumnIndexIDScreening === false) {
                    // We are expecting columns now, because we just started a new section.
                    if (!preg_match('/^(("\{\{[A-Za-z0-9_\/]+\}\}"|\{\{[A-Za-z0-9_\/]+\}\})\t)+$/', $sLine . "\t")) { // FIXME: Can we make this a simpler regexp?
                        // Columns not found; either we have data without a column header, or a malformed column header. Abort import.
                        print('Error while parsing meta file (line ' . $nLine . '): Expected column header, but got something else.' . "\n");
                        continue 2; // Continue to try the next file.
                    }

                    $aColumns = explode("\t", $sLine);
                    $nColumns = count($aColumns);
                    $aColumns = array_map('trim', $aColumns, array_fill(0, $nColumns, '"{ }'));
                    if ($bParseColumns == 'Individuals' && $nColumnIndexIDMiracle === false) {
                        $nColumnIndexIDMiracle = array_search('id_miracle', $aColumns);
                    } elseif ($bParseColumns == 'Screenings') {
                        $nColumnIndexIDScreening = array_search('id', $aColumns);
                    }
                    if ($nColumnIndexIDScreening === false && $nColumnIndexIDMiracle === false) {
                        print('Error while parsing meta file (line ' . $nLine . '): Expected ID column header, could not find it.' . "\n");
                        continue 2; // Continue to try the next file.
                    }
                    continue; // Data is on the next line.

                } else {
                    // We've got a line of data here. Isolate the values.
                    $aLine = explode("\t", rtrim($sLine, "\r\n"));
                    // For any category, the number of columns should be the same as the number of fields.
                    // However, less fields may be encountered because the spreadsheet program just put tabs and no quotes in empty fields.
                    if (count($aLine) < $nColumns) {
                        $aLine = array_pad($aLine, $nColumns, '');
                    }
                    if ($nColumnIndexIDMiracle !== false) {
                        $nMiracleID = trim($aLine[$nColumnIndexIDMiracle], '"');
                        $nColumnIndexIDMiracle = false;
                    } elseif ($nColumnIndexIDScreening !== false) {
                        $nScreeningID = trim($aLine[$nColumnIndexIDScreening], '"');
                        $nColumnIndexIDScreening = false;
                    }
                    $bParseColumns = false;
                    if ($nMiracleID && $nScreeningID) {
                        break;
                    }
                }
            }
        }
        if (!$nScreeningID || !$nMiracleID) {
            print('Error while parsing meta file: Unable to find the Screening ID and/or Miracle ID.' . "\n");
            // Here, we won't try and remove the temp file. We need it for diagnostics, and it will save us from running into the same error over and over again.
            continue; // Continue to try the next file.
        }
    }

    $zAdapter->setScriptVars(compact('nScreeningID', 'nMiracleID'));
    $nScreeningID = sprintf('%010d', $nScreeningID);
    print('Isolated Screening ID: ' . $nScreeningID . "...\n");
    flush();





    // Now open and parse the file for real, appending to the temporary file.
    // It's usually a big file, and we don't want to use too much memory... so using fgets().
    // First line should be headers, we already read it out somewhere above here.
    // $aHeaders = array_map('trim', $aHeaders, array_fill(0, count($aHeaders), '"')); // In case we ever need to trim off quotes.
    $aHeaders = $zAdapter->prepareHeaders($aHeaders);
    $nHeaders = count($aHeaders);


    // Now start parsing the file, reading it out line by line, building up the variant data in $aData.
    $dStart = time();
    $aMutalyzerCalls = array(
        'getTranscriptsAndInfo' => 0,
        'numberConversion' => 0,
        'runMutalyzer' => 0,
    );
    $tMutalyzerCalls = 0; // Time spent doing Mutalyzer calls.
    $aData = array(); // 'chr1:1234567C>G' => array(array(genomic_data), array(transcript1), array(transcript2), ...)
    print('Parsing file. Current time: ' . date('Y-m-d H:i:s') . ".\n");
    flush();

    $nLine = 0;
    $sLastChromosome = '';
    $aGenes = array(); // GENE => array(<gene_info_from_database>)
    $aTranscripts = array(); // NM_000001.1 => array(<transcript_info>)
    $nHGNC = 0; // Count the number of times HGNC is called.
    $nMutalyzer = 0; // Count the number of times Mutalyzer is called.
    $nAnnotationErrors = 0; // Count the number of line we cannot import.

    // Get all the genes related data in one database call.
    $aResult = $_DB->query('SELECT g.id, g.refseq_UD, g.name FROM ' . TABLE_GENES . ' AS g')->fetchAllAssoc();
    foreach ($aResult as $key => $aGene) {
        $aGene['transcripts_in_UD'] = array();
        $aGenes[$aGene['id']] = $aGene;
    }

    // Get all the transcripts related data in one database call.
    $aResult = $_DB->query('SELECT id, geneid, id_mutalyzer, id_ncbi, position_c_cds_end, position_g_mrna_start, position_g_mrna_end FROM ' . TABLE_TRANSCRIPTS . ' ORDER BY id_ncbi DESC, id DESC')->fetchAllAssoc();
    foreach ($aResult as $key => $aTranscript) {
        $sTranscriptId = $aTranscript['id_ncbi'];
        if (empty($aTranscripts[$sTranscriptId])) {
            $aTranscripts[$sTranscriptId] = $aTranscript;
        }
    }

    $zAdapter->setScriptVars(compact('aGenes', 'aTranscripts'));
    while ($sLine = fgets($fInput)) {
        $nLine ++;
        $bDropTranscriptData = false;
        if (!trim($sLine)) {
            continue;
        }

        // We've got a line of data here. Isolate the values.
        $aLine = explode("\t", rtrim($sLine, "\r\n"));
        // The number of columns should be the same as the number of fields.
        // However, less fields may be encountered, if the last fields were empty.
        if (count($aLine) < $nHeaders) {
            $aLine = array_pad($aLine, $nHeaders, '');
        }
        $aLine = array_combine($aHeaders, $aLine);
        $aVariant = array(); // Will contain the mapped, possibly modified, data.
        // $aLine = array_map('trim', $aLine, array_fill(0, count($aLine), '"')); // In case we ever need to trim off quotes.


        // VCF 4.2 can contain lines with an ALT allele of "*", indicating the allele is
        //  not WT at this position, but affected by an earlier mentioned variant instead.
        // Because these are not actually variants, we ignore them.
        if ($aLine['ALT'] == '*') {
            continue;
        }

        // Reformat variant data if extra modification required by different instance of LOVD.
        $aLine = $zAdapter->prepareVariantData($aLine);

        // Map VEP columns to LOVD columns.
        foreach ($aColumnMappings as $sVEPColumn => $sLOVDColumn) {
            // 2015-10-28; But don't let columns overwrite each other! Problem because we have double mappings; two MAGPIE columns pointing to the same LOVD column.
            if (!isset($aLine[$sVEPColumn]) && isset($aVariant[$sLOVDColumn])) {
                // VEP column doesn't actually exist in the file, but we do already have created the column in the $aVariant array...
                // Never mind then!
                continue;
            }
            
            if (empty($aLine[$sVEPColumn]) || $aLine[$sVEPColumn] == 'unknown' || $aLine[$sVEPColumn] == '.') {
                $aVariant = $zAdapter->formatEmptyColumn($aLine, $sVEPColumn, $sLOVDColumn, $aVariant);
            } else {
                $aVariant[$sLOVDColumn] = $aLine[$sVEPColumn];
            }
        }

        // When seeing a new chromosome, reset these variables. We don't want them too big; it's useless and takes up a lot of memory.
        if ($sLastChromosome != $aVariant['chromosome']) {
            $sLastChromosome = $aVariant['chromosome'];
            $aMappings = array(); // chrX:g.123456del => array(NM_000001.1 => 'c.123del', ...); // To prevent us from running numberConversion too many times.

        }

        // Now "fix" certain values.
        // First, VOG fields.
        // Allele.
        if (!empty($aVariant['allele'])) {
            if ($aVariant['allele'] == '1/1') {
                $aVariant['allele'] = 3; // Homozygous.
            } elseif (isset($aVariant['VariantOnGenome/Sequencing/Father/VarPresent']) && isset($aVariant['VariantOnGenome/Sequencing/Mother/VarPresent'])) {
                if ($aVariant['VariantOnGenome/Sequencing/Father/VarPresent'] >= 5 && $aVariant['VariantOnGenome/Sequencing/Mother/VarPresent'] <= 3) {
                    // From father, inferred.
                    $aVariant['allele'] = 10;
                } elseif ($aVariant['VariantOnGenome/Sequencing/Mother/VarPresent'] >= 5 && $aVariant['VariantOnGenome/Sequencing/Father/VarPresent'] <= 3) {
                    // From mother, inferred.
                    $aVariant['allele'] = 20;
                } else {
                    $aVariant['allele'] = 0;
                }
            } else {
                $aVariant['allele'] = 0;
            }
        }


        // Chromosome.
        $aVariant['chromosome'] = substr($aVariant['chromosome'], 3); // chr1 -> 1
        // VOG/DNA and the position fields.
        lovd_getVariantDescription($aVariant, $aVariant['ref'], $aVariant['alt']);
        // dbSNP.
        if ($aVariant['VariantOnGenome/dbSNP'] && strpos($aVariant['VariantOnGenome/dbSNP'], ';') !== false) {
            // Sometimes we get two dbSNP IDs. Store the first one, only.
            $aDbSNP = explode(';', $aVariant['VariantOnGenome/dbSNP']);
            $aVariant['VariantOnGenome/dbSNP'] = $aDbSNP[0];
        } elseif (!$aVariant['VariantOnGenome/dbSNP'] && !empty($aVariant['existingvariation']) && $aVariant['existingvariation'] != 'unknown') {
            $aIDs = explode('&', $aVariant['existingvariation']);
            foreach ($aIDs as $sID) {
                if (substr($sID, 0, 2) == 'rs') {
                    $aVariant['VariantOnGenome/dbSNP'] = $sID;
                    break;
                }
            }
        }
        // Fixing some other VOG fields.
        foreach (array('VariantOnGenome/Sequencing/Father/GenoType', 'VariantOnGenome/Sequencing/Father/GenoType/Quality', 'VariantOnGenome/Sequencing/Mother/GenoType', 'VariantOnGenome/Sequencing/Mother/GenoType/Quality') as $sCol) {
            if (!empty($aVariant[$sCol]) && $aVariant[$sCol] == 'None') {
                $aVariant[$sCol] = '';
            }
        }

        // Some percentages we get need to be turned into decimals before it can be stored.
        // 2015-10-28; Because of the double column mappings, we ended up with values divided twice.
        // Flipping the array makes sure we get rid of double mappings.
        foreach (array_flip($aColumnMappings) as $sLOVDColumn => $sVEPColumn) {
            if ($sVEPColumn == 'AFESP5400' || strpos($sVEPColumn, 'ALTPERC_') === 0) {
                $aVariant[$sLOVDColumn] /= 100;
            }
        }

        // Now, VOT fields.
        // Find gene && transcript in database. When not found, try to create it. Otherwise, throw a fatal error.
        // Trusting the gene symbol information from VEP is by far the easiest method, and the fastest. This can fail, therefore we also created an alias list.
        if (!empty($_INI['database']['enforce_hgnc_gene']) && isset($aGeneAliases[$aVariant['symbol']])) {
            $aVariant['symbol'] = $aGeneAliases[$aVariant['symbol']];
        }
        // Get gene information. LOC* genes always fail here, so those we don't try.
        if (!isset($aGenes[$aVariant['symbol']]) && !in_array($aVariant['symbol'], $aGenesToIgnore) && !preg_match('/^LOC[0-9]+$/', $aVariant['symbol'])) {
            // First try to get this gene from the database.
            // FIXME: This is duplicated code. Make it into a function, perhaps?
            if ($aGene = $_DB->query('SELECT g.id, g.refseq_UD, g.name FROM ' . TABLE_GENES . ' AS g WHERE g.id = ?', array($aVariant['symbol']))->fetchAssoc()) {
                // We've got it in the database.
                // Sometimes, we don't have an UD there. It happens, because now and then we manually created genes and transcripts.
                if (!$aGene['refseq_UD']) {
                    $aGene['refseq_UD'] = lovd_getUDForGene($_CONF['refseq_build'], $aGene['id']);
                }
                if ($aGene['refseq_UD']) {
                    // Silent error if not found. We were already like this. But we'll ignore the gene.
                    $aGenes[$aVariant['symbol']] = array_merge($aGene, array('transcripts_in_UD' => array()));
                }
            } else {

                // Getting all gene information from the HGNC takes a few seconds.
                $aGeneInfo = false;
                if (!empty($_INI['database']['enforce_hgnc_gene'])) {
print('Loading gene information for ' . $aVariant['symbol'] . '...' . "\n");
                    $aGeneInfo = lovd_getGeneInfoFromHGNC($aVariant['symbol'], true);
                    $nHGNC++;
                    if (!$aGeneInfo) {
                        // We can't gene information from the HGNC, so we can't add them.
                        // This is a major problem and we can't just continue.
//                    die('Gene ' . $aLine['SYMBOL'] . ' can\'t be identified by the HGNC.' . "\n\n");
print('Gene ' . $aVariant['symbol'] . ' can\'t be identified by the HGNC.' . "\n");
                    }
                }

                // Detect alias. We should store these, for next run (which will crash on a duplicate key error).
                if ($aGeneInfo && $aVariant['symbol'] != $aGeneInfo['symbol']) {
                    print('\'' . $aVariant['symbol'] . '\' => \'' . $aGeneInfo['symbol'] . '\',' . "\n");
                    // In fact, let's try not to die if we know we'll die.
                    // FIXME: This is duplicated code. Make it into a function, perhaps?
                    if ($aGene = $_DB->query('SELECT g.id, g.refseq_UD, g.name FROM ' . TABLE_GENES . ' AS g WHERE g.id = ?', array($aGeneInfo['symbol']))->fetchAssoc()) {
                        // We've got the alias already in the database.
                        $aGenes[$aVariant['symbol']] = array_merge($aGene, array('transcripts_in_UD' => array()));
                    }
                }

                if ($aGeneInfo && !isset($aGenes[$aVariant['symbol']])) {
                    $sRefseqUD = lovd_getUDForGene($_CONF['refseq_build'], $aGeneInfo['symbol']);
                    if (!$sRefseqUD) {
//                        die('Can\'t load UD for gene ' . $aLine['SYMBOL'] . '.' . "\n");
print('Can\'t load UD for gene ' . $aGeneInfo['symbol'] . '.' . "\n");
                    }

                    // Not getting an UD no longer kills the script, so...
                    if ($sRefseqUD) {
                        if (!$_DB->query('INSERT INTO ' . TABLE_GENES . '
                             (id, name, chromosome, chrom_band, refseq_genomic, refseq_UD, reference, url_homepage, url_external, allow_download, allow_index_wiki, id_hgnc, id_entrez, id_omim, show_hgmd, show_genecards, show_genetests, note_index, note_listing, refseq, refseq_url, disclaimer, disclaimer_text, header, header_align, footer, footer_align, created_by, created_date, updated_by, updated_date)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())',
                            array($aGeneInfo['symbol'], $aGeneInfo['name'], $aGeneInfo['chromosome'], $aGeneInfo['chrom_band'], $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aGeneInfo['chromosome']], $sRefseqUD, '', '', '', 0, 0, $aGeneInfo['hgnc_id'], $aGeneInfo['entrez_id'], (!$aGeneInfo['omim_id']? NULL : $aGeneInfo['omim_id']), 0, 0, 0, '', '', '', '', 0, '', '', 0, '', 0, 0, 0))) {
                            die('Can\'t create gene ' . $aVariant['symbol'] . '.' . "\n");
                        }

                        // Add the default custom columns to this gene.
                        lovd_addAllDefaultCustomColumns('gene', $aGeneInfo['symbol']);

                        // Write to log...
                        lovd_writeLog('Event', LOG_EVENT, 'Created gene information entry ' . $aGeneInfo['symbol'] . ' (' . $aGeneInfo['name'] . ')');
                        print('Created gene ' . $aGeneInfo['symbol'] . ".\n");
                        flush();

                        // Store this gene.
                        $aGenes[$aVariant['symbol']] = array('id' => $aGeneInfo['symbol'], 'refseq_UD' => $sRefseqUD, 'name' => $aGeneInfo['name'], 'transcripts_in_UD' => array());
                    }
                }
            }
        }



        // Store transcript ID without version, we'll use it plenty of times.
        $aLine['transcript_noversion'] = substr($aVariant['transcriptid'], 0, strpos($aVariant['transcriptid'] . '.', '.')+1);
        if (!isset($aGenes[$aVariant['symbol']]) || !$aGenes[$aVariant['symbol']]) {
            // We really couldn't do anything with this gene (now, or last time).
            $aGenes[$aVariant['symbol']] = false;

        } elseif (!empty($aLine['Feature']) && !isset($aTranscripts[$aVariant['transcriptid']])) {
            // Gene found, get transcript information.
            // First try to get this transcript from the database.
            if ($aTranscript = $_DB->query('SELECT id, geneid, id_mutalyzer, id_ncbi, position_c_cds_end, position_g_mrna_start, position_g_mrna_end FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi LIKE ? ORDER BY (id_ncbi = ?) DESC, id DESC LIMIT 1', array(substr($aVariant['transcriptid'], 0, strpos($aVariant['transcriptid'] . '.', '.')+1) . '%', $aVariant['transcriptid']))->fetchAssoc()) {

                // We've got it in the database.
                $aTranscripts[$aVariant['transcriptid']] = $aTranscript;

            } elseif ($aGenes[$aVariant['symbol']]['refseq_UD']) {
                // To prevent us from having to check the available transcripts all the time, we store the available transcripts, but only insert those we need.
                if ($aGenes[$aVariant['symbol']]['transcripts_in_UD']) {
                    $aTranscriptInfo = $aGenes[$aVariant['symbol']]['transcripts_in_UD'];

                } else {
                    $aTranscriptInfo = array();
                    $sJSONResponse = false;
                    if (!empty($_INI['database']['enforce_hgnc_gene'])) {
print('Loading transcript information for ' . $aGenes[$aVariant['symbol']]['id'] . '...' . "\n");
                        $nSleepTime = 2;
                        for ($i = 0; $i <= $nMutalyzerRetries; $i++) { // Retry Mutalyzer call several times until successful.
                            $aMutalyzerCalls['getTranscriptsAndInfo'] ++;
                            $tMutalyzerStart = microtime(true);
                            $sJSONResponse = mutalyzer_getTranscriptsAndInfo($aGenes[$aVariant['symbol']]['refseq_UD'], $aGenes[$aVariant['symbol']]['id']);
                            $tMutalyzerCalls += (microtime(true) - $tMutalyzerStart);
                            $nMutalyzer++;
                            if ($sJSONResponse === false) { // The Mutalyzer call has failed.
                                sleep($nSleepTime); // Sleep for some time.
                                $nSleepTime = $nSleepTime * 2; // Double the amount of time that we sleep each time.
                            } else {
                                break;
                            }
                        }
                        if ($sJSONResponse === false) {
                            print('>>>>> Attempted to call Mutalyzer ' . $nMutalyzerRetries . ' times to getTranscriptsAndInfo and failed on line ' . $nLine . '.' . "\n");
                        }
                    }

                    if ($sJSONResponse && $aResponse = json_decode($sJSONResponse, true)) {
                        // Before we had to go two layers deep; through the result, then read out the info.
                        // But now apparently this service just returns the string with quotes (the latter are removed by json_decode()).
                        $aTranscriptInfo = $aResponse;
                    }
                    if (empty($aTranscriptInfo)) {
//                        die('Can\'t load available transcripts for gene ' . $aLine['SYMBOL'] . '.' . "\n");
//print('Can\'t load available transcripts for gene ' . $aLine['SYMBOL'] . '.' . "\n");
print('No available transcripts for gene ' . $aGenes[$aVariant['symbol']]['id'] . ' found.' . "\n"); // Usually this is the case. Not always an error. We might get an error, but that will show now.
                        $aTranscripts[$aVariant['transcriptid']] = false; // Ignore transcript.
                        $aTranscriptInfo = array(array('id' => 'NO_TRANSCRIPTS')); // Basically, any text will do. Just stop searching for other transcripts for this gene.
                    }
                    // Store for next time.
                    $aGenes[$aVariant['symbol']]['transcripts_in_UD'] = $aTranscriptInfo;
                }

                // Loop transcript options, add the one we need.
                foreach($aTranscriptInfo as $aTranscript) {
                    // Comparison is made without looking at version numbers!
                    if (substr($aTranscript['id'], 0, strpos($aTranscript['id'] . '.', '.')+1) == $aLine['transcript_noversion']) {
                        // Store in database, prepare values.
                        $sTranscriptName = str_replace($aGenes[$aVariant['symbol']]['name'] . ', ', '', $aTranscript['product']);
                        $aTranscript['id_mutalyzer'] = str_replace($aGenes[$aVariant['symbol']]['id'] . '_v', '', $aTranscript['name']);
                        $aTranscript['id_ncbi'] = $aTranscript['id'];
                        $sTranscriptProtein = (!isset($aTranscript['proteinTranscript']['id'])? '' : $aTranscript['proteinTranscript']['id']);
                        $aTranscript['position_c_cds_end'] = $aTranscript['cCDSStop']; // To calculate VOT variant position, if in 3'UTR.

                        // Add transcript to gene.
                        if (!$_DB->query('INSERT INTO ' . TABLE_TRANSCRIPTS . '
                             (id, geneid, name, id_mutalyzer, id_ncbi, id_ensembl, id_protein_ncbi, id_protein_ensembl, id_protein_uniprot, remarks, position_c_mrna_start, position_c_mrna_end, position_c_cds_end, position_g_mrna_start, position_g_mrna_end, created_date, created_by)
                            VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
                            array($aGenes[$aVariant['symbol']]['id'], $sTranscriptName, $aTranscript['id_mutalyzer'], $aTranscript['id_ncbi'], '', $sTranscriptProtein, '', '', '', $aTranscript['cTransStart'], $aTranscript['sortableTransEnd'], $aTranscript['cCDSStop'], $aTranscript['chromTransStart'], $aTranscript['chromTransEnd'], 0))) {
                            die('Can\'t create transcript ' . $aTranscript['id_ncbi'] . ' for gene ' . $aVariant['symbol'] . '.' . "\n");
                        }

                        // Save the ID before the writeLog deletes it...
                        $nTranscriptID = str_pad($_DB->lastInsertId(), $_SETT['objectid_length']['transcripts'], '0', STR_PAD_LEFT);

                        // Write to log...
                        lovd_writeLog('Event', LOG_EVENT, 'Transcript entry successfully added to gene ' . $aGenes[$aVariant['symbol']]['id'] . ' - ' . $sTranscriptName);
                        print('Created transcript ' . $aTranscript['id'] . ".\n");
                        flush();

                        // Store in memory.
                        $aTranscripts[$aVariant['transcriptid']] = array_merge($aTranscript, array('id' => $nTranscriptID)); // Contains a lot more info than needed, but whatever.
                    }
                }

                if (!isset($aTranscripts[$aVariant['transcriptid']])) {
                    // We don't have it, we can't get it... Stop looking for it, please!
                    $aTranscripts[$aVariant['transcriptid']] = false;
                }
            }
        }

        // Now check, if we managed to get the transcript ID. If not, then we'll have to continue without it.
        if ($zAdapter->ignoreTranscript($aVariant['transcriptid']) || !isset($aTranscripts[$aVariant['transcriptid']]) || !$aTranscripts[$aVariant['transcriptid']]) {
            // When the transcript still doesn't exist, or it evaluates to false (we don't have it, we can't get it), then skip it.
            $aVariant['transcriptid'] = '';
        } else {
            // Handle the rest of the VOT columns.
            // First, take off the transcript name, so we can easily check for a del/ins checking for an underscore.
            $aVariant['VariantOnTranscript/DNA'] = substr($aVariant['VariantOnTranscript/DNA'], strpos($aVariant['VariantOnTranscript/DNA'], ':')+1); // NM_000000.1:c.1del -> c.1del
            $bCallMutalyzer = !$aVariant['VariantOnTranscript/DNA'];
            if (empty($_INI['import']['skip_check_indel_description'])) {
                $bCallMutalyzer = $bCallMutalyzer || (strpos($aVariant['VariantOnTranscript/DNA'], '_') !== false);
            }

            if ($bCallMutalyzer) {
                // We don't have a DNA field from VEP, or we get them with an underscore which we don't trust, because
                //  at VEP they don't understand that when the gene is on reverse, they have to switch the positions.
                // Also, sometimes a delins is simply a substitution, when the VCF file is all messed up (ACGT to ACCT for example).
                // No other option, call Mutalyzer.
                // But first check if I did that before.
                if (!isset($aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']])) {
                    $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']] = array();
//print('Running position converter, DNA was: "' . $aVariant['VariantOnTranscript/DNA'] . '"' . "\n");


                    $sJSONResponse = false;
                    $nSleepTime = 2;
                    for($i=0; $i <= $nMutalyzerRetries; $i++){ // Retry Mutalyzer call several times until successful.
                        $aMutalyzerCalls['numberConversion'] ++;
                        $tMutalyzerStart = microtime(true);
                        $sJSONResponse = mutalyzer_numberConversion($_CONF['refseq_build'], $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']] . ':' . $aVariant['VariantOnGenome/DNA']);
                        $tMutalyzerCalls += (microtime(true) - $tMutalyzerStart);
                        $nMutalyzer++;
                        if ($sJSONResponse === false) { // The Mutalyzer call has failed.
                            sleep($nSleepTime); // Sleep for some time.
                            $nSleepTime = $nSleepTime * 2; // Double the amount of time that we sleep each time.
                        } else {
                            break;
                        }
                    }

                    if ($sJSONResponse === false) {
                        print('>>>>> Attempted to call Mutalyzer ' . $nMutalyzerRetries . ' times for numberConversion and failed on line ' . $nLine . '.' . "\n");
                    }                        

                    if ($sJSONResponse && $aResponse = json_decode($sJSONResponse, true)) {
                        // Before we had to go two layers deep; through the result, then read out the string.
                        // But now apparently this service just returns the string with quotes (the latter are removed by json_decode()).
                        foreach ($aResponse as $sResponse) {
                            list($sRef, $sDNA) = explode(':', $sResponse, 2);
                            $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$sRef] = $sDNA;
                        }
                    }
                }

                if (!isset($aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aTranscripts[$aVariant['transcriptid']]['id_ncbi']])) {
                    // Somehow, we can't find the transcript in the mapping info.
                    // This sometimes happens when the slice has a newer transcript than the one we have in the position converter database.
                    // This can also happen, when VEP says the variant maps, but Mutalyzer disagrees (boundaries may be different, variant may be outside of gene).
                    // Try the version we actually requested.

                    if ($aVariant['transcriptid'] != $aTranscripts[$aVariant['transcriptid']]['id_ncbi']) {
                        // The database has selected a different version; just copy that...
                        $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aTranscripts[$aVariant['transcriptid']]['id_ncbi']] = $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aVariant['transcriptid']];
                    } else {
                        $aAlternativeVersions = array();
                        foreach ($aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']] as $sRef => $sDNA) {
                            if (strpos($sRef, $aLine['transcript_noversion']) === 0) {
                                $aAlternativeVersions[] = $sRef;
                            }
                        }
                        if ($aAlternativeVersions) {
                            var_dump('Found alternative by searching: ', $aLine['Feature'], $aAlternativeVersions);
                            $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aTranscripts[$aLine['Feature']]['id_ncbi']] = $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aAlternativeVersions[0]];
                        } else {
                            // This happens when VEP says we can map on a known transcript, but doesn't provide us a valid mapping,
                            // *and* Mutalyzer at the same time doesn't seem to be able to map to this transcript at all.
                            // This happens sometimes with variants outside of genes, that VEP apparently considers close enough.
                            // Getting here will trigger an error in the next block, because no valid mapping has been provided.
                        }
                    }

                    if (!isset($aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aTranscripts[$aVariant['transcriptid']]['id_ncbi']])) {
                        $sErrorMsg = 'Can\'t map variant ' . $aVariant['VariantOnGenome/DNA'] . ' onto transcript ' . $aTranscripts[$aVariant['transcriptid']]['id_ncbi'] . '.';
                        $nAnnotationErrors = lovd_handleAnnotationError($aVariant, $sErrorMsg);
                        $bDropTranscriptData = true;

                    }
                }
                $aVariant['VariantOnTranscript/DNA'] = $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aTranscripts[$aVariant['transcriptid']]['id_ncbi']];
            }
            // For the position fields, there is VariantOnTranscript/Position (coming from CDS_position), but it's hardly usable. Calculate ourselves.
            list($aVariant['position_c_start'], $aVariant['position_c_start_intron'], $aVariant['position_c_end'], $aVariant['position_c_end_intron']) = array_values(lovd_getVariantPosition($aVariant['VariantOnTranscript/DNA'], $aTranscripts[$aVariant['transcriptid']]));

            // VariantOnTranscript/Position is an integer column; so just copy the c_start.
            $aVariant['VariantOnTranscript/Position'] = $aVariant['position_c_start'];
            $aVariant['VariantOnTranscript/Distance_to_splice_site'] = ((bool) $aVariant['position_c_start_intron'] == (bool) $aVariant['position_c_end_intron']? min(abs($aVariant['position_c_start_intron']), abs($aVariant['position_c_end_intron'])) : ($aVariant['position_c_start_intron']? abs($aVariant['position_c_start_intron']) : abs($aVariant['position_c_end_intron'])));

            // VariantOnTranscript/RNA && VariantOnTranscript/Protein.
            // Try to do as much as possible by ourselves.
            $aVariant['VariantOnTranscript/RNA'] = '';
            if ($aVariant['VariantOnTranscript/Protein']) {
                // VEP came up with something...
                $aVariant['VariantOnTranscript/RNA'] = 'r.(?)';
                if ($aVariant['VariantOnTranscript/Protein'] == $aVariant['VariantOnTranscript/DNA'] . '(p.%3D)') {
                    // But sometimes VEP messes up; DNA: NM_000093.4:c.4482G>A; Prot: NM_000093.4:c.4482G>A(p.%3D)
                    $aVariant['VariantOnTranscript/Protein'] = 'p.(=)';
                } else {
                    $aVariant['VariantOnTranscript/Protein'] = substr($aVariant['VariantOnTranscript/Protein'], strpos($aVariant['VariantOnTranscript/Protein'], ':')+1); // NP_000000.1:p.Met1? -> p.Met1?
                    $aVariant['VariantOnTranscript/Protein'] = str_replace('p.', 'p.(', $aVariant['VariantOnTranscript/Protein'] . ')');
                }
            } elseif (($aVariant['position_c_start'] < 0 && $aVariant['position_c_end'] < 0)
                || ($aVariant['position_c_start'] > $aTranscripts[$aVariant['transcriptid']]['position_c_cds_end'] && $aVariant['position_c_end'] > $aTranscripts[$aVariant['transcriptid']]['position_c_cds_end'])
                || ($aVariant['position_c_start_intron'] && $aVariant['position_c_end_intron'] && min(abs($aVariant['position_c_start_intron']), abs($aVariant['position_c_end_intron'])) > 5
                    && ($aVariant['position_c_start'] == $aVariant['position_c_end'] || ($aVariant['position_c_start'] == ($aVariant['position_c_end']-1) && $aVariant['position_c_start_intron'] > 0 && $aVariant['position_c_end_intron'] < 0)))) {
                // 5'UTR, 3'UTR, fully intronic in one intron only (at least 5 bases away from exon border).
                $aVariant['VariantOnTranscript/RNA'] = 'r.(=)';
                $aVariant['VariantOnTranscript/Protein'] = 'p.(=)';
            } elseif (($aVariant['position_c_start_intron'] && (!$aVariant['position_c_end_intron'] || abs($aVariant['position_c_start_intron']) <= 5))
                || ($aVariant['position_c_end_intron'] && (!$aVariant['position_c_start_intron'] || abs($aVariant['position_c_end_intron']) <= 5))) {
                // Partially intronic, or variants spanning multiple introns, or within first/last 5 bases of an intron.
                $aVariant['VariantOnTranscript/RNA'] = 'r.spl?';
                $aVariant['VariantOnTranscript/Protein'] = 'p.?';
            } else {
                // OK, too bad, we need to run Mutalyzer anyway.

                // It sometimes happens that we don't have a id_mutalyzer value. Before, we used to create transcripts manually if we couldn't recognize them.
                // This is now working against us, as we really need this ID now.
                if ($aTranscripts[$aVariant['transcriptid']]['id_mutalyzer'] == '000') {
                    // Normally, we would implement a cache here, but we rarely run Mutalyzer, and if we do, we will not likely run it on a variant on the same transcript.
                    // So, first just check if we still don't have a Mutalyzer ID.
                    $sJSONResponse = false;
                    if (!empty($_INI['database']['enforce_hgnc_gene'])) {
                        print('Reloading Mutalyzer ID for ' . $aTranscripts[$aVariant['transcriptid']]['id_ncbi'] . ' in ' . $aVariant[$aVariant['symbol']]['refseq_UD'] . ' (' . $aGenes[$aVariant['symbol']]['id'] . ')' . "\n");
                        $nSleepTime = 2;
                        for ($i = 0; $i <= $nMutalyzerRetries; $i++) { // Retry Mutalyzer call several times until successful.
                            $aMutalyzerCalls['getTranscriptsAndInfo'] ++;
                            $tMutalyzerStart = microtime(true);
                            $sJSONResponse = mutalyzer_getTranscriptsAndInfo(rawurlencode($aGenes[$aVariant['symbol']]['refseq_UD']), rawurlencode($aGenes[$aVariant['symbol']]['id']));
                            $tMutalyzerCalls += (microtime(true) - $tMutalyzerStart);
                            $nMutalyzer++;
                            if ($sJSONResponse === false) { // The Mutalyzer call has failed.
                                sleep($nSleepTime); // Sleep for some time.
                                $nSleepTime = $nSleepTime * 2; // Double the amount of time that we sleep each time.
                            } else {
                                break;
                            }
                        }
                        if ($sJSONResponse === false) {
                            print('>>>>> Attempted to call Mutalyzer ' . $nMutalyzerRetries . ' times to getTranscriptsAndInfo and failed on line ' . $nLine . '.' . "\n");
                        }
                    }

                    if ($sJSONResponse && $aResponse = json_decode($sJSONResponse, true)) {
                        // Loop transcripts, find the one in question, then isolate Mutalyzer ID.
                        foreach ($aResponse as $aTranscript) {
                            if ($aTranscript['id'] == $aTranscripts[$aVariant['transcriptid']]['id_ncbi'] && $aTranscript['name']) {
                                $sMutalyzerID = str_replace($aGenes[$aVariant['symbol']]['id'] . '_v', '', $aTranscript['name']);

                                // Store locally, then store in database.
                                $aTranscripts[$aVariant['transcriptid']]['id_mutalyzer'] = $sMutalyzerID;
                                $_DB->query('UPDATE ' . TABLE_TRANSCRIPTS . ' SET id_mutalyzer = ? WHERE id_ncbi = ?', array($sMutalyzerID, $aTranscript['id']));
                                break;
                            }
                        }
                    }
                }

print('Running mutalyzer to predict protein change for ' . $aGenes[$aVariant['symbol']]['refseq_UD'] . '(' . $aGenes[$aVariant['symbol']]['id'] . '_v' . $aTranscripts[$aVariant['transcriptid']]['id_mutalyzer'] . '):' . $aVariant['VariantOnTranscript/DNA'] . "\n");
                $sJSONResponse = false;
                $nSleepTime = 2;
                for($i=0; $i <= $nMutalyzerRetries; $i++){ // Retry Mutalyzer call several times until successful.
                    $aMutalyzerCalls['runMutalyzer'] ++;
                    $tMutalyzerStart = microtime(true);
                    $sJSONResponse = mutalyzer_runMutalyzer(rawurlencode($aGenes[$aLine['SYMBOL']]['refseq_UD'] . '(' . $aTranscripts[$aVariant['transcriptid']]['id_ncbi'] . '):' . $aVariant['VariantOnTranscript/DNA']));
                    $tMutalyzerCalls += (microtime(true) - $tMutalyzerStart);
                    $nMutalyzer++;
                    if ($sJSONResponse === false) { // The Mutalyzer call has failed.
                        sleep($nSleepTime); // Sleep for some time.
                        $nSleepTime = $nSleepTime * 2; // Double the amount of time that we sleep each time.
                    } else {
                        break;
                    }
                }
                if ($sJSONResponse === false) {
                    print('>>>>> Attempted to call Mutalyzer ' . $nMutalyzerRetries . ' times to runMutalyzer and failed on line ' . $nLine . '.' . "\n");
                }

//var_dump('https://mutalyzer.nl/json/runMutalyzer?variant=' . rawurlencode($aGenes[$aLine['SYMBOL']]['refseq_UD'] . '(' . $aGenes[$aLine['SYMBOL']]['id'] . '_v' . $aTranscripts[$aLine['Feature']]['id_mutalyzer'] . '):' . $aVariant['VariantOnTranscript/DNA']));
                if ($sJSONResponse && $aResponse = json_decode($sJSONResponse, true)) {
                    if (!isset($aResponse['proteinDescriptions'])) {
                        // Not sure if this can happen using JSON.
                        $aResponse['proteinDescriptions'] = array();
                    }

                    // Predict RNA && Protein change.
                    // 'Intelligent' error handling.
                    foreach ($aResponse['messages'] as $aError) {
                        // Pass other errors on to the users?
                        // FIXME: This is implemented as well in inc-lib-variants.php (LOVD3.0-15).
                        //  When we update LOVD+ to LOVD 3.0-15, use this lib so we don't duplicate code...
                        if (isset($aError['errorcode']) && $aError['errorcode'] == 'ERANGE') {
                            // Ignore 'ERANGE' as an actual error, because we can always interpret this as p.(=), p.? or p.0.
                            $aVariantRange = explode('_', $aVariant['VariantOnTranscript/DNA']);
                            // Check what the variant looks like and act accordingly.
                            if (count($aVariantRange) === 2 && preg_match('/-\d+/', $aVariantRange[0]) && preg_match('/-\d+/', $aVariantRange[1])) {
                                // Variant has 2 positions. Variant has both the start and end positions upstream of the transcript, we can assume that the product will not be affected.
                                $sPredictR = 'r.(=)';
                                $sPredictP = 'p.(=)';
                            } elseif (count($aVariantRange) === 2 && preg_match('/-\d+/', $aVariantRange[0]) && preg_match('/\*\d+/', $aVariantRange[1])) {
                                // Variant has 2 positions. Variant has an upstream start position and a downstream end position, we can assume that the product will not be expressed.
                                $sPredictR = 'r.0?';
                                $sPredictP = 'p.0?';
                            } elseif (count($aVariantRange) == 2 && preg_match('/\*\d+/', $aVariantRange[0]) && preg_match('/\*\d+/', $aVariantRange[1])) {
                                // Variant has 2 positions. Variant has both the start and end positions downstream of the transcript, we can assume that the product will not be affected.
                                $sPredictR = 'r.(=)';
                                $sPredictP = 'p.(=)';
                            } elseif (count($aVariantRange) == 1 && preg_match('/-\d+/', $aVariantRange[0]) || preg_match('/\*\d+/', $aVariantRange[0])) {
                                // Variant has 1 position and is either upstream or downstream from the transcript, we can assume that the product will not be affected.
                                $sPredictR = 'r.(=)';
                                $sPredictP = 'p.(=)';
                            } else {
                                // One of the positions of the variant falls within the transcript, so we can not make any assumptions based on that.
                                $sPredictR = 'r.?';
                                $sPredictP = 'p.?';
                            }
                            // Fill in our assumption to forge that this information came from Mutalyzer.
                            $aVariant['VariantOnTranscript/RNA'] = $sPredictR;
                            $aVariant['VariantOnTranscript/Protein'] = $sPredictP;
                            break;
                        } elseif (isset($aError['errorcode']) && $aError['errorcode'] == 'WSPLICE') {
                            $aVariant['VariantOnTranscript/RNA'] = 'r.spl?';
                            $aVariant['VariantOnTranscript/Protein'] = 'p.?';
                            break;
                        } elseif (isset($aError['errorcode']) && $aError['errorcode'] == 'EREF') {
                            // This can happen, because we have UDs from hg38, but the alignment and variant calling is done on hg19... :(  Sequence can be different.
                            $aVariant['VariantOnTranscript/RNA'] = 'r.(?)';
                            $aVariant['VariantOnTranscript/Protein'] = 'p.?';
print('Mutalyzer returned EREF error, hg19/hg38 error?' . "\n");
                            // We don't break here, because if there is also a WSPLICE we rather go with that one.
                        }
                    }
                    if (!$aVariant['VariantOnTranscript/Protein'] && !empty($aResponse['proteinDescriptions'])) {
                        foreach ($aResponse['proteinDescriptions'] as $sVariantOnProtein) {
                            if (($nPos = strpos($sVariantOnProtein, $aGenes[$aVariant['symbol']]['id'] . '_i' . $aTranscripts[$aVariant['transcriptid']]['id_mutalyzer'] . '):p.')) !== false) {
                                // FIXME: Since this code is the same as the code used in the variant mapper (2x), better make a function out of it.
                                $aVariant['VariantOnTranscript/Protein'] = substr($sVariantOnProtein, $nPos + strlen($aGenes[$aVariant['symbol']]['id'] . '_i' . $aTranscripts[$aVariant['transcriptid']]['id_mutalyzer'] . '):'));
                                if ($aVariant['VariantOnTranscript/Protein'] == 'p.?') {
                                    $aVariant['VariantOnTranscript/RNA'] = 'r.?';
                                } elseif ($aVariant['VariantOnTranscript/Protein'] == 'p.(=)') {
                                    // FIXME: Not correct in case of substitutions e.g. in the third position of the codon, not leading to a protein change.
                                    $aVariant['VariantOnTranscript/RNA'] = 'r.(=)';
                                } else {
                                    // RNA will default to r.(?).
                                    $aVariant['VariantOnTranscript/RNA'] = 'r.(?)';
                                }
                                break;
                            }
                        }
                    }
                }
                // Any errors related to the prediction of Exon, RNA or Protein are silently ignored.
            }

            if (!$aVariant['VariantOnTranscript/RNA']) {
                // Script dies here, because I want to know if I missed something. This happens with NR transcripts, but those were ignored anyway, right?
                //var_dump($aVariant);
                //exit;

                $sErrorMsg = "Missing VariantOnTranscript/RNA. Chromosome: ". $aVariant['chromosome'] . ". VariantOnGenome/DNA: " . $aVariant['VariantOnGenome/DNA'] . ".";
                $nAnnotationErrors = lovd_handleAnnotationError($aVariant, $sErrorMsg);
                $bDropTranscriptData = true;
            }
        }

        // DNA fields and protein field can be super long with long inserts.
        foreach (array('VariantOnGenome/DNA', 'VariantOnTranscript/DNA') as $sField) {
            if (isset($aVariant[$sField]) && strlen($aVariant[$sField]) > 100 && preg_match('/ins([ACTG]+)$/', $aVariant[$sField], $aRegs)) {
                $aVariant[$sField] = str_replace('ins' . $aRegs[1], 'ins' . strlen($aRegs[1]), $aVariant[$sField]);
            }
        }

        $sField = 'VariantOnTranscript/Protein';
        if (isset($aVariant[$sField]) && strlen($aVariant[$sField]) > 100 && preg_match('/ins(([A-Z][a-z]{2})+)\)$/', $aVariant[$sField], $aRegs)) {
            $aVariant[$sField] = str_replace('ins' . $aRegs[1], 'ins' . strlen($aRegs[1]), $aVariant[$sField]);
        }

        // Replace the ncbi ID with the transcripts LOVD database ID to be used when creating the VOT record.
        // This used to be done at the start of this else statement but since we have switched from using the headers in the file
        // to using the column mappings (much more robust) we no longer had the ncbi ID available as it was overwritten.
        // By moving this code down here we retain the ncbi ID for use and then overwrite at the last step.
        $aVariant['transcriptid'] = (!isset($aTranscripts[$aVariant['transcriptid']]['id'])? '' : $aTranscripts[$aVariant['transcriptid']]['id']);


        // Now store the variants, first the genomic stuff, then the VOT stuff.
        // If the VOG data has already been stored, we will *not* overwrite it.
        // Build the key.
        $sKey = $aVariant['chromosome'] . ':' . $aVariant['position'] . $aVariant['ref'] . '>' . $aVariant['alt'];

        if (!isset($aData[$sKey])) {
            // Create key, put in VOG data.
            $aVOG = array();
            foreach ($aVariant as $sCol => $sVal) {
                if (in_array($sCol, $aColumnsForVOG) || substr($sCol, 0, 16) == 'VariantOnGenome/') {
                    $aVOG[$sCol] = $sVal;
                }
            }
            $aData[$sKey] = array($aVOG);
        }

        $zAdapter->postValueAssignmentUpdate($sKey, $aVariant, $aData);

        // Now, store VOT data. Because I had received test files with repeated lines, and allowing repeated lines will break import, also here we will check for the key.
        // Also check for a set transcriptid, because it can be empty (transcript could not be created).
        $aVOT = array();
        if (!$bDropTranscriptData && !isset($aData[$sKey][$aVariant['transcriptid']]) && $aVariant['transcriptid']) {
            foreach ($aVariant as $sCol => $sVal) {
                if (in_array($sCol, $aColumnsForVOT) || substr($sCol, 0, 20) == 'VariantOnTranscript/') {
                    $aVOT[$sCol] = $sVal;
                    $aVOTKeys[$sCol] = $sCol;
                }
            }

            $aData[$sKey][$aVariant['transcriptid']] = $aVOT;
        }

        // Some reporting of where we are...
        if (!($nLine % 100)) {
            print('------- Line ' . $nLine . ' -------' . str_repeat(' ', 7-strlen($nLine)) . date('Y-m-d H:i:s') . "\n");
            flush();
        }
    }
    fclose($fInput); // Close input file.

    print('Done parsing file. Current time: ' . date('Y-m-d H:i:s') . ".\n");
    // Show the number of times HGNC and Mutalyzer were called.
    print('Number of times HGNC called: ' . $nHGNC . ".\n");
    print('Number of times Mutalyzer called: ' . $nMutalyzer . ".\n");
    print('Number of lines with annotation error: ' . $nAnnotationErrors . ".\n");
    if (filesize($sFileError) > 0) {
        print("ERROR FILE: Please check details of dropped annotation data in " . $sFileError . "\n");
    } else {
        $sFileMessage = '';
        fclose($fError);
        unlink($sFileError);
    }

    if (!$aData) {
        // No variants!
        print('No variants found to import.' . "\n");
        // Here, we won't try and remove the temp file. It will save us from running into the same error over and over again.
        continue; // Try the next file.
    }
    print('Now creating output...' . "\n");





    // Prepare VOG and VOT column arrays, include the found columns.
    // $aVOG should still exist. Take VOG columns from there.
    foreach (array_keys($aVOG) as $sCol) {
        if (substr($sCol, 0, 16) == 'VariantOnGenome/') {
            $aColumnsForVOG[] = $sCol;
        }
    }
    
    foreach ($aVOTKeys as $sCol) {
        if (substr($sCol, 0, 20) == 'VariantOnTranscript/') {
            $aColumnsForVOT[] = $sCol;
        }
    }

    // Start storing the data into the total data file.
    $fOutput = fopen($sFileTmp, 'a');
    if ($fOutput === false) {
        die('Error opening file for appending: ' . $sFileTmp . ".\n");
    }



    // VOG data.
    $nVOG = 0;
    fputs($fOutput, "\r\n" .
        '## Genes ## Do not remove or alter this header ##' . "\r\n" . // Needed to load the existing genes, otherwise we'll only have errors.
        '## Transcripts ## Do not remove or alter this header ##' . "\r\n" . // Needed to load the existing transcripts, otherwise we'll only have errors.
        '## Variants_On_Genome ## Do not remove or alter this header ##' . "\r\n" .
        '## Count = ' . count($aData) . "\r\n" .
        '{{' . implode("}}\t{{", $aColumnsForVOG) . '}}' . "\r\n");
    $nVariant = 0;
    $nVOTs = 0;
    foreach ($aData as $sKey => $aVariant) {
        $nVariant ++;
        $nVOTs += count($aVariant) - 1;
        $nID = sprintf('%010d', $nVariant);
        $aData[$sKey][0]['id'] = $aVariant[0]['id'] = $nID;
        foreach ($aDefaultValues as $sCol => $sValue) {
            if (empty($aVariant[0][$sCol])) {
                $aVariant[0][$sCol] = $sValue;
            }
        }
        foreach ($aColumnsForVOG as $nKey => $sCol) {
            fputs($fOutput, (!$nKey? '' : "\t") . '"' . (!isset($aVariant[0][$sCol])? '' : str_replace(array("\r\n", "\r", "\n"), array('\r\n', '\r', '\n'), addslashes($aVariant[0][$sCol]))) . '"');
        }
        fputs($fOutput, "\r\n");
        $nVOG++;
    }

    // Show number of Variants on Genome data created.
    print("Number of Variants On Genome rows created: " . $nVOG . "\n");



    // VOT data.
    $nVOT = 0;
    fputs($fOutput, "\r\n\r\n" .
        '## Variants_On_Transcripts ## Do not remove or alter this header ##' . "\r\n" .
        '## Count = ' . $nVOTs . "\r\n" .
        '{{' . implode("}}\t{{", $aColumnsForVOT) . '}}' . "\r\n");
    foreach ($aData as $aVariant) {
        $nID = $aVariant[0]['id'];
        unset($aVariant[0]);
        foreach ($aVariant as $aVOT) {
            // Loop through all VOTs.
            $aVOT['id'] = $nID;
            foreach ($aDefaultValues as $sCol => $sValue) {
                if (empty($aVOT[$sCol])) {
                    $aVOT[$sCol] = $sValue;
                }
            }
            foreach ($aColumnsForVOT as $nKey => $sCol) {
                fputs($fOutput, (!$nKey? '' : "\t") . '"' . (!isset($aVOT[$sCol])? '' : str_replace(array("\r\n", "\r", "\n"), array('\r\n', '\r', '\n'), addslashes($aVOT[$sCol]))) . '"');
            }
            fputs($fOutput, "\r\n");
            $nVOT++;
        }
    }

    // Show number of Variants on Transcripts data created.
    print("Number of Variants On Transcripts rows created: " . $nVOT . "\n");


    // Link all variants to the screening.
    fputs($fOutput, "\r\n" .
        '## Screenings_To_Variants ## Do not remove or alter this header ##' . "\r\n" .
        '## Count = ' . count($aData) . "\r\n" .
        '{{screeningid}}' . "\t" . '{{variantid}}' . "\r\n");
    for ($nVariant = 1; $nVariant <= count($aData); $nVariant ++) {
        $nID = sprintf('%010d', $nVariant);
        fputs($fOutput, '"' . $nScreeningID . "\"\t\"" . $nID . "\"\r\n");
    }



    fclose($fOutput); // Close output file.
    // Now move the tmp to the final file, and close this loop.
    if (!rename($sFileTmp, $sFileDone)) {
        // Fatal error, because we're all done actually!
        die('Error moving temp file to target: ' . $sFileDone . ".\n");
    }

    // OK, so file is done, and can be scheduled now. Just auto-schedule it.
    if ($_DB->query('INSERT IGNORE INTO ' . TABLE_SCHEDULED_IMPORTS . ' (filename, scheduled_by, scheduled_date) VALUES (?, 0, NOW())', array(basename($sFileDone)))->rowCount()) {
        print('File scheduled for import.' . "\n");
    } elseif ($_DB->query('UPDATE ' . TABLE_SCHEDULED_IMPORTS . ' SET scheduled_date = NOW() WHERE filename = ?', array(basename($sFileDone)))->rowCount()) {
        print('File scheduled for import.' . "\n");
    } else {
        print('Error scheduling file for import!' . "\n");
    }

    print('All done, ' . $sFileDone . ' ready for import.' . "\n" . 'Current time: ' . date('Y-m-d H:i:s') . "\n" .
          '  Took ' . round((time() - $dStart)/60) . ' minutes, Mutalyzer calls taking ' . round($tMutalyzerCalls/60) . ' minutes.' . "\n");
    foreach ($aMutalyzerCalls as $sFunction => $nCalls) {
        print('    ' . $sFunction . ': ' . $nCalls . "\n");
    }
    print("\n");
    break;// Keep this break in the loop, so we will only continue the loop to the next file when there is a continue;
}
?>
