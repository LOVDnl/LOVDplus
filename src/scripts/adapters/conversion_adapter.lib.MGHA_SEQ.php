<?php

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

function lovd_prepareMappings() {

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

        // made up columns
        'allele' => 'allele',
    );

    return $aColumnMappings;
}

function lovd_prepareVariantData($aLine) {
    global $_LINE_AGGREGATED;
    $_LINE_AGGREGATED = array();

    $aLine['CHROM'] = 'chr' . $aLine['CHROM'];
    $aLine = lovd_prepareFrequencyColumns($aLine);

    return $aLine;
}

function lovd_prepareFrequencyColumns($aLine) {
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

function lovd_prepareGeneAliases() {
    // Prepare the $aGeneAliases array with a site specific gene alias list.
    // The convert and merge script will provide suggested gene alias key value pairs to add to this array.
    $aGeneAliases = array();
    return $aGeneAliases;
}

function lovd_prepareGenesToIgnore() {
    // Prepare the $aGenesToIgnore array with a site specific gene list.
    $aGenesToIgnore = array();
    return $aGenesToIgnore;
}

function lovd_prepareScreeningID($aMetaData) {
    return 1;
}

function lovd_getInputFilePrefixPattern() {
    return '(.+)';
}

function lovd_getRequiredHeaderColumns() {
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

function lovd_prepareHeaders($aHeaders, $options = array()) {
    extract($options);
    return $aHeaders;
}

function lovd_formatEmptyColumn($aLine, $sLOVDColumn, $aVariant) {
    $aVariant[$sLOVDColumn] = '';
    return $aVariant;
}

function lovd_postValueAssignmentUpdate($sKey, $aVariant, $aData) {
    return $aData;
}
