<?php

/*******************************************************************************
 * CREATE MAPPINGS AND PROCESS VARIANT FILE FOR MGHA
 * Created: 2016-06-01
 * Programmer: Candice McGregor
 *************/

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
                'Screening/Father/Sample_ID',
                'Screening/Mother/Sample_ID',
                'Screening/Mean_coverage',
                'Screening/Library_preparation',
                'Screening/Pipeline/Run_ID',
                'variants_found_',
                'analysis_status'

            )
        )
    )
);


function lovd_prepareMappings()
{

    // Updates the $aColumnMapping array with site specific mappings.

    $aColumnMappings = array(
        // Mappings for fields used to process other fields but not imported into the database.
        'SYMBOL' => 'symbol',
        'REF' => 'ref',
        'ALT' => 'alt',
        'Existing_variation' => 'existingvariation',
        'Feature' => 'transcriptid',
        // VariantOnGenome/DNA - constructed by the lovd_getVariantDescription function later on.
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
        'EA_MAF' => 'VariantOnGenome/Frequency/EVS/VEP/European_American',
        'AA_MAF' => 'VariantOnGenome/Frequency/EVS/VEP/African_American',
        'ExAC_MAF' => 'VariantOnGenome/Frequency/ExAC',
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

        // Child/Singleton fields.
        'Child_DP' => 'VariantOnGenome/Sequencing/Depth/Total',
        'Child_GQ' => 'VariantOnGenome/Sequencing/Genotype/Quality',
        'Child_GT' => 'allele', // this is in the form of A/A, A/T etc. This is converted to 0/0, 1/0 later on
        'Child_JL' => 'VariantOnGenome/Sequencing/Phredscaled_Joint_Likelihood',
        'Child_JP' => 'VariantOnGenome/Sequencing/Phredscaled_Joint_Probability',
        'Child_PID' => 'VariantOnGenome/Sequencing/Physical_Phasing_ID',
        'Child_PL' => 'VariantOnGenome/Sequencing/Phredscaled_Likelihoods',
        'Child_PP' => 'VariantOnGenome/Sequencing/Phredscaled_Probabilities',

        // Father fields.
        'Father_DP' => 'VariantOnGenome/Sequencing/Father/Depth/Total',// We actually do not receive a value for depth in this column, we need to calculate this using AD & PL.
        'Father_GQ' => 'VariantOnGenome/Sequencing/Father/Genotype/Quality',
        'Father_GT' => 'VariantOnGenome/Sequencing/Father/GenoType',
        'Father_JL' => 'VariantOnGenome/Sequencing/Father/Phredscaled_Joint_Likelihood',
        'Father_JP' => 'VariantOnGenome/Sequencing/Father/Phredscaled_Joint_Probability',
        'Father_PID' => 'VariantOnGenome/Sequencing/Father/Physical_Phasing_ID',
        'Father_PL' => 'VariantOnGenome/Sequencing/Father/Phredscaled_Likelihoods',// Used to calculate the allele value.
        'Father_PP' => 'VariantOnGenome/Sequencing/Father/Phredscaled_Probabilities',

        // Mother fields.
        'Mother_DP' => 'VariantOnGenome/Sequencing/Mother/Depth/Total',// We actually do not receive a value for depth in this column, we need to calculate this using AD & PL.
        'Mother_GQ' => 'VariantOnGenome/Sequencing/Mother/Genotype/Quality',
        'Mother_GT' => 'VariantOnGenome/Sequencing/Mother/GenoType',
        'Mother_JL' => 'VariantOnGenome/Sequencing/Mother/Phredscaled_Joint_Likelihood',
        'Mother_JP' => 'VariantOnGenome/Sequencing/Mother/Phredscaled_Joint_Probability',
        'Mother_PID' => 'VariantOnGenome/Sequencing/Mother/Physical_Phasing_ID',
        'Mother_PL' => 'VariantOnGenome/Sequencing/Mother/Phredscaled_Likelihoods',// Used to calculate the allele value.
        'Mother_PP' => 'VariantOnGenome/Sequencing/Mother/Phredscaled_Probabilities',

        // Columns that are created when processing data in lovd_prepareVariantData function.
        'Father_Depth_Ref' => 'VariantOnGenome/Sequencing/Father/Depth/Ref', // Derived from Father_AD.
        'Father_Depth_Alt' => 'VariantOnGenome/Sequencing/Father/Depth/Alt', // Derived from Father_AD.
        'Father_Alt_Percentage' => 'VariantOnGenome/Sequencing/Father/Depth/Alt/Fraction', // Derived from Father_AD.
        'Father_VarPresent' => 'VariantOnGenome/Sequencing/Father/VarPresent',
        'Mother_Depth_Ref' => 'VariantOnGenome/Sequencing/Mother/Depth/Ref', // Derived from Mother_AD.
        'Mother_Depth_Alt' => 'VariantOnGenome/Sequencing/Mother/Depth/Alt', // Derived from Mother_AD.
        'Mother_Alt_Percentage' => 'VariantOnGenome/Sequencing/Mother/Depth/Alt/Fraction', // Derived from Mother_AD.
        'Mother_VarPresent' => 'VariantOnGenome/Sequencing/Mother/VarPresent',
        'PolyPhen_Text' => 'VariantOnTranscript/Prediction/PolyPhen_VEP',
        'PolyPhen_Value' => 'VariantOnTranscript/Prediction/PolyPhen_Score_VEP',
        'SIFT_Text' => 'VariantOnTranscript/Prediction/SIFT_VEP',
        'SIFT_Value' => 'VariantOnTranscript/Prediction/SIFT_Score_VEP',
        'Variant_Priority' => 'VariantOnGenome/Variant_priority',

        // Extra column for us to store data related to dropped transcripts
        'Variant_Remarks' => 'VariantOnGenome/Remarks'


    );

    return $aColumnMappings;
}





function lovd_prepareVariantData($aLine, $options)
{
    // Processes the variant data file for MGHA.
    // Cleans up data in existing columns and splits some columns out to two columns.

    // expect to see $aGenes, $aTranscripts
    extract($options);
    $aGenes = (!isset($aGenes)? array() : $aGenes);
    $aTranscripts = (!isset($aTranscripts)? array() : $aTranscripts);

    // Move transcripts that are to be dropped into VariantOnGenome/Remarks
    $aLine['Variant_Remarks'] = '';

    // Handle genes that start with 'LOC'
    // Handle genes that are not found in our database
    // Handle transcripts that are not found in our database
    $bDropTranscript = false;
    if (!empty($aLine['SYMBOL']) && strpos(strtolower($aLine['SYMBOL']), 'loc') === 0) {
        $bDropTranscript = true;
    } elseif (!empty($aLine['SYMBOL']) && empty($aGenes[$aLine['SYMBOL']])) {
        $aLine['Variant_Remarks'] = "UNKNOWN GENE\n";
        $bDropTranscript = true;
    } elseif (!empty($aLine['Feature']) && empty($aTranscripts[$aLine['Feature']])) {
        $aLine['Variant_Remarks'] = "UNKNOWN TRANSCRIPT\n";
        $bDropTranscript = true;
    } elseif (!empty($aLine['HGVSc']) && strpos($aLine['HGVSc'], '*-') !== false) {
        $aLine['Variant_Remarks'] = "UNKNOWN TRANSCRIPT\n";
        $bDropTranscript = true;
    }

    if ($bDropTranscript) {
        $aLine['Variant_Remarks'] .= "SYMBOL: " . (!empty($aLine['SYMBOL'])? $aLine['SYMBOL'] : '') . "\n";
        $aLine['Variant_Remarks'] .= "HGVSc: " . (!empty($aLine['HGVSc'])? $aLine['HGVSc'] : '') . "\n";
        $aLine['Variant_Remarks'] .= "HGVSp: " . (!empty($aLine['HGVSp'])? $aLine['HGVSp'] : '') . "\n";
        $aLine['Variant_Remarks'] .= "Consequence: " . (!empty($aLine['Consequence'])? $aLine['Consequence'] : '')  . "\n";
        $aLine['Variant_Remarks'] .= "IMPACT: " . (!empty($aLine['IMPACT'])? $aLine['IMPACT'] : '')  . "\n";

        $aLine['Feature'] = NO_TRANSCRIPT;
    }


    // For MGHA the allele column is in the format A/A, C/T etc. Leiden have converted this to 1/1, 0/1, etc.
    // MGHA also need to calculate the VarPresent for Father and Mother as this is required later on when assigning a value to allele
    $aChildGenotypes = explode('/', $aLine['Child_GT']);

    if ($aLine['Child_GT'] == './.') {
        // We set it to '' as this is what Leiden do.
        $aLine['Child_GT'] = '';

    } elseif ($aChildGenotypes[0] !== $aChildGenotypes[1]) {
        // Het.
        $aLine['Child_GT'] = '0/1';

    } elseif ($aChildGenotypes[0] == $aChildGenotypes[1] && $aChildGenotypes[0] == $aLine['ALT']) {
        // Homo alt.
        $aLine['Child_GT'] = '1/1';

    } elseif ($aChildGenotypes[0] == $aChildGenotypes[1] && $aChildGenotypes[0] == $aLine['REF']) {
        // Homo ref.
        $aLine['Child_GT'] = '0/0';
    }


    if (!empty($aLine['Mother_GT']) || !empty($aLine['Father_GT'])){
        // Check whether the mother or father's genotype is present.
        // If so we are dealing with a trio and we need to calculate the following.

        for ($nParentCount = 1; $nParentCount <= 2; $nParentCount++) {

            if ($nParentCount == 1) {
                $sParent = 'Father';

            } else {
                $sParent = 'Mother';
            }


            // Get the genotypes for the parents and compare them to each other.
            // Data is separated by a / or a |.
            if (strpos($aLine[$sParent . '_GT'], '|') !== false) {
                $aParentGenotypes = explode('|', $aLine[$sParent . '_GT']);

            } elseif (strpos($aLine[$sParent . '_GT'], '/') !== false) {
                $aParentGenotypes = explode('/', $aLine[$sParent . '_GT']);

            } else {
                die('Unexpected delimiter in ' . $sParent . '_GT column. We cannot process the file as values from this column are required to calculate the allele.' . ".\n");
            }



            if ($aParentGenotypes[0] == $aParentGenotypes[1] && $aParentGenotypes[0] == $aLine['ALT']) {
                // Homo alt.
                $aLine[$sParent . '_GT'] = '1/1';
                $aLine[$sParent . '_VarPresent'] = 6;

            } elseif ($aParentGenotypes[0] !== $aParentGenotypes[1]) {
                // Het.
                $aLine[$sParent . '_GT'] = '0/1';
                $aLine[$sParent . '_VarPresent'] = 6;

            } else {


                if ($aParentGenotypes[0] == $aParentGenotypes[1] && $aParentGenotypes[0] == $aLine['REF']) {
                    // Homo ref.
                    $aLine[$sParent . '_GT'] = '0/0';
                }

                if ($aLine[$sParent . '_GT'] = './.') {
                    // We set it to '' as this is what Leiden do.
                    $aLine[$sParent . '_GT'] = '';
                }

                // Calculate the VarPresent for the mother and the father using the allelic depths (Parent_AD) and Phred-scaled Likelihoods (Parent_PL)
                // Parent_AD(x,y)   Parent_PL(a,b,c)
                // Calculate the alt depth as fraction (/100).
                $aParentAllelicDepths = explode(',', $aLine[$sParent . '_AD']);

                // Set the ref and alt values in $aLine.
                $aLine[$sParent . '_Depth_Ref'] = $aParentAllelicDepths[0];
                $aLine[$sParent . '_Depth_Alt'] = $aParentAllelicDepths[1];


                if ($aParentAllelicDepths[1] == 0) {
                    $sParentAltPercentage = 0;
                    $aLine[$sParent . '_Depth_Alt_Frac'] = 0;

                } else {
                    // alt percentage = Parent_AD(y) / (Parent.AD(x) + Parent.AD(y))
                    $aLine[$sParent . '_Depth_Alt_Frac'] = $aParentAllelicDepths[1]/100;
                    $sParentAltPercentage = $aParentAllelicDepths[1] / ($aParentAllelicDepths[0] + $aParentAllelicDepths[1]);
                }

                // Set the alt percentage in $aLine.
                $aLine[$sParent . '_Alt_Percentage'] = $sParentAltPercentage;

                if ($aLine[$sParent . '_PL'] == '' || $aLine[$sParent . '_PL'] == 'unknown') {
                    $sParentPLAlt = 'unknown';

                } else {
                    $aParentPL = explode(',', $aLine[$sParent . '_PL']);
                    $sParentPLAlt = $aParentPL[1]; // Parent PLAlt = Parent_PL(b)
                }



                if ($sParentAltPercentage > 10) {
                    $aLine[$sParent . '_VarPresent'] = 5;

                } elseif ($sParentAltPercentage > 0 && $sParentAltPercentage <= 10) {
                    $aLine[$sParent . '_VarPresent'] = 4;

                } elseif ($sParentPLAlt < 30 || $sParentPLAlt == 'unknown') {
                    $aLine[$sParent . '_VarPresent'] = 3;

                } elseif ($sParentPLAlt >= 30 && $sParentPLAlt < 60) {
                    $aLine[$sParent . '_VarPresent'] = 2;

                } else {
                    $aLine[$sParent . '_VarPresent'] = 1;
                }


            }
        }
    }


    // Split up PolyPhen to extract text and value.
    if (preg_match('/(\D+)\((.+)\)/',$aLine['PolyPhen'],$aPoly)){
        $aLine['PolyPhen_Text'] = $aPoly[1];
        $aLine['PolyPhen_Value'] = $aPoly[2];
    }


    // Split up SIFT to extract text and value.
    if (preg_match('/(\D+)\((.+)\)/',$aLine['SIFT'],$aSIFT)){
        $aLine['SIFT_Text'] = $aSIFT[1];
        $aLine['SIFT_Value'] = $aSIFT[2];
    }


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
        'EAS_MAF',
        'EUR_MAF',
        'SAS_MAF',
        'EA_MAF',
        'AA_MAF',
        'ExAC_MAF',
        'ExAC_Adj_MAF',
        'ExAC_AFR_MAF',
        'ExAC_AMR_MAF',
        'ExAC_EAS_MAF',
        'ExAC_FIN_MAF',
        'ExAC_NFE_MAF',
        'ExAC_OTH_MAF',
        'ExAC_SAS_MAF',
        'ESP6500_AA_AF',
        'ESP6500_EA_AF'
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

    // Variant Priority.
    if (!empty($aLine['CPIPE_BED'])) {
        $aLine['Variant_Priority'] = 6;

    } else {
        if ($aLine['IMPACT'] == 'HIGH') {

            if (($aLine['ID'] == '.' || $aLine['ID'] == '') && $sMaxFreq == '') {
                // If novel - SNP138 ($aLine['ID']) is = '.' or '' and there is no frequency.
                $aLine['Variant_Priority'] = 5;

            } elseif ($sMaxFreq <= 0.0005) {
                $aLine['Variant_Priority'] = 4;

            } elseif ($sMaxFreq <= 0.01) {
                $aLine['Variant_Priority'] = 3;

            } else {
                $aLine['Variant_Priority'] = 1;
            }

        } elseif ($aLine['IMPACT'] == 'MODERATE') {

            if ($sMaxFreq <= 0.01) {
                // Check if it is rare.

                if ((($aLine['ID'] == '.' || $aLine['ID'] == '') && $sMaxFreq == '') || $sMaxFreq <= 0.0005) {
                    // check if novel - SNP138 ($aLine['ID']) is = '.' or '' and there is no frequency OR if very rare (<0.0005).

                    if ($aLine['Condel'] >= 0.07) {
                        // Check if it is conserved - condel >= 0.07.
                        $aLine['Variant_Priority'] = 4;

                    } else {
                        $aLine['Variant_Priority'] = 3;
                    }

                } else {
                    $aLine['Variant_Priority'] = 2;
                }

            } else {
                $aLine['Variant_Priority'] = 1;
            }

        } elseif ($aLine['IMPACT'] == 'LOW') {
            $aLine['Variant_Priority'] = 0;

        } elseif ($aLine['IMPACT'] == 'MODIFIER') {
            $aLine['Variant_Priority'] = 0;

        } else {
            $aLine['Variant_Priority'] = 0;
        }

    }

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

?>