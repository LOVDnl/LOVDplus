<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-07-15
 * Modified    : 2016-08-05
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
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
}

$aAnalysesSQL = array(); // To prevent notices if instance name is not recognized.

// To have default analyses available directly after installing LOVD+, create a
// list of analyses here using your instance name (alphabetically ordered).
// Make sure your instance name is defined in the config.ini.php file.
switch ($_INI['instance']['name']) {
    case 'leiden':
        $aAnalysesSQL =
            array(
                'INSERT INTO ' . TABLE_ANALYSES . ' (sortid, name, description, filters, created_by, created_date) VALUES
                 (1,"De novo","Filters for de novo variants, not reported before in known databases.","remove_not_in_gene_panel\r\napply_selected_gene_panels\r\nremove_by_quality_lte_100\r\nremove_by_indb_count_hc_gte_2\r\nremove_by_indb_count_ug_gte_2\r\nremove_with_any_frequency_gt_2\r\nremove_with_any_frequency_1000G\r\nremove_with_any_frequency_dbSNP\r\nremove_with_any_frequency_goNL\r\nremove_with_any_frequency_EVS\r\nis_present_mother_lte_4\r\nis_present_father_lte_4\r\nis_present_mother_1\r\nis_present_father_1\r\nremove_intronic_distance_gt_8\r\nremove_intronic_distance_gt_2\r\nremove_by_function_utr3\r\nremove_by_function_utr5\r\nremove_by_function_utr_or_intronic\r\nremove_by_function_coding_synonymous\r\nremove_by_function_utr_or_intronic_or_synonymous",00000,NOW()),
                 (2,"Gene panel","Filters for coding or splice site variants within the gene panel.","remove_not_in_gene_panel\r\napply_selected_gene_panels\r\nremove_by_quality_lte_100\r\nremove_by_function_utr_or_intronic_gt_20",00000,NOW()),
                 (3,"X-linked recessive","Filters for X-linked recessive variants, not found in father, not homozygous in mother. High frequencies (> 3%) are also filtered out.","chromosome_X\r\nremove_not_in_gene_panel\r\napply_selected_gene_panels\r\nremove_by_quality_lte_100\r\nremove_by_indb_count_hc_gte_2\r\nremove_by_indb_count_ug_gte_2\r\nremove_with_any_frequency_gt_3\r\nis_present_father_lte_4\r\nremove_intronic_distance_gt_8\r\nremove_intronic_distance_gt_2\r\nremove_by_function_utr3\r\nremove_by_function_utr5\r\nremove_by_function_utr_or_intronic\r\nremove_by_function_coding_synonymous\r\nremove_by_function_utr_or_intronic_or_synonymous",00000,NOW()),
                 (4,"Recessive (gene panel)","Filters for recessive variants, homozygous or compound heterozygous in patient, but not in the parents. High frequencies (> 3%) are also filtered out.","remove_not_in_gene_panel\r\napply_selected_gene_panels\r\nremove_by_quality_lte_100\r\nremove_by_indb_count_hc_gte_5\r\nremove_by_indb_count_ug_gte_5\r\nremove_by_indb_count_hc_gte_2\r\nremove_by_indb_count_ug_gte_2\r\nremove_with_any_frequency_gt_3\r\nremove_intronic_distance_gt_8\r\nremove_intronic_distance_gt_2\r\nremove_by_function_utr3\r\nremove_by_function_utr5\r\nremove_by_function_utr_or_intronic\r\nremove_by_function_coding_synonymous\r\nremove_by_function_utr_or_intronic_or_synonymous\r\nselect_homozygous_or_compound_heterozygous",00000,NOW()),
                 (5,"Recessive (whole exome)","Filters for recessive variants, homozygous or compound heterozygous in patient, but not in the parents. High frequencies (> 3%) are also filtered out.","remove_by_quality_lte_100\r\nselect_gatkcaller_ug_hc\r\nremove_by_indb_count_hc_gte_5\r\nremove_by_indb_count_ug_gte_5\r\nremove_by_indb_count_hc_gte_2\r\nremove_by_indb_count_ug_gte_2\r\nremove_with_any_frequency_gt_3\r\nremove_intronic_distance_gt_8\r\nremove_intronic_distance_gt_2\r\nremove_by_function_utr3\r\nremove_by_function_utr5\r\nremove_by_function_utr_or_intronic\r\nremove_by_function_coding_synonymous\r\nremove_by_function_utr_or_intronic_or_synonymous\r\nremove_missense_with_phylop_lte_2.5\r\nselect_homozygous_or_compound_heterozygous\r\nremove_in_gene_blacklist",00000,NOW()),
                 (6,"Imprinted genes","Filters for variants found in imprinted genes.","remove_not_in_gene_panel\r\napply_selected_gene_panels\r\nremove_by_quality_lte_100\r\nremove_not_imprinted\r\nremove_by_indb_count_hc_gte_2\r\nremove_by_indb_count_ug_gte_2\r\nremove_with_any_frequency_gt_2\r\nremove_with_any_frequency_1000G\r\nremove_with_any_frequency_dbSNP\r\nremove_with_any_frequency_goNL\r\nremove_with_any_frequency_EVS\r\nremove_intronic_distance_gt_8\r\nremove_intronic_distance_gt_2\r\nremove_by_function_utr3\r\nremove_by_function_utr5\r\nremove_by_function_utr_or_intronic\r\nremove_by_function_coding_synonymous\r\nremove_by_function_utr_or_intronic_or_synonymous",00000,NOW())',
            );
        break;
    case 'mgha':
        $aAnalysesSQL =
            array(
                'INSERT INTO ' . TABLE_ANALYSES . ' (`id`, `sortid`, `name`, `description`, `filters`, `created_by`, `created_date`, `edited_by`, `edited_date`) 
                     VALUES (1, 1, "Default Analysis",         "Default analysis.", "apply_selected_gene_panels\r\nremove_by_quality_lte_100\r\nremove_with_any_gmaf_gt_2\r\nremove_with_any_gmaf_1000g\r\nremove_with_any_maf_exac\r\nremove_obs_count_ratio_gte_1\r\nremove_deep_intronic_variants\r\nremove_intronic_splice_region_variants\r\nremove_utr_variants\r\nremove_synonymous_variants\r\nremove_low_impact_variants", 0, NOW(), NULL, NULL),
                            (2, 2, "De novo",                  "Filters for de novo variants, not reported before in known databases.", "apply_selected_gene_panels\r\nremove_by_quality_lte_100\r\nremove_with_any_gmaf_gt_2\r\nremove_with_any_gmaf_1000g\r\nremove_with_any_maf_exac\r\nremove_deep_intronic_variants\r\nremove_intronic_splice_region_variants\r\nremove_utr_variants\r\nremove_synonymous_variants\r\nremove_low_impact_variants\r\nis_present_mother_lte_4\r\nis_present_father_lte_4\r\nis_present_mother_1\r\nis_present_father_1", 0, NOW(), NULL, NULL),
                            (3, 3, "Recessive Singleton",      "Filters for recessive candidate variants, homozygous or potential compound heterozygous.", "apply_selected_gene_panels\r\nremove_by_quality_lte_100\r\nremove_with_any_gmaf_gt_2\r\nremove_with_any_gmaf_1000g\r\nremove_with_any_maf_exac\r\nremove_deep_intronic_variants\r\nremove_intronic_splice_region_variants\r\nremove_utr_variants\r\nremove_synonymous_variants\r\nremove_low_impact_variants\r\nselect_homozygous_or_potential_compound_het", 0, NOW(), NULL, NULL),
                            (4, 4, "Recessive Trio",           "Filters for recessive variants, homozygous or compound heterozygous in patient, but not in the parents. High frequencies (> 3%) are also filtered out.", "apply_selected_gene_panels\r\nremove_by_quality_lte_100\r\nremove_with_any_gmaf_gt_2\r\nremove_with_any_gmaf_1000g\r\nremove_with_any_maf_exac\r\nremove_deep_intronic_variants\r\nremove_intronic_splice_region_variants\r\nremove_utr_variants\r\nremove_synonymous_variants\r\nremove_low_impact_variants\r\nselect_homozygous_or_potential_compound_het\r\nselect_homozygous_or_candidate_compound_het\r\nselect_homozygous_or_confirmed_compound_het", 0, NOW(), NULL, NULL),
                            (5, 5, "X-linked Recessive Trio",  "Filters for X-linked recessive variants, not found in father, not homozygous in mother.", "apply_selected_gene_panels\r\nchromosome_X\r\nremove_by_quality_lte_100\r\nis_present_father_lte_4\r\nnot_homo_in_mother\r\nremove_with_any_gmaf_gt_2\r\nremove_with_any_gmaf_1000g\r\nremove_with_any_maf_exac\r\nremove_deep_intronic_variants\r\nremove_intronic_splice_region_variants\r\nremove_utr_variants\r\nremove_synonymous_variants\r\nremove_low_impact_variants", 0, NOW(), NULL, NULL)',
            );
        break;
}

if (lovd_getProjectFile() == '/install/inc-sql-analyses.php') {
    header('Content-type: text/plain; charset=UTF-8');
    print_r($aAnalysesSQL);
}
?>
