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
    'REQUEST_URI' => 'scripts/convert_and_merge_data_files.php',
    'QUERY_STRING' => '',
    'REQUEST_METHOD' => 'GET',
));

require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-genes.php';
ini_set('memory_limit', '4294967296');

/*******************************************************************************/
// set variables

$vFiles = array();
$metaFile = '';

// open the data files folder and process files
$h = opendir($_INI['paths']['data_files']);

if (!$h) {
    die('Can\'t open directory.' . "\n");
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
    // the naming of the file has not yet been confirmed, for testing we are using "meta"
    if (preg_match('/^(.+?)meta(.+?).txt/i', $xFile)) {

        $metaFile = $_INI['paths']['data_files']  . $xFile;
    }

    // get all the variant files into an array
    if (preg_match('/^(.+?).tsv/', $xFile, $vRegs)){
        //list($sID, $vFileName) = $vRegs;
        $sID = $vRegs[1];
        $vFiles[$sID] = $xFile;

    }

}

// If no SMDF found do not continue
if(!$metaFile){
    die('No Sample Meta Data File found.');
}

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

$motherColumnMappings = array(
    'Sample_ID' => 'Screening/Mother/Sample_ID',
    'Ethnicity' => 'Screening/Mother/Origin/Ethnic',
    'DNA_Tube_ID' => 'Screening/Mother/DNA/Tube_ID',
    'Notes' => 'Screening/Mother/Notes'
);

$fatherColumnMappings = array(
    'Sample_ID' => 'Screening/Father/Sample_ID',
    'Ethnicity' => 'Screening/Father/Origin/Ethnic',
    'DNA_Tube_ID' => 'Screening/Father/DNA/Tube_ID',
    'Notes' => 'Screening/Father/Notes'
);

// open the sample meta data file
// check to make sure you have the right file format, convert and merge data files uses left 53 characters of the heading

// NEED TO ADD THIS CODE

// process the sample IDs, any samples that have pedigree_file column populated are child samples, we need to add columns to flag if a record is a parent and for the father and mother's sample ID

$sDataArr = array();

// open the sample meta data file and read the first line which is the headers
$sFile = file($metaFile,FILE_IGNORE_NEW_LINES);

$sHeader = explode("\t",$sFile[0]); // Create an array of headers from the first line


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


foreach ($sDataArr as $sample) {

   // if Pedigree_File column is not empty, then it is a trio and we need to get the parent IDs, work out who is mother and father based on sex and update child's record
   If(!empty($sample['Pedigree_File'])){

        $sampleID = $sample['Sample_ID'];
        if (preg_match('/^fid\d+\=(.+)/', $sample['Pedigree_File'], $pRegs)) {
            // can combine this into the above regex
            $parentIDs = explode(",", $pRegs[1]);
            $fatherCount = 0;
            $motherCount = 0;
            //loop through parent sample IDs and check gender. Should have male and female. If unknown we exit. If both male or female, we exit out
            foreach ($parentIDs as $parentID){
                $parentGender = $sDataArr[$parentID]['Sex'];
                $sDataArr[$parentID]['parent'] = "T";

                if($parentGender == 'Male'){
                    $fatherID = $parentID;
                    $fatherCount++;
                    if($fatherCount > 1){
                        die('We have 2 parent IDs with the gender Male for sample ID ' . $sampleID);
                    }
                    // NEED TO ADD CODE TO LOOP THROUGH FATHER COLUMNS
                }elseif($parentGender == 'Female'){
                    $motherID = $parentID;
                    $motherCount++;
                    if($motherCount > 1){
                        die('We have 2 parent IDs with the gender Female for sample ID ' . $sampleID);
                    }
                    // NEED TO ADD CODE TO LOOP THROUGH MOTHER COLUMNS
                }else{
                    die('Unknown Gender for Sample' . $parentID);
                }
            }
            // update the father ID and mother ID on the child's record
            $sDataArr[$sampleID]['father_id'] = $fatherID;
            $sDataArr[$sampleID]['mother_id'] = $motherID;
        }
    }
}


// remove any tsv files that are not for a singleton or child listed in the SMDF
foreach($vFiles as $vFileSampleID => $variantFileName){

    If(!in_array($vFileSampleID, array_keys($sDataArr))) {
        unset($vFiles[$vFileSampleID]);
        continue;
    }
}


// check we have variant files for all the samples
foreach($sDataArr as $sKeys) {
    $sID = $sKeys['Sample_ID'];
    $parent = $sKeys['parent'];

    if(!$parent) {
        If (!in_array($sID, array_keys($vFiles))) {
            die('There is no variant file for Sample ID ' . $sID . "\n");
        }
    }
}

$zDataArr = array(); // output data array

// Prepare Individuals and Screenings, include the found columns.

foreach ($sDataArr as $sKey => $sVal){
    if(empty($sVal['parent'])) {

        // set the IDs for each section, since we are generating one meta data file per child/singleton, there will only ever be 1 individual record and screening
        $aColumnsForScreening['id'] = 1;
        $aColumnsForScreening['individual_id'] = $sVal['Sample_ID'];
        $aColumnsForIndividual['id'] = $sVal['Sample_ID'];

        // Map VEP columns to LOVD columns.
        foreach ($aColumnMappings as $pipelineColumn => $sLOVDColumn) {

            if (empty($sVal[$pipelineColumn]) || $sVal[$pipelineColumn] == 'unknown' || $sVal[$pipelineColumn] == '.') {
                $sVal[$pipelineColumn] = '';
            }

            if (substr($sLOVDColumn, 0, 11) == 'Individual/') {
                $aColumnsForIndividual[$sLOVDColumn] = $sVal[$pipelineColumn];

            } elseif (substr($sLOVDColumn, 0, 10) == 'Screening/') {
                $aColumnsForScreening[$sLOVDColumn] = $sVal[$pipelineColumn];
            }
        }
        // for each singleton and/or child we need to create a meta file
        // create a temp file first while we write out the records
        $xFileTmp = $_INI['paths']['data_files'] . $sVal['Sample_ID'] . '_Meta_File.tmp';;
        $xFileDone = $_INI['paths']['data_files'] . $sVal['Sample_ID'] . '_Meta_File.txt';


        // open the temporary file for writing
        $fOutput = fopen($xFileTmp, 'w');

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
            // Fatal error, because we're all done actually!
            die('Error moving temp file to target: ' . $xFileDone . ".\n");
        }
    }
}


// Now rename the SMDF to .ARK
//*** NEED TO ADD THIS CODE ***

print('All done, files are ready for merging.' . "\n" . 'Current time: ' . date('Y-m-d H:i:s') . ".\n\n");

?>
