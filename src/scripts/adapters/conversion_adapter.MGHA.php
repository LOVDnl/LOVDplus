#!/usr/bin/php
<?php
/*******************************************************************************
 * CREATE META DATA FILES FOR MGHA
 * Created: 2016-04-22
 * Programmer: Candice McGregor
 *************/

define('ROOT_PATH', dirname(__FILE__) . '/../');
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
        'Screening/Template' => 'DNA',
        'Screening/Technique' => 'SEQ-NG',
        'variants_found' => 1,
        'id' => 1,
        'id_sample' => 1
    );

    // open the data files folder and process files
    $h = opendir($_INI['paths']['data_files']);

    if (!$h) {
        print('Can\'t open directory.' . "\n");
        die(51);
    }

    // need to find the sample meta data file (SMDF) first. There may be multiple SMDF as we do not move files after they are processed.
    // However once processed they are renamed to .ARK so we are able to find files to process based on this
    while (($xFile = readdir($h)) !== false) {
        if ($xFile{0} == '.') {
            // Current dir, parent dir, and hidden files.
            continue;
        }

        // get the SMDF, it is possible there could be more than one, but we are only going to take the first one
        // we have discussed that this means there is the potential that any subsequent SMDF will not be processed until any issues with the first one are addressed, at this stage we are not concerned with this
        // the naming of the file has not yet been confirmed, for testing we are using "SMDF"
        if (preg_match('/^.+?\.SMDF$/', $xFile)) {

            $metaFile = $_INI['paths']['data_files'] . $xFile;
        }

        // get all the variant files into an array
        if (preg_match('/^(.+?)\.tsv/', $xFile, $vRegs)) {
            $sID = $vRegs[1];
            $vFiles[$sID] = $xFile;

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
    $sFile = file($metaFile);

    // Create an array of headers from the first line
    $sHeader = explode("\t", $sFile[0]);

    foreach ($sFile as $nKey => $sValue) {

        if ($nKey > 0) { // Skips the first line
            $sValues = explode("\t", $sValue);
            $sValues = array_combine($sHeader, $sValues);
            $sValues['parent'] = null;
            $sValues['mother_id'] = null;
            $sValues['father_id'] = null;
            $sDataArr[$sValues['Sample_ID']] = $sValues;
        }
    }

    // loop through each sample
    foreach ($sDataArr as $sample) {

        $sampleID = $sample['Sample_ID'];
        // if Pedigree_File column is not empty, then it is a trio and we need to get the parent IDs, work out who is mother and father based on sex and update child's record
        If (!empty($sample['Pedigree_File'])) {

            if (preg_match('/^fid\d+\=(.+)/', $sample['Pedigree_File'], $pRegs)) {
                // can combine this into the above regex
                $parentIDs = explode(",", $pRegs[1]);
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
            }
        }

        // convert unknown to ? for Consanguinity otherwise will not import into LOVD
        if (strtoupper($sample['Consanguinity']) == 'UNKNOWN') {
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
        // update the gender (sex) to only store the first character. M = Male  F = Female
        $sDataArr[$sID]['Sex'] = strtoupper(substr($sKeys['Sex'], 0, 1));

        if (!$parent) {
            If (!in_array($sID, array_keys($vFiles))) {
                print('There is no variant file for Sample ID ' . $sID . "\n");
                die(52);
            } else {
                // update the headers in the variant file for the singleton/child
                $variantFile = $_INI['paths']['data_files'] . $vFiles[$sID];
                $variantFileArr = file($variantFile);
                // use preg_replace to update the column headers using child, father and mother sample IDs.
                $variantHeader = preg_replace("/" . $sID . "\./", "Child_", $variantFileArr[0]);

                if (!empty($sKeys['mother_id'])) {
                    $variantHeader = preg_replace("/" . $sKeys['mother_id'] . "\./", "Mother_", $variantHeader);
                }
                if (!empty($sKeys['father_id'])) {
                    $variantHeader = preg_replace("/" . $sKeys['father_id'] . "\./", "Father_", $variantHeader);
                }

                $variantFileArr[0] = $variantHeader;
                file_put_contents($variantFile, $variantFileArr);
                // ********** error handling to check the contents were updated
            }
        }
    }

    $zDataArr = array(); // output data array

    // Prepare Individuals and Screenings, include the found columns.
    foreach ($sDataArr as $sKey => $sVal) {
        if (empty($sVal['parent'])) {

            // set the IDs for each section, since we are generating one meta data file per child/singleton, there will only ever be 1 individual record and screening
            $aColumnsForScreening['individualid'] = $sVal['Sample_ID'];
            $aColumnsForIndividual['id'] = $sVal['Sample_ID'];


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
            if (!empty($sVal['mother_id']) || !empty($sVal['father_id'])) {
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
            // create a temp file first while we write out the records
            // file formats are in line with Lieden
            $xFileTmp = $_INI['paths']['data_files'] . $sVal['Sample_ID'] . '.meta.lovd.tmp';;
            $xFileDone = $_INI['paths']['data_files'] . $sVal['Sample_ID'] . '.meta.lovd';


            // open the temporary file for writing
            $fOutput = fopen($xFileTmp, 'w');
            if ($fOutput === false) {
                print('Error opening the temporary output file: ' . $xFileTmp . ".\n");
                die(51);
            }

            // write out the heading information for the meta data file
            fputs($fOutput,
                '### LOVD-version 3000-080 ### Full data download ### To import, do not remove or alter this header ###' . "\r\n" .
                '# charset = UTF-8' . "\r\n\r\n" .
                '## Diseases ## Do not remove or alter this header ##' . "\r\n\r\n" .
                '## Individuals ## Do not remove or alter this header ##' . "\r\n" .
                '"{{' . implode("}}\"\t\"{{", array_keys($aColumnsForIndividual)) . '}}"' . "\r\n" .
                '"' . implode("\"\t\"", array_values($aColumnsForIndividual)) . '"' . "\r\n\r\n" .
                '## Individuals_To_Diseases ## Do not remove or alter this header ##' . "\r\n\r\n" .
                '## Screenings ## Do not remove or alter this header ##' . "\r\n" .
                '"{{' . implode("}}\"\t\"{{", array_keys($aColumnsForScreening)) . '}}"' . "\r\n" .
                '"' . implode("\"\t\"", array_values($aColumnsForScreening)) . '"' . "\r\n"

            );

            fclose($fOutput);

            // Now rename the tmp to the final file, and close this loop.
            if (!rename($xFileTmp, $xFileDone)) {
                print('Error renaming temp file to target: ' . $xFileDone . ".\n");
                die(51);
            }
        }
    }


    // Now rename the SMDF to .ARK
    $archiveMetaFile = $metaFile . '.ARK';
    if (!rename($metaFile, $archiveMetaFile)) {
        print('Error archiving SMDF to: ' . $archiveMetaFile . ".\n");
        die(51);
    }

    // Now rename all the variant .tsv files to .ARK
    foreach ($vFiles as $vID => $vFileName) {
        $oldVariantFile = $_INI['paths']['data_files'] . $vFileName;
        $newVariantFile = $_INI['paths']['data_files'] . $vID . '.directvep.data.lovd';
        if (!rename($oldVariantFile, $newVariantFile)) {
            print('Error renaming tsv variant file to: ' . $newVariantFile . "\n");
            die(51);
        }
    }

    print('Adapter Process Complete' . "\n" . 'Current time: ' . date('Y-m-d H:i:s') . ".\n\n");
}
?>