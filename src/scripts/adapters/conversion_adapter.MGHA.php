#!/usr/bin/php
<?php
/*******************************************************************************
 * CREATE META DATA FILES FOR MGHA
 * Created: 2016-04-22
 * Programmer: Candice McGregor
 *************/

// We are using a symlink to include this file so any further includes relative to this file needs to use the symlink path instead of the actual files path.
define('ROOT_PATH', realpath(dirname($_SERVER["SCRIPT_FILENAME"])) . '/../');
define('FORMAT_ALLOW_TEXTPLAIN', true);
$_GET['format'] = 'text/plain';
// To prevent notices when running inc-init.php.
$_SERVER = array_merge($_SERVER, array(
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => 'scripts/adapter.php',
    'QUERY_STRING' => '',
    'REQUEST_METHOD' => 'GET',
));

require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-genes.php';
ini_set('memory_limit', '4294967296');


if ($argc != 1 && in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
    ?>

    This is a command line PHP script which creates meta data files for MGHA that are compatible with LOVD
    After this script is executed the convert_and_merge_data_files.php script should be run to merge
    the meta data files with the variant files. The final merged file should then be imported into LOVD

    Error Handling
    51 - Error opening or renaming files or directories
    52 - Required files are missing. Check sample meta data file or variant files
    53 - File does not conform to expected format. Check sample meta data file.
    54 - Unexpected gender for parent. Either two females, two males or gender other than male or female
    <?php
} else {


    /*******************************************************************************/
    // set variables

    $vFiles = array(); // array(ID => array(files), ...);
    $metaFile = '';

    // create mapping arrays for singleton/child record, mother and father
    $aColumnMappings = array(
        'Pipeline_Run_ID' => 'Screening/Pipeline/Run_ID',
        'Batch' => 'Screening/Batch',
        'Sample_ID' => 'Individual/Sample_ID',
        'DNA_Tube_ID' => 'Screening/DNA/Tube_ID',
        'Sex' => 'Individual/Gender',
        'DNA_Concentration' => 'Screening/DNA/Concentration',
        'DNA_Volume' => 'Screening/DNA/Volume',
        'DNA_Quantity' => 'Screening/DNA/Quantity',
        'DNA_Quality' => 'Screening/DNA/Quality',
        'DNA_Date' => 'Screening/DNA/Date',
        'Cohort' => 'Individual/Cohort',
        'Sample_Type' => 'Screening/Sample/Type',
        'Fastq_Files' => 'Screening/FastQ_files',
        'Prioritised_Genes' => 'Screening/Prioritised_genes',
        'Consanguinity' => 'Individual/Consanguinity',
        'Variants_File' => 'Screening/Variants_file',
        'Pedigree_File' => 'Screening/Pedigree_file',
        'Ethnicity' => 'Individual/Origin/Ethnic',
        'VariantCall_Group' => 'Screening/Variant_call_group',
        'Capture_Date' => 'Screening/Capture_date',
        'Sequencing_Date' => 'Screening/Sequencing_date',
        'Mean_Coverage' => 'Screening/Mean_coverage',
        'Duplicate_Percentage' => 'Screening/Duplicate_percentage',
        'Machine_ID' => 'Screening/Machine_ID',
        'DNA_Extraction_Lab' => 'Screening/DNA_extraction_lab',
        'Sequencing_Lab' => 'Screening/Sequencing_lab',
        'Exome_Capture' => 'Screening/Exome_capture',
        'Library_Preparation' => 'Screening/Library_preparation',
        'Barcode_Pool_Size' => 'Screening/Barcode_pool_size',
        'Read_Type' => 'Screening/Read_type',
        'Machine_Type' => 'Screening/Machine_type',
        'Sequencing_Chemistry' => 'Screening/Sequencing_chemistry',
        'Sequencing_Software' => 'Screening/Sequencing_software',
        'Demultiplex_Software' => 'Screening/Demultiplex_software',
        'Hospital_Centre' => 'Individual/Hospital_centre',
        'Sequencing_Contact' => 'Screening/Sequencing_contact',
        'Pipeline_Contact' => 'Screening/Pipeline_contact',
        'Notes' => 'Screening/Notes',
        'Pipeline_Notes' => 'Screening/Pipeline/Notes',
        'Analysis_Type' => 'Screening/Analysis_type'

    );

    // these are the columns for the mother and father. As they are the same columns for both, we will loop through and replace "Parent" with "Father" and "Mother" before writing out the data
    $parentColumnMappings = array(
        'Sample_ID' => 'Screening/Parent/Sample_ID',
        'Ethnicity' => 'Screening/Parent/Origin/Ethnic',
        'DNA_Tube_ID' => 'Screening/Parent/DNA/Tube_ID',
        'Notes' => 'Screening/Parent/Notes'
    );


    // Screening Default values.
    $aDefaultValues = array(
        'variants_found' => 1,
        'id' => 1,
        'id_sample' => 0
    );

    // open the data files folder and process files
    $h = opendir($_INI['paths']['data_files']);

    if (!$h) {
        print('Can\'t open directory.' . "\n");
        die(51);
    }

    // Fix any trailing slashes in the path to the data files.
    $_INI['paths']['data_files'] = rtrim($_INI['paths']['data_files'], "\\/") . "/";

    // need to find the sample meta data file (SMDF) first. There may be multiple SMDF as we do not move files after they are processed.
    // However once processed they are renamed to .ARK so we are able to find files to process based on this
    while (($xFile = readdir($h)) !== false) {
        if ($xFile{0} == '.') {
            // Current dir, parent dir, and hidden files.
            continue;
        }

        // get the SMDF, it is possible there could be more than one, but we are only going to take the first one
        // we have discussed that this means there is the potential that any subsequent SMDF will not be processed until any issues with the first one are addressed, at this stage we are not concerned with this

        if (preg_match('/^.+?\.meta$/', $xFile)) {

            $metaFile = $_INI['paths']['data_files'] . $xFile;
            $archiveMetaFile = $xFile . '.ARK';
        }

        // get all the variant files into an array
        // fileType is trio or individual
        if (preg_match('/^(.+?)\.tsv/', $xFile, $vRegs)) {
            $variantFilePrefix = explode('_', $vRegs[1]);
            $fileSampleIDs = explode('.', $variantFilePrefix[3]);
            $sID = $fileSampleIDs[0];
            $fileType = $fileSampleIDs[1];
            $vFiles[$sID][$fileType] = $xFile;

        }

    }


    // If no SMDF found and tsv variant files are found, do not continue
    if (!$metaFile && !empty($vFiles)) {
        print('Variant files found without a sample meta data file' . ".\n");
        die(52);
    }elseif(!$metaFile){
        return;
    }

    // set arrays
    $sDataArr = array();
    $parentArr = array();

    // open the file, get first line as string to check headers match expected output.
    $fInput = fopen($metaFile, 'r');
    if ($fInput === false) {
        print('Error opening file: ' . $metaFile . ".\n");
        die(51);
    }

    $strHeaders = fgets($fInput);

    if (substr($strHeaders, 0, 76) != "Pipeline_Run_ID\tBatch\tSample_ID\tDNA_Tube_ID\tSex\tDNA_Concentration\tDNA_Volume") {
        print('File does not conform to format: ' . $metaFile . ".\n");
        die(53);
    }

    fclose($fInput);

    // open the sample meta data file into an array
    $sFile = file($metaFile,FILE_IGNORE_NEW_LINES);

    // Create an array of headers from the first line
    $sHeader = explode("\t", $sFile[0]);

    foreach ($sFile as $nKey => $sValue) {

        if ($nKey > 0) { // Skips the first line
            $sValues = explode("\t", $sValue);
            $sValues = array_combine($sHeader, $sValues);
            $sValues['trio'] = null;
            $sValues['parent'] = null;
            $sValues['mother_id'] = null;
            $sValues['father_id'] = null;
            $sDataArr[$sValues['Sample_ID']] = $sValues;

            // get the pipeline run ID
            $pipelineRunID = $sValues['Pipeline_Run_ID'];
        }
    }

    // loop through each sample
    foreach ($sDataArr as $sample) {

        $sampleID = $sample['Sample_ID'];
        // if Pedigree_File column is not empty, then it is a trio and we need to get the parent IDs, work out who is mother and father based on sex and update child's record
        If (!empty($sample['Pedigree_File'])) {

            if (preg_match('/^(.+?)\=(.+)/', $sample['Pedigree_File'], $pRegs)) {
                // can combine this into the above regex
                $parentIDs = explode(",", $pRegs[2]);
                $fatherCount = 0;
                $motherCount = 0;

                //loop through parent sample IDs and check gender. Should have male and female. If unknown we exit. If both male or female, we exit out
                foreach ($parentIDs as $parentID) {
                    $parentGender = $sDataArr[$parentID]['Sex'];
                    $sDataArr[$parentID]['parent'] = "T";

                    if ($parentGender == 'Male') {
                        $fatherID = $parentID;
                        $parent = 'Father';
                        $fatherCount++;
                        if ($fatherCount > 1) {
                            print('We have 2 parent IDs with the gender Male for sample ID ' . $sampleID . ".\n");
                            die(54);
                        }
                    } elseif ($parentGender == 'Female') {
                        $motherID = $parentID;
                        $parent = 'Mother';
                        $motherCount++;
                        if ($motherCount > 1) {
                            print('We have 2 parent IDs with the gender Female for sample ID ' . $sampleID . ".\n");
                            die(54);
                        }
                    } else {
                        print('Unknown Gender for Sample' . $parentID . ".\n");
                        die(54);
                    }

                    foreach ($parentColumnMappings as $pCol => $lCol) {
                        $LOVDColumn = str_replace('Parent', $parent, $lCol);
                        $parentArr[$sampleID][$LOVDColumn] = $sDataArr[$parentID][$pCol];

                    }
                }
                // update the father ID and mother ID on the child's record
                $sDataArr[$sampleID]['father_id'] = $fatherID;
                $sDataArr[$sampleID]['mother_id'] = $motherID;

                // update the trio flag on the child record
                $sDataArr[$sampleID]['trio'] = "T";
            }
        }

        // convert unknown to ? for Consanguinity otherwise will not import into LOVD
        if (strtoupper($sample['Consanguinity']) == 'UNKNOWN' | $sample['Consanguinity'] == '') {
            $sDataArr[$sampleID]['Consanguinity'] = '?';
        } else {
            $sDataArr[$sampleID]['Consanguinity'] = strtolower($sDataArr[$sampleID]['Consanguinity']);
        }

    }

    // remove any tsv files that are not for a singleton or child listed in the SMDF
    foreach ($vFiles as $vFileSampleID => $variantFileName) {

        If (!in_array($vFileSampleID, array_keys($sDataArr))) {
            unset($vFiles[$vFileSampleID]);
            continue;
        }
    }


    // check we have variant files for all the samples
    foreach ($sDataArr as $sKeys) {
        $sID = $sKeys['Sample_ID'];
        $parent = $sKeys['parent'];
        $trio = $sKeys['trio'];

        // update the gender (sex) to only store the first character. M = Male  F = Female
        $sDataArr[$sID]['Sex'] = strtoupper(substr($sKeys['Sex'], 0, 1));

        if (!$parent) {
            If (!in_array($sID, array_keys($vFiles))) {
                print('There is no variant file for Sample ID ' . $sID . "\n");
                die(52);
            } else {
                if ($trio == 'T') {

                    if (!in_array('trio', array_keys($vFiles[$sID]))) {
                        print('There is no trio variant file for Sample ID ' . $sID . "\n");
                        die(52);
                    } else {

                        // use preg_replace to update the column headers using child, father and mother sample IDs.
                        $variantFile = $_INI['paths']['data_files'] . $vFiles[$sID]['trio'];
                        $variantFileArr = file($variantFile, FILE_IGNORE_NEW_LINES);
                        $variantHeader = preg_replace("/" . $sID . "\./", "Child_", $variantFileArr[0]);

                        if (!empty($sKeys['mother_id'])) {
                            $variantHeader = preg_replace("/" . $sKeys['mother_id'] . "\./", "Mother_", $variantHeader);
                        }
                        if (!empty($sKeys['father_id'])) {
                            $variantHeader = preg_replace("/" . $sKeys['father_id'] . "\./", "Father_", $variantHeader);
                        }

                        $variantFileArr[0] = $variantHeader;
                        file_put_contents($variantFile, implode(PHP_EOL, $variantFileArr));
                        // ********** error handling to check the contents were updated
                    }
                }
                // we should have a tsv file for the singleton(individual)
                if (!in_array('individual', array_keys($vFiles[$sID]))) {
                    print('There is no individual variant file for Sample ID ' . $sID . "\n");
                    die(52);
                } else {
                    // use preg_replace to update the column headers using child, father and mother sample IDs.
                    $variantFile = $_INI['paths']['data_files'] . $vFiles[$sID]['individual'];
                    $variantFileArr = file($variantFile, FILE_IGNORE_NEW_LINES);
                    $variantHeader = preg_replace("/" . $sID . "\./", "Child_", $variantFileArr[0]);

                    $variantFileArr[0] = $variantHeader;
                    file_put_contents($variantFile, implode(PHP_EOL, $variantFileArr));
                    // ********** error handling to check the contents were updated
                }

            }

        }
    }

    $zDataArr = array(); // output data array

    // Prepare Individuals and Screenings, include the found columns.
    foreach ($sDataArr as $sKey => $sVal) {
        if (empty($sVal['parent'])) {

            // set the IDs for each section, since we are generating one meta data file per child/singleton, there will only ever be 1 individual record and screening
            // look up sample ID in the database to check if it exists. If it does, then use the database ID as the individualid and id to link the new screening information
            if ($existingSampArr = $_DB->query('SELECT id FROM ' . TABLE_INDIVIDUALS . ' WHERE `Individual/Sample_ID` = ' . $sVal['Sample_ID'])->fetchAssoc()) {
                $individualExists = true;
                if ($sVal['trio'] == 'T' && $sVal['Cohort'] == 'CN'){
                    // individual trio files for CN patients go into a separate LOVD+ database, since we do not know that specific database ID we cannot continue and need to rename this file and alert user
                    print('Cannot process individual variant file for sample ' . $sVal['Sample_ID'] . ' as the sample is for cohort CN and has already been imported into the database. Please handle manually' . "\n");

                }else{
                    $aColumnsForScreening['individualid'] = $existingSampArr['id'];
                }

            } elseif ($sVal['trio'] == 'T' && $sVal['Cohort'] !== 'CN') {
                // need to insert a database record so we can get the database ID for the meta data files
                // we only do this if the cohort is not CN, as we need the individual info to be created during import as the file is imported into another database

                // current custom columns are: Sample_ID, Consanguinity, Hospital_centre, Cohort
                if ($newDBID = $_DB->query('INSERT INTO ' . TABLE_INDIVIDUALS . ' (panel_size, custom_panel, created_date, `Individual/Sample_ID`,`Individual/Consanguinity`,`Individual/Hospital_centre`,`Individual/Cohort`) VALUES (?, ?, NOW(), ?, ?, ?, ?)', array(3, '', $sVal['Sample_ID'], $sVal['Consanguinity'], $sVal['Hospital_Centre'], $sVal['Cohort']))) {
                    $individualID = $_DB->lastInsertId();
                    $aColumnsForScreening['individualid'] = $individualID;
                    $individualExists = true;
                } else {
                    // check with anthony whether we should error out  *************FIX
                    print('Can\'t create individual record in database for ' . $sVal['Sample_ID'] . '.' . "\n");

                }
            } else {
                $individualExists = false;
                $aColumnsForScreening['individualid'] = $sVal['Sample_ID'];
                $aColumnsForIndividual['id'] = $sVal['Sample_ID'];
            }

            // Create the custom link data for the pipeline files.
            // TODO MGHA AM - How do we know if we are creating the singleton or the trio screening here when this sample is run for both? We need to know this for the summary file name as it could have .trio in it.
            $aColumnsForScreening['Screening/Pipeline_files'] = '{gap:' . $sVal['Pipeline_Run_ID'] . '_' . $sVal['Sample_ID'] . '.gap.csv} {prov:' . $sVal['Pipeline_Run_ID'] . '_' . $sVal['Sample_ID'] . '.provenance.pdf} {summary:' . $sVal['Pipeline_Run_ID'] . '_' . $sVal['Sample_ID'] . '.summary.htm}';

            // add default values, we currently only have them for screening, if we get some for individual we need to handle this below.
            foreach ($aDefaultValues as $dKey => $dVal) {
                $aColumnsForScreening[$dKey] = $dVal;
            }

            // Map pipeline columns to LOVD columns.
            foreach ($aColumnMappings as $pipelineColumn => $sLOVDColumn) {

                if (empty($sVal[$pipelineColumn]) || $sVal[$pipelineColumn] == '.') {
                    $sVal[$pipelineColumn] = '';
                }

                if (substr($sLOVDColumn, 0, 11) == 'Individual/') {
                    $aColumnsForIndividual[$sLOVDColumn] = $sVal[$pipelineColumn];
                } elseif (substr($sLOVDColumn, 0, 10) == 'Screening/') {
                    $aColumnsForScreening[$sLOVDColumn] = $sVal[$pipelineColumn];
                }

            }

            // if trio then add the parent columns and mark panel size as trio
            if ($sVal['trio'] == 'T') {
                $aColumnsForIndividual['panel_size'] = 3;
                foreach ($parentArr as $cKey => $pVal) {
                    if ($cKey == $sKey) {
                        $aColumnsForScreening = array_merge($aColumnsForScreening, $pVal);
                        // Need to add code here to check if column name is Individual or Screening to make it more robust.
                    }
                }
            } else {
                // not a trio, so must be a singleton
                $aColumnsForIndividual['panelsize'] = 1;
            }


            // for each singleton and/or child we need to create a meta file
            // for trios we also need to create meta file for the pro band (child)
            // if the cohort is CN we need to append that to the file name as they are imported into a diff database
            // create a temp file first while we write out the records
            // file formats are in line with Lieden
            $xFileTmp = $_INI['paths']['data_files'] . $sVal['Sample_ID'] . '.individual.meta.lovd.tmp';;

            // set the meta data file
            if ($sVal['trio'] == 'T' && $sVal['Cohort'] == 'CN'){

                if ($individualExists === true){
                    $xFileDone = $_INI['paths']['data_files'] . $sVal['Sample_ID'] . '.individual.meta.lovd.CNEXISTING';
                    $CNVariantFile = $_INI['paths']['data_files'] . $vFiles[$sVal['Sample_ID']]['individual'];
                    $newCNVariantFile = $_INI['paths']['data_files'] . $sVal['Sample_ID'] . '.directvep.data.lovd.CNEXISTING';

                }else{
                    $xFileDone = $_INI['paths']['data_files'] . $sVal['Sample_ID'] . '.individual.meta.lovd.CN';
                    $CNVariantFile = $_INI['paths']['data_files'] . $vFiles[$sVal['Sample_ID']]['individual'];
                    $newCNVariantFile = $_INI['paths']['data_files'] . $sVal['Sample_ID'] . '.directvep.data.lovd.CN';

                }
                if (!rename($CNVariantFile, $newCNVariantFile)) {
                    print('Error renaming CN individual variant file ' . $CNVariantFile . ".\n");
                    die(51);
                }else{
                    unset($vFiles[$sVal['Sample_ID']]['individual']);
                }

            }else {
                $xFileDone = $_INI['paths']['data_files'] . $sVal['Sample_ID'] . '.individual.meta.lovd';
            }

            // open the temporary file for writing
            $fOutput = fopen($xFileTmp, 'w');
            if ($fOutput === false) {
                print('Error opening the temporary output file: ' . $xFileTmp . ".\n");
                die(51);
            }
            //write the output data to a variable
            $OutputData =
                '### LOVD-version 3000-080 ### Full data download ### To import, do not remove or alter this header ###' . "\r\n" .
                '# charset = UTF-8' . "\r\n\r\n" .
                '## Diseases ## Do not remove or alter this header ##' . "\r\n\r\n" .
                '## Individuals ## Do not remove or alter this header ##' . "\r\n";

            if ($individualExists === false){
                $OutputData .=
                    '"{{' . implode("}}\"\t\"{{", array_keys($aColumnsForIndividual)) . '}}"' . "\r\n" .
                    '"' . implode("\"\t\"", array_values($aColumnsForIndividual)) . '"' . "\r\n\r\n";
            }
            $OutputData .=
                '## Individuals_To_Diseases ## Do not remove or alter this header ##' . "\r\n\r\n" .
                '## Screenings ## Do not remove or alter this header ##' . "\r\n" .
                '"{{' . implode("}}\"\t\"{{", array_keys($aColumnsForScreening)) . '}}"' . "\r\n" .
                '"' . implode("\"\t\"", array_values($aColumnsForScreening)) . '"' . "\r\n";

            // write out the heading information for the meta data file
            fputs($fOutput,$OutputData);

            fclose($fOutput);

            // Now rename the tmp to the final file, and close this loop.
            if (!rename($xFileTmp, $xFileDone)) {
                print('Error renaming temp file to target: ' . $xFileDone . ".\n");
                die(51);
            }

            if ($sVal['trio'] == 'T') {
                // copy the individual meta data file and rename for trio
                $xFileTrio = $_INI['paths']['data_files'] . $sVal['Sample_ID'] . '.trio.meta.lovd';

                if (!copy($xFileDone, $xFileTrio)) {
                    print('Error creating trio meta data file ' . $xFileTrio . ".\n");
                    die(51);
                }
            }
        }
    }


    // Now rename the SMDF to .ARK
    $archiveMetaFile = $_INI['paths']['data_files'] . $pipelineRunID . '_' . $archiveMetaFile;
    if (!rename($metaFile, $archiveMetaFile)) {
        print('Error archiving SMDF to: ' . $archiveMetaFile . ".\n");
        die(51);
    }

    // Now rename all the variant .tsv files to .ARK
    foreach ($vFiles as $vID => $vFileTypes) {
        $fileSampleID = $vID;
        foreach ($vFileTypes as $vFileType => $vFileName) {
            $oldVariantFile = $_INI['paths']['data_files'] . $vFileName;
            $newVariantFile = $_INI['paths']['data_files'] . $fileSampleID . '.' . $vFileType . '.directvep.data.lovd';
            if (!rename($oldVariantFile, $newVariantFile)) {
                print('Error renaming tsv variant file to: ' . $newVariantFile . "\n");
                die(51);
            }

        }
    }

    print('Adapter Process Complete' . "\n" . 'Current time: ' . date('Y-m-d H:i:s') . ".\n\n");
}
?>