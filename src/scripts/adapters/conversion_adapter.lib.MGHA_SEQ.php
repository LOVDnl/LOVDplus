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

class LOVD_MghaSeqDataConverter extends LOVD_DefaultDataConverter {

    static $sAdapterName = 'MGHA_SEQ';

    function lovd_prepareMappings()
    {

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
            'DB' => 'VariantOnGenome/Sequencing/dbSNP_Membership',
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

            'VT' => 'VariantOnGenome/Sequencing/Variant_Type',

            // Normal
            'Dels' => 'VariantOnGenome/Sequencing/Dels',
            'HaplotypeScore' => 'VariantOnGenome/Sequencing/HaplotypeScore',
            'MQ0' => 'VariantOnGenome/Sequencing/Total_Mapping_Quality_0_Reads',
            'RPA' => 'VariantOnGenome/Sequencing/Tandem/Repeat_Num',
            'RU' => 'VariantOnGenome/Sequencing/Tandem/Repeat_Unit',
            'STR' => 'VariantOnGenome/Sequencing/Tandem/Repeat_Short',
            'normal:AD' => 'VariantOnGenome/Sequencing/Normal/Depth',
            'normal:DP' => 'VariantOnGenome/Sequencing/Normal/Depth/Total',
            'normal:GQ' => 'VariantOnGenome/Sequencing/Normal/Genotype/Quality',
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
            'HIGH_INF_POS' => 'VariantOnGenome/Sequencing/High_Information_Position',
            'MOTIF_NAME' => 'VariantOnTranscript/TFBP/Name',
            'MOTIF_POS' => 'VariantOnTranscript/TFBP/Position',
            'MOTIF_SCORE_CHANGE' => 'VariantOnTranscript/TFBP/Motif_Score_Change',

            // Tumour
            'tumour:AD' => 'VariantOnGenome/Sequencing/Tumour/Depth',
            'tumour:DP' => 'VariantOnGenome/Sequencing/Tumour/Depth/Total',
            'tumour:GQ' => 'VariantOnGenome/Sequencing/Tumour/Genotype/Quality',
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
            'N_AC' => 'VariantOnGenome/Sequencing/Normal/Indel/Reads',
            'N_DP' => 'VariantOnGenome/Sequencing/Normal/Total_Coverage',
            'N_MM' => 'VariantOnGenome/Sequencing/Normal/Indel/Mismatches/Average',
            'N_MQ' => 'VariantOnGenome/Sequencing/Normal/Indel/Mapping_Quality',
            'N_NQSBQ' => 'VariantOnGenome/Sequencing/Normal/Indel/Average_Quality',
            'N_NQSMM' => 'VariantOnGenome/Sequencing/Normal/Indel/Mismatches/Fraction',
            'N_SC' => 'VariantOnGenome/Sequencing/Normal/Indel/Strandness',
            'SL_N_AD_INDELOCATOR' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Allele/Indel',
            'SL_N_AD_MUTECT' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Allele/Mutect',
            'SL_N_AD_VARSCAN' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Allele/Varscan',
            'SL_N_DP_INDELOCATOR' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Coverage/Indelocator',
            'SL_N_DP_MUTECT' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Coverage/Mutect',
            'SL_N_DP_VARSCAN' => 'VariantOnGenome/Sequencing/Normal/Seqliner/Coverage/Varscan',
            'SL_T_AD_INDELOCATOR' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Allele/Indel',
            'SL_T_AD_MUTECT' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Allele/Mutect',
            'SL_T_AD_VARSCAN' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Allele/Varscan',
            'SL_T_DP_INDELOCATOR' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Coverage/Indelocator',
            'SL_T_DP_MUTECT' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Coverage/Mutect',
            'SL_T_DP_VARSCAN' => 'VariantOnGenome/Sequencing/Tumour/Seqliner/Coverage/Varscan',
            'SPV' => 'VariantOnGenome/Sequencing/Fisher/Somatic',
            'SS' => 'VariantOnGenome/Sequencing/Somatic/Status',
            'SSC' => 'VariantOnGenome/Sequencing/Somatic/Score',
            'T_AC' => 'VariantOnGenome/Sequencing/Tumour/Indel/Reads',
            'T_DP' => 'VariantOnGenome/Sequencing/Tumour/Total_Coverage',
            'T_MM' => 'VariantOnGenome/Sequencing/Tumour/Indel/Mismatches/Average',
            'T_MQ' => 'VariantOnGenome/Sequencing/Tumour/Indel/Mapping_Quality',
            'T_NQSBQ' => 'VariantOnGenome/Sequencing/Tumour/Indel/Average_Quality',
            'T_NQSMM' => 'VariantOnGenome/Sequencing/Tumour/Indel/Mismatches/Fraction',
            'T_SC' => 'VariantOnGenome/Sequencing/Tumour/Indel/Strandness',
            'normal:BQ' => 'VariantOnGenome/Sequencing/Normal/Allele/Average_Quality',
            'normal:DP4' => 'VariantOnGenome/Sequencing/Normal/Strand/Count',
            'normal:FA' => 'VariantOnGenome/Sequencing/Normal/Allele/Fraction',
            'normal:FREQ' => 'VariantOnGenome/Sequencing/Normal/Allele/Frequency',
            'normal:RD' => 'VariantOnGenome/Sequencing/Normal/Depth/Ref',
            'normal:SS' => 'VariantOnGenome/Sequencing/Normal/Somatic_Status',
            'tumour:BQ' => 'VariantOnGenome/Sequencing/Tumour/Allele/Average_Quality',
            'tumour:DP4' => 'VariantOnGenome/Sequencing/Tumour/Strand/Count',
            'tumour:FA' => 'VariantOnGenome/Sequencing/Tumour/Allele/Fraction',
            'tumour:FREQ' => 'VariantOnGenome/Sequencing/Tumour/Allele/Frequency',
            'tumour:RD' => 'VariantOnGenome/Sequencing/Tumour/Depth/Ref',
            'tumour:SS' => 'VariantOnGenome/Sequencing/Tumour/Somatic_Status',

            // Columns we add.
            'allele' => 'allele',

        );

        return $aColumnMappings;
    }






    function lovd_prepareVariantData($aLine)
    {
        global $_LINE_AGGREGATED;
        $_LINE_AGGREGATED = array();

        $aLine['CHROM'] = 'chr' . $aLine['CHROM'];
        $aLine = $this->lovd_prepareFrequencyColumns($aLine);

        return $aLine;
    }






    function lovd_prepareFrequencyColumns($aLine)
    {
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






    function lovd_prepareGeneAliases()
    {
        // Prepare the $aGeneAliases array with a site specific gene alias list.
        // The convert and merge script will provide suggested gene alias key value pairs to add to this array.
        $aGeneAliases = array();
        return $aGeneAliases;
    }






    function lovd_prepareGenesToIgnore()
    {
        // Prepare the $aGenesToIgnore array with a site specific gene list.
        $aGenesToIgnore = array();
        return $aGenesToIgnore;
    }






    function lovd_prepareScreeningID($aMetaData)
    {
        return 1;
    }





    function lovd_getInputFilePrefixPattern()
    {
        return '(.+)';
    }






    function lovd_getRequiredHeaderColumns()
    {
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