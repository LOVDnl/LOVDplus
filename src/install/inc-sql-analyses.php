<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-07-15
 * Modified    : 2017-03-08
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Anthony Marty <anthony.marty@unimelb.edu.au>
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

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', '../');
    require ROOT_PATH . 'inc-init.php';
    lovd_requireAUTH(LEVEL_MANAGER);
}

$aAnalysesSQL = array(); // To prevent notices if instance name is not recognized.

// To have default analyses available directly after installing LOVD+, create a
// list of analyses here using your instance name (alphabetically ordered).
// Make sure your instance name is defined in the config.ini.php file.
switch ($_INI['instance']['name']) {
    case 'leiden':
        $aAnalysesSQL =
            array(
                'INSERT INTO ' . TABLE_ANALYSES . ' (`id`, `sortid`, `name`, `description`, `version`, `created_by`, `created_date`, `edited_by`, `edited_date`) VALUES
                  (1, 1, "De novo",                 "Filters for de novo variants, not reported before in known databases.", 1, 0, NOW(), NULL, NULL),
                  (2, 2, "Gene panel",              "Filters for coding or splice site variants within the gene panel.", 1, 0, NOW(), NULL, NULL),
                  (3, 3, "X-linked recessive",      "Filters for X-linked recessive variants, not found in father, not homozygous in mother. High frequencies (> 3%) are also filtered out.", 1, 0, NOW(), NULL, NULL),
                  (4, 4, "Recessive (gene panel)",  "Filters for recessive variants, homozygous or compound heterozygous in patient, but not in the parents. High frequencies (> 3%) are also filtered out.", 1, 0, NOW(), NULL, NULL),
                  (5, 5, "Recessive (whole exome)", "Filters for recessive variants, homozygous or compound heterozygous in patient, but not in the parents. High frequencies (> 3%) are also filtered out.", 1, 0, NOW(), NULL, NULL),
                  (6, 6, "Imprinted genes",         "Filters for variants found in imprinted genes.", 1, 0, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_ANALYSIS_FILTERS . ' (`id`, `name`, `description`) VALUES 
                  ("apply_selected_gene_panels", "", "Select only variants that are associated with a gene that is in the selected gene panels and not within the selected blacklists."),
                  ("chromosome_X", "", "Select only variants that are located on the X chromosome."),
                  ("is_present_father_1", "", ""),
                  ("is_present_father_lte_4", "", ""),
                  ("is_present_mother_1", "", ""),
                  ("is_present_mother_lte_4", "", ""),
                  ("remove_by_indb_count_hc_gte_2", "", ""),
                  ("remove_by_indb_count_ug_gte_2", "", ""),
                  ("remove_by_function_coding_synonymous", "", "Remove all variants that are only labeled as coding-synonymous."),
                  ("remove_by_function_utr3", "", "Remove all variants that are only mapped to the 3\' UTR."),
                  ("remove_by_function_utr5", "", "Remove all variants that are only mapped to the 5\' UTR."),
                  ("remove_by_function_utr_or_intronic", "", "Remove all variants that are only mapped to the UTR or introns."),
                  ("remove_by_function_utr_or_intronic_or_synonymous", "", "Remove all variants that are only mapped to the UTR or introns, or labeled as coding-synonymous."),
                  ("remove_by_function_utr_or_intronic_gt_20", "", "Remove all variants that are only mapped to the UTR or introns, >20 bp from the exon."),
                  ("remove_by_quality_lte_100", "", "Remove all variants with a sequencing quality score that is less than, or equal to, 100."),
                  ("remove_intronic_distance_gt_2", "", "Remove all variants that are only mapped to introns, >2 bp from the exon."),
                  ("remove_intronic_distance_gt_8", "", "Remove all variants that are only mapped to introns, >8 bp from the exon."),
                  ("remove_missense_with_phylop_lte_2.5", "", "Remove all substitutions having a PhyloP score of less than or equal to 2.5, if missense but not the wobble base, or intronic."),
                  ("remove_not_imprinted", "", "Remove all variants within genes not in the imprinted gene list (disease ID: 931)."),
                  ("remove_with_any_frequency_1000G", "", "Remove all variants that have a frequency 1000G."),
                  ("remove_with_any_frequency_dbSNP", "", "Remove all variants that have a dbSNP ID."),
                  ("remove_with_any_frequency_EVS", "", "Remove all variants that have a frequency in EVS."),
                  ("remove_with_any_frequency_goNL", "", "Remove all variants that have a frequency in GoNL."),
                  ("remove_with_any_frequency_gt_2", "", "Remove all variants that have a frequency higher than 2% in 1000G, GoNL or EVS."),
                  ("remove_with_any_frequency_gt_3", "", "Remove all variants that have a frequency higher than 3% in 1000G, GoNL or EVS."),
                  ("remove_by_indb_count_hc_gte_5", "", ""),
                  ("remove_by_indb_count_ug_gte_5", "", ""),
                  ("select_gatkcaller_ug_hc", "", "Select only variants called by both UG and HC."),
                  ("select_homozygous_or_compound_heterozygous", "", "Select only homozygous variants or variants associated with a gene that currently has more than one variant left.")',
                'INSERT INTO ' . TABLE_A2AF . ' (`analysisid`, `filterid`, `filter_order`) VALUES
                  (1, "apply_selected_gene_panels", 1), 
                  (1, "remove_by_quality_lte_100", 2), 
                  (1, "remove_by_indb_count_hc_gte_2", 3), 
                  (1, "remove_by_indb_count_ug_gte_2", 4), 
                  (1, "remove_with_any_frequency_gt_2", 5), 
                  (1, "remove_with_any_frequency_1000G", 6), 
                  (1, "remove_with_any_frequency_dbSNP", 7), 
                  (1, "remove_with_any_frequency_goNL", 8), 
                  (1, "remove_with_any_frequency_EVS", 9), 
                  (1, "is_present_mother_lte_4", 10), 
                  (1, "is_present_father_lte_4", 11), 
                  (1, "is_present_mother_1", 12), 
                  (1, "is_present_father_1", 13), 
                  (1, "remove_intronic_distance_gt_8", 14), 
                  (1, "remove_intronic_distance_gt_2", 15), 
                  (1, "remove_by_function_utr3", 16), 
                  (1, "remove_by_function_utr5", 17), 
                  (1, "remove_by_function_utr_or_intronic", 18), 
                  (1, "remove_by_function_coding_synonymous", 19), 
                  (1, "remove_by_function_utr_or_intronic_or_synonymous", 20), 
                  (2, "apply_selected_gene_panels", 1), 
                  (2, "remove_by_quality_lte_100", 2), 
                  (2, "remove_by_function_utr_or_intronic_gt_20", 3), 
                  (3, "chromosome_X", 1), 
                  (3, "apply_selected_gene_panels", 2), 
                  (3, "remove_by_quality_lte_100", 3), 
                  (3, "remove_by_indb_count_hc_gte_2", 4), 
                  (3, "remove_by_indb_count_ug_gte_2", 5), 
                  (3, "remove_with_any_frequency_gt_3", 6), 
                  (3, "is_present_father_lte_4", 7), 
                  (3, "remove_intronic_distance_gt_8", 8), 
                  (3, "remove_intronic_distance_gt_2", 9), 
                  (3, "remove_by_function_utr3", 10), 
                  (3, "remove_by_function_utr5", 11), 
                  (3, "remove_by_function_utr_or_intronic", 12), 
                  (3, "remove_by_function_coding_synonymous", 13), 
                  (3, "remove_by_function_utr_or_intronic_or_synonymous", 14), 
                  (4, "apply_selected_gene_panels", 1), 
                  (4, "remove_by_quality_lte_100", 2), 
                  (4, "remove_by_indb_count_hc_gte_5", 3), 
                  (4, "remove_by_indb_count_ug_gte_5", 4), 
                  (4, "remove_by_indb_count_hc_gte_2", 5), 
                  (4, "remove_by_indb_count_ug_gte_2", 6), 
                  (4, "remove_with_any_frequency_gt_3", 7), 
                  (4, "remove_intronic_distance_gt_8", 8), 
                  (4, "remove_intronic_distance_gt_2", 9), 
                  (4, "remove_by_function_utr3", 10), 
                  (4, "remove_by_function_utr5", 11), 
                  (4, "remove_by_function_utr_or_intronic", 12), 
                  (4, "remove_by_function_coding_synonymous", 13), 
                  (4, "remove_by_function_utr_or_intronic_or_synonymous", 14), 
                  (4, "select_homozygous_or_compound_heterozygous", 15), 
                  (5, "remove_by_quality_lte_100", 1), 
                  (5, "select_gatkcaller_ug_hc", 2), 
                  (5, "remove_by_indb_count_hc_gte_5", 3), 
                  (5, "remove_by_indb_count_ug_gte_5", 4), 
                  (5, "remove_by_indb_count_hc_gte_2", 5), 
                  (5, "remove_by_indb_count_ug_gte_2", 6), 
                  (5, "remove_with_any_frequency_gt_3", 7), 
                  (5, "remove_intronic_distance_gt_8", 8), 
                  (5, "remove_intronic_distance_gt_2", 9), 
                  (5, "remove_by_function_utr3", 10), 
                  (5, "remove_by_function_utr5", 11), 
                  (5, "remove_by_function_utr_or_intronic", 12), 
                  (5, "remove_by_function_coding_synonymous", 13), 
                  (5, "remove_by_function_utr_or_intronic_or_synonymous", 14), 
                  (5, "remove_missense_with_phylop_lte_2.5", 15), 
                  (5, "select_homozygous_or_compound_heterozygous", 16), 
                  (6, "apply_selected_gene_panels", 1), 
                  (6, "remove_by_quality_lte_100", 2), 
                  (6, "remove_not_imprinted", 3), 
                  (6, "remove_by_indb_count_hc_gte_2", 4), 
                  (6, "remove_by_indb_count_ug_gte_2", 5), 
                  (6, "remove_with_any_frequency_gt_2", 6), 
                  (6, "remove_with_any_frequency_1000G", 7), 
                  (6, "remove_with_any_frequency_dbSNP", 8), 
                  (6, "remove_with_any_frequency_goNL", 9), 
                  (6, "remove_with_any_frequency_EVS", 10), 
                  (6, "remove_intronic_distance_gt_8", 11), 
                  (6, "remove_intronic_distance_gt_2", 12), 
                  (6, "remove_by_function_utr3", 13), 
                  (6, "remove_by_function_utr5", 14), 
                  (6, "remove_by_function_utr_or_intronic", 15), 
                  (6, "remove_by_function_coding_synonymous", 16), 
                  (6, "remove_by_function_utr_or_intronic_or_synonymous", 17)',
            );
        break;
    case 'mgha':
        $aAnalysesSQL =
            array(
                'INSERT INTO ' . TABLE_ANALYSES . ' (`id`, `sortid`, `name`, `description`, `version`, `created_by`, `created_date`, `edited_by`, `edited_date`) VALUES 
                  (1, 1, "Dominant Singleton",             "Default analysis.", 1, 0, NOW(), NULL, NULL), 
                  (2, 2, "De Novo Trio",                   "Filters for de novo variants, not reported before in known databases.", 1, 0, NOW(), NULL, NULL), 
                  (3, 3, "Recessive Singleton",            "Filters for recessive candidate variants, homozygous or potential compound heterozygous.", 1, 0, NOW(), NULL, NULL), 
                  (4, 4, "Recessive Trio",                 "Filters for recessive variants, homozygous or compound heterozygous in patient, but not in the parents. High frequencies (> 3%) are also filtered out.", 1, 0, NOW(), NULL, NULL), 
                  (5, 5, "X-linked Recessive Trio",        "Filters for X-linked recessive variants, not found in father, not homozygous in mother.", 1, 0, NOW(), NULL, NULL), 
                  (6, 6, "Unknown Singleton Inheritance",  "Unknown Singleton Inheritance", 1, 0, NOW(), NULL, NULL), 
                  (7, 7, "X-linked Recessive Singleton",   "X-linked Recessive Singleton", 1, 0, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_ANALYSIS_FILTERS . ' (`id`, `name`, `description`) VALUES 
                  ("apply_selected_gene_panels", "Apply selected gene panels", "Include any variants that appear with the selected gene panels but not within the selected blacklists."), 
                  ("remove_by_quality_lte_100", "Remove by quality <= 100", "Only keep variants that have a sequencing quality score that is greater than 100."), 
                  ("remove_with_any_gmaf_exac_gt_0.1", "Remove with exac > 0.1", "Only keep variants that have a frequency of 0.1% or less in ExAC or have no value."), 
                  ("remove_obs_count_gte_5_percent", "Remove obs count >= 5 percent", "Only keep variants that occur in less than 5% of the individuals within this LOVD+ instance."), 
                  ("remove_deep_intronic_variants", "Remove deep intronic variants", "Only keep variants that have no VEP consequence type or don’t have intron_variant as the VEP consequence type."), 
                  ("remove_intronic_splice_region_variants", "Remove intronic splice region variants", "Only keep variants that have no VEP consequence type or don’t have splice_region_variant&intron_variant as the VEP consequence type."), 
                  ("remove_utr_variants", "Remove utr variants", "Only keep variants that have no VEP consequence type or don’t have 3_prime_UTR_variant or 5_prime_UTR_variant as the VEP consequence type."), 
                  ("remove_synonymous_variants", "Remove synonymous variants", "Only keep variants that have no VEP consequence type or don’t have synonymous_variant as the VEP consequence type."), 
                  ("remove_low_impact_variants", "Remove low impact variants", "Only keep variants that have no VEP consequence impact or have MODERATE or HIGH within the VEP consequence impact text."), 
                  ("remove_with_any_gmaf_gt_2", "Remove with any gmaf > 2", "Only keep variants that have a frequency of 2% or less in 1000G and ExAC or have no value."), 
                  ("remove_with_any_gmaf_1000g", "Remove with 1000G value", "Only keep variants that have no 1000G frequency."), 
                  ("remove_with_any_gmaf_exac", "Remove with ExAC value", "Only keep variants that have no ExAC frequency."), 
                  ("select_variants_absent_in_mother_low_conf", "Select variants absent in mother low conf", "Only keep variants that have been observed in the mother with a confidence score of 4 or less. The lower the number the higher the confidence level."), 
                  ("select_variants_absent_in_father_low_conf", "Select variants absent in father low conf", "Only keep variants that have been observed in the father with a confidence score of 4 or less. The lower the number the higher the confidence level."), 
                  ("select_variants_absent_in_mother_high_conf", "Select variants absent in mother high conf", "Only keep variants that have been observed in the mother with a confidence score of 1 or less. The lower the number the higher the confidence level."), 
                  ("select_variants_absent_in_father_high_conf", "Select variants absent in father high conf", "Only keep variants that have been observed in the father with a confidence score of 1 or less. The lower the number the higher the confidence level."), 
                  ("select_homozygous_or_potential_compound_het", "Select homozygous or potential compound het", ""), 
                  ("select_homozygous_or_candidate_compound_het", "Select homozygous or candidate compound het", ""), 
                  ("select_homozygous_or_confirmed_compound_het", "Select homozygous or confirmed compound het", ""), 
                  ("select_variants_on_chr_x", "Select variants on chr x", "Only keep variants that are found within the X chromosome."), 
                  ("remove_variants_hom_in_mother", "Remove variants hom in mother", "Only keep variants that are heterozygous in the mother.")',
                'INSERT INTO ' . TABLE_A2AF . ' (`analysisid`, `filterid`, `filter_order`) VALUES 
                  (1, "apply_selected_gene_panels", 1), 
                  (1, "remove_by_quality_lte_100", 2), 
                  (1, "remove_with_any_gmaf_exac_gt_0.1", 3), 
                  (1, "remove_obs_count_gte_5_percent", 4), 
                  (1, "remove_deep_intronic_variants", 5), 
                  (1, "remove_intronic_splice_region_variants", 6), 
                  (1, "remove_utr_variants", 7), 
                  (1, "remove_synonymous_variants", 8), 
                  (1, "remove_low_impact_variants", 9),
                  (2, "apply_selected_gene_panels", 1), 
                  (2, "remove_by_quality_lte_100", 2), 
                  (2, "remove_with_any_gmaf_gt_2", 3), 
                  (2, "remove_with_any_gmaf_1000g", 4), 
                  (2, "remove_with_any_gmaf_exac", 5), 
                  (2, "remove_obs_count_gte_5_percent", 6), 
                  (2, "remove_deep_intronic_variants", 7), 
                  (2, "remove_intronic_splice_region_variants", 8), 
                  (2, "remove_utr_variants", 9), 
                  (2, "remove_synonymous_variants", 10), 
                  (2, "remove_low_impact_variants", 11), 
                  (2, "select_variants_absent_in_mother_low_conf", 12), 
                  (2, "select_variants_absent_in_father_low_conf", 13), 
                  (2, "select_variants_absent_in_mother_high_conf", 14), 
                  (2, "select_variants_absent_in_father_high_conf", 15), 
                  (3, "apply_selected_gene_panels", 1), 
                  (3, "remove_by_quality_lte_100", 2), 
                  (3, "remove_with_any_gmaf_gt_2", 3), 
                  (3, "remove_obs_count_gte_5_percent", 4), 
                  (3, "remove_deep_intronic_variants", 5), 
                  (3, "remove_intronic_splice_region_variants", 6), 
                  (3, "remove_utr_variants", 7), 
                  (3, "remove_synonymous_variants", 8), 
                  (3, "remove_low_impact_variants", 9), 
                  (3, "select_homozygous_or_potential_compound_het", 10), 
                  (4, "apply_selected_gene_panels", 1), 
                  (4, "remove_by_quality_lte_100", 2), 
                  (4, "remove_with_any_gmaf_gt_2", 3), 
                  (4, "remove_obs_count_gte_5_percent", 4), 
                  (4, "remove_deep_intronic_variants", 5), 
                  (4, "remove_intronic_splice_region_variants", 6), 
                  (4, "remove_utr_variants", 7), 
                  (4, "remove_synonymous_variants", 8), 
                  (4, "remove_low_impact_variants", 9), 
                  (4, "select_homozygous_or_potential_compound_het", 10), 
                  (4, "select_homozygous_or_candidate_compound_het", 11), 
                  (4, "select_homozygous_or_confirmed_compound_het", 12), 
                  (5, "apply_selected_gene_panels", 1), 
                  (5, "select_variants_on_chr_x", 2), 
                  (5, "remove_by_quality_lte_100", 3), 
                  (5, "select_variants_absent_in_father_low_conf", 4), 
                  (5, "remove_variants_hom_in_mother", 5), 
                  (5, "remove_with_any_gmaf_gt_2", 6), 
                  (5, "remove_obs_count_gte_5_percent", 7), 
                  (5, "remove_deep_intronic_variants", 8), 
                  (5, "remove_intronic_splice_region_variants", 9), 
                  (5, "remove_utr_variants", 10), 
                  (5, "remove_synonymous_variants", 11), 
                  (5, "remove_low_impact_variants", 12), 
                  (6, "apply_selected_gene_panels", 1), 
                  (6, "remove_by_quality_lte_100", 2), 
                  (6, "remove_with_any_gmaf_gt_2", 3), 
                  (6, "remove_obs_count_gte_5_percent", 4), 
                  (6, "remove_deep_intronic_variants", 5), 
                  (6, "remove_intronic_splice_region_variants", 6), 
                  (6, "remove_utr_variants", 7), 
                  (6, "remove_synonymous_variants", 8), 
                  (6, "remove_low_impact_variants", 9), 
                  (7, "apply_selected_gene_panels", 1), 
                  (7, "select_variants_on_chr_x", 2), 
                  (7, "remove_by_quality_lte_100", 3), 
                  (7, "remove_with_any_gmaf_gt_2", 4), 
                  (7, "remove_obs_count_gte_5_percent", 5), 
                  (7, "remove_deep_intronic_variants", 6), 
                  (7, "remove_intronic_splice_region_variants", 7), 
                  (7, "remove_utr_variants", 8), 
                  (7, "remove_synonymous_variants", 9), 
                  (7, "remove_low_impact_variants", 10)',
            );
        break;
    default: // The default analyses that is installed with LOVD+.
        $aAnalysesSQL =
            array(
                'INSERT INTO ' . TABLE_ANALYSES . ' (`id`, `sortid`, `name`, `description`, `version`, `created_by`, `created_date`, `edited_by`, `edited_date`) VALUES 
                  (1, 1, "Default Analysis",             "This is the default analysis installed with LOVD+. Additional analyses can be created as required.", 1, 0, NOW(), NULL, NULL)',
                'INSERT INTO ' . TABLE_ANALYSIS_FILTERS . ' (`id`, `name`, `description`) VALUES 
                  ("apply_selected_gene_panels", "Apply selected gene panels", "Select only variants that are associated with a gene that is in the selected gene panels and not within the selected blacklists."),
                  ("remove_by_quality_lte_100", "Remove by quality <= 100", "Remove all variants with a sequencing quality score that is less than, or equal to, 100.")',
                'INSERT INTO ' . TABLE_A2AF . ' (`analysisid`, `filterid`, `filter_order`) VALUES 
                  (1, "apply_selected_gene_panels", 1), 
                  (1, "remove_by_quality_lte_100", 2)',
            );
}

if (lovd_getProjectFile() == '/install/inc-sql-analyses.php') {
    header('Content-type: text/plain; charset=UTF-8');
    print_r($aAnalysesSQL);
}
?>
