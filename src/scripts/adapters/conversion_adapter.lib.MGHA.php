<?php

/*******************************************************************************
 * CREATE MAPPINGS AND PROCESS VARIANT FILE FOR MGHA
 * Created: 2016-06-01
 * Programmer: Candice McGregor
 *************/


// updates the $aColumnMapping array with site specific mappings
function lovd_prepareMappings()
{

    $aColumnMappings = array(
        // Mappings for fields used to process other fields but not imported into the database
        'SYMBOL' => 'symbol',
        'REF' => 'ref',
        'ALT' => 'alt',
        'Existing_variation' => 'existingvariation',
        'Feature' => 'transcriptid',
        // VariantOnGenome/DNA - constructed by the lovd_getVariantDescription function later on
        'CHROM' => 'chromosome',
        'POS' => 'position', // lovd_getVariantDescription() needs this.
        'ID' => 'VariantOnGenome/dbSNP',
        'QUAL' => 'VariantOnGenome/Sequencing/Quality',
        'FILTER' => 'VariantOnGenome/Sequencing/Filter',
        'ABHet' => 'VariantOnGenome/Sequencing/Allele/Balance_Het',
        'ABHom' => 'VariantOnGenome/Sequencing/Allele/Balance_Homo',
        'AC' => 'VariantOnGenome/Sequencing/Allele/Count',
        'AF' => 'VariantOnGenome/Sequencing/Allele/Frequency',
        'AN' => 'VariantOnGenome/Sequencing/Allele/Total',
        'BaseQRankSum' => 'VariantOnGenome/Sequencing/Base_Qualities_Score',
        'DB' => 'VariantOnGenome/Sequencing/dbSNP_Membership',
        'DP' => 'VariantOnGenome/Sequencing/Depth/Unfiltered_All',
        'ExcessHet' => 'VariantOnGenome/Sequencing/Excess_Heterozygosity',
        'FS' => 'VariantOnGenome/Sequencing/Fisher_Strand_Bias',
        'GQ_MEAN' => 'VariantOnGenome/Sequencing/Genotype/Quality/Mean',
        'LikelihoodRankSum' => 'VariantOnGenome/Sequencing/Haplotype_Likelihood_Score',
        'MLEAC' => 'VariantOnGenome/Sequencing/Max_Likelihood_Exp_Allele_Count',
        'MLEAF' => 'VariantOnGenome/Sequencing/Max_Likelihood_Exp_Allele_Freq',
        'MQ' => 'VariantOnGenome/Sequencing/Mapping_Quality',
        'MQRankSum' => 'VariantOnGenome/Sequencing/Mapping_Quality_Score',
        'OND' => 'VariantOnGenome/Sequencing/Non_diploid_Ratio',
        'PG' => 'VariantOnGenome/Sequencing/Genotype_Likelihood_Prior',
        'QD' => 'VariantOnGenome/Sequencing/Quality_by_depth',
        'ReadPosRankSum' => 'VariantOnGenome/Sequencing/Read_Position_Bias_Score',
        'SOR' => 'VariantOnGenome/Sequencing/Symmetric_Odds_Ratio',
        'VariantType' => 'VariantOnGenome/Sequencing/Variant_Type',
        'hiConfDeNovo' => 'VariantOnGenome/Sequencing/High_Confidence_DeNovo',
        'loConfDeNovo' => 'VariantOnGenome/Sequencing/Low_Confidence_DeNovo',
        'set' => 'VariantOnGenome/Sequencing/Source_VCF',
        'Allele' => 'VariantOnTranscript/Consequence_Variant_Allele',
        'Consequence' => 'VariantOnTranscript/Consequence_Type',
        'IMPACT' => 'VariantOnTranscript/Consequence_Impact',
        'Gene' => 'VariantOnTranscript/Emsembl_Stable_ID',
        'Feature_type' => 'VariantOnTranscript/Feature_Type',
        'BIOTYPE' => 'VariantOnTranscript/Biotype',
        'EXON' => 'VariantOnTranscript/Exon',
        'INTRON' => 'VariantOnTranscript/Intron',
        'HGVSc' => 'VariantOnTranscript/DNA',
        'HGVSp' => 'VariantOnTranscript/Protein',
        'cDNA_position' => 'VariantOnTranscript/cDNA_Position',
        'CDS_position' => 'VariantOnTranscript/Position',
        'Protein_position' => 'VariantOnTranscript/Protein_Position',
        'Amino_acids' => 'VariantOnTranscript/Amino_Acids',
        'Codons' => 'VariantOnTranscript/Alternative_Codons',
        'STRAND' => 'VariantOnTranscript/DNA_Strand',
        'CANONICAL' => 'VariantOnTranscript/Canonical_Transcript',
        'ENSP' => 'VariantOnTranscript/Embsembl_Protein_Identifier',
        'HGVS_OFFSET' => 'VariantOnTranscript/HGVS_Offset',
        'GMAF' => 'VariantOnGenome/Frequency/1000G/VEP',
        'AFR_MAF' => 'VariantOnGenome/Frequency/1000G/VEP/African',
        'AMR_MAF' => 'VariantOnGenome/Frequency/1000G/VEP/American',
        'EAS_MAF' => 'VariantOnGenome/Frequency/1000G/VEP/East_Asian',
        'EUR_MAF' => 'VariantOnGenome/Frequency/1000G/VEP/European',
        'SAS_MAF' => 'VariantOnGenome/Frequency/1000G/VEP/South_Asian',
        'AA_MAF' => 'VariantOnGenome/Frequency/EVS/VEP/African_American',
        'ExAC_Adj_MAF' => 'VariantOnGenome/Frequency/ExAC/Adjusted',
        'ExAC_AFR_MAF' => 'VariantOnGenome/Frequency/ExAC/African_American',
        'ExAC_AMR_MAF' => 'VariantOnGenome/Frequency/ExAC/American',
        'ExAC_EAS_MAF' => 'VariantOnGenome/Frequency/ExAC/East_Asian',
        'ExAC_FIN_MAF' => 'VariantOnGenome/Frequency/ExAC/Finnish',
        'ExAC_NFE_MAF' => 'VariantOnGenome/Frequency/ExAC/Non_Finnish',
        'ExAC_OTH_MAF' => 'VariantOnGenome/Frequency/ExAC/Other',
        'ExAC_SAS_MAF' => 'VariantOnGenome/Frequency/ExAC/South_Asian',
        'CLIN_SIG' => 'VariantOnTranscript/Clinical_Significance',
        'SOMATIC' => 'VariantOnTranscript/Somatic_Status',
        'PHENO' => 'VariantOnTranscript/Phenotype',
        'PUBMED' => 'VariantOnTranscript/Pubmed',
        'Condel' => 'VariantOnTranscript/Prediction/Condel_Score',
        '1000Gp1_AC' => 'VariantOnGenome/Frequency/1000G/dbNSFP/Allele_Count',
        '1000Gp1_AF' => 'VariantOnGenome/Frequency/1000G/dbNSFP',
        '1000Gp1_AFR_AC' => 'VariantOnGenome/Frequency/1000G/dbNSFP/African/Allele_Count',
        '1000Gp1_AFR_AF' => 'VariantOnGenome/Frequency/1000G/dbNSFP/African',
        '1000Gp1_AMR_AC' => 'VariantOnGenome/Frequency/1000G/dbNSFP/American/Allele_Count',
        '1000Gp1_AMR_AF' => 'VariantOnGenome/Frequency/1000G/dbNSFP/American',
        '1000Gp1_ASN_AC' => 'VariantOnGenome/Frequency/1000G/dbNSFP/Asian/Allele_Count',
        '1000Gp1_ASN_AF' => 'VariantOnGenome/Frequency/1000G/dbNSFP/Asian',
        '1000Gp1_EUR_AC' => 'VariantOnGenome/Frequency/1000G/dbNSFP/European/Allele_Count',
        '1000Gp1_EUR_AF' => 'VariantOnGenome/Frequency/1000G/dbNSFP/European',
        'CADD_phred' => 'VariantOnTranscript/Prediction/CADD_Phredlike',
        'CADD_raw' => 'VariantOnTranscript/Prediction/CADD_Raw',
        'CADD_raw_rankscore' => 'VariantOnTranscript/Prediction/CADD_Raw_Ranked',
        'ESP6500_AA_AF' => 'VariantOnGenome/Frequency/ESP6500/American',
        'ESP6500_EA_AF' => 'VariantOnGenome/Frequency/ESP6500/European_American',
        'FATHMM_pred' => 'VariantOnTranscript/Prediction/FATHMM',
        'FATHMM_rankscore' => 'VariantOnTranscript/Prediction/FATHMM_Ranked_Score',
        'FATHMM_score' => 'VariantOnTranscript/Prediction/FATHMM_Score',
        'GERP++_NR' => 'VariantOnTranscript/Prediction/GERP_Neutral_Rate',
        'GERP++_RS' => 'VariantOnTranscript/Prediction/GERP_Score',
        'GERP++_RS_rankscore' => 'VariantOnTranscript/Prediction/GERP_Ranked_Score',
        'LRT_Omega' => 'VariantOnTranscript/Prediction/LRT_Omega',
        'LRT_converted_rankscore' => 'VariantOnTranscript/Prediction/LRT_Ranked_Score',
        'LRT_pred' => 'VariantOnTranscript/Prediction/LRT',
        'LRT_score' => 'VariantOnTranscript/Prediction/LRT_Score',
        'MetaLR_pred' => 'VariantOnTranscript/Prediction/MetaLR',
        'MetaLR_rankscore' => 'VariantOnTranscript/Prediction/MetaLR_Ranked_Score',
        'MetaLR_score' => 'VariantOnTranscript/Prediction/MetaLR_Score',
        'MetaSVM_pred' => 'VariantOnTranscript/Prediction/MetaSVM',
        'MetaSVM_rankscore' => 'VariantOnTranscript/Prediction/MetaSVM_Ranked_Score',
        'MetaSVM_score' => 'VariantOnTranscript/Prediction/MetaSVM_Score',
        'MutationAssessor_pred' => 'VariantOnTranscript/Prediction/MutationAssessor',
        'MutationAssessor_rankscore' => 'VariantOnTranscript/Prediction/MutationAssessor_Ranked_Score',
        'MutationAssessor_score' => 'VariantOnTranscript/Prediction/MutationAssessor_Score',
        'MutationTaster_converted_rankscore' => 'VariantOnTranscript/Prediction/MutationTaster_Ranked_Score',
        'MutationTaster_pred' => 'VariantOnTranscript/Prediction/MutationTaster',
        'MutationTaster_score' => 'VariantOnTranscript/Prediction/MutationTaster_Score',
        'PROVEAN_converted_rankscore' => 'VariantOnTranscript/Prediction/PROVEAN_Ranked_Score',
        'PROVEAN_pred' => 'VariantOnTranscript/Prediction/PROVEAN',
        'PROVEAN_score' => 'VariantOnTranscript/Prediction/PROVEAN_Score',
        'Polyphen2_HDIV_pred' => 'VariantOnTranscript/Prediction/Polyphen2_HDIV',
        'Polyphen2_HDIV_rankscore' => 'VariantOnTranscript/Prediction/Polyphen2_HDIV_Ranked_Score',
        'Polyphen2_HDIV_score' => 'VariantOnTranscript/Prediction/Polyphen2_HDIV_Score',
        'Polyphen2_HVAR_pred' => 'VariantOnTranscript/Prediction/Polyphen2_HVAR',
        'Polyphen2_HVAR_rankscore' => 'VariantOnTranscript/Prediction/Polyphen2_HVAR_Ranked_Score',
        'Polyphen2_HVAR_score' => 'VariantOnTranscript/Prediction/Polyphen2_HVAR_Score',
        'Reliability_index' => 'VariantOnTranscript/Prediction/MetaSVM_MetaLR_Reliability_Index',
        'SIFT_pred' => 'VariantOnTranscript/Prediction/SIFT_dbNSFP',
        'SiPhy_29way_logOdds' => 'VariantOnTranscript/Prediction/SiPhy29way_Score',
        'SiPhy_29way_logOdds_rankscore' => 'VariantOnTranscript/Prediction/SiPhy29way_Ranked_Score',
        'SiPhy_29way_pi' => 'VariantOnTranscript/Prediction/SiPhy29way_Distribution',
        'UniSNP_ids' => 'VariantOnTranscript/UniSNP_IDs',
        'VEST3_rankscore' => 'VariantOnTranscript/Prediction/VEST3_Ranked_Score',
        'VEST3_score' => 'VariantOnTranscript/Prediction/VEST3_Score',
        'phastCons100way_vertebrate' => 'VariantOnTranscript/Prediction/phastCons100way_Vert_Score',
        'phastCons100way_vertebrate_rankscore' => 'VariantOnTranscript/Prediction/phastCons100way_Vert_Ranked_Score',
        'phastCons46way_placental' => 'VariantOnTranscript/Prediction/phastCons46way_Plac_Score',
        'phastCons46way_placental_rankscore' => 'VariantOnTranscript/Prediction/phastCons46way_Plac_Ranked_Score',
        'phastCons46way_primate' => 'VariantOnTranscript/Prediction/phastCons46way_Prim_Score',
        'phastCons46way_primate_rankscore' => 'VariantOnTranscript/Prediction/phastCons46way_Prim_Ranked_Score',
        'phyloP100way_vertebrate' => 'VariantOnTranscript/Prediction/phyloP100way_Vert_Score',
        'phyloP100way_vertebrate_rankscore' => 'VariantOnTranscript/Prediction/phyloP100way_Vert_Ranked_Score',
        'phyloP46way_placental' => 'VariantOnTranscript/Prediction/phyloP46way_Plac_Score',
        'phyloP46way_placental_rankscore' => 'VariantOnTranscript/Prediction/phyloP46way_Plac_Ranked_Score',
        'phyloP46way_primate' => 'VariantOnTranscript/Prediction/phyloP46way_Prim_Score',
        'phyloP46way_primate_rankscore' => 'VariantOnTranscript/Prediction/phyloP46way_Prim_Ranked_Score',
        'Grantham' => 'VariantOnTranscript/Prediction/Grantham',
        'CPIPE_BED' => 'VariantOnTranscript/Pipeline_V6_bed_file',
        // child/singleton fields
        'Child_DP' => 'VariantOnGenome/Sequencing/Depth/Total',
        'Child_GQ' => 'VariantOnGenome/Sequencing/Genotype/Quality',
        'Child_GT' => 'allele', // this is in the form of A/A, A/T etc. This is converted to 0/0, 1/0 later on
        'Child_JL' => 'VariantOnGenome/Sequencing/Phredscaled_Joint_Likelihood',
        'Child_JP' => 'VariantOnGenome/Sequencing/Phredscaled_Joint_Probability',
        'Child_PID' => 'VariantOnGenome/Sequencing/Physical_Phasing_ID',
        'Child_PL' => 'VariantOnGenome/Sequencing/Phredscaled_Likelihoods',
        'Child_PP' => 'VariantOnGenome/Sequencing/Phredscaled_Probabilities',
        // father fields
        'Father_DP' => 'VariantOnGenome/Sequencing/Father/Depth/Total',// we actually do not receive a value for depth in this column, we need to calculate this using AD & PL
        'Father_GQ' => 'VariantOnGenome/Sequencing/Father/Genotype/Quality',
        'Father_GT' => 'VariantOnGenome/Sequencing/Father/GenoType',
        'Father_JL' => 'VariantOnGenome/Sequencing/Father/Phredscaled_Joint_Likelihood',
        'Father_JP' => 'VariantOnGenome/Sequencing/Father/Phredscaled_Joint_Probability',
        'Father_PID' => 'VariantOnGenome/Sequencing/Father/Physical_Phasing_ID',// used to calculate the allele value
        'Father_PL' => 'VariantOnGenome/Sequencing/Father/Phredscaled_Likelihoods',// used to calculate the allele value
        'Father_PP' => 'VariantOnGenome/Sequencing/Father/Phredscaled_Probabilities',// used to calculate the allele value
        // mother fields
        'Mother_DP' => 'VariantOnGenome/Sequencing/Mother/Depth/Total',// we actually do not receive a value for depth in this column, we need to calculate this using AD & PL
        'Mother_GQ' => 'VariantOnGenome/Sequencing/Mother/Genotype/Quality',
        'Mother_GT' => 'VariantOnGenome/Sequencing/Mother/GenoType',
        'Mother_JL' => 'VariantOnGenome/Sequencing/Mother/Phredscaled_Joint_Likelihood',
        'Mother_JP' => 'VariantOnGenome/Sequencing/Mother/Phredscaled_Joint_Probability',
        'Mother_PID' => 'VariantOnGenome/Sequencing/Mother/Physical_Phasing_ID',// used to calculate the allele value
        'Mother_PL' => 'VariantOnGenome/Sequencing/Mother/Phredscaled_Likelihoods',// used to calculate the allele value
        'Mother_PP' => 'VariantOnGenome/Sequencing/Mother/Phredscaled_Probabilities',// used to calculate the allele value

        // columns that are created when processing data in lovd_prepareVariantData function
        'Father_Depth_Ref' => 'VariantOnGenome/Sequencing/Father/Depth/Ref', // derived from Father_AD
        'Father_Depth_Alt' => 'VariantOnGenome/Sequencing/Father/Depth/Alt', // derived from Father_AD
        'Father_Alt_Percentage' => 'VariantOnGenome/Sequencing/Father/Depth/Alt/Fraction', // derived from Father_AD
        'Father_VarPresent' => 'VariantOnGenome/Sequencing/Father/VarPresent',
        'Mother_Depth_Ref' => 'VariantOnGenome/Sequencing/Mother/Depth/Ref', // derived from Mother_AD
        'Mother_Depth_Alt' => 'VariantOnGenome/Sequencing/Mother/Depth/Alt', // derived from Mother_AD
        'Mother_Alt_Percentage' => 'VariantOnGenome/Sequencing/Mother/Depth/Alt/Fraction', // derived from Mother_AD
        'Mother_VarPresent' => 'VariantOnGenome/Sequencing/Mother/VarPresent',
        'PolyPhen_Text' => 'VariantOnTranscript/Prediction/PolyPhen_VEP',
        'PolyPhen_Value' => 'VariantOnTranscript/Prediction/PolyPhen_Score_VEP',
        'SIFT_Text' => 'VariantOnTranscript/Prediction/SIFT_VEP',
        'SIFT_Value' => 'VariantOnTranscript/Prediction/SIFT_Score_VEP',
        '1000G' => 'VariantOnGenome/Frequency/1000G/VEP',
        'EVS' => 'VariantOnGenome/Frequency/EVS/VEP/European_American',
        'ExAC' => 'VariantOnGenome/Frequency/ExAC',
        'Variant_Priority' => 'VariantOnGenome/Variant_priority'


    );

    return $aColumnMappings;
}

// processes the variant data file for MGHA
// cleans up data in existing columns and splits some columns out to two columns
function lovd_prepareVariantData($aLine)
{

    // For MGHA the allele column is in the format A/A, C/T etc. Leiden have converted this to 1/1, 0/1, etc.
    // MGHA also need to calculate the VarPresent for Father and Mother as this is required later on when assigning a value to allele
    $childGenotypes = explode('/', $aLine['Child_GT']);

    if ($aLine['Child_GT'] == './.') {
        // we set it to '' as this is what Leiden do
        $aLine['Child_GT'] = '';
    } elseif ($childGenotypes[0] !== $childGenotypes[1]) {
        //het
        $aLine['Child_GT'] = '0/1';
    } elseif ($childGenotypes[0] == $childGenotypes[1] && $childGenotypes[0] == $aLine['ALT']) {
        // homo alt
        $aLine['Child_GT'] = '1/1';
    } elseif ($childGenotypes[0] == $childGenotypes[1] && $childGenotypes[0] == $aLine['REF']) {
        // homo ref
        $aLine['Child_GT'] = '0/0';
    }

    // check whether the mother or father's genotype is present. If so we are dealing with a trio and we need to calculate the following
    if ($aLine['Mother_GT'] | $aLine['Father_GT']) {
        for ($parentCount = 1; $parentCount <= 2; $parentCount++) {
            if ($parentCount == 1) {
                $parent = 'Father';
            } else {
                $parent = 'Mother';
            }
            // get the genotypes for the parents and compare them to each other
            // data is separated by a / or a |
            if (strpos($aLine[$parent . '_GT'], '|') !== false) {
                $parentGenotypes = explode('|', $aLine[$parent . '_GT']);
            } elseif (strpos($aLine[$parent . '_GT'], '/') !== false) {
                $parentGenotypes = explode('/', $aLine[$parent . '_GT']);
            } else {
                print('Unexpected delimiter in ' . $parent . '_GT column. Current time: ' . date('Y-m-d H:i:s') . ".\n");
                // DIE ??
            }

            if ($parentGenotypes[0] == $parentGenotypes[1] && $parentGenotypes[0] == $aLine['ALT']) {
                // homo alt
                $aLine[$parent . '_GT'] = '1/1';
                $aLine[$parent . '_VarPresent'] = 6;
            } elseif ($parentGenotypes[0] !== $parentGenotypes[1]) {
                // het
                $aLine[$parent . '_GT'] = '0/1';
                $aLine[$parent . '_VarPresent'] = 6;
            } else {
                if ($parentGenotypes[0] == $parentGenotypes[1] && $parentGenotypes[0] == $aLine['REF']) {
                    // homo ref
                    $aLine[$parent . '_GT'] = '0/0';
                }
                if ($aLine[$parent . '_GT'] = './.') {
                    // we set it to '' as this is what Leiden do.
                    $aLine[$parent . '_GT'] = '';
                }

                // calculate the VarPresent for the mother and the father using the allelic depths (Parent_AD) and Phred-scaled Likelihoods (Parent_PL)
                // Parent_AD(x,y)   Parent_PL(a,b,c)
                // calculate the alt depth as fraction (/100)
                $parentAllelicDepths = explode(',', $aLine[$parent . '_AD']);

                // set the ref and alt values in $aLine
                $aLine[$parent . '_Depth_Ref'] = $parentAllelicDepths[0];
                $aLine[$parent . '_Depth_Alt'] = $parentAllelicDepths[1];

                if ($parentAllelicDepths[1] == 0) {
                    $parentAltPercentage = 0;
                    $aLine[$parent . '_Depth_Alt_Frac'] = 0;
                } else {
                    // alt percentage = Parent_AD(y) / (Parent.AD(x) + Parent.AD(y))
                    $aLine[$parent . '_Depth_Alt_Frac'] = $parentAllelicDepths[1]/100;
                    $parentAltPercentage = $parentAllelicDepths[1] / ($parentAllelicDepths[0] + $parentAllelicDepths[1]);
                }

                // set the alt percentage in $aLine
                $aLine[$parent . '_Alt_Percentage'] = $parentAltPercentage;

                if ($aLine[$parent . '_PL'] == '' | $aLine[$parent . '_PL'] == 'unknown') {
                    $parentPLAlt = 'unknown';
                } else {

                    $parentPL = explode(',', $aLine[$parent . '_PL']);
                    // parent PLAlt = Parent_PL(b)
                    $parentPLAlt = $parentPL[1];
                }

                if ($parentAltPercentage > 10) {
                    $aLine[$parent . '_VarPresent'] = 5;
                } elseif ($parentAltPercentage > 0 && $parentAltPercentage <= 10) {
                    $aLine[$parent . '_VarPresent'] = 4;
                } elseif ($parentPLAlt < 30 || $parentPLAlt == 'unknown') {
                    $aLine[$parent . '_VarPresent'] = 3;
                } elseif ($parentPLAlt >= 30 && $parentPLAlt < 60) {
                    $aLine[$parent . '_VarPresent'] = 2;
                } else {
                    $aLine[$parent . '_VarPresent'] = 1;
                }
            }
        }
    }

    //split up PolyPhen to extract text and value
    if (preg_match('/(\D+)\((.+)\)/',$aLine['PolyPhen'],$pRegs)){
        $aLine['PolyPhen_Text'] = $pRegs[1];
        $aLine['PolyPhen_Value'] = $pRegs[2];
    }

    //split up SIFT to extract text and value
    if (preg_match('/(\D+)\((.+)\)/',$aLine['SIFT'],$sRegs)){
        $aLine['SIFT_Text'] = $sRegs[1];
        $aLine['SIFT_Value'] = $sRegs[2];
    }

    //process EVS (EA_MAF) multiple values can be present, take the highest frequency
    $EVSArr = explode("&",$aLine['EA_MAF']);
    $EVSValArray = array();
    foreach($EVSArr as $EVS_Data){
        if (preg_match('/^\D+:(.+)$/',$EVS_Data,$EVS_Freq)) {
            $EVS = $EVS_Freq[1];
        }
        if(is_numeric($EVS)){
            array_push($EVSValArray,$EVS);
        }
    }
    $EVS_Value = max($EVSValArray);
    $aLine['EVS'] = $EVS_Value;

    //process 1000G (GMAF) A:0.4545 - take the frequency only
    if (preg_match('/^\D+:(.+)$/',$aLine['GMAF'],$G1000)) {
        $GMAF = $G1000[1];
        $aLine['1000G'] = $GMAF;
    }

    //process ExAC (ExAC_MAF) multiple values and scientific values. Taken the highest frequency
    $ExACArr = explode("&",$aLine['ExAC_MAF']);
    $ExACValArray = array();
    foreach($ExACArr as $ExAC_Data){
        if (preg_match('/^\D+:(.+)$/',$ExAC_Data,$ExAC_Freq)) {
            $ExAC = $ExAC_Freq[1];
        }
        if(is_numeric($ExAC)){
            array_push($ExACValArray,$ExAC);
        }
    }
    $ExAC_Value = max($ExACValArray);
    $aLine['ExAC'] = $ExAC_Value;

    // get maximum frequency using EA_MAF, GMAF, ExAC_MAF
    $max_Freq = max($GMAF,$ExAC_Value,$EVS_Value);

    //variant priority
    if ($aLine['IMPACT'] == 'HIGH'){
        // check if novel - SNP138 ($aLine['ID']) is = '.' or '' and there is no frequency
        if ($aLine['ID'] == '.' | $aLine == '') {

        } else if($max_Freq < 0.0005){
            $aLine['Variant_Priority'] = 4;
        }
    }


return $aLine;
}


?>