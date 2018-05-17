<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-09-02
 * Modified    : 2018-03-23
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2018 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *
 * This file is part of LOVD.
 *
 * LOVD is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * LOVD is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LOVD.  If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

// These are the default instance-specific settings.
// Create a new file, change the "DEFAULT" in the name to your uppercased
//  instance name, and add the settings you'd like to override.
// Optionally, copy this file completely and change the settings in your copy.
// Change the settings to your liking in your own instance-specific adapter file.

// Default settings.
$_INSTANCE_CONFIG = array();

// If you wish to allow for attachment uploads, enable this code.
// These are some example default file types and settings.
// FIXME: Allow for one file type to be linked to multiple objects.
/*
$_INSTANCE_CONFIG['attachments'] = array(
    'igv' => array(
        'linked_to' => 'variant',
        'label' => 'IGV screenshot'),
    'ucsc' => array(
        'linked_to' => 'summary_annotation',  // This file is stored using the Summary Annotation Record DBID.
        'label' => 'UCSC screenshot (Summary Annotation)'),
    'confirmation' => array(
        'linked_to' => 'variant',
        'label' => 'Confirmation screenshot'),
    'workfile' => array(
        'linked_to' => 'variant',
        'label' => 'Excel file'),
);
*/



$_INSTANCE_CONFIG['columns'] = array(
    'lab_id' => 'Individual/Lab_ID',
    'family' => array(
        // Insert columns here that define a certain family role.
        // For instance, if the Individual/MotherID column contains the Lab ID
        //  of the mother of the current Individual, define this as:
        // 'mother' => 'Individual/MotherID',
        // Note that the value in the column needs to match the value of
        //  the other Individual's column defined in the 'lab_id' setting.
    ),
);

$_INSTANCE_CONFIG['cross_screenings'] = array(
    'format_screening_name' => function($zScreening)
    {
        // This function formats the label for screenings to use in the cross screening filter.
        // It can use any Individual or Screening column to format the label.
        // Default is: "Individual/Lab_ID (role)".
        global $_INSTANCE_CONFIG;

        $sReturn = $zScreening[$_INSTANCE_CONFIG['columns']['lab_id']];
        if (!empty($zScreening['role'])) {
            $sReturn .= ' (' . $zScreening['role'] . ')';
        }

        return $sReturn;
    }
);

$_INSTANCE_CONFIG['viewlists'] = array(
    // If set to true, ViewLists are not allowed to be downloaded, except specifically
    //  enabled as 'allow_download_from_level' in the ViewLists's settings below.
    'restrict_downloads' => true,

    // The screenings data listing on the individual's detailed view.
    'Screenings_for_I_VE' => array(
        'cols_to_show' => array(
            // Select these columns for the screenings listing on the individual's page.
            // Note, that you also need to define the hidden columns that
            //  are to be active, since LOVD+ might be filtering on them.
            // You can change the order of columns to any order you like.
            'id',
            'individualid',
            'curation_progress_',
            'variants_found_',
            'analysis_status',
            'analysis_by_',
            'analysis_date_',
            'analysis_approved_by_',
            'analysis_approved_date_',
        )
    ),
    // The data analysis results data listing.
    'CustomVL_AnalysisRunResults_for_I_VE' => array(
        // Even when downloading ViewLists is restricted, allow downloading from LEVEL_MANAGER.
        'allow_download_from_level' => LEVEL_MANAGER,
        'cols_to_show' => array(
            // Select these columns for the analysis results table.
            // Note, that you also need to define the hidden columns that
            //  are to be active, since LOVD+ might be filtering on them.
            // By default, these columns are sorted by object type, but you can change the order to any order you like.
            'curation_status_',
            'curation_statusid',
            'variantid',
            'vog_effect',
            'chromosome',
            'allele_',
            'VariantOnGenome/DNA',
            'VariantOnGenome/Alamut',
            'VariantOnGenome/Conservation_score/PhyloP',
            'VariantOnGenome/HGMD/Association',
            'VariantOnGenome/Sequencing/Depth/Alt/Fraction',
            'VariantOnGenome/Sequencing/Quality',
            'VariantOnGenome/Sequencing/GATKcaller',
            'obs_variant',
            'obs_var_ind_ratio',
            'obs_disease',
            'obs_var_dis_ind_ratio',

            'gene_disease_names',
            'VariantOnTranscript/DNA',
            'VariantOnTranscript/Protein',
            'VariantOnTranscript/GVS/Function',
            'gene_OMIM_',

            'runid',

            'gene_panels',
        )
    )
);

$_INSTANCE_CONFIG['conversion'] = array(
    'annotation_error_drops_line' => false, // Should we discard the variant's mapping on this transcript on annotation errors?
    'annotation_error_exits' => false, // Whether to halt on the first annotation error.
    'annotation_error_max_allowed' => 20, // Maximum number of errors with VOTs before the script dies anyway.
    'check_indel_description' => true, // Should we check all indels using Mutalyzer? Vep usually does a bad job at them.
    'enforce_hgnc_gene' => true, // Check for aliases, allow automatic creation of genes using the HGNC, allow automatic creation of transcripts.
    'verbosity_cron' => 5, // How verbose should we be when running through cron? (default: 5; currently supported: 0,3,5,7,9)
    'verbosity_other' => 7, // How verbose should we be otherwise? (default: 7; currently supported: 0,3,5,7,9)
);

// This is the default configuration of the observation count feature.
// To disable this feature completely, set 'observation_counts' to an empty
//  array in your instance-specific settings.
// FIXME: Make the columns configurable like the categories; just let the
//  instances select which columns they want; the values are defined elsewhere.
//  Now, every instance has to redefine the labels, but never does actually
//  change them.
$_INSTANCE_CONFIG['observation_counts'] = array(
    // If you want to display the gene panel observation counts using the default
    //  configuration, you can also simply write: 'genepanel' => array(),
    'genepanel' => array(
        // These are the columns to choose from. If you'd like to display all
        //  default columns, you can also simply write:
        //  'columns' => array(),
        'columns' => array(
            'value' => 'Gene Panel',
            'total_individuals' => 'Total # Individuals',
            'num_affected' => '# of Affected Individuals',
            'num_not_affected' => '# of Unaffected Individuals',
            'percentage' => 'Percentage (%)'
        ),
        // These are the categories to choose from. If you'd like to use all
        //  default categories, you can also also simply write:
        //  'categories' => array(),
        'categories' => array(
            'all',
            'gender',
            'ethnic',
        ),
        // Round calculated percentages to what amount of decimals? (0-3)
        'show_decimals' => 1,
    ),

    // If you want to display the general observation counts using the default
    //  configuration, you can also simply write: 'general' => array(),
    'general' => array(
        // These are the columns to choose from. If you'd like to display all
        //  default columns, you can also simply write:
        //  'columns' => array(),
        'columns' => array(
            'label' => 'Category',
            'value' => 'Value',
            'threshold' => 'Percentage'
        ),
        // These are the categories to choose from. If you'd like to use all
        //  default categories, you can also also simply write:
        //  'categories' => array(),
        'categories' => array(
            'all',
            'Individual/Gender',
            'Individual/Origin/Ethnic',
            'Screening/Sample/Type',
            'Screening/Library_preparation',
            'Screening/Sequencing_software',
            'Screening/Analysis_type',
            'Screening/Library_preparation&Screening/Sequencing_software',
            'Screening/Library_preparation&Screening/Sequencing_software&Screening/Analysis_type',
        ),
        // This is the minimal population size that is required for the
        //  general observation counts to be calculated.
        'min_population_size' => 100,
        // Round calculated percentages to what amount of decimals? (0-3)
        'show_decimals' => 1,
    ),
);





// FIXME: This class should not be mixed with the above settings, I reckon? Split it?
// FIXME: Some methods are never overloaded and aren't meant to be, better put those elsewhere to prevent confusion.
class LOVD_DefaultDataConverter {
    // Class with methods and variables for convert_and_merge_data_files.php.

    var $sAdapterPath;
    var $aScriptVars = array();
    var $aMetadata; // Contains the meta data file, parsed.
    const NO_TRANSCRIPT = '-----'; // Transcripts with this value will be ignored.

    public function __construct ($sAdapterPath)
    {
        $this->sAdapterPath = $sAdapterPath;
    }





    function cleanGenoType ($sGenoType)
    {
        // Returns a "cleaned" genotype (GT) field, given the VCF's GT field.
        // VCFs can contain many different GT values that should be cleaned/simplified into fewer options.

        static $aGenotypes = array(
            './.' => '0/0', // No coverage taken as homozygous REF.
            './0' => '0/0', // REF + no coverage taken as homozygous REF.
            '0/.' => '0/0', // REF + no coverage taken as homozygous REF.

            './1' => '0/1', // ALT + no GT due to multi allelic SNP taken as heterozygous ALT.
            '1/.' => '0/1', // ALT + no GT due to multi allelic SNP taken as heterozygous ALT.

            '1/0' => '0/1', // Just making sure we only have one way to describe HET calls.
        );

        if (isset($aGenotypes[$sGenoType])) {
            return $aGenotypes[$sGenoType];
        } else {
            return $sGenoType;
        }
    }





    function cleanHeaders ($aHeaders)
    {
        // Return the headers, cleaned up if needed.
        // You can add code here that will clean the headers, directly after reading.

        return $aHeaders;
    }





    function convertGenoTypeToAllele ($aVariant)
    {
        // Converts the GenoType data (already stored in the 'allele' field) to an LOVD-style allele value.
        // To stop variants from being imported, set $aVariant['lovd_ignore_variant'] to something non-false.
        // Possible values:
        // 'silent' - for silently ignoring the variant.
        // 'log' - for ignoring the variant and logging the line number.
        // 'separate' - for storing the variant in a separate screening (not implemented yet).
        // When set to something else, 'log' is assumed.
        // Note that when verbosity is set to low (3) or none (0), then no logging will occur.

        // First verify the GT (allele) column. VCFs might have many interesting values (mostly for multisample VCFs).
        // Clean the value a bit (will result in "0/." calls to be converted to "0/0", for instance).
        if (!isset($aVariant['allele'])) {
            $aVariant['allele'] = '';
        }
        $aVariant['allele'] = $this->cleanGenoType($aVariant['allele']);

        // Then, convert the GT values to proper LOVD-style allele values.
        switch ($aVariant['allele']) {
            case '0/0':
                // Homozygous REF; not a variant. Skip this line silently.
                $aVariant['lovd_ignore_variant'] = 'silent';
                break;
            case '0/1':
                // Heterozygous.
                if (!empty($aVariant['VariantOnGenome/Sequencing/Father/GenoType']) && !empty($aVariant['VariantOnGenome/Sequencing/Mother/GenoType'])) {
                    if (strpos($aVariant['VariantOnGenome/Sequencing/Father/GenoType'], '1') !== false && strpos($aVariant['VariantOnGenome/Sequencing/Mother/GenoType'], '1') === false) {
                        // From father, inferred.
                        $aVariant['allele'] = 10;
                    } elseif (strpos($aVariant['VariantOnGenome/Sequencing/Mother/GenoType'], '1') !== false && strpos($aVariant['VariantOnGenome/Sequencing/Father/GenoType'], '1') === false) {
                        // From mother, inferred.
                        $aVariant['allele'] = 20;
                    } else {
                        $aVariant['allele'] = 0;
                    }
                } else {
                    $aVariant['allele'] = 0;
                }
                break;
            case '1/1':
                // Homozygous.
                $aVariant['allele'] = 3;
                break;
            default:
                // Unexpected value (empty string?). Ignore the variant, log.
                $aVariant['lovd_ignore_variant'] = 'log';
        }

        return $aVariant;
    }





    function formatEmptyColumn ($aLine, $sVEPColumn, $sLOVDColumn, $aVariant)
    {
        // Returns how we want to represent empty data in the $aVariant array.
        // Fields that evaluate true with empty() or set to "." or "unknown" are sent here.
        // The default is to set them to an empty string.
        // You can overload this function to include different functionality,
        //  such as returning 0 in some cases.

        /*
        if (isset($aLine[$sVEPColumn]) && ($aLine[$sVEPColumn] === 0 || $aLine[$sVEPColumn] === '0')) {
            $aVariant[$sLOVDColumn] = 0;
        } else {
            $aVariant[$sLOVDColumn] = '';
        }
        */
        $aVariant[$sLOVDColumn] = '';

        return $aVariant;
    }





    function getInputFilePrefixPattern ()
    {
        // Returns the regex pattern of the prefix of variant input file names.
        // The prefix is often the sample ID or individual ID, and can be formatted to your liking.
        // Data files must be named "prefix.suffix", using the suffixes as defined in the conversion script.

        // If using sub patterns, make sure they are not counted, like so:
        //  (?:subpattern)
        return '.+';
    }





    function getRequiredHeaderColumns ()
    {
        // Returns an array of required variant input file column headers.
        // The order of these columns does NOT matter.

        return array(
            'Location',
            'GIVEN_REF',
            'Allele',
            'QUAL',
            'GT',
            'Consequence',
            'SYMBOL',
            'Feature',
        );
    }





    function ignoreTranscript ($sTranscriptID)
    {
        // Returns true for transcripts whose annotation should be ignored.
        // You can overload this function to define which transcripts to ignore;
        //  you can use lists, prefixes or other rules.

        if ($sTranscriptID === static::NO_TRANSCRIPT) {
            return true;
        }

        // Here, set any prefixes of transcripts that you'd like ignored, like 'NR_'.
        $aTranscriptsPrefixToIgnore = array(
            // 'NR_',
        );

        foreach ($aTranscriptsPrefixToIgnore as $sPrefix) {
            if (strpos($sTranscriptID, $sPrefix) === 0) {
                return true;
            }
        }

        return false;
    }





    function postValueAssignmentUpdate ($sKey, &$aVariant, &$aData)
    {
        // This function is run after every line has been read;
        // $aData[$sKey] contains the parsed and stored data of the genomic variant.
        // $aVariant contains all the data of the line just read,
        //  including the transcript-specific data.
        // You can overload this function if you need to generate aggregated
        //  data over the different transcripts mapped to one variant.

        return $aData;
    }





    function prepareGeneAliases ()
    {
        // Return an array of gene aliases, with the gene symbol as given by VEP
        //  as the key, and the symbol as known by LOVD/HGNC as the value.
        // Example:
        // return array(
        //     'C4orf40' => 'PRR27',
        // );

        return array(
        );
    }





    function prepareGenesToIgnore ()
    {
        // Return an array of gene symbols of genes you wish to ignore.
        // These could be genes that you know can't be imported/created in LOVD,
        //  or genes whose annotation you wish to ignore for a different reason.
        // Example:
        // return array(
        //     'FLJ12825',
        //     'FLJ27354',
        //     'FLJ37453',
        // );

        return array(
        );
    }





    // FIXME: This is Leiden-specific code, put it in the Leiden adapter and make a proper default.
    // FIXME: This function does not have a clearly matching name.
    function prepareMappings ()
    {
        // Returns an array that map VEP columns to LOVD columns.

        $aColumnMappings = array(
            'chromosome' => 'chromosome',
            'position' => 'position', // lovd_getVariantDescription() needs this.
            'QUAL' => 'VariantOnGenome/Sequencing/Quality',
            'FILTERvcf' => 'VariantOnGenome/Sequencing/Filter',
            'GATKCaller' => 'VariantOnGenome/Sequencing/GATKcaller',
            'Feature' => 'transcriptid',
            'GVS' => 'VariantOnTranscript/GVS/Function',
            'CDS_position' => 'VariantOnTranscript/Position',
//    'PolyPhen' => 'VariantOnTranscript/PolyPhen', // We don't use this anymore.
            'HGVSc' => 'VariantOnTranscript/DNA',
            'HGVSp' => 'VariantOnTranscript/Protein',
            'Grantham' => 'VariantOnTranscript/Prediction/Grantham',
            'INDB_COUNT_UG' => 'VariantOnGenome/InhouseDB/Count/UG',
            'INDB_COUNT_HC' => 'VariantOnGenome/InhouseDB/Count/HC',
            'GLOBAL_VN' => 'VariantOnGenome/InhouseDB/Position/Global/Samples_w_coverage',
            'GLOBAL_VF_HET' => 'VariantOnGenome/InhouseDB/Count/Global/Heterozygotes',
            'GLOBAL_VF_HOM' => 'VariantOnGenome/InhouseDB/Count/Global/Homozygotes',
            'WITHIN_PANEL_VN' => 'VariantOnGenome/InhouseDB/Position/InPanel/Samples_w_coverage',
            'WITHIN_PANEL_VF_HET' => 'VariantOnGenome/InhouseDB/Count/InPanel/Heterozygotes',
            'WITHIN_PANEL_VF_HOM' => 'VariantOnGenome/InhouseDB/Count/InPanel/Homozygotes',
            'OUTSIDE_PANEL_VN' => 'VariantOnGenome/InhouseDB/Position/OutOfPanel/Samples_w_coverage',
            'OUTSIDE_PANEL_VF_HET' => 'VariantOnGenome/InhouseDB/Count/OutOfPanel/Heterozygotes',
            'OUTSIDE_PANEL_VF_HOM' => 'VariantOnGenome/InhouseDB/Count/OutOfPanel/Homozygotes',
            'AF1000G' => 'VariantOnGenome/Frequency/1000G',
            'rsID' => 'VariantOnGenome/dbSNP',
            'AFESP5400' => 'VariantOnGenome/Frequency/EVS', // Will be divided by 100 later.
            'CALC_GONL_AF' => 'VariantOnGenome/Frequency/GoNL',
            'AFGONL' => 'VariantOnGenome/Frequency/GoNL_old',
            'EXAC_AF' => 'VariantOnGenome/Frequency/ExAC',
            'MutationTaster_pred' => 'VariantOnTranscript/Prediction/MutationTaster',
            'MutationTaster_score' => 'VariantOnTranscript/Prediction/MutationTaster/Score',
            'Polyphen2_HDIV_score' => 'VariantOnTranscript/PolyPhen/HDIV',
            'Polyphen2_HVAR_score' => 'VariantOnTranscript/PolyPhen/HVAR',
            'SIFT_score' => 'VariantOnTranscript/Prediction/SIFT',
            'CADD_raw' => 'VariantOnGenome/CADD/Raw',
            'CADD_phred' => 'VariantOnGenome/CADD/Phred',
            'HGMD_association' => 'VariantOnGenome/HGMD/Association',
            'HGMD_reference' => 'VariantOnGenome/HGMD/Reference',
            'phyloP' => 'VariantOnGenome/Conservation_score/PhyloP',
            'scorePhastCons' => 'VariantOnGenome/Conservation_score/Phast',
            'GT' => 'allele',
            'GQ' => 'VariantOnGenome/Sequencing/GenoType/Quality',
            'DP' => 'VariantOnGenome/Sequencing/Depth/Total',
            'DPREF' => 'VariantOnGenome/Sequencing/Depth/Ref',
            'DPALT' => 'VariantOnGenome/Sequencing/Depth/Alt',
            'ALTPERC' => 'VariantOnGenome/Sequencing/Depth/Alt/Fraction', // Will be divided by 100 later.
            'GT_Father' => 'VariantOnGenome/Sequencing/Father/GenoType',
            'GQ_Father' => 'VariantOnGenome/Sequencing/Father/GenoType/Quality',
            'DP_Father' => 'VariantOnGenome/Sequencing/Father/Depth/Total',
            'ALTPERC_Father' => 'VariantOnGenome/Sequencing/Father/Depth/Alt/Fraction', // Will be divided by 100 later.
            'ISPRESENT_Father' => 'VariantOnGenome/Sequencing/Father/VarPresent',
            'GT_Mother' => 'VariantOnGenome/Sequencing/Mother/GenoType',
            'GQ_Mother' => 'VariantOnGenome/Sequencing/Mother/GenoType/Quality',
            'DP_Mother' => 'VariantOnGenome/Sequencing/Mother/Depth/Total',
            'ALTPERC_Mother' => 'VariantOnGenome/Sequencing/Mother/Depth/Alt/Fraction', // Will be divided by 100 later.
            'ISPRESENT_Mother' => 'VariantOnGenome/Sequencing/Mother/VarPresent',
//    'distanceToSplice' => 'VariantOnTranscript/Distance_to_splice_site',

            // Mappings for fields used to process other fields but not imported into the database.
            'SYMBOL' => 'symbol',
            'REF' => 'ref',
            'ALT' => 'alt',
            'Existing_variation' => 'existing_variation'
        );

        return $aColumnMappings;
    }





    // FIXME: This function does not have a clearly matching name.
    function prepareVariantData (&$aLine)
    {
        // Reformat a line of raw variant data into the format that works for this instance.
        // To stop certain variants being imported add some logic to check for these variants
        //  and then set $aLine['lovd_ignore_variant'] to something non-false.
        // Possible values:
        // 'silent' - for silently ignoring the variant.
        // 'log' - for ignoring the variant and logging the line number.
        // 'separate' - for storing the variant in a separate screening (not implemented yet).
        // When set to something else, 'log' is assumed.
        // Note that when verbosity is set to low (3) or none (0), then no logging will occur.

        return $aLine;
    }





    function readMetadata ($aMetaDataLines)
    {
        // Read array of lines from .meta.lovd file of each .directvep.lovd file.
        // Return an array of metadata keyed by column names.

        $aKeyedMetadata = array(); // The array we're building up.
        $aColNamesByPos = array(); // The list of columns in the section, temp variable.
        $bHeaderPrevRow = false;   // Boolean indicating whether we just saw the header row or not.
        $sSection = '';            // In which section are we?
        foreach ($aMetaDataLines as $sLine) {
            $sLine = trim($sLine);
            if (empty($sLine)) {
                continue;
            }

            if ($bHeaderPrevRow) {
                // Assuming we always only have 1 row of data after each header.

                // Some lines are commented out so that they can be skipped during import.
                // But, this metadata is still valid and we want this data.
                // FIXME: Does somebody really need this functionality? It's not really in line with LOVD normally handles reading files.
                $sLine = trim($sLine, "# ");
                $aDataRow = explode("\t", $sLine);
                $aDataRow = array_map(function($sData) {
                    return trim($sData, '"');
                }, $aDataRow);

                foreach ($aColNamesByPos as $nPos => $sColName) {
                    // Read data.
                    $aKeyedMetadata[$sSection][$sColName] = $aDataRow[$nPos];
                }

                $bHeaderPrevRow = false;
            }

            if (preg_match('/^##\s*([A-Za-z_]+)\s*##\s*Do not remove/', ltrim($sLine, '"'), $aRegs)) {
                // New section. Store variables per section, so they don't get overwritten.
                $sSection = $aRegs[1];
                $aKeyedMetadata[$sSection] = array();
                continue;
            } elseif (substr($sLine, 0) == '#') {
                continue;
            } elseif (substr($sLine, 0, 3) == '"{{') {
                // Read header.
                $aColNamesByPos = array();
                $aCols = explode("\t", $sLine);
                foreach ($aCols as $sColName) {
                    $sColName = trim($sColName, '"{}');
                    $aColNamesByPos[] = $sColName;
                }
                $bHeaderPrevRow = true;
            }
        }

        $this->aMetadata = $aKeyedMetadata;
        return $this->aMetadata;
    }





    // FIXME: This function is not overwritten anywhere, and should perhaps not be defined here. Maybe remove and move the functionality?
    // FIXME: What is this for?
    function setScriptVars ($aVars = array())
    {
        // Keep track of the values of some variables defined in the script that calls this adapter object.

        // Newly set vars overwrites existing vars.
        $this->aScriptVars = $aVars + $this->aScriptVars;
    }
}
