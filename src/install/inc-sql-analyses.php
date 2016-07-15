<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-07-15
 * Modified    : 2016-07-15
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2012 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Anthony Marty <anthony.marty@unimelb.edu.au>
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

$aAnalysesSQL = '';

if ($_INI['instance']['name'] == 'mgha') {
    $aAnalysesSQL =
        array(
            'INSERT INTO ' . TABLE_ANALYSES . ' (`id`, `sortid`, `name`, `description`, `filters`, `created_by`, `created_date`, `edited_by`, `edited_date`) 
                 VALUES (1, 1, "Default Analysis",         "Default analysis.", "apply_selected_gene_panels\r\nremove_by_quality_lte_100\r\nremove_with_any_gmaf_gt_2\r\nremove_with_any_gmaf_1000g\r\nremove_with_any_maf_exac\r\nremove_deep_intronic_variants\r\nremove_intronic_splice_region_variants\r\nremove_utr_variants\r\nremove_synonymous_variants\r\nremove_low_impact_variants", 0, NOW(), NULL, NULL),
                        (2, 2, "De novo",                  "Filters for de novo variants, not reported before in known databases.", "apply_selected_gene_panels\r\nremove_by_quality_lte_100\r\nremove_with_any_gmaf_gt_2\r\nremove_with_any_gmaf_1000g\r\nremove_with_any_maf_exac\r\nremove_deep_intronic_variants\r\nremove_intronic_splice_region_variants\r\nremove_utr_variants\r\nremove_synonymous_variants\r\nremove_low_impact_variants\r\nis_present_mother_lte_4\r\nis_present_father_lte_4\r\nis_present_mother_1\r\nis_present_father_1", 0, NOW(), NULL, NULL),
                        (3, 3, "Recessive Singleton",      "Filters for recessive candidate variants, homozygous or potential compound heterozygous.", "apply_selected_gene_panels\r\nremove_by_quality_lte_100\r\nremove_with_any_gmaf_gt_2\r\nremove_with_any_gmaf_1000g\r\nremove_with_any_maf_exac\r\nremove_deep_intronic_variants\r\nremove_intronic_splice_region_variants\r\nremove_utr_variants\r\nremove_synonymous_variants\r\nremove_low_impact_variants\r\nselect_homozygous_or_potential_compound_het", 0, NOW(), NULL, NULL),
                        (4, 4, "Recessive Trio",           "Filters for recessive variants, homozygous or compound heterozygous in patient, but not in the parents. High frequencies (> 3%) are also filtered out.", "apply_selected_gene_panels\r\nremove_by_quality_lte_100\r\nremove_with_any_gmaf_gt_2\r\nremove_with_any_gmaf_1000g\r\nremove_with_any_maf_exac\r\nremove_deep_intronic_variants\r\nremove_intronic_splice_region_variants\r\nremove_utr_variants\r\nremove_synonymous_variants\r\nremove_low_impact_variants\r\nselect_homozygous_or_potential_compound_het\r\nselect_homozygous_or_candidate_compound_het\r\nselect_homozygous_or_confirmed_compound_het", 0, NOW(), NULL, NULL),
                        (5, 5, "X-linked Recessive Trio",  "Filters for X-linked recessive variants, not found in father, not homozygous in mother.", "apply_selected_gene_panels\r\nchromosome_X\r\nremove_by_quality_lte_100\r\nis_present_father_lte_4\r\nremove_with_any_gmaf_gt_2\r\nremove_with_any_gmaf_1000g\r\nremove_with_any_maf_exac\r\nremove_deep_intronic_variants\r\nremove_intronic_splice_region_variants\r\nremove_utr_variants\r\nremove_synonymous_variants\r\nremove_low_impact_variants", 0, NOW(), NULL, NULL)',
        );
}

if (lovd_getProjectFile() == '/install/inc-sql-analyses.php') {
    header('Content-type: text/plain; charset=UTF-8');
    print_r($aAnalysesSQL);
}
?>
