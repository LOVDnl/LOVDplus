#!/usr/bin/php
<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE FOR DIAGNOSTICS (LOVD+)
 *
 * Created     : 2018-08-17
 * Modified    : 2018-08-23
 * Version     : 0.1
 * For LOVD+   : 3.0-18
 *
 * Purpose     : Takes (VEP) annotated VCF files and converts them to tab-
 *               delimited files, with one transcript mapping per line. There
 *               are various tools to do this, but these either can't be shipped
 *               with LOVD+ due to license issues, or they're not standalone.
 *
 * Changelog   : 0.1    2018-08-23
 *               Initial release.
 *
 * Copyright   : 2004-2018 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *
 * This file is part of LOVD+.
 *
 * LOVD+ is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * LOVD+ is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LOVD+. If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

// Command line only.
if (isset($_SERVER['HTTP_HOST'])) {
    die('Please run this script through the command line.' . "\n");
}

// NOTE: The INFO field will be *dropped* except for the annotated data.
// Default settings.
$_CONFIG = array(
    'version' => '0.1',
    'annotation_ids' => array(
        'CSQ' => '|', // VEP (at least until v93) uses 'CSQ' for the annotation, and splits fields on '|'.
    ),
    'annotation_fields' => array(), // Will be built during parsing of the header.
    'format_fields' => array(), // Will be built during the parsing of the header.
    'VCF_fields' => array( // Mandatory VCF files.
        'CHROM',
        'POS',
        'ID',
        'REF',
        'ALT',
        'QUAL',
        'FILTER',
        'INFO',
    ),
);

// Exit codes.
// See http://tldp.org/LDP/abs/html/exitcodes.html for recommendations, in particular:
// "[I propose] restricting user-defined exit codes to the range 64 - 113 (...), to conform with the C/C++ standard."
define('EXIT_OK', 0);
define('EXIT_WARNINGS_OCCURRED', 64);
define('EXIT_ERROR_INSUFFICIENT_ARGS', 65);
define('EXIT_ERROR_INPUT_NOT_A_FILE', 66);
define('EXIT_ERROR_INPUT_UNREADABLE', 67);
define('EXIT_ERROR_INPUT_CANT_OPEN', 68);
define('EXIT_ERROR_TAG_NOT_FOUND', 69);
define('EXIT_ERROR_TAG_FOUND_DOUBLE', 70);
define('EXIT_ERROR_TAG_UNPARSABLE', 71);
define('EXIT_ERROR_HEADER_FIELDS_NOT_FOUND', 72);
define('EXIT_ERROR_HEADER_FIELDS_INCORRECT', 73);
define('EXIT_ERROR_DATA_FIELD_COUNT_INCORRECT', 74);

define('VERBOSITY_NONE', 0); // No output whatsoever.
define('VERBOSITY_LOW', 3); // Low output, only the really important messages.
define('VERBOSITY_MEDIUM', 5); // Medium output. No output if there is nothing to do. Useful for when using cron.
define('VERBOSITY_HIGH', 7); // High output. The default.
define('VERBOSITY_FULL', 9); // Full output, including debug statements.
$bCron = (empty($_SERVER['REMOTE_ADDR']) && empty($_SERVER['TERM']));
// FIXME: Make this a setting from the library? If so, make it optional, we prefer being standalone.
define('VERBOSITY', ($bCron? 5 : 7));





function lovd_cleanGenoType ($sGenoType, $nKeyALT)
{
    // Cleans the GenoType field (GT) depending on which ALT allele we're seeing now.
    // For instance, a compound heterozygous call (1/2) should be split in a 1/0 and 0/1 call.
    static $aAlleles = array('nAllele1', 'nAllele2');

    if (preg_match('/^(\d+)(\/|\|)(\d+)$/', $sGenoType, $aRegs)) {
        list(,$nAllele1, $sSeparator, $nAllele2) = $aRegs;
        foreach ($aAlleles as $sAlleleVar) {
            if ($$sAlleleVar !== '0') {
                // ALT call.
                if ($$sAlleleVar == (string) ($nKeyALT+1)) {
                    // The ALT we're handling.
                    $$sAlleleVar = '1';
                } else {
                    // Not this ALT.
                    $$sAlleleVar = '.';
                }
            }
        }
        $sGenoType = $nAllele1 . $sSeparator . $nAllele2;
    }

    return $sGenoType;
}





function lovd_ltrimCommonChars ($aStrings)
{
    // ltrim()s all strings in $aStrings if they have a common left character. Returns the resulting array.
    // Assumes it receives at least two strings. Assumes the array has numerical indices, starting with 0.
    $n = count($aStrings);
    while (true) {
        $s = substr($aStrings[0], 0, 1);
        if ($s === false) {
            return $aStrings;
        }
        for ($i = 1; $i < $n; $i ++) {
            if (substr($aStrings[$i], 0, 1) != $s) {
                return $aStrings;
            }
        }

        // If we're here, all characters match.
        $aStrings = array_map('strval',
            array_map('substr', $aStrings, array_fill(0, $n, 1)));
    }
}





function lovd_printIfVerbose ($nVerbosity, $sMessage)
{
    // This function only prints the given message when the current verbosity is set to a level high enough.

    // If no verbosity is currently defined, just print everything.
    if (!defined('VERBOSITY')) {
        define('VERBOSITY', 9);
    }

    if (VERBOSITY >= $nVerbosity) {
        // Write to STDERR, as this script dumps the resulting output file to STDOUT.
        fwrite(STDERR, $sMessage);
    }
    return true;
}





// Parse command line options.
$aArgs = $_SERVER['argv'];
$nArgs = $_SERVER['argc'];
$sScriptName = array_shift($aArgs);
$nArgs --;
$bWarningsOcurred = false;

// We need at least one argument, the file to convert.
// FIXME: If we'll ever support more arguments, adapt this if().
if (!$nArgs || $nArgs > 1) {
    lovd_printIfVerbose(VERBOSITY_LOW,
        'VCF to TSV (Tab Separated Values) v' . $_CONFIG['version'] . '.' . "\n" .
        'Usage: ' . $sScriptName . ' input.vcf' . "\n\n");
    die(EXIT_ERROR_INSUFFICIENT_ARGS);
}

// First argument should be the file to convert.
$sFile = array_shift($aArgs);
$nArgs --;





// Check file passed as an argument.
if (!file_exists($sFile) || !is_readable($sFile)) {
    lovd_printIfVerbose(VERBOSITY_LOW,
        'Error: Unreadable input file.' . "\n\n");
    die(EXIT_ERROR_INPUT_UNREADABLE);
}
if (!is_file($sFile)) {
    lovd_printIfVerbose(VERBOSITY_LOW,
        'Error: Input is not a file.' . "\n\n");
    die(EXIT_ERROR_INPUT_NOT_A_FILE);
}

// Check headers. If we don't find annotation, we'll reject the file. Make sure we don't create any output yet.
$aHeaders = array(); // To temporarily store the headers to not have to parse them twice.
$nAnnotationFields = 0;
$nLine = 0;
$fInput = fopen($sFile, 'r');
if ($fInput === false) {
    lovd_printIfVerbose(VERBOSITY_LOW,
        'Error: Can not open file.' . "\n\n");
    die(EXIT_ERROR_INPUT_CANT_OPEN);
}

// We might be running for some time.
set_time_limit(0);

while ($sLine = fgets($fInput)) {
    $nLine++;
    $sLine = trim($sLine);
    if (!$sLine) {
        continue;
    }
    if (substr($sLine, 0, 2) != '##') {
        // Read too far, no good.
        break;
    }

    // Add to headers.
    $aHeaders[] = $sLine;

    // We're not going to be super strict about things. If we find the annotation header, we're already cool.
    if (preg_match('/^##(INFO|FORMAT)=<.*>$/', $sLine, $aRegs)) {
        // Parse this field.
        $sHeaderType = $aRegs[1];
        $sHeader = substr($sLine, 4 + strlen($sHeaderType), -1);
        $sPattern = '(?:,(?:([A-Za-z]+)=([^"=,]+|".+")))';
        if (!preg_match('/^' . $sPattern . '+$/', ',' . $sHeader)) {
            // Couldn't parse FORMAT or INFO tag.
            lovd_printIfVerbose(VERBOSITY_LOW,
                'Error: Can not parse ' . $sHeaderType . ' header. Got:' . "\n" . $sLine . "\n\n");
            die(EXIT_ERROR_TAG_UNPARSABLE);
        }

        // Check tag.
        preg_match_all('/' . $sPattern . '/', ',' . $sHeader, $aRegs);
        // $aRegs: [1] = array of fields, [2] = array of values.
        $nKeyID = array_search('ID', $aRegs[1]);
        if ($nKeyID === false) {
            // This header doesn't contain an ID... Eh?
            lovd_printIfVerbose(VERBOSITY_HIGH,
                'Warning: Found ' . $sHeaderType . ' header without ID. Got:' . "\n" . $sLine . "\n");
            $bWarningsOcurred = true;
            continue;
        }
        $sHeaderID = $aRegs[2][$nKeyID];

        if ($sHeaderType == 'FORMAT') {
            // Store the ID, as we'll need to use this field in the resulting file's header.
            // For now, we'll ignore the rest of the data.
            $_CONFIG['format_fields'][] = $sHeaderID;

        } elseif ($sHeaderType == 'INFO' && isset($_CONFIG['annotation_ids'][$sHeaderID])) {
            // This field is one of the annotation fields we are requesting.
            if (isset($_CONFIG['annotation_fields'][$sHeaderID])) {
                // We're seeing a field more than once. That will be a problem.
                lovd_printIfVerbose(VERBOSITY_LOW,
                    'Error: Found ' . $sHeaderID . ' INFO tag twice. Can not determine how to parse now.' . "\n\n");
                die(EXIT_ERROR_TAG_FOUND_DOUBLE);
            }
            // Parse fields from the Description.
            // FIXME: Include case-insensitive search by running strtolower() on all elements first?
            $nKeyDescription = array_search('Description', $aRegs[1]);
            if ($nKeyDescription === false) {
                // This annotation header doesn't contain a Description... Eh?
                lovd_printIfVerbose(VERBOSITY_HIGH,
                    'Warning: Found ' . $sHeaderID . ' annotation INFO header without Description. Got:' . "\n" . $sLine . "\n");
                $bWarningsOcurred = true;
                continue;
            }

            // Find the field list, dump the info in the output if desired.
            $sDescription = trim(stripslashes($aRegs[2][$nKeyDescription]), '"');
            // Isolate the last word from the Description. This should be the list of fields.
            $sFields = trim(strrchr(' ' . $sDescription, ' '));
            $_CONFIG['annotation_fields'][$sHeaderID] = explode($_CONFIG['annotation_ids'][$sHeaderID], $sFields);
            $nAnnotationFields = count($_CONFIG['annotation_fields'][$sHeaderID]);
            // Is it enough, do we think so?
            if (strlen($sDescription) < 2 || strlen($sFields) < 2) {
                lovd_printIfVerbose(VERBOSITY_HIGH,
                    'Warning: Found ' . $sHeaderID . ' annotation INFO header without any fields in the description? Got:' . "\n" . $sLine . "\n");
                $bWarningsOcurred = true;
                continue;
            }
            // Report. If there is a dot, also report first sentence.
            lovd_printIfVerbose(VERBOSITY_FULL,
                'Info: Found ' . $sHeaderID . ' annotation INFO header with ' . $nAnnotationFields . ' field' . ($nAnnotationFields == 1? '' : 's') .
                (strpos($sDescription, '.') === false? '.' : ': ' . substr($sDescription, 0, strpos($sDescription, '.')+1)) . "\n");

            // If we get here, we're done. Stop looking now.
            break;
        }
    }
}

// Do we have everything?
if (empty($_CONFIG['annotation_fields'])) {
    lovd_printIfVerbose(VERBOSITY_LOW,
        'Error: Can not find annotation header. Looking for: ' . implode(', ', array_keys($_CONFIG['annotation_ids'])) . '.' . "\n\n");
    die(EXIT_ERROR_TAG_NOT_FOUND);
}
$sAnnotationTag = key($_CONFIG['annotation_fields']); // What to look for in the INFO fields.

// We'll create some columns, too.
if (in_array('AD', $_CONFIG['format_fields'])) {
    foreach (array('DP', 'DPREF', 'DPALT') as $sField) {
        if (!in_array($sField, $_CONFIG['format_fields'])) {
            $_CONFIG['format_fields'][] = $sField;
        }
    }
}





// Start outputting the file.
// Print the headers that we already saw (might not be all).
print(implode("\n", $aHeaders) . "\n");
unset($aHeaders);
$bData = false; // Are we parsing data yet?
$aVCFFields = array(); // VCF fields in this VCF file (including samples, hopefully).
$nVCFFields = 0; // Number of fields in this VCF file.
$nMandatoryVCFFields = count($_CONFIG['VCF_fields']);
$aVOGFields = array_slice($_CONFIG['VCF_fields'], 0, $nMandatoryVCFFields - 1); // VCF fields specific for the VOG.
$aHeadersToPrint = array();
$aSamples = array();

while ($sLine = fgets($fInput)) {
    $nLine++;
    $sLine = trim($sLine);
    if (!$sLine) {
        continue;
    }
    if (substr($sLine, 0, 2) == '##') {
        // Just more headers.
        print($sLine . "\n");
        continue;
    }

    if (substr($sLine, 0, 1) == '#') {
        if ($bData) {
            // Wait, we already saw headers before... Treat as comment.
            print($sLine . "\n");
            continue;
        }
        // The VCF file header!
        $aVCFFields = explode("\t", substr($sLine, 1));
        // Check if the headers are OK.
        if (array_slice($aVCFFields, 0, $nMandatoryVCFFields) !== $_CONFIG['VCF_fields']) {
            // Mandatory fields not found.
            lovd_printIfVerbose(VERBOSITY_LOW,
                'Error: Can not parse VCF file fields. Looking for:' . "\n" .
                implode(', ', $_CONFIG['VCF_fields']) . '. Got:' . "\n" .
                implode(', ', array_slice($aVCFFields, 0, $nMandatoryVCFFields)) . '.' . "\n\n");
            die(EXIT_ERROR_HEADER_FIELDS_INCORRECT);
        }

        // Now, we'll look for format and one sample.
        if ($aVCFFields[$nMandatoryVCFFields] != 'FORMAT') {
            lovd_printIfVerbose(VERBOSITY_LOW,
                'Error: Can not parse VCF file fields. Looking for:' . "\n" .
                implode(', ', $_CONFIG['VCF_fields']) . ', FORMAT. Got:' . "\n" .
                implode(', ', array_slice($aVCFFields, 0, $nMandatoryVCFFields + 1)) . '.' . "\n\n");
            die(EXIT_ERROR_HEADER_FIELDS_INCORRECT);
        }

        // Everything after FORMAT is a sample. We don't restrict to a certain number of samples.
        // It is up to the converter later to choose which sample it will import.
        $bData = true; // From now on, we'll treat each line as data.
        $nVCFFields = count($aVCFFields);

        // Now print the header together with all the info from the annotation.
        // We drop INFO, FORMAT and the sample header.
        $aHeadersToPrint = array_merge(
            $aVOGFields,
            current($_CONFIG['annotation_fields'])
        );
        // Format columns are per sample.
        $aSamples = array_slice($aVCFFields, $nMandatoryVCFFields + 1);
        foreach ($aSamples as $sSample) {
            foreach ($_CONFIG['format_fields'] as $sFormatField) {
                $aHeadersToPrint[] = $sSample . '.' . $sFormatField;
            }
        }
        print('#' . implode("\t", $aHeadersToPrint) . "\n");
        continue;

    } elseif (!$bData) {
        // Seeing data without having parsed the fields... Nope.
        lovd_printIfVerbose(VERBOSITY_LOW,
            'Error: Can not find VCF file fields. Looking for:' . "\n" .
            '#' . implode("\t", $_CONFIG['VCF_fields']) . "\n\n");
        die(EXIT_ERROR_HEADER_FIELDS_NOT_FOUND);
    }



    // Parsing data.
    $aLine = explode("\t", $sLine);
    if (count($aLine) != $nVCFFields) {
        // Incorrect number of fields found.
        lovd_printIfVerbose(VERBOSITY_LOW,
            'Error: Incorrect number of fields on line ' . $nLine . '. Looking for ' . $nVCFFields . ' fields, found ' . count($aLine) . '.' . "\n\n");
        die(EXIT_ERROR_DATA_FIELD_COUNT_INCORRECT);
    }

    $aLine = array_combine($aVCFFields, $aLine);

    $aALTs = explode(',', $aLine['ALT']);
    $nALTs = count($aALTs);

    // Now populate the annotation fields, and the format fields.
    $nPosAnnotation = strpos(';' . $aLine['INFO'], ';' . $sAnnotationTag . '=');
    if ($nPosAnnotation === false) {
        // Annotation not found. Don't die.
        lovd_printIfVerbose(VERBOSITY_MEDIUM,
            'Warning: Line ' . $nLine . ' does not contain any annotation. Looking for ' . $sAnnotationTag . ' in the INFO field.' . "\n");
        $bWarningsOcurred = true;
        $aVOTs = array(
            '', // Just empty data.
        );

    } else {
        // Now cut out just the annotation and discard any other info that might be there.
        // FIXME: If we choose to copy ALL data from the INFO field, then we might as well run an explode(";") on the INFO data.
        //   But I don't think we need everything (will require more header parsing) and substr() with strpos() will probably be much faster.
        $sAnnotation = substr($aLine['INFO'], ($nPosAnnotation + 4), strpos($aLine['INFO'] . ';', ';', $nPosAnnotation) - ($nPosAnnotation + 4));
        $aVOTs = explode(',', $sAnnotation);
    }

    // Handle the FORMAT column.
    $aFormatFields = explode(':', $aLine['FORMAT']);
    $aFormatValues = array();
    foreach ($aSamples as $sSample) {
        $aSampleValues = explode(':', $aLine[$sSample]);
        if (count($aSampleValues) != count($aFormatFields)) {
            // Eh? Different number of sample fields found than defined in the FORMAT value?
            lovd_printIfVerbose(VERBOSITY_MEDIUM,
                'Warning: Line ' . $nLine . ' does not contain correct number of FORMAT fields. Looking for ' . count($aFormatFields) . ' fields, found ' . count($aSampleValues) . ".\n");
            $bWarningsOcurred = true;
            // Not sure if this ever happens, but let's try to pad the data we received.
            // If it contains more fields than the field listing, feel free to fail completely.
            $aSampleValues = array_pad($aSampleValues, count($aFormatFields), '');
        }

        $aSampleValues = array_combine($aFormatFields, $aSampleValues);

        // The FORMAT fields also sometimes depend on the ALTs.
        foreach ($aALTs as $nKeyALT => $sALT) {
            $aFormatValues[$sALT][$sSample] = $aSampleValues;
            if ($nALTs > 1) {
                // Handle special cases.
                if (isset($aSampleValues['GT'])) {
                    $aFormatValues[$sALT][$sSample]['GT'] = lovd_cleanGenoType($aSampleValues['GT'], $nKeyALT);
                }
            }
            // Handle Allelic Depths.
            if (isset($aSampleValues['AD'])) {
                $aADs = explode(',', $aSampleValues['AD']);
                // Fill in total depth, depth of REF and ALT depths, based on the AD field.
                // We don't overwrite the DP, as it may include reads with alleles that aren't mentioned here.
                if (!isset($aFormatValues[$sALT][$sSample]['DP'])) {
                    $aFormatValues[$sALT][$sSample]['DP'] = array_sum($aADs);
                }
                if (!isset($aFormatValues[$sALT][$sSample]['DPREF'])) {
                    $aFormatValues[$sALT][$sSample]['DPREF'] = $aADs[0];
                }
                if (!isset($aFormatValues[$sALT][$sSample]['DPALT'])) {
                    $aFormatValues[$sALT][$sSample]['DPALT'] = $aADs[$nKeyALT+1];
                }
            }
        }
    }

    // VEP shortens the Allele in the annotation, so we need to do the same (VEP v93).
    // Their shortening is based on REF, too. ALTs "A,AT" only get shortened to "-,T" if REF starts with "A".
    // In principle, we could assume that when having just one ALT allele, VEP will only provide info for this one.
    $aALTsCleaned = array_slice(lovd_ltrimCommonChars(array_merge(array($aLine['REF']), $aALTs)), 1);
    $aVOTsPerAllele = array(); // Sort the annotation per the ALT allele.

    // Now loop the transcript mappings.
    foreach ($aVOTs as $sVOT) {
        $aVOT = explode($_CONFIG['annotation_ids'][$sAnnotationTag], $sVOT);
        if (count($aVOT) != $nAnnotationFields) {
            // Eh? Different number of annotation fields found than defined in the VCF header?
            // Don't print the warning if we already warned about not having annotation at all.
            if ($nPosAnnotation !== false) {
                lovd_printIfVerbose(VERBOSITY_MEDIUM,
                    'Warning: Line ' . $nLine . ' does not contain correct number of annotation fields. Looking for ' . $nAnnotationFields . ' fields, found ' . count($aVOT) . ".\n");
                $bWarningsOcurred = true;
            }
            // Not sure if this ever happens, but let's try to pad the data we received.
            // If it contains more fields than the header, feel free to fail completely.
            $aVOT = array_pad($aVOT, $nAnnotationFields, '');
        }
        $aVOT = array_combine($_CONFIG['annotation_fields'][$sAnnotationTag], $aVOT);
        $aVOT['__allele__'] = (isset($aVOT['Allele'])? trim($aVOT['Allele'], '-') : '');

        // Check with allele this line belongs to.
        if (!in_array($aVOT['__allele__'], $aALTsCleaned) && $nPosAnnotation !== false) {
            lovd_printIfVerbose(VERBOSITY_MEDIUM,
                'Warning: Line ' . $nLine . ' contains annotation for Allele = "' . $aVOT['__allele__'] . '", which cannot be mapped to one of ("' . implode('", "', $aALTsCleaned) . '").' . "\n");
            $bWarningsOcurred = true;
            continue;
        }

        $aVOTsPerAllele[$aVOT['__allele__']][] = $aVOT;
    }

    // Now print the data.
    foreach ($aALTs as $nKeyALT => $sALT) {
        $aLine['ALT'] = $sALT;

        // It is possible that there is no VOT data for this ALT. Still, we want to print some data.
        if (!isset($aVOTsPerAllele[$aALTsCleaned[$nKeyALT]])) {
            $aVOTsPerAllele[$aALTsCleaned[$nKeyALT]] = array(array());
        }

        foreach ($aVOTsPerAllele[$aALTsCleaned[$nKeyALT]] as $aVOT) {
            // Print the data line.
            // VCF data.
            foreach ($aVOGFields as $nKey => $sHeader) {
                print((!$nKey? '' : "\t") .
                    $aLine[$sHeader]);
            }
            // Annotation data.
            foreach ($_CONFIG['annotation_fields'][$sAnnotationTag] as $sHeader) {
                print("\t" . (!isset($aVOT[$sHeader])? '' : $aVOT[$sHeader]));
            }
            // Sample data.
            foreach ($aFormatValues[$sALT] as $aSampleValues) {
                foreach ($_CONFIG['format_fields'] as $sHeader) {
                    print("\t" . (!isset($aSampleValues[$sHeader])? '' : $aSampleValues[$sHeader]));
                }
            }
            print("\n");
        }
    }

    if (!($nLine % 10000)) {
        lovd_printIfVerbose(VERBOSITY_HIGH,
            '------- Line ' . str_repeat(' ', 6 - strlen($nLine)) . $nLine . ' ------- ' . date('Y-m-d H:i:s') . "\n");
    }
}

die($bWarningsOcurred? EXIT_WARNINGS_OCCURRED : EXIT_OK);
?>
