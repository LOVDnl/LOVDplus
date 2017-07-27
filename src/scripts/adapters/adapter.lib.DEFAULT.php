<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-09-02
 * Modified    : 2017-07-27
 * For LOVD    : 3.0-19
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
//$_INSTANCE_CONFIG['attachments'] = array(
//    'igv' => array(
//        'linked_to' => 'variant',
//        'label' => 'IGV screenshot'),
//    'ucsc' => array(
//        'linked_to' => 'summary_annotation',  // This file is stored using the Summary Annotation Record DBID.
//        'label' => 'UCSC screenshot (Summary Annotation)'),
//    'confirmation' => array(
//        'linked_to' => 'variant',
//        'label' => 'Confirmation screenshot'),
//    'workfile' => array(
//        'linked_to' => 'variant',
//        'label' => 'Excel file'),
//);





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





class LOVD_DefaultDataConverter {
    // Class with methods and variables for convert_and_merge_data_files.php.

    var $sAdapterPath;
    var $aScriptVars;
    var $aMetadata;
    static $sAdapterName = 'DEFAULT';
    static $NO_TRANSCRIPT = '-----';

    public function __construct($sAdapterPath)
    {
        $this->sAdapterPath = $sAdapterPath;

    }





    function convertInputFiles ()
    {
        // Run the adapter script for this instance.

        $this->aScriptVars = array();

        print("> Running " . static::$sAdapterName . " adapter\n");
        $cmd = 'php '. $this->sAdapterPath .'/adapter.' . static::$sAdapterName .'.php';
        passthru($cmd, $adapterResult);
        if ($adapterResult !== 0){
            die('Adapter Failed');
        }
    }





    function readMetadata ($aMetaDataLines)
    {
        // Read array of lines from .meta.lovd file of each .directvep.lovd file.
        // Return an array of metadata keyed by column names.

        $aKeyedMetadata = array();
        $aColNamesByPos = array();
        $bHeaderPrevRow = false;
        foreach ($aMetaDataLines as $sLine) {
            $sLine = trim($sLine);
            if (empty($sLine)) {
                continue;
            }

            if ($bHeaderPrevRow) {
                // Assuming we always only have 1 row of data after each header.

                // Some lines are commented out so that they can be skipped during import.
                // But, this metadata is still valid and we want this data.
                $sLine = trim($sLine, "# ");
                $aDataRow = explode("\t", $sLine);
                $aDataRow = array_map(function($sData) {
                    return trim($sData, '"');
                }, $aDataRow);

                foreach ($aColNamesByPos as $nPos => $sColName) {
                    // Read data.
                    $aKeyedMetadata[$sColName] = $aDataRow[$nPos];
                }

                $bHeaderPrevRow = false;
            }

            if (substr($sLine, 0) == '#') {
                continue;
            } elseif (substr($sLine, 0, 3) == '"{{') {
                // Read header
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





    function setScriptVars ($aVars = array())
    {
        // Keep track of the values of some variables defined in the script that calls this adapter object.

        // Newly set vars overwrites existing vars.
        $this->aScriptVars = $aVars + $this->aScriptVars;
    }





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





    function prepareGeneAliases ()
    {
        // Prepare the $aGeneAliases array with a site specific gene alias list.
        // The convert and merge script will provide suggested gene alias key value pairs to add to this array.
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
        // Prepare the $aGenesToIgnore array with a site specific gene list.
        $aGenesToIgnore = array(
            /*
            // 2015-01-19; Not recognized by HGNC.
            'FLJ12825',
            'FLJ27354',
            'FLJ37453',
            'HEATR8-TTC4',
            'HSD52',
            'LPPR5',
            'MGC34796',
            'MGC27382',
            'SEP15',
            'TNFAIP8L2-SCNM1',
            // 2015-01-20; Not recognized by HGNC.
            'BLOC1S1-RDH5',
            'C10orf32-AS3MT',
            'CAND1.11',
            'DKFZp686K1684',
            'FAM24B-CUZD1',
            'FLJ46300',
            'FLJ46361',
            'GNN',
            'KIAA1804',
            'NS3BP',
            'OVOS',
            'OVOS2',
            'PRH1-PRR4',
            // 2015-01-20; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
            'FLJ26245',
            'DKFZP434H168',
            'FLJ30679',
            'C17orf61-PLSCR3',
            'FLJ36000',
            'RAD51L3-RFFL',
            'MGC57346',
            'FLJ40194',
            'FLJ45513',
            'MTVR2',
            'MGC16275',
            'FLJ45079',
            'C18orf61',
            'KC6',
            'FLJ44313',
            'HDGFRP2',
            'FLJ22184',
            'CYP3A7-CYP3AP1',
            'DKFZp434J0226',
            'DKFZp434L192',
            'EEF1E1-MUTED',
            'FLJ16171',
            'FLJ16779',
            'FLJ25363',
            'FLJ33360',
            'FLJ33534',
            'FLJ34503',
            'FLJ40288',
            'FLJ41941',
            'FLJ42351',
            'FLJ42393',
            'FLJ42969',
            'FLJ43879',
            'FLJ44511',
            'FLJ46066',
            'FLJ46284',
            'GIMAP1-GIMAP5',
            'HMP19',
            'HOXA10-HOXA9',
            'IPO11-LRRC70',
            'KIAA1656',
            'LGALS17A',
            'LPPR2',
            'MGC45922',
            'MGC72080',
            'NHEG1',
            'NSG1',
            'PAPL',
            'PHOSPHO2-KLHL23',
            'PP12613',
            'PP14571',
            'PP7080',
            'SELK',
            'SELO',
            'SELT',
            'SELV',
            'SF3B14',
            'SGK223',
            'SLED1',
            'SLMO2-ATP5E',
            'SMA5',
            'WTH3DI',
            'ZAK',
            // 2015-01-22; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
            'LPPR1',
            'FLJ44635',
            'MAGEA10-MAGEA5',
            'ZNF664-FAM101A',
            // 2015-03-05; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
            'TARP',
            'LPPR3',
            'THEG5',
            'LZTS3',
            // 2015-03-12; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
            'HDGFRP3',
            'HGC6.3',
            'NARR',
            // 2015-03-13; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
            'SELM',
            // 2015-03-16; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
            'DKFZP434L187',
            'DKFZp566F0947',
            'DKFZP586I1420',
            'FLJ22447',
            'FLJ31662',
            'FLJ36777',
            'FLJ38576',
            'GM140',
            'LINC00417',
            'MGC27345',
            'PER4',
            'UQCRHL',
            'LPPR4',
            // 2016-03-04; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
            'SGK494',
            // 2016-03-04; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!


            // 2015-01-19; No UD could be loaded.
            'RNU6-2',
            'BRWD1-AS2',
            'GSTTP2',
            'PRSS3P2',
            // 2015-01-22; No UD could be loaded.
            'DUX2',
            // 2015-03-04; No UD could be loaded.
            'PRAMEF22',
            'AGAP10',
            'ANXA8L2',
            'TRIM49D2P',
            'C12orf68',
            'CXorf64',
            // 2015-03-13; No UD could be loaded.
            'LMO7DN',
            'MKRN2OS',
            'RTP5',
            'MALRD1',
            // 2015-03-16; No UD could be loaded.
            'LINC01193',
            'LINC01530',
            'IZUMO1R',
            'MRLN',
            'LINC01184',
            'LINC01185',
            'NBPF17P',
            'PCDHB17P',
            'PERM1',
            'COA7',
            'NIM1K',
            'ZNF561-AS1',
            // 2015-06-25; No UD could be loaded.
            'SCX',
            'HGH1',
            // 2015-08-??; No UD could be loaded.
            'ARRDC1-AS1',
            // 2016-02-19; No UD could be loaded.
            'AZIN2',
            'ADGRB2',
            'KDF1',
            'ERICH3',
            'LEXM',
            'CIART',
            'FAAP20',
            'ADGRL4',
            'CPTP',
            'MFSD14A',
            'CFAP74',
            'P3H1',
            'ADGRL2',
            'NRDC',
            'PLPP3',
            'DISP3',
            'CFAP57',
            // 2016-03-04; No UD could be loaded.
            'ADGRD2',
            'ADGRE5',
            // 2016-03-04; No UD could be loaded.


            // 2015-01-16; No transcripts could be found.
            'HNRNPCL2',
            'MST1L',
            'PIK3CD-AS1',
            'PLEKHM2',
            // 2015-01-19; No transcripts could be found.
            'AKR1C8P',
            'ATP1A1OS',
            'C1orf213',
            'DARC',
            'FALEC',
            'MIR664A',
            'RSRP1',
            'SNORA42',
            'SRGAP2B',
            // 2015-01-20; No transcripts could be found.
            // Still needs to be sorted.
            'C10orf115',
            'C10orf126',
            'ZNF487',
            'TIMM23B',
            'OLMALINC',
            'LINC01561',
            'PHRF1',
            'EWSAT1',
            'ST20-AS1',
            'NR2F2-AS1',
            'LINC00273',
            'SNORA76A',
            'MIR4520-2',
            'MIR4520-1',
            'CDRT8',
            'LRRC75A-AS1',
            'C17orf76-AS1',
            'SNF8',
            'ZNF271',
            'TCEB3CL',
            'INSL3',
            'ZNF738',
            'ERVV-1',
            'KIR3DX1',
            'SMYD5',
            'DIRC3',
            'SNPH',
            'FAM182A',
            'FRG1B',
            'PPP4R1L',
            'C21orf37',
            'KRTAP20-4',
            'C21orf49',
            'C21orf54',
            'C21orf67',
            'RFPL3S',
            'C22orf34',
            'SNORA76C',
            'ERICH4',
            'ZNF350-AS1',
            'CFAP221',
            'CATIP',
            'MIR3648-1',
            'MIR3687-1',
            'CYP4F29P',
            'MIR99AHG',
            'UMODL1-AS1',
            'LINC00692',
            'C3orf49',
            'PLD1',
            'C3orf65',
            'KLF3-AS1',
            'SERPINB9P1',
            'MIR219A1',
            'RNF217-AS1',
            'UMAD1',
            'LINC01446',
            'FKBP9P1',
            'ABHD11-AS1',
            'APTR',
            'LINC-PINT',
            'INAFM2',
            'ZNF767P',
            'MIR124-2HG',
            'LINC01298',
            'DUX4',
            'LTC4S',
            'ERVFRD-1',
            'HCG8',
            'C6orf147',
            'INTS4L2',
            'SPDYE6',
            'ST7-OT4',
            'ZNF783',
            // 2015-01-22; No transcripts could be found.
            'TMEM210', // getTranscriptsAndInfo() gets me an HTTP 500.
            'STK26',
            'ZNF75D',
            // 2015-03-05; No transcripts could be found.
            'TMEM56',
            'PRR27',
            'CXCL8',
            'LVRN',
            'ERICH5',
            'NAPRT',
            'POLE3',
            'CYSRT1',
            'TPTE2',
            'WDR72',
            // 2015-03-12; No transcripts could be found.
            'ABHD11',
            'AMN1',
            'APH1A',
            'ATP5L',
            'AVPR2',
            'SMCO1',
            'CDK2AP2',
            'ESR2',
            'FAM230A',
            'GHDC',
            'LYPD8',
            'PRKAR1A',
            'RIPPLY2',
            'SAT1',
            'SBK3',
            'SLC52A2',
            'TMEM134',
            'ZNF625',
            // 2015-03-13; No transcripts could be found.
            'ARL6IP4',
            'C9orf173',
            'C9orf92',
            'IFITM3',
            'MROH7',
            'PPP2R2B',
            'SRSF2',
            'UXT',
            'VCPKMT',
            'DXO',
            'NT5C',
            'PAXBP1',
            'RGS8',
            // 2015-03-16; No transcripts could be found.
            'ADAMTS9-AS2',
            'ALG1L9P',
            'ALMS1P',
            'ANKRD26P1',
            'ANKRD30BL',
            'BANCR',
            'BCRP3',
            'BOK-AS1',
            'C21orf91-OT1',
            'C5orf56',
            'CASC9',
            'CEP170P1',
            'CMAHP',
            'CXorf28',
            'DDX11L2',
            'DGCR10',
            'DIO2-AS1',
            'EFCAB10',
            'FAM27E2',
            'FAM41C',
            'FAM83H-AS1',
            'FAM86JP',
            'GMDS-AS1',
            'GOLGA2P5',
            'GUSBP1',
            'HCCAT5',
            'HCG4',
            'HCG9',
            'HERC2P3',
            'HERC2P7',
            'HLA-F-AS1',
            'HTT-AS',
            'IQCH-AS1',
            'KCNQ1DN',
            'KLHDC9',
            'KRT16P3',
            'SPACA6P',
            'LINC00112',
            'LINC00184',
            'LINC00189',
            'LINC00202-1',
            'LINC00202-2',
            'LINC00238',
            'LINC00239',
            'LINC00240',
            'LINC00254',
            'LINC00290',
            'LINC00293',
            'LINC00310',
            'LINC00317',
            'LINC00324',
            'LINC00326',
            'LINC00333',
            'LINC00379',
            'LINC00421',
            'LINC00424',
            'LINC00443',
            'LINC00446',
            'LINC00467',
            'LINC00476',
            'LINC00491',
            'BMS1P18',
            'LINC00525',
            'LINC00540',
            'LINC00545',
            'LINC00558',
            'LINC00563',
            'LINC00589',
            'LINC00592',
            'LINC00605',
            'LINC00613',
            'LINC00620',
            'LINC00635',
            'LINC00636',
            'TRERNA1',
            'LINC00652',
            'LINC00656',
            'LINC00661',
            'LINC00665',
            'LINC00701',
            'LINC00707',
            'LINC00890',
            'LINC00899',
            'LINC00910',
            'LINC00925',
            'LINC00929',
            'LINC00959',
            'LINC00963',
            'LINC00968',
            'LINC00977',
            'LINC00982',
            'LINC01003',
            'LINC01005',
            'LINC01061',
            'LINC01121',
            'LY86-AS1',
            'MAGI2-AS3',
            'MEG9',
            'MEIS1-AS3',
            'MIR4458HG',
            'MIR4477A',
            'MIRLET7BHG',
            'MLK7-AS1',
            'MST1P2',
            'NACAP1',
            'NPHP3-AS1',
            'PCGEM1',
            'PDXDC2P',
            'PGAM1P5',
            'PRKY',
            'PSORS1C3',
            'RNF126P1',
            'RNF216P1',
            'ROCK1P1',
            'RSU1P2',
            'SDHAP1',
            'SDHAP2',
            'SH3RF3-AS1',
            'SIGLEC16',
            'SMEK3P',
            'SNHG11',
            'SNHG7',
            'SPATA41',
            'SPATA42',
            'SRP14-AS1',
            'SSR4',
            'ST3GAL6-AS1',
            'TDRG1',
            'TEKT4P2',
            'TEX21P',
            'TEX26-AS1',
            'THTPA',
            'TPTEP1',
            'TRIM52-AS1',
            'WASH2P',
            'WASH7P',
            'ZNF252P-AS1',
            'ZNF252P',
            'ZNF525',
            'ZNF667-AS1',
            'ZNF876P',
            'ZNRD1-AS1',
            'MIB2',
            'AKR1E2',
            'C11orf82',
            'CORO1C',
            'PRSS23',
            'RWDD3',
            'SMYD3',
            'C15orf38',
            'CLK3',
            'ELFN2',
            'GNL3L',
            'GOLGA6L4',
            'GPR128',
            'KCTD2',
            'KLK8',
            'KTN1',
            'PFKFB4',
            'POMK',
            'SP9',
            'UQCC1',
            'ZNF112',
            'ZSCAN23',
            'MYL4',
            'OOSP2',
            'PRAC1',
            'TNFSF13',
            'UQCC2',
            'VASH2',
            'ZNF429',
            'ZNF577',
            'GDNF-AS1',
            'HOXA10',
            'TCL1A',
            // 2015-08-??; No transcripts could be found.
            'ARL6',
            'CXCL1',
            'GPER1',
            'MRPL45',
            'SRGAP2C',
            // 2016-02-19; No transcripts could be found.
            'NBPF9',
            // 2016-03-04; No transcripts could be found.
            'ADGRA1',
            'ADGRA2',
            'ADGRE2',
            'ADGRG5',
            'ADGRV1',
            'C1R',
            'CCAR2',
            'CCDC191',
            'CEP126',
            'CEP131',
            'CEP295',
            'CFAP100',
            'CFAP20',
            'CFAP43',
            'CFAP45',
            'CFAP47',
            'CRACR2A',
            'CRACR2B',
            'CRAMP1',
            'DOCK1',
            'EEF2KMT',
            'ERC1',
            'EXOC3-AS1',
            'GAREM2',
            'HEATR9',
            'ICE1',
            'IKZF1',
            'KIF5C',
            'LRRC75A',
            'MIR1-1HG',
            'MTCL1',
            'MUC19',
            'NECTIN1',
            'NWD2',
            'P3H3',
            'PCNX1',
            'PCNX3',
            'PIDD1',
            'PLPP6',
            'POMGNT2',
            'PRELID3A',
            'PRELID3B',
            'PRR35',
            'SHTN1',
            'SLF1',
            'SMIM11A',
            'STKLD1',
            'SUSD6',
            'TMEM247',
            'TMEM94',
            'TYMSOS',
            'USF3',
            'WAPL',
            'ZNF812P',
            'ZPR1',
            // 2016-03-04; No transcripts could be found.
            */
        );

        return $aGenesToIgnore;
    }





    function ignoreTranscript ($sTranscriptId)
    {
        // Check if we want to skip importing the annotation for this transcript.

        // What is this?
        if ($sTranscriptId === static::$NO_TRANSCRIPT) {
            return true;
        }

        return false;
    }





    function prepareTranscriptsPrefixToIgnore ()
    {
        // Prepare the $aGenesToIgnore array with a site specific gene list.
        $aTranscriptsPrefixToIgnore = array(
            'NR_'
        );
        return $aTranscriptsPrefixToIgnore;
    }





    function prepareScreeningID ($aMetaData)
    {
        // Returns the screening ID.

        return '';
    }






    function getInputFilePrefixPattern ()
    {
        // Returns the regex pattern of the prefix of variant input file names.

        return '(?:Child|Patient)_(?:\d+)';
    }






    function getRequiredHeaderColumns ()
    {
        // Returns an array of required input variant file column headers. The order of these columns does NOT matter.

        return array(
            'chromosome',
            'position',
            'REF',
            'ALT',
            'QUAL',
            'FILTERvcf',
            'GATKCaller'
        );
    }





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






    function formatEmptyColumn ($aLine, $sVEPColumn, $sLOVDColumn, $aVariant)
    {
        // Returns how we want to represent empty data in $aVariant array given a LOVD column name.
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






    function postValueAssignmentUpdate ($sKey, &$aVariant, &$aData)
    {
        // Update $aData if there is any aggregated data that we need to update after each input line is read.

        return $aData;
    }
}
