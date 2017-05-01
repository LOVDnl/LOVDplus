<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-09-02
 * Modified    : 2017-04-13
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
    'max_annotation_error_allowed' => 20,
    'exit_on_annotation_error' => true,
    'enforce_hgnc_gene' => true,
    'check_indel_description' => true
);



class LOVD_DefaultDataConverter {

    var $sAdapterPath;
    var $aScriptVars;
    var $aMetadata;
    static $sAdapterName = 'DEFAULT';
    static $NO_TRANSCRIPT = '-----';

    public function __construct($sAdapterPath)
    {
        $this->sAdapterPath = $sAdapterPath;

    }





    function convertInputFiles()
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





    function readMetadata($aMetaDataLines)
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





    function setScriptVars($aVars = array())
    {
        // Keep track of the values of some variables defined in the script that calls this adapter object.

        // Newly set vars overwrites existing vars.
        $this->aScriptVars = $aVars + $this->aScriptVars;
    }





    function prepareMappings()
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
            'GLOBAL_VN' => 'VariantOnGenome/InhouseDB/Position/Global/Samples_with_coverage',
            'GLOBAL_VF_HET' => 'VariantOnGenome/InhouseDB/Count/Global/Heterozygotes',
            'GLOBAL_VF_HOM' => 'VariantOnGenome/InhouseDB/Count/Global/Homozygotes',
            'WITHIN_PANEL_VN' => 'VariantOnGenome/InhouseDB/Position/InPanel/Samples_with_coverage',
            'WITHIN_PANEL_VF_HET' => 'VariantOnGenome/InhouseDB/Count/InPanel/Heterozygotes',
            'WITHIN_PANEL_VF_HOM' => 'VariantOnGenome/InhouseDB/Count/InPanel/Homozygotes',
            'OUTSIDE_PANEL_VN' => 'VariantOnGenome/InhouseDB/Position/OutOfPanel/Samples_with_coverage',
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
            'Existing_variation' => 'existingvariation'

        );

        return $aColumnMappings;
    }





    function prepareVariantData(&$aLine)
    {
        // Reformat a line of raw variant data into the format that works for this instance.

        return $aLine;
    }





    function prepareGeneAliases()
    {
        // Prepare the $aGeneAliases array with a site specific gene alias list.
        // The convert and merge script will provide suggested gene alias key value pairs to add to this array.
        $aGeneAliases = array(
            /*
            // Sort? Keep forever?
            'C1orf63' => 'RSRP1',
            'C1orf170' => 'PERM1',
            'C1orf200' => 'PIK3CD-AS1',
            'FAM5C' => 'BRINP3',
            'HNRNPCP5' => 'HNRNPCL2',
            'SELRC1' => 'COA7',
            'C1orf191' => 'SSBP3-AS1',
            'LINC00568' => 'FALEC',
            'MIR664' => 'MIR664A',
            'C1orf148' => 'IBA57-AS1',
            'AKR1CL1' => 'AKR1C8P',
            'C10orf112' => 'MALRD1',
            'LINC00263' => 'OLMALINC',
            'NEURL' => 'NEURL1',
            'C10orf85' => 'LINC01561',
            'C11orf92' => 'COLCA1',
            'C11orf34' => 'PLET1',
            'FLI1-AS1' => 'SENCR',
            'HOXC-AS5' => 'HOXC13-AS',
            'LINC00277' => 'EWSAT1',
            'C15orf37' => 'ST20-AS1',
            'RPS17L' => 'RPS17',
            'SNORA50' => 'SNORA76A',
            'MIR4520B' => 'MIR4520-2',
            'MIR4520A' => 'MIR4520-1',
            'LSMD1' => 'NAA38',
            'C17orf76-AS1' => 'LRRC75A-AS1',
            'PRAC' => 'PRAC1',
            'HOXB-AS5' => 'PRAC2',
            'KIAA1704' => 'GPALPP1',
            'KIAA1737' => 'CIPC',
            'CNIH' => 'CNIH1',
            'METTL21D' => 'VCPKMT',
            'LINC00984' => 'INAFM2',
            'SNORA76' => 'SNORA76C',
            'FLJ37644' => 'SOX9-AS1',
            'RPL17-C18ORF32' => 'RPL17-C18orf32',
            'CYP2B7P1' => 'CYP2B7P',
            'C19orf69' => 'ERICH4',
            'ZFP112' => 'ZNF112',
            'HCCAT3' => 'ZNF350-AS1',
            'SGK110' => 'SBK3',
            'UNQ6975' => 'LINC01121',
            'FLJ30838' => 'LINC01122',
            'FLJ16341' => 'LINC01185',
            'PCDP1' => 'CFAP221',
            'C2orf62' => 'CATIP',
            'UQCC' => 'UQCC1',
            'C20orf201' => 'LKAAEAR1',
            'MIR3648' => 'MIR3648-1',
            'MIR3687' => 'MIR3687-1',
            'C21orf15' => 'CYP4F29P',
            'LINC00478' => 'MIR99AHG',
            'BRWD1-IT2' => 'BRWD1-AS2',
            'C21orf128' => 'UMODL1-AS1',
            'SETD5-AS1' => 'THUMPD3-AS1',
            'GTDC2' => 'POMGNT2',
            'CT64' => 'LINC01192',
            'HTT-AS1' => 'HTT-AS',
            'FLJ13197' => 'KLF3-AS1',
            'FLJ14186' => 'LINC01061',
            'PHF17' => 'JADE1',
            'PRMT10' => 'PRMT9',
            'CCDC111' => 'PRIMPOL',
            'NIM1' => 'NIM1K',
            'FLJ33630' => 'LINC01184',
            'PHF15' => 'JADE2',
            'C5orf50' => 'SMIM23',
            'MGC39372' => 'SERPINB9P1',
            'LINC00340' => 'CASC15',
            'MIR219-1' => 'MIR219A1',
            'STL' => 'RNF217-AS1',
            'RPA3-AS1' => 'UMAD1',
            'HOXA-AS4' => 'HOXA10-AS',
            'C7orf41' => 'MTURN',
            'FLJ45974' => 'LINC01446',
            'FKBP9L' => 'FKBP9P1',
            'LINC00035' => 'ABHD11-AS1',
            'RSBN1L-AS1' => 'APTR',
            'MKLN1-AS1' => 'LINC-PINT',
            'ZNF767' => 'ZNF767P',
            'KIAA1967' => 'CCAR2',
            'SGK196' => 'POMK',
            'LINC00966' => 'MIR124-2HG',
            'REXO1L1' => 'REXO1L1P',
            'C8orf69' => 'LINC01298',
            'C8orf56' => 'BAALC-AS2',
            'PHF16' => 'JADE3',
            'MST4' => 'STK26',
            'SMCR7L' => 'MIEF1',
            'C4orf40' => 'PRR27',
            'IL8' => 'CXCL8',
            'AQPEP' => 'LVRN',
            'MNF1' => 'UQCC2',
            'GPER' => 'GPER1',
            'C8orf47' => 'ERICH5',
            'NAPRT1' => 'NAPRT',
            'C9orf123' => 'TMEM261',
            'KIAA1984' => 'CCDC183',
            'C9orf169' => 'CYSRT1',
            'C11orf93' => 'COLCA2',
            'C12orf52' => 'RITA1',
            'SMCR7' => 'MIEF2',
            'C3orf37' => 'HMCES',
            'C3orf43' => 'SMCO1',
            'C6orf70' => 'ERMARD',
            'C9orf37' => 'ARRDC1-AS1',
            'CXorf48' => 'CT55',
            'TGIF2-C20ORF24' => 'TGIF2-C20orf24',
            'C13orf45' => 'LMO7DN',
            'C3orf83' => 'MKRN2OS',
            'CXorf61' => 'CT83',
            'CXXC11' => 'RTP5',
            'DOM3Z' => 'DXO',
            'SPATA31A2' => 'SPATA31A1',
            'CT60' => 'LINC01193',
            'FLJ30403' => 'LINC01530',
            'FOLR4' => 'IZUMO1R',
            'GOLGA6L5' => 'GOLGA6L5P',
            'LINC00085' => 'SPACA6P',
            'LINC00516' => 'BMS1P18',
            'LINC00651' => 'TRERNA1',
            'LINC00948' => 'MRLN',
            'NBPF23' => 'NBPF17P',
            'PCDHB17' => 'PCDHB17P',
            'C10orf137' => 'EDRF1',
            'PLAC1L' => 'OOSP2',
            'MKI67IP' => 'NIFK',
            'C19orf82' => 'ZNF561-AS1',
            'SPANXB2' => 'SPANXB1',
            'SCXB' => 'SCX',
            'FAM203B' => 'HGH1',
            'PNMA6C' => 'PNMA6A',
            // 2016-02-19; New aliases.
            'ADC' => 'AZIN2',
            'BAI2' => 'ADGRB2',
            'C1orf172' => 'KDF1',
            'C1orf173' => 'ERICH3',
            'C1orf177' => 'LEXM',
            'C1orf51' => 'CIART',
            'C1orf86' => 'FAAP20',
            'ELTD1' => 'ADGRL4',
            'GLTPD1' => 'CPTP',
            'HIAT1' => 'MFSD14A',
            'KIAA1751' => 'CFAP74',
            'LEPRE1' => 'P3H1',
            'LPHN2' => 'ADGRL2',
            'NBPF16' => 'NBPF15',
            'NRD1' => 'NRDC',
            'PPAP2B' => 'PLPP3',
            'PTCHD2' => 'DISP3',
            'WDR65' => 'CFAP57',
            // 2016-03-04; New aliases.
            'ANKRD32' => 'SLF1',
            'AZI1' => 'CEP131',
            'C16orf11' => 'PRR35',
            'C16orf80' => 'CFAP20',
            'C17orf66' => 'HEATR9',
            'C18orf56' => 'TYMSOS',
            'C20orf166' => 'MIR1-1HG',
            'C5orf55' => 'EXOC3-AS1',
            'C9orf117' => 'CFAP157',
            'C9orf96' => 'STKLD1',
            'CCDC19' => 'CFAP45',
            'CCDC37' => 'CFAP100',
            'CD97' => 'ADGRE5',
            'CRAMP1L' => 'CRAMP1',
            'CXorf30' => 'CFAP47',
            'EFCAB4A' => 'CRACR2B',
            'EFCAB4B' => 'CRACR2A',
            'EMR2' => 'ADGRE2',
            'FAM211A' => 'LRRC75A',
            'FAM86A' => 'EEF2KMT',
            'GAREML' => 'GAREM2',
            'GPR114' => 'ADGRG5',
            'GPR123' => 'ADGRA1',
            'GPR124' => 'ADGRA2',
            'GPR144' => 'ADGRD2',
            'GPR98' => 'ADGRV1',
            'HIATL1' => 'MFSD14B',
            'IGJ' => 'JCHAIN',
            'KIAA0195' => 'TMEM94',
            'KIAA0247' => 'SUSD6',
            'KIAA0947' => 'ICE1',
            'KIAA1239' => 'NWD2',
            'KIAA1377' => 'CEP126',
            'KIAA1407' => 'CCDC191',
            'KIAA1598' => 'SHTN1',
            'KIAA1731' => 'CEP295',
            'KIAA2018' => 'USF3',
            'LEPREL2' => 'P3H3',
            'NARG2' => 'ICE2',
            'PCNXL3' => 'PCNX3',
            'PCNX' => 'PCNX1',
            'PIDD' => 'PIDD1',
            'PPAPDC2' => 'PLPP6',
            'PVRL1' => 'NECTIN1',
            'SLMO1' => 'PRELID3A',
            'SLMO2' => 'PRELID3B',
            'SMIM11' => 'SMIM11A',
            'SOGA2' => 'MTCL1',
            'WAPAL' => 'WAPL',
            'WDR96' => 'CFAP43',
            'ZNF259' => 'ZPR1',
            'ZNF812' => 'ZNF812P',
            // 2016-03-04; New aliases.
            */
        );

        return $aGeneAliases;
    }






    function prepareGenesToIgnore()
    {
        // Prepare the $aGenesToIgnore array with a site specific gene list.
        $aGenesToIgnore = array(
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
            'KANSL1',
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
        );

        return $aGenesToIgnore;
    }





    function ignoreTranscript($sTranscriptId)
    {
        // Check if we want to skip importing the annotation for this transcript.

        if ($sTranscriptId === static::$NO_TRANSCRIPT) {
            return true;
        }

        return false;
    }





    function prepareTranscriptsPrefixToIgnore()
    {
        // Prepare the $aGenesToIgnore array with a site specific gene list.
        $aTranscriptsPrefixToIgnore = array(
            'NR_'
        );
        return $aTranscriptsPrefixToIgnore;
    }





    function prepareScreeningID($aMetaData)
    {
        // Returns the screening ID.

        return '';
    }






    function getInputFilePrefixPattern()
    {
        // Returns the regex pattern of the prefix of variant input file names.

        return '((?:Child|Patient)_(?:\d+))';
    }






    function getRequiredHeaderColumns()
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





    function prepareHeaders($aHeaders)
    {
        // Returns an array of ordered column headers of the input files.

        return $aHeaders;
    }






    function formatEmptyColumn($aLine, $sVEPColumn, $sLOVDColumn, $aVariant)
    {
        // Returns how we want to represent empty data in $aVariant array given a LOVD column name.
        if (isset($aLine[$sVEPColumn]) && ($aLine[$sVEPColumn] === 0 || $aLine[$sVEPColumn] === '0')) {
            $aVariant[$sLOVDColumn] = 0;
        } else {
            $aVariant[$sLOVDColumn] = '';
        }

        return $aVariant;
    }






    function postValueAssignmentUpdate($sKey, &$aVariant, &$aData)
    {
        // Update $aData if there is any aggregated data that we need to update after each input line is read.

        return $aData;
    }
}