<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-09-02
 * Modified    : 2017-10-25
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
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





$_INSTANCE_CONFIG['viewlists'] = array(
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
    'max_annotation_error_allowed' => 20, // Maximum number of errors with VOTs before the script dies.
    'exit_on_annotation_error' => true, // Whether to halt on an annotation error.
    'enforce_hgnc_gene' => true, // Check for aliases, allow automatic creation of genes using the HGNC, allow automatic creation of transcripts.
    'check_indel_description' => true, // Should we check all indels using Mutalyzer? Vep usually does a bad job at them.
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
        'min_population_size' => 100
    ),
);





// FIXME: This class should not be mixed with the above settings, I reckon? Split it?
// FIXME: Some methods are never overloaded and aren't meant to be, better put those elsewhere to prevent confusion.
class LOVD_DefaultDataConverter {
    // Class with methods and variables for convert_and_merge_data_files.php.

    var $sAdapterPath;
    var $aScriptVars = array();
    var $aMetadata; // Contains the meta data file, parsed.
    static $NO_TRANSCRIPT = '-----';

    public function __construct ($sAdapterPath)
    {
        $this->sAdapterPath = $sAdapterPath;
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
            'Consequence',
            'SYMBOL',
            'Feature',
        );
    }





    function ignoreTranscript ($sTranscriptId)
    {
        // Returns true for transcripts whose annotation should be ignored.
        // You can overload this function to define which transcripts to ignore;
        //  you can use lists, prefixes or other rules.

        // FIXME: What is this?
        if ($sTranscriptId === static::$NO_TRANSCRIPT) {
            return true;
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





    // FIXME: This is Leiden-specific code, put it in the Leiden adapter and make a proper default.
    function prepareGeneAliases ()
    {
        // Return an array of gene aliases, with the gene symbol as given by VEP
        //  as the key, and the symbol as known by LOVD/HGNC as the value.
        // Example:
        // return array(
        //     'C4orf40' => 'PRR27',
        // );

        $aGeneAliases = array(
            // This list needs to be replaced now and then.
            // These below have been added 2017-07-27. Expire 2018-07-27.
            'AQPEP' => 'LVRN',
            'C10orf112' => 'MALRD1',
            'C11orf34' => 'PLET1',
            'C19orf69' => 'ERICH4',
            'C4orf40' => 'PRR27',
            'C5orf50' => 'SMIM23',
            'C8orf47' => 'ERICH5',
            'C9orf169' => 'CYSRT1',
            'C9orf173' => 'STPG3',
            'C9orf37' => 'ARRDC1-AS1',
            'DOM3Z' => 'DXO',
            'FAM203A' => 'HGH1',
            'FAM25B' => 'FAM25BP',
            'FAM5C' => 'BRINP3',
            'FOLR4' => 'IZUMO1R',
            'GTDC2' => 'POMGNT2',
            'HDGFRP2' => 'HDGFL2',
            'HDGFRP3' => 'HDGFL3',
            'HMP19' => 'NSG2',
            'HNRNPCP5' => 'HNRNPCL2',
            'IL8' => 'CXCL8',
            'KIAA1737' => 'CIPC',
            'KIAA1804' => 'MAP3K21',
            'KIAA1967' => 'CCAR2',
            'LIMS3L' => 'LIMS4',
            'LINC00984' => 'INAFM2',
            'LPPR1' => 'PLPPR1',
            'LPPR2' => 'PLPPR2',
            'LPPR3' => 'PLPPR3',
            'LPPR4' => 'PLPPR4',
            'LPPR5' => 'PLPPR5',
            'LSMD1' => 'NAA38',
            'METTL21D' => 'VCPKMT',
            'MKI67IP' => 'NIFK',
            'MNF1' => 'UQCC2',
            'NAPRT1' => 'NAPRT',
            'NARR' => 'RAB34',
            'NEURL' => 'NEURL1',
            'NIM1' => 'NIM1K',
            'PAPL' => 'ACP7',
            'PCDP1' => 'CFAP221',
            'PHF17' => 'JADE1',
            'PPIAL4B' => 'PPIAL4A',
            'PRMT10' => 'PRMT9',
            'REXO1L1' => 'REXO1L1P',
            'SCXA' => 'SCX',
            'SELK' => 'SELENOK',
            'SELM' => 'SELENOM',
            'SELO' => 'SELENOO',
            'SELT' => 'SELENOT',
            'SELV' => 'SELENOV',
            'SEP15' => 'SELENOF',
            'SGK110' => 'SBK3',
            'SGK223' => 'PRAG1',
            'SMCR7L' => 'MIEF1',
            'TCEB3CL' => 'ELOA3B',
            'YES' => 'YES1',
            'ZAK' => 'MAP3K20',
            // These above have been added 2017-07-27. Expire 2018-07-27.
        );

        return $aGeneAliases;
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





    // FIXME: This is Leiden-specific code, put it in the Leiden adapter? It's not defined anywhere else, so make a default that doesn't do anything?
    // FIXME: This function does not have a valid name, it does not prepare any headers.
    function prepareHeaders ($aHeaders)
    {
        // Verify the identity of this file. Some columns are appended by the Miracle ID.
        // Check the child's Miracle ID with that we have in the meta data file, and remove all the IDs so the headers are recognized normally.
        foreach ($aHeaders as $key => $sHeader) {
            if (preg_match('/(Child|Patient|Father|Mother)_(\d+)$/', $sHeader, $aRegs)) {
                // If Child, check ID.
                if (!empty($this->aScriptVars['nMiracleID']) && in_array($aRegs[1], array('Child', 'Patient')) && $aRegs[2] != $this->aScriptVars['nMiracleID']) {
                    // Here, we won't try and remove the temp file. We need it for diagnostics, and it will save us from running into the same error over and over again.
                    die('Fatal: Miracle ID of ' . $aRegs[1] . ' (' . $aRegs[2] . ') does not match that from the meta file (' . $this->aScriptVars['nMiracleID'] . ')' . "\n");
                }
                // Clean ID from column.
                $aHeaders[$key] = substr($sHeader, 0, -(strlen($aRegs[2]) + 1));
            }
        }

        return $aHeaders;
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
            'GT_Child' => 'allele',
            'GT_Patient' => 'allele',
            'GQ_Child' => 'VariantOnGenome/Sequencing/GenoType/Quality',
            'GQ_Patient' => 'VariantOnGenome/Sequencing/GenoType/Quality',
            'DP_Child' => 'VariantOnGenome/Sequencing/Depth/Total',
            'DP_Patient' => 'VariantOnGenome/Sequencing/Depth/Total',
            'DPREF_Child' => 'VariantOnGenome/Sequencing/Depth/Ref',
            'DPREF_Patient' => 'VariantOnGenome/Sequencing/Depth/Ref',
            'DPALT_Child' => 'VariantOnGenome/Sequencing/Depth/Alt',
            'DPALT_Patient' => 'VariantOnGenome/Sequencing/Depth/Alt',
            'ALTPERC_Child' => 'VariantOnGenome/Sequencing/Depth/Alt/Fraction', // Will be divided by 100 later.
            'ALTPERC_Patient' => 'VariantOnGenome/Sequencing/Depth/Alt/Fraction', // Will be divided by 100 later.
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





    // FIXME: Merge this with ignoreTranscript(), this is too much of the same and no need to make it so complicated.
    // FIXME: This function is not referenced anywhere, so it can't do anything.
    function prepareTranscriptsPrefixToIgnore ()
    {
        // Prepare the $aGenesToIgnore array with a site specific gene list.
        $aTranscriptsPrefixToIgnore = array(
            'NR_'
        );
        return $aTranscriptsPrefixToIgnore;
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
