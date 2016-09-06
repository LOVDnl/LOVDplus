#!/usr/bin/php
<?php
/*******************************************************************************
 * CREATE META DATA FILES FOR MGHA
 * Created: 2016-08-29
 * Programmer: Juny Kesumadewi
 *************/

// We are using a symlink to include this file so any further includes relative to this file needs to use the symlink path instead of the actual files path.
define('ROOT_PATH', realpath(dirname($_SERVER["SCRIPT_FILENAME"])) . '/../');
define('FORMAT_ALLOW_TEXTPLAIN', true);

define('MISSING_COL_INDEX', -1);
define('MAX_NUM_INDIVIDUALS', 1);
define('COMMENT_START', '##');
define('HEADER_START', '#');
define('BATCH_FOLDER_PREFIX', 'batch');
define('BATCH_FOLDER_DELIMITER', '_');

define('ERROR_OPEN_FILES', 51);
define('ERROR_MISSING_FILES', 52);
define('ERROR_INCORRECT_FORMAT', 53);
define('ERROR_INVALID_METADATA', 54);


$_GET['format'] = 'text/plain';

// To prevent notices when running inc-init.php.
$_SERVER = array_merge($_SERVER, array(
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => 'scripts/adapter.php',
    'QUERY_STRING' => '',
    'REQUEST_METHOD' => 'GET',
));

require_once ROOT_PATH . 'inc-init.php';
require_once ROOT_PATH . 'inc-lib-genes.php';
ini_set('memory_limit', '4294967296');


if ($argc != 1 && in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
    printHelp();
    exit();
}

// Fix any trailing slashes in the path to the data files.
$_INI['paths']['data_files'] = rtrim($_INI['paths']['data_files'], "\\/") . "/";

$aBatchFolders = glob($_INI['paths']['data_files'] . BATCH_FOLDER_PREFIX . BATCH_FOLDER_DELIMITER . '*');
if (empty($aBatchFolders)) {
    print("No batch folder found\nBatch folder name must start with " . BATCH_FOLDER_PREFIX . BATCH_FOLDER_DELIMITER . "\n");
    exit();
}

// Loop through each batch folder to be processed
foreach($aBatchFolders as $sBatchFolderPath) {
    $sBatchFolderName = basename($sBatchFolderPath);
    print("> Processing " . $sBatchFolderName . "\n");

    // Check if all the required files exist.
    list($sPrefix, $sBatchNumber, $sIndividualID) = validateBatchFolderName($sBatchFolderName);

    $sMetaFile = validateMetaDataFile($sBatchFolderPath, $sBatchNumber, $sIndividualID);
    print("> Required metadata file exists\n");

    $aVariantFiles = validateVariantFiles($sBatchFolderPath, $sBatchNumber, $sIndividualID);
    print("> Required variant files exist\n");

    // Now validate the metadata VALUES.
    $aMetadata = getMetaData($sMetaFile, $sBatchNumber, $sIndividualID);
    print("> Metadata values validated\n");

    // Get database ID of the individual to be processed in this batch.
    $sIndDBID = getIndividualDBID($aMetadata);

    // Create output files
    foreach (getVariantFileTypes() as $sType => $sPrefix) {
        createMetaFile($sType, $sBatchNumber, $sIndividualID, $aMetadata, $sIndDBID);
        print("> $sType meta file created\n");

        reformatVariantFile($aVariantFiles[$sIndividualID][$sType], $sType, $sBatchNumber, $sIndividualID);
        print("> $sType variant file created\n");
    }

    archiveBatchFolder($sBatchFolderPath);
    print("> batch folder archived\n");
}





function getVariantFileTypes() {
    // All variant file types and their filename prefixes.
    $aFileTypes = array(
        'tnc' => 'tumour--normal_combined',
        'tnm' => 'tumour_normal_merged',
        't' => 'tumour_UG',
        'n' => 'normal_UG'
    );

    return $aFileTypes;
}





function getColumnMappings() {
    // Mapping vep columns to lovd columns

    $aColumnMappings = array(
        'Individual_ID' => 'Individual/Sample_ID',
        'Sex' => 'Individual/Gender',
        'Normal_Sample_ID' => 'Screening/Normal/Sample_ID',
        'Tumor_Sample_ID' => 'Screening/Tumour/Sample_ID',
        'Fastq_Files' => 'Screening/FastQ_files',
        'Notes' => 'Screening/Notes',
        'pipeline_path' => 'Screening/Pipeline/Path'
    );

    return $aColumnMappings;
}





function reformatVariantFile($sVariantFile, $sType, $sBatch, $sIndividual) {
    // Create a new copy of the variant file with the following changes:
    // - Remove all comment lines that start with '##'.
    // - Remove '#' fromt he start of header line.
    // - Rename the file to batchNumber_IndividualID.tsvType.directvep.data.lovd.

    global $_INI;

    $sNewVariantFileName = $_INI['paths']['data_files'] . $sBatch . "_" . $sIndividual . "." . $sType . '.directvep.data.lovd';
    $fOutput = fopen($sNewVariantFileName, 'w');

    if (empty($fOutput)) {
        print('ERROR: failed to create new variant file ' . $sNewVariantFileName);
        exit(ERROR_OPEN_FILES);
    }

    $fInput = fopen($sVariantFile, 'r');

    while (($sLine = fgets($fInput)) !== false) {
        $sLine = trim($sLine, " \n");

        // Skip empty lines.
        if (empty($sLine)) {
            continue;
        }

        // Skip commented out lines.
        if (strpos($sLine, COMMENT_START) === 0) {
            continue;
        }

        // Remove # (and any extra spaces that follow) from header start of line.
        $sLine = ltrim($sLine, " " . HEADER_START);

        // Print all non-comments line in the new reformatted variant file.
        fputs($fOutput, $sLine . "\n");
    }

    fclose($fInput);
    fclose($fOutput);
}





function createMetaFile($sType, $sBatch, $sIndividual, $aMetadata, $sIndDBID) {
    // Create meta file for each variant file.

    global $_INI;

    $sNewMetaFileName = $_INI['paths']['data_files'] . $sBatch . "_" . $sIndividual . "." . $sType . '.meta.lovd';

    // Build 'Individual' columns.
    $aColumnsForIndividual = array (
        'panel_size' => 1,
        'id' => $sIndDBID
    );
    $aColumnsForIndividual = $aColumnsForIndividual + getCustomColumnsData('Individual/', $aMetadata);

    // Build 'Screening' columns.
    $aColumnsForScreening = array(
        'individualid' => $sIndDBID,
        'variants_found' => '1',
        'id' => '1',
        'id_sample' => '0',
        'Screening/Pipeline/Path' => $sType
    );
    $aColumnsForScreening = $aColumnsForScreening + getCustomColumnsData('Screening/', $aMetadata);

    $sOutputData =
        '### LOVD-version 3000-080 ### Full data download ### To import, do not remove or alter this header ###' . "\r\n" .
        '# charset = UTF-8' . "\r\n\r\n" .
        '## Diseases ## Do not remove or alter this header ##' . "\r\n\r\n" .
        '## Individuals ## Do not remove or alter this header ##' . "\r\n" .
        '"{{' . implode("}}\"\t\"{{", array_keys($aColumnsForIndividual)) . '}}"' . "\r\n" .
        '# "' . implode("\"\t\"", array_values($aColumnsForIndividual)) . '"' . "\r\n\r\n" .
        '## Individuals_To_Diseases ## Do not remove or alter this header ##' . "\r\n\r\n" .
        '## Screenings ## Do not remove or alter this header ##' . "\r\n" .
        '"{{' . implode("}}\"\t\"{{", array_keys($aColumnsForScreening)) . '}}"' . "\r\n" .
        '"' . implode("\"\t\"", array_values($aColumnsForScreening)) . '"' . "\r\n";

    $fOutput = fopen($sNewMetaFileName, 'w');
    fputs($fOutput, $sOutputData);
    fclose($fOutput);
}





function getCustomColumnsData($sColPrefix, $aMetadata) {
    // A helper function to get the custom columns listed on columnMappings list and their data.

    $aColumns = array();
    foreach (getColumnMappings() as $sPipelineColumn => $sLOVDColumn) {
        if (substr($sLOVDColumn, 0, strlen($sColPrefix)) === $sColPrefix) {
            $aColumns[$sLOVDColumn] = (empty($aMetadata[$sPipelineColumn]) || $aMetadata[$sPipelineColumn] == '.' ? '' : $aMetadata[$sPipelineColumn]);
        }
    }

    return $aColumns;
}





function archiveBatchFolder($sBatchPath) {
    global $_INI;

    $sArchivePath = $_INI['paths']['data_files'] . 'archives';
    if (!file_exists($sArchivePath)) {
        mkdir($sArchivePath);
    }

    $sBatchFolderName = basename($sBatchPath);
    if (!rename($sBatchPath, $sArchivePath . '/archived_' . time() . '_' . $sBatchFolderName)) {
        print("ERROR: failed to archive batch folder " . $sBatchPath);
        exit(ERROR_OPEN_FILES);
    }

    return true;
}





function getIndividualDBID($aMetadata) {
    // Get database ID of the given individual ID provided in the metadata file.
    // If the individual has been inserted in the database in the past, then, simply retrieve the database ID.
    // If the individual does not already exist in the database, then create this new individual in the database an returns this new database ID.

    global $_DB;

    $aMetadata['individual_exists'] = false;
    $sIndividualID = $aMetadata['Individual_ID'];
    $sIndDBID = $_DB->query('SELECT `id` FROM ' . TABLE_INDIVIDUALS . ' WHERE `Individual/Sample_ID` = ?', array($sIndividualID))->fetchColumn();

    // If the individual does not already exist in the database, then create it.
    if (!$sIndDBID) {
        // Prepare the columns for inserting the individual record.
        $aIndFields = array(
            'panel_size' => 1,
            'owned_by' => 0,
            'custom_panel' => '',
            'statusid' => 4,
            'created_by' => 0,
            'created_date' => date('Y-m-d H:i:s'),
        );

        // Add in any custom columns for the individual.
        $aIndFields = $aIndFields + getCustomColumnsData('Individual/', $aMetadata);

        $_DB->query('INSERT INTO ' . TABLE_INDIVIDUALS . ' (`' . implode('`, `', array_keys($aIndFields)) . '`) VALUES (?' . str_repeat(', ?', count($aIndFields) - 1) . ')', array_values($aIndFields));
        $sIndDBID = sprintf('%08d', $_DB->lastInsertId());

    }

    return $sIndDBID;
}





function getMetaData($sMetaDataFilename, $sBatch, $sIndividual) {
    // Validate if the metadata provided is in the correct format and returns the metadata if everything is valid.
    // It will print error and stop the script if it is invalid.
    // It does the following validations:
    // - Whether all expected columns are in the file (Order does not matter. Extra columns are also allowed).
    // - Whether the file only has one row of data (One individual only).
    // - Whether the Batch ID in the metadata matches the Batch ID of the processed folder name.
    // - Whether the Individual ID in the metadata matches the Individual ID of the processed folder name.
    //
    // When we pass all validations, we reformat the metadata to a format that LOVD understands
    // and returned them as an array keyed by their column names.


    if (!($fMetaData = fopen($sMetaDataFilename, 'r'))) {
        print("ERROR: failed to open file " . $sMetaDataFilename . "\n");
        exit(ERROR_OPEN_FILES);
    }

    $sDelimiter = "\t";
    $aExpectedColumns = array(
        'Batch' => MISSING_COL_INDEX,
        'Individual_ID' => MISSING_COL_INDEX,
        'Sex' => MISSING_COL_INDEX,
        'Normal_Sample_ID' => MISSING_COL_INDEX,
        'Tumor_Sample_ID' => MISSING_COL_INDEX,
        'Fastq_Files' => MISSING_COL_INDEX,
        'Notes' => MISSING_COL_INDEX
    );

    $bHeaderRead = false;
    $bDataRead = false;
    $aMetadata = array();
    while (($sLine = fgets($fMetaData)) !== false) {
        $sLine = trim($sLine, " \n");
        if (empty($sLine)) {
            continue;
        }

        // If data has been read. But, there are still more lines to be processed. The metadata file contains more rows than it should.
        if ($bDataRead) {
            print("ERROR: metadata file should not contain more than one row of data\n");
            exit(ERROR_INCORRECT_FORMAT);
        }

        // Process header.
        if (!$bHeaderRead) {
            $aHeader = explode($sDelimiter, $sLine);
            foreach ($aHeader as $nIndex => $sColumn) {
                $aExpectedColumns[$sColumn] = $nIndex;
            }

            // Check if we get all the required columns.
            if (in_array(MISSING_COL_INDEX, $aExpectedColumns)) {
                print("Metadata file is missing the following columns:\n");
                foreach ($aExpectedColumns as $sColumn => $nIndex) {
                    if ($nIndex === MISSING_COL_INDEX) {
                        print($sColumn . "\n");
                    }
                }
                exit(ERROR_INCORRECT_FORMAT);
            }

            $bHeaderRead = true;
            continue;
        }

        // Process data.
        $aLine = explode($sDelimiter, $sLine);

        if ($aLine[$aExpectedColumns['Batch']] !== $sBatch) {
            print("ERROR: unmatched Batch ID. Batch ID in metadata file is " . $aLine[$aExpectedColumns['Batch']] . "Folder batch ID is " . $sBatch);
            exit(ERROR_INVALID_METADATA);
        }

        if ($aLine[$aExpectedColumns['Individual_ID']] !== $sIndividual) {
            print("ERROR: unmatched Individual ID. Individual ID in metadata file is " . $aLine[$aExpectedColumns['Individual_ID']] . "Folder Individual ID is " . $sIndividual);
            exit(ERROR_INVALID_METADATA);
        }

        $aMetadata = array();
        foreach ($aExpectedColumns as $sColName => $nIndex) {
            $aMetadata[$sColName] = formatMetadataValue($sColName, $aLine[$nIndex]);
        }
        $bDataRead = true;
    }

    fclose($fMetaData);
    return $aMetadata;
}





function formatMetadataValue($sColName, $sRawValue) {
    // Reformat a column of metadata to a format that LOVD understands.

    switch ($sColName) {
        case 'Sex':
            $aGenderMaps = array(
                'female' => 'F',
                'f' => 'F',
                'male' => 'M',
                'm' => 'M'
            );
            $sRawValue = strtolower($sRawValue);
            return (empty($aGenderMaps[$sRawValue]) ? '?' : $aGenderMaps[$sRawValue]);
        default:
            return $sRawValue;
    }

}





function validateBatchFolderName($sBatchFolderName) {
    // Validate if batch folder name is in the correct format.
    $aExpectedParts = array(
        BATCH_FOLDER_PREFIX => '',
        '[BATCH NUMBER]' => 'Batch number must not contain ' . BATCH_FOLDER_DELIMITER,
        '[INDIVIDUAL ID]' => 'Individual ID must not contain ' . BATCH_FOLDER_DELIMITER
    );

    $parts = explode(BATCH_FOLDER_DELIMITER, $sBatchFolderName);
    if (count($parts) !== count($aExpectedParts)) {
        print("ERROR: batch folder name must follow this pattern " . implode(BATCH_FOLDER_DELIMITER, array_keys($aExpectedParts)) . "\n");
        foreach ($aExpectedParts as $sPart => $sMessage) {
            if (!empty($sMessage)) {
                print($sPart . ": " . $sMessage . "\n");
            }
        }

        exit(ERROR_MISSING_FILES);
    }

    return $parts;
}





function validateMetaDataFile($sPath, $sBatch, $sIndividual) {
    // Validate if metadata file EXISTS in this batch folder.

    $aMetadataFiles = glob($sPath . '/*.meta');
    if (count($aMetadataFiles) > 1) {
        print("ERROR: More than one metadata file found\nPlease keep only one correct metadata file in the batch folder\n");
        exit(ERROR_MISSING_FILES);
    }

    foreach($aMetadataFiles as $sFileName) {
        return $sFileName;
    }

    print("ERROR: Missing metadata file\n");
    exit(ERROR_MISSING_FILES);
}





function validateVariantFiles($sPath, $sBatch, $sIndividual) {
    // Get all the variant file names listed in getVariantFileTypes().

    $aVariantFiles = array();
    $aMissingFiles = array();

    foreach (getVariantFileTypes() as $sType => $sPrefix) {
        $aFiles = glob($sPath . '/' . $sPrefix . '*.tsv');
        if (!empty($aFiles[0])) {
            $aVariantFiles[$sIndividual][$sType] = $aFiles[0];
        } else {
            $aMissingFiles[] = $sPrefix;
        }
    }

    // If one or more variant file is not found, exit
    if (!empty($aMissingFiles)) {
        print("ERROR: Missing variant files with prefix:\n");
        foreach ($aMissingFiles as $sMissingFile) {
            print($sMissingFile . "\n");
        }
        exit(ERROR_MISSING_FILES);
    }

    return $aVariantFiles;
}





function printHelp() {
    ?>
    This is a command line PHP script which creates meta data files for MGHA Seqliner that are compatible with LOVD
    After this script is executed the convert_and_merge_data_files.php script should be run to merge
    the meta data files with the variant files. The final merged file should then be imported into LOVD

    Error Handling
    <?php echo ERROR_OPEN_FILES ?> - Error opening or renaming files or directories
    <?php echo ERROR_MISSING_FILES ?> - Required files are missing. Check sample meta data file or variant files
    <?php echo ERROR_INCORRECT_FORMAT ?> - File does not conform to expected format. Check sample meta data file.
    <?php echo ERROR_INVALID_METADATA ?> - Unexpected metadata values.
    <?php
}