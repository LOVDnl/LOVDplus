<?php

require_once dirname(__FILE__) . '/conversion_adapter.lib.DEFAULT.php';

$_INSTANCE_CONFIG = array();

$_INSTANCE_CONFIG['screenings'] = array(
    'viewList' => array(
        'colsToShow' => array(
            // We can have view list id as key here if needed.
            // 0 here means the viewList columns seen by the constructor (at the point where we don't know VL id yet.
            0 => array(
                // Invisible.
                'individualid',

                // Visible.
                'id',
                'Screening/Tumor/Sample_ID',
                'Screening/Normal/Sample_ID',
                'Screening/Pipeline/Path',
                'variants_found_',
                'analysis_status'
            )
        )
    )
);

$_INSTANCE_CONFIG['custom_object'] = array(
    'viewList' => array(
        'colsToShow' => array(
            // We can have view list id as key here if needed.
            // 0 here means the viewList columns seen by the constructor (at the point where we don't know VL id yet.
            0 => array(
                // VOG
                'VariantOnGenome/DNA',
                'VariantOnGenome/Sequencing/Quality',
                'VariantOnGenome/Variant_priority',
                'VariantOnGenome/Frequency/1000G/VEP',
                'VariantOnGenome/Frequency/EVS/VEP/European_American',
                'VariantOnGenome/Frequency/ExAC',
                'VariantOnGenome/COSMIC_IDs',
                'VariantOnGenome/Sequencing/Normal/Genotype/Quality',
                'VariantOnGenome/Sequencing/Tumour/Genotype/Quality',
                'VariantOnGenome/Sequencing/Normal/BI/Depth/Total',
                'VariantOnGenome/Sequencing/Tumour/BI/Depth/Total',
                'VariantOnGenome/Sequencing/Normal/BI/Allele/Frequency',
                'VariantOnGenome/Sequencing/Tumour/BI/Allele/Frequency',
                'VariantOnGenome/Sequencing/Normal/BI/Depth/Ref',
                'VariantOnGenome/Sequencing/Tumour/BI/Depth/Ref',
                'VariantOnGenome/Sequencing/Normal/BI/Depth/Alt',
                'VariantOnGenome/Sequencing/Tumour/BI/Depth/Alt',
                'VariantOnGenome/Sequencing/Somatic/Score',
                'VariantOnGenome/Sequencing/Fisher/Germline',
                'VariantOnGenome/Sequencing/Fisher/Somatic',
                'VariantOnGenome/Sequencing/Normal/Indel/Reads',
                'VariantOnGenome/Sequencing/Normal/Total_Coverage',
                'VariantOnGenome/Sequencing/Normal/Indel/Mismatches/Average',
                'VariantOnGenome/Sequencing/Normal/Indel/Mapping_Quality',
                'VariantOnGenome/Sequencing/Tumour/Indel/Reads',
                'VariantOnGenome/Sequencing/Tumour/Total_Coverage',
                'VariantOnGenome/Sequencing/Tumour/Indel/Mismatches/Average',
                'VariantOnGenome/Sequencing/Tumour/Indel/Mapping_Quality',
                'VariantOnTranscript/SIFT',
                'VariantOnTranscript/PolyPhen',
                'VariantOnGenome/Sequencing/Somatic/Status',

                // VOT
                'VariantOnTranscript/DNA',
                'VariantOnTranscript/Protein',
                'VariantOnGenome/Consequence',
                'VariantOnGenome/DbSNP_IDs',


            ),

            'CustomVL_AnalysisRunResults_for_I_VE' => array(
                // Invisible.
                'runid',
                'curation_statusid',
                'variantid',


                // Visible.
                'curation_status_',
                'VariantOnGenome/Sequencing/Somatic/Status',
                'chromosome',
                'symbol',
                'preferred_transcripts',
                'VariantOnGenome/DNA',
                'VariantOnTranscript/DNA',
                'VariantOnTranscript/Protein',
                'VariantOnGenome/Consequence',
                'VariantOnGenome/DbSNP_IDs',
                'obs_variant',
                'obs_var_ind_ratio',
                'obs_disease',
                'VariantOnGenome/Frequency/1000G/VEP',
                'VariantOnGenome/Frequency/EVS/VEP/European_American',
                'VariantOnGenome/COSMIC_IDs',
                'VariantOnGenome/Sequencing/Tumour/Genotype/Quality',
                'VariantOnGenome/Sequencing/Normal/BI/Depth/Total',
                'VariantOnGenome/Sequencing/Tumour/BI/Depth/Total',
                'VariantOnGenome/Sequencing/Normal/BI/Allele/Frequency',
                'VariantOnGenome/Sequencing/Tumour/BI/Allele/Frequency',
                'VariantOnGenome/Sequencing/Normal/BI/Depth/Ref',
                'VariantOnGenome/Sequencing/Tumour/BI/Depth/Ref',
                'VariantOnGenome/Sequencing/Normal/BI/Depth/Alt',
                'VariantOnGenome/Sequencing/Tumour/BI/Depth/Alt',
                'VariantOnGenome/Sequencing/Somatic/Score',
                'VariantOnGenome/Sequencing/Fisher/Germline',
                'VariantOnGenome/Sequencing/Fisher/Somatic',
                'VariantOnGenome/Sequencing/Quality',
                'VariantOnGenome/Sequencing/Normal/Indel/Reads',
                'VariantOnGenome/Sequencing/Normal/Total_Coverage',
                'VariantOnGenome/Sequencing/Normal/Indel/Mismatches/Average',
                'VariantOnGenome/Sequencing/Normal/Indel/Mapping_Quality',
                'VariantOnGenome/Sequencing/Tumour/Indel/Reads',
                'VariantOnGenome/Sequencing/Tumour/Total_Coverage',
                'VariantOnGenome/Sequencing/Tumour/Indel/Mismatches/Average',
                'VariantOnGenome/Sequencing/Tumour/Indel/Mapping_Quality',
                'VariantOnTranscript/SIFT',
                'VariantOnTranscript/PolyPhen',
                'VariantOnGenome/Sequencing/Normal/Genotype/Quality',
                'vog_effect',
            )
        )
    )
);

$_INSTANCE_CONFIG['variants'] = array(
    'curation_files' => array(
        'igv' => array(
            'type' => 'image' ,
            'id' => 'nid',
            'label' => 'IGV screenshot'),
        'ucsc' => array(
            'type' => 'image' ,
            'id' => 'said',  // This file is stored using the Summary Annotation Record DBID.
            'label' => 'UCSC screenshot (Summary Annotation)'),
        'confirmation' => array(
            'type' => 'image' ,
            'id' => 'nid',
            'label' => 'Confirmation screenshot'),
        'workfile' => array(
            'type' => 'excel' ,
            'id' => 'nid',
            'label' => 'Excel file')
    )
);


class LOVD_MghaSeqDataConverter extends LOVD_DefaultDataConverter {

    static $sAdapterName = 'MGHA_SEQ';

    function prepareMappings()
    {
        // Returns an array that map VEP columns to LOVD columns.

        $aColumnMappings = array(
            'CHROM' => 'chromosome',
            'POS' => 'position',
            'ID' => 'VariantOnGenome/dbSNP',
            'REF' => 'ref',
            'ALT' => 'alt',
            'QUAL' => 'VariantOnGenome/Sequencing/Quality',
            'SYMBOL' => 'symbol',

            'Feature' => 'transcriptid',
            'HGVSc' => 'VariantOnTranscript/DNA',
            'HGVSp' => 'VariantOnTranscript/Protein',

            // Re-use from MGHA
            'FILTER' => 'VariantOnGenome/Sequencing/Filter',
            'AC' => 'VariantOnGenome/Sequencing/Allele/Count',
            'AF' => 'VariantOnGenome/Sequencing/Allele/Frequency',
            'AN' => 'VariantOnGenome/Sequencing/Allele/Total',
            'BaseQRankSum' => 'VariantOnGenome/Sequencing/Base_Qualities_Score',
//            'DB' => 'VariantOnGenome/Sequencing/dbSNP_Membership',
            'DP' => 'VariantOnGenome/Sequencing/Depth/Unfiltered_All',
            'FS' => 'VariantOnGenome/Sequencing/Fisher_Strand_Bias',
            'MLEAC' => 'VariantOnGenome/Sequencing/Max_Likelihood_Exp_Allele_Count',
            'MLEAF' => 'VariantOnGenome/Sequencing/Max_Likelihood_Exp_Allele_Freq',
            'MQ' => 'VariantOnGenome/Sequencing/Mapping_Quality',
            'MQRankSum' => 'VariantOnGenome/Sequencing/Mapping_Quality_Score',
            'QD' => 'VariantOnGenome/Sequencing/Quality_by_depth',
            'ReadPosRankSum' => 'VariantOnGenome/Sequencing/Read_Position_Bias_Score',
            'GMAF' => 'VariantOnGenome/Frequency/1000G/VEP',
            'EUR_MAF' => 'VariantOnGenome/Frequency/1000G/VEP/European',
            'AFR_MAF' => 'VariantOnGenome/Frequency/1000G/VEP/African',
            'AMR_MAF' => 'VariantOnGenome/Frequency/1000G/VEP/American',
            'AA_MAF' => 'VariantOnGenome/Frequency/EVS/VEP/African_American',
            'EA_MAF' => 'VariantOnGenome/Frequency/EVS/VEP/European_American',

            'CANONICAL' => 'VariantOnTranscript/Canonical_Transcript',
            'ENSP' => 'VariantOnTranscript/Embsembl_Protein_Identifier',
            'EXON' => 'VariantOnTranscript/Exon',
            'INTRON' => 'VariantOnTranscript/Intron',
            'cDNA_position' => 'VariantOnTranscript/cDNA_Position',
            'CDS_position' => 'VariantOnTranscript/Position',
            'Protein_position' => 'VariantOnTranscript/Protein_Position',
            'Amino_acids' => 'VariantOnTranscript/Amino_Acids',
            'Codons' => 'VariantOnTranscript/Alternative_Codons',
            'PUBMED' => 'VariantOnTranscript/Pubmed',
            'BIOTYPE' => 'VariantOnTranscript/Biotype',
            'CLIN_SIG' => 'VariantOnTranscript/Clinical_Significance',
            'SOMATIC' => 'VariantOnTranscript/Somatic_Status',
            'STRAND' => 'VariantOnTranscript/DNA_Strand',
            'Feature_type' => 'VariantOnTranscript/Feature_Type',

//            'VT' => 'VariantOnGenome/Sequencing/Variant_Type',

            // Normal
//            'Dels' => 'VariantOnGenome/Sequencing/Dels',
//            'HaplotypeScore' => 'VariantOnGenome/Sequencing/Haplotype_Score',
//            'MQ0' => 'VariantOnGenome/Sequencing/Total_Mapping_Quality_0_Reads',
//            'RPA' => 'VariantOnGenome/Sequencing/Tandem/Repeat_Num',
//            'RU' => 'VariantOnGenome/Sequencing/Tandem/Repeat_Unit',
//            'STR' => 'VariantOnGenome/Sequencing/Tandem/Repeat_Short',
//            'normal:AD' => 'VariantOnGenome/Sequencing/Normal/Depth',
            'normal:DP' => 'VariantOnGenome/Sequencing/Normal/Depth/Total',
//            'normal:GQ' => 'VariantOnGenome/Sequencing/Normal/Genotype/Quality',
            'normal:GT' => 'VariantOnGenome/Sequencing/Normal/GenoType',
            'normal:PL' => 'VariantOnGenome/Sequencing/Normal/Phredscaled_Likelihoods',
            'normal:PMCAD' => 'VariantOnGenome/Sequencing/Normal/BI/Depth/Alt',
            'normal:PMCADF' => 'VariantOnGenome/Sequencing/Normal/BI/Depth/Alt/Forward',
            'normal:PMCADR' => 'VariantOnGenome/Sequencing/Normal/BI/Depth/Alt/Reverse',
            'normal:PMCBDIR' => 'VariantOnGenome/Sequencing/Normal/BI/Bidirectional',
            'normal:PMCDP' => 'VariantOnGenome/Sequencing/Normal/BI/Depth/Total',
            'normal:PMCFREQ' => 'VariantOnGenome/Sequencing/Normal/BI/Allele/Frequency',
            'normal:PMCRD' => 'VariantOnGenome/Sequencing/Normal/BI/Depth/Ref',
            'normal:PMCRDF' => 'VariantOnGenome/Sequencing/Normal/BI/Depth/Ref/Forward',
            'normal:PMCRDR' => 'VariantOnGenome/Sequencing/Normal/BI/Depth/Ref/Reverse',
            'Consequence' => 'VariantOnGenome/Consequence',
            'Gene' => 'VariantOnGenome/Gene_ID',
            'DISTANCE' => 'VariantOnTranscript/Distance',
            'dbSNP_ids' => 'VariantOnGenome/DbSNP_IDs',
            'COSMIC_ids' => 'VariantOnGenome/COSMIC_IDs',
            'PolyPhen' => 'VariantOnTranscript/PolyPhen',
            'SIFT' => 'VariantOnTranscript/SIFT',
            'ASN_MAF' => 'VariantOnGenome/Frequency/1000G/VEP/Asian',
//            'HIGH_INF_POS' => 'VariantOnGenome/Sequencing/High_Information_Position',
//            'MOTIF_NAME' => 'VariantOnTranscript/TFBP/Name',
//            'MOTIF_POS' => 'VariantOnTranscript/TFBP/Position',
            'MOTIF_SCORE_CHANGE' => 'VariantOnTranscript/TFBP/Motif_Score_Change',

            // Tumour
//            'tumour:AD' => 'VariantOnGenome/Sequencing/Tumour/Depth',
            'tumour:DP' => 'VariantOnGenome/Sequencing/Tumour/Depth/Total',
//            'tumour:GQ' => 'VariantOnGenome/Sequencing/Tumour/Genotype/Quality',
            'tumour:GT' => 'VariantOnGenome/Sequencing/Tumour/GenoType',
            'tumour:PL' => 'VariantOnGenome/Sequencing/Tumour/Phredscaled_Likelihoods',
            'tumour:PMCAD' => 'VariantOnGenome/Sequencing/Tumour/BI/Depth/Alt',
            'tumour:PMCADF' => 'VariantOnGenome/Sequencing/Tumour/BI/Depth/Alt/Forward',
            'tumour:PMCADR' => 'VariantOnGenome/Sequencing/Tumour/BI/Depth/Alt/Reverse',
            'tumour:PMCBDIR' => 'VariantOnGenome/Sequencing/Tumour/BI/Bidirectional',
            'tumour:PMCDP' => 'VariantOnGenome/Sequencing/Tumour/BI/Depth/Total',
            'tumour:PMCFREQ' => 'VariantOnGenome/Sequencing/Tumour/BI/Allele/Frequency',
            'tumour:PMCRD' => 'VariantOnGenome/Sequencing/Tumour/BI/Depth/Ref',
            'tumour:PMCRDF' => 'VariantOnGenome/Sequencing/Tumour/BI/Depth/Ref/Forward',
            'tumour:PMCRDR' => 'VariantOnGenome/Sequencing/Tumour/BI/Depth/Ref/Reverse',

            // Tummour Normal Combined
            'GPV' => 'VariantOnGenome/Sequencing/Fisher/Germline',
            'Identified' => 'VariantOnGenome/Sequencing/VCF_Source',
//            'N_AC' => 'VariantOnGenome/Sequencing/Normal/Indel/Reads',
//            'N_DP' => 'VariantOnGenome/Sequencing/Normal/Total_Coverage',
//            'N_MM' => 'VariantOnGenome/Sequencing/Normal/Indel/Mismatches/Average',
//            'N_MQ' => 'VariantOnGenome/Sequencing/Normal/Indel/Mapping_Quality',
//            'N_NQSBQ' => 'VariantOnGenome/Sequencing/Normal/Indel/Average_Quality',
//            'N_NQSMM' => 'VariantOnGenome/Sequencing/Normal/Indel/Mismatches/Fraction',
//            'N_SC' => 'VariantOnGenome/Sequencing/Normal/Indel/Strandness',
            //'SL_N_AD_INDELOCATOR' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Allele/Indel',
            //'SL_N_AD_MUTECT' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Allele/Mutect',
            'SL_N_AD_VARSCAN' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Allele/Varscan',
//            'SL_N_DP_INDELOCATOR' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Coverage/Indelocator',
//            'SL_N_DP_MUTECT' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Coverage/Mutect',
            'SL_N_DP_VARSCAN' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Coverage/Varscan',
            //'SL_T_AD_INDELOCATOR' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Allele/Indel',
            //'SL_T_AD_MUTECT' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Allele/Mutect',
            'SL_T_AD_VARSCAN' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Allele/Varscan',
//            'SL_T_DP_INDELOCATOR' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Coverage/Indelocator',
//            'SL_T_DP_MUTECT' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Coverage/Mutect',
            'SL_T_DP_VARSCAN' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Coverage/Varscan',
            'SPV' => 'VariantOnGenome/Sequencing/Fisher/Somatic',
            'SS' => 'VariantOnGenome/Sequencing/Somatic/Status',
            'SSC' => 'VariantOnGenome/Sequencing/Somatic/Score',
//            'T_AC' => 'VariantOnGenome/Sequencing/Tumour/Indel/Reads',
//            'T_DP' => 'VariantOnGenome/Sequencing/Tumour/Total_Coverage',
//            'T_MM' => 'VariantOnGenome/Sequencing/Tumour/Indel/Mismatches/Average',
//            'T_MQ' => 'VariantOnGenome/Sequencing/Tumour/Indel/Mapping_Quality',
//            'T_NQSBQ' => 'VariantOnGenome/Sequencing/Tumour/Indel/Average_Quality',
//            'T_NQSMM' => 'VariantOnGenome/Sequencing/Tumour/Indel/Mismatches/Fraction',
//            'T_SC' => 'VariantOnGenome/Sequencing/Tumour/Indel/Strandness',
//            'normal:BQ' => 'VariantOnGenome/Sequencing/Normal/Allele/Average_Quality',
            'normal:DP4' => 'VariantOnGenome/Sequencing/Normal/Strand/Count',
//            'normal:FA' => 'VariantOnGenome/Sequencing/Normal/Allele/Fraction',
            'normal:FREQ' => 'VariantOnGenome/Sequencing/Normal/Allele/Frequency',
            'normal:RD' => 'VariantOnGenome/Sequencing/Normal/Depth/Ref',
//            'normal:SS' => 'VariantOnGenome/Sequencing/Normal/Somatic_Status',
//            'tumour:BQ' => 'VariantOnGenome/Sequencing/Tumour/Allele/Average_Quality',
            'tumour:DP4' => 'VariantOnGenome/Sequencing/Tumour/Strand/Count',
//            'tumour:FA' => 'VariantOnGenome/Sequencing/Tumour/Allele/Fraction',
            'tumour:FREQ' => 'VariantOnGenome/Sequencing/Tumour/Allele/Frequency',
            'tumour:RD' => 'VariantOnGenome/Sequencing/Tumour/Depth/Ref',
//            'tumour:SS' => 'VariantOnGenome/Sequencing/Tumour/Somatic_Status',

            // Columns we add.
            'allele' => 'allele',

            'Normal_Depth_Ref' => 'VariantOnGenome/Sequencing/Normal/Allele/Depth/Ref', // Derived from Normal:AD.
            'Normal_Depth_Alt' => 'VariantOnGenome/Sequencing/Normal/Allele/Depth/Alt', // Derived from Normal:AD.

            'Tumour_Depth_Ref' => 'VariantOnGenome/Sequencing/Tumour/Allele/Depth/Ref', // Derived from Tumour:AD.
            'Tumour_Depth_Alt' => 'VariantOnGenome/Sequencing/Tumour/Allele/Depth/Alt', // Derived from Tumour:AD.

            'Normal_Seqliner_Indel_Depth_Ref' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Indel/Depth/Ref', // Derived from SL_N_AD_INDELOCATOR.
            'Normal_Seqliner_Indel_Depth_Alt' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Indel/Depth/Alt', // Derived from SL_N_AD_INDELOCATOR.

            'Normal_Seqliner_Mutect_Depth_Ref' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Mutect/Depth/Ref', // Derived from SL_N_AD_MUTECT.
            'Normal_Seqliner_Mutect_Depth_Alt' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Mutect/Depth/Alt', // Derived from SL_N_AD_MUTECT.

            'Normal_Seqliner_Varscan_Depth_Ref' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Varscan/Depth/Ref', // Derived from SL_N_AD_VARSCAN.
            'Normal_Seqliner_Varscan_Depth_Alt' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Varscan/Depth/Alt', // Derived from SL_N_AD_VARSCAN.

            'Tumour_Seqliner_Indel_Depth_Ref' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Indel/Depth/Ref', // Derived from SL_T_AD_INDELOCATOR.
            'Tumour_Seqliner_Indel_Depth_Alt' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Indel/Depth/Alt', // Derived from SL_T_AD_INDELOCATOR.

            'Tumour_Seqliner_Mutect_Depth_Ref' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Mutect/Depth/Ref', // Derived from SL_T_AD_MUTECT.
            'Tumour_Seqliner_Mutect_Depth_Alt' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Mutect/Depth/Alt', // Derived from SL_T_AD_MUTECT.

            'Tumour_Seqliner_Varscan_Depth_Ref' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Varscan/Depth/Ref', // Derived from SL_T_AD_VARSCAN.
            'Tumour_Seqliner_Varscan_Depth_Alt' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Varscan/Depth/Alt', // Derived from SL_T_AD_VARSCAN.

        );

        return $aColumnMappings;
    }






    function prepareVariantData(&$aLine)
    {
        // Reformat a line of raw variant data into the format that works for this instance.

        global $_LINE_AGGREGATED;
        $_LINE_AGGREGATED = array();

        $aLine = $this->prepareFrequencyColumns($aLine);
        $aLine = $this->prepareAlleleDepthColumns($aLine);

        return $aLine;
    }






    function prepareFrequencyColumns($aLine)
    {
        // Reformat frequency datas and map them into new columns that were not in the input file.

        global $_LINE_AGGREGATED;

        // FREQUENCIES
        // Make all bases uppercase.
        $sRef = strtoupper($aLine['REF']);
        $sAlt = strtoupper($aLine['ALT']);

        // 'Eat' letters from either end - first left, then right - to isolate the difference.
        while (strlen($sRef) > 0 && strlen($sAlt) > 0 && $sRef{0} == $sAlt{0}) {
            $sRef = substr($sRef, 1);
            $sAlt = substr($sAlt, 1);
        }

        while (strlen($sRef) > 0 && strlen($sAlt) > 0 && $sRef[strlen($sRef) - 1] == $sAlt[strlen($sAlt) - 1]) {
            $sRef = substr($sRef, 0, -1);
            $sAlt = substr($sAlt, 0, -1);
        }

        // Insertions/duplications, deletions, inversions, indels.
        // We do not want to display the frequencies for these, set frequency columns to empty.
        if (strlen($sRef) != 1 || strlen($sAlt) != 1) {
            $sAlt = '';
        }

        // Set frequency columns array, this is using the column names from the file before they are mapped to LOVD columns names.
        $aFreqColumns = array(
            'GMAF',
            'AFR_MAF',
            'AMR_MAF',
            'EUR_MAF',
            'EA_MAF',
            'AA_MAF',
            'ASN_MAF'

        );

        // Array of frequency columns used for variant priority calculation. The maximum frequency of all these columns is used.
        $aFreqCalcColumns = array(
            'GMAF',
            'EA_MAF',
            'ExAC_MAF'
        );

        $aFreqCalcValues = array();

        foreach($aFreqColumns as $sFreqColumn) {

            if ($aLine[$sFreqColumn] == 'unknown' || $aLine[$sFreqColumn] == '' || $sAlt == '' || empty($sAlt) || strlen($sAlt) == 0) {
                $aLine[$sFreqColumn] = '';

            } else {
                $aFreqArr = explode("&", $aLine[$sFreqColumn]);
                $aFreqValArray = array();


                foreach ($aFreqArr as $freqData) {

                    if (preg_match('/^(\D+)\:(.+)$/', $freqData, $freqCalls)) {
                        $sFreqPrefix = $freqCalls[1];

                        if ($sFreqPrefix == $sAlt && is_numeric($freqCalls[2])){
                            array_push($aFreqValArray, $freqCalls[2]);
                        }

                    }
                }
                // Check there are values in the array before taking max.
                $sFreqCheck = array_filter($aFreqValArray);

                if (!empty($sFreqCheck)){
                    $aLine[$sFreqColumn] = max($aFreqValArray);
                } else {
                    $aLine[$sFreqColumn] = '';
                }
            }

            // If column is required for calculating variant priority then add to array.
            if(in_array($sFreqColumn,$aFreqCalcColumns)){
                array_push($aFreqCalcValues,$aLine[$sFreqColumn]);
            }
        }



        // Get maximum frequency.
        $sMaxFreq = max($aFreqCalcValues);

        $_LINE_AGGREGATED['MaxFreq'] = $sMaxFreq;

        return $aLine;
    }





    function prepareAlleleDepthColumns($aLine)
    {
        // Reformat columns related to allele depth and map them into new columns that were not in the input file.

        $aADColumns = array(
            'normal:AD' => 'Normal_Depth_',
            'tumour:AD' => 'Tumour_Depth_',

            'SL_N_AD_INDELOCATOR' => 'Normal_Seqliner_Indel_Depth_',
            'SL_N_AD_MUTECT' => 'Normal_Seqliner_Mutect_Depth_',
            'SL_N_AD_VARSCAN' => 'Normal_Seqliner_Varscan_Depth_',

            'SL_T_AD_INDELOCATOR' => 'Tumour_Seqliner_Indel_Depth_',
            'SL_T_AD_MUTECT' => 'Tumour_Seqliner_Mutect_Depth_',
            'SL_T_AD_VARSCAN' => 'Tumour_Seqliner_Varscan_Depth_',
        );

        foreach ($aADColumns as $sVepCol => $sNewCol) {
            if (!isset($aLine[$sVepCol])) {
                continue;
            }

            $aValues = explode(',', $aLine[$sVepCol]);
            if (count($aValues) < 2) {
                continue;
            }

            list($sRef, $sAlt) = $aValues;
            $aLine[$sNewCol . 'Ref'] = $sRef;
            $aLine[$sNewCol . 'Alt'] = $sAlt;
        }

        return $aLine;
    }





    function prepareGeneAliases()
    {
        // Prepare the $aGeneAliases array with a site specific gene alias list.
        // The convert and merge script will provide suggested gene alias key value pairs to add to this array.
        $aGeneAliases = array();
        return $aGeneAliases;
    }






    function prepareGenesToIgnore()
    {
        // Prepare the $aGenesToIgnore array with a site specific gene list.
        $aGenesToIgnore = array();
        return $aGenesToIgnore;
    }





    function ignoreTranscript($sTranscriptId)
    {
        // Check if we want to skip importing the annotation for this transcript.

        if (parent::ignoreTranscript($sTranscriptId)) {
            return true;
        }

        // If transcript is NOT ignored by parent adapter, then we check further here.
        // Prepare the transcript ID prefixes that we want to ignore.
        $aTranscriptsPrefixToIgnore = array(
            'NR_'
        );

        foreach ($aTranscriptsPrefixToIgnore as $sPrefix) {
            if (strpos($sTranscriptId, $sPrefix) === 0) {
                return true;
            }
        }

        return false;
    }





    function prepareScreeningID($aMetaData)
    {
        // Returns the screening ID.

        return 1;
    }





    function getInputFilePrefixPattern()
    {
        // Returns the regex pattern of the prefix of variant input file names.

        return '(.+)';
    }






    function getRequiredHeaderColumns()
    {
        // Returns an array of required input variant file column headers. The order of these columns does NOT matter.

        return array(
            'CHROM',
            'POS',
            'ID',
            'REF',
            'ALT',
            'QUAL',
            'FILTER'
        );
    }
}