<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-06
 * Modified    : 2016-08-05
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-analyses.php';
set_time_limit(0); // Unfortunately, necessary.

// Require collaborator clearance.
if (!$_AUTH || $_AUTH['level'] < LEVEL_ANALYZER) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

// Check if the data sent is correct or not.
if (empty($_GET['runid']) || !ctype_digit($_GET['runid'])) {
    die(AJAX_DATA_ERROR);
}



// Check if run exists.
$nRunID = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_ANALYSES_RUN . ' WHERE id = ?', array($_GET['runid']))->fetchColumn();
if (!$nRunID) {
    die(json_encode(array('result' => false, 'msg' => 'Analysis run not recognized. If the analysis is defined properly, this is an error in the software.')));
}

// Check if session var exists.
if (empty($_SESSION['analyses'][$nRunID]) || empty($_SESSION['analyses'][$nRunID]['filters']) || !isset($_SESSION['analyses'][$nRunID]['IDsLeft'])) {
    die(json_encode(array('result' => false, 'msg' => 'Analysis run data not found. It\'s either not your analysis run, it\'s already done, or you have been logged out.')));
}



// OK, let's start, get filter information.
$aFilters = &$_SESSION['analyses'][$nRunID]['filters'];
list(,$sFilter) = each($aFilters);

// Run filter, but only if there are variants left.
$aVariantIDs = &$_SESSION['analyses'][$nRunID]['IDsLeft'];
//sleep(2);
// Information about the selected gene panels for the apply_selected_gene_panels filter.
$sGenePanelsInfo = '';
$tStart = microtime(true);
if ($aVariantIDs) {
    $aVariantIDsFiltered = false;
    switch ($sFilter) {

        // MGHA specific filters.
        // TODO MGHA AM - We really should separate these filters out into a site specific configuration area as most of them depend on custom columns.
        case 'remove_variant_priority_lte_3':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Variant_priority` > 3 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_gmaf_gt_2':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G/VEP` IS NULL OR `VariantOnGenome/Frequency/1000G/VEP` <= 0.02) AND (`VariantOnGenome/Frequency/ExAC` IS NULL OR `VariantOnGenome/Frequency/ExAC` <= 0.02) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_gmaf_1000g':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G/VEP` IS NULL OR `VariantOnGenome/Frequency/1000G/VEP` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_maf_exac':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/ExAC` IS NULL OR `VariantOnGenome/Frequency/ExAC` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_deep_intronic_variants':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/Consequence_Type` IS NULL OR vot.`VariantOnTranscript/Consequence_Type` != "intron_variant") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_intronic_splice_region_variants':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/Consequence_Type` IS NULL OR vot.`VariantOnTranscript/Consequence_Type` != "splice_region_variant&intron_variant") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_utr_variants':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/Consequence_Type` IS NULL OR (vot.`VariantOnTranscript/Consequence_Type` != "3_prime_UTR_variant" AND vot.`VariantOnTranscript/Consequence_Type` != "5_prime_UTR_variant")) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_synonymous_variants':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/Consequence_Type` IS NULL OR vot.`VariantOnTranscript/Consequence_Type` != "synonymous_variant") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_low_impact_variants':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/Consequence_Impact` IS NULL OR vot.`VariantOnTranscript/Consequence_Impact` LIKE "%MODERATE%" OR vot.`VariantOnTranscript/Consequence_Impact` LIKE "%HIGH%") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'select_homozygous_or_potential_compound_het':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot1 USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t1 ON (vot1.transcriptid = t1.id) WHERE (vog.allele = 3 OR EXISTS (SELECT vot2.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot2 INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t2 ON (vot2.transcriptid = t2.id) WHERE vot2.id != vog.id AND t1.geneid = t2.geneid AND vot2.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . '))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge($aVariantIDs, $aVariantIDs), false)->fetchAllColumn();
            break;
        case 'select_homozygous_or_candidate_compound_het':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot1 USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t1 ON (vot1.transcriptid = t1.id) WHERE (vog.allele = 3 OR (vog.allele = 10 AND EXISTS (SELECT vot2.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot2 INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t2 ON (vot2.transcriptid = t2.id) INNER JOIN ' . TABLE_VARIANTS . ' as vog2 ON (vot2.id = vog2.id) WHERE vot2.id != vog.id AND t1.geneid = t2.geneid AND vog2.allele != 10 AND vot2.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . '))) OR (vog.allele = 20 AND EXISTS (SELECT vot3.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot3 INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t3 ON (vot3.transcriptid = t3.id) INNER JOIN ' . TABLE_VARIANTS . ' as vog3 ON (vot3.id = vog3.id) WHERE vot3.id != vog.id AND t1.geneid = t3.geneid AND vog3.allele != 20 AND vot3.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge($aVariantIDs, $aVariantIDs, $aVariantIDs), false)->fetchAllColumn();
            break;
        case 'select_homozygous_or_confirmed_compound_het':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot1 USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t1 ON (vot1.transcriptid = t1.id) WHERE (vog.allele = 3 OR (vog.allele = 10 AND EXISTS (SELECT vot2.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot2 INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t2 ON (vot2.transcriptid = t2.id) INNER JOIN ' . TABLE_VARIANTS . ' as vog2 ON (vot2.id = vog2.id) WHERE vot2.id != vog.id AND t1.geneid = t2.geneid AND vog2.allele = 20 AND vot2.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . '))) OR (vog.allele = 20 AND EXISTS (SELECT vot3.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot3 INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t3 ON (vot3.transcriptid = t3.id) INNER JOIN ' . TABLE_VARIANTS . ' as vog3 ON (vot3.id = vog3.id) WHERE vot3.id != vog.id AND t1.geneid = t3.geneid AND vog3.allele = 10 AND vot3.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge($aVariantIDs, $aVariantIDs, $aVariantIDs), false)->fetchAllColumn();
            break;
        case 'not_homo_in_mother':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Sequencing/Mother/GenoType` IS NULL OR `VariantOnGenome/Sequencing/Mother/GenoType` != "1/1") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_obs_count_ratio_gte_1':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT JOIN ' . TABLE_VARIANTS . ' AS ovog ON (vog.`VariantOnGenome/DBID` = ovog.`VariantOnGenome/DBID`) LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (ovog.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) WHERE vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ') GROUP BY vog.`VariantOnGenome/DBID` HAVING ((COUNT(DISTINCT s.individualid) - 1) / (SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS . ')) < 0.01', $aVariantIDs, false)->fetchAllColumn();
            break;

        // Filters requested by Arthur at CTP.
        // Remove SNVs with QUAL < 50.
        case 'remove_snv_by_quality_lte_50':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`type` IS NULL OR `type` != "subst" OR (`VariantOnGenome/Sequencing/Quality` > 50 AND `type` = "subst")) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        // Remove InDels with QUAL < 500.
        case 'remove_indel_by_quality_lte_500':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`type` IS NULL OR (`type` NOT IN ("ins","del")) OR (`VariantOnGenome/Sequencing/Quality` > 500 AND `type` IN ("ins","del"))) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        // Remove variants with max gMAF (ExAC, EVS, 1000g) > 0.01, no value is assumed 0.
        case 'remove_with_any_gmaf_gt_1':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G/VEP` IS NULL OR `VariantOnGenome/Frequency/1000G/VEP` <= 0.01) AND (`VariantOnGenome/Frequency/ExAC` IS NULL OR `VariantOnGenome/Frequency/ExAC` <= 0.01) AND (`VariantOnGenome/Frequency/EVS/VEP/European_American` IS NULL OR `VariantOnGenome/Frequency/EVS/VEP/European_American` <= 0.01) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        // Remove variants with max gMAF (EVS, 1000g) > 0.01, no value is assumed 0.
        // NOTE: seqliner data does not have ExAC
        case 'remove_with_any_gmaf_evs_1000g_gt_1':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G/VEP` IS NULL OR `VariantOnGenome/Frequency/1000G/VEP` <= 0.01) AND (`VariantOnGenome/Frequency/EVS/VEP/European_American` IS NULL OR `VariantOnGenome/Frequency/EVS/VEP/European_American` <= 0.01) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        // Variant with ind ratio < 0.5.
        case 'remove_obs_count_ratio_gte_50':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT JOIN ' . TABLE_VARIANTS . ' AS ovog ON (vog.`VariantOnGenome/DBID` = ovog.`VariantOnGenome/DBID`) LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (ovog.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) WHERE vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ') GROUP BY vog.`VariantOnGenome/DBID` HAVING ((COUNT(DISTINCT s.individualid) - 1) / (SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS . ')) < 0.5', $aVariantIDs, false)->fetchAllColumn();
            break;
        // End MGHA specific filters. You need to be careful when using anything below this line as it might not work with MGHA custom columns. The following filters are known to work:
        // chromosome_X, is_present_father_1, is_present_father_lte_4, is_present_mother_1, is_present_mother_lte_4, remove_by_quality_lte_100, select_homozygous_or_compound_heterozygous

        case 'chromosome_X':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE chromosome = "X" AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'is_present_father_1':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Father/VarPresent` = 1 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'is_present_father_lte_4':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Father/VarPresent` <= 4 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'is_present_mother_1':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Mother/VarPresent` = 1 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'is_present_mother_lte_4':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Mother/VarPresent` <= 4 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_coding_synonymous':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR vot.`VariantOnTranscript/GVS/Function` != "coding-synonymous") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_utr3':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR vot.`VariantOnTranscript/GVS/Function` != "utr-3") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_utr5':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR vot.`VariantOnTranscript/GVS/Function` != "utr-5") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_utr_or_intronic':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR (vot.`VariantOnTranscript/GVS/Function` != "utr-3" AND vot.`VariantOnTranscript/GVS/Function` != "utr-5" AND !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 8))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_utr_or_intronic_gt_20':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR (vot.`VariantOnTranscript/GVS/Function` != "utr-3" AND vot.`VariantOnTranscript/GVS/Function` != "utr-5" AND !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 20))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_utr_or_intronic_or_synonymous':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR (vot.`VariantOnTranscript/GVS/Function` != "utr-3" AND vot.`VariantOnTranscript/GVS/Function` != "utr-5" AND !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 8) AND vot.`VariantOnTranscript/GVS/Function` != "coding-synonymous")) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_utr_or_intronic_gt_20_or_synonymous':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR (vot.`VariantOnTranscript/GVS/Function` != "utr-3" AND vot.`VariantOnTranscript/GVS/Function` != "utr-5" AND !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 20) AND vot.`VariantOnTranscript/GVS/Function` != "coding-synonymous")) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_indb_count_hc_gte_1':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/InhouseDB/Count/HC` IS NULL OR `VariantOnGenome/InhouseDB/Count/HC` < 1) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_indb_count_hc_gte_2':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/InhouseDB/Count/HC` IS NULL OR `VariantOnGenome/InhouseDB/Count/HC` < 2) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_indb_count_hc_gte_5':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/InhouseDB/Count/HC` IS NULL OR `VariantOnGenome/InhouseDB/Count/HC` < 5) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_indb_count_ug_gte_1':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/InhouseDB/Count/UG` IS NULL OR `VariantOnGenome/InhouseDB/Count/UG` < 1) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_indb_count_ug_gte_2':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/InhouseDB/Count/UG` IS NULL OR `VariantOnGenome/InhouseDB/Count/UG` < 2) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_indb_count_ug_gte_5':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/InhouseDB/Count/UG` IS NULL OR `VariantOnGenome/InhouseDB/Count/UG` < 5) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_quality_lte_100':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Quality` > 100 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_in_gene_blacklist':
            // The blacklist could be looked up, but since the name can change and we know the ID, we'll just use that.
            $nDiseaseID = 918;
            // This query, including the preparation of the arguments, takes <0.1 second. The problem is the fetchAllColumn() which takes 2 minutes.
            //   Limit on 1000 entries max: 2.5s.
            // Problem is directly related to number of results IN COMBINATION WITH the arguments to send.
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE (t.geneid IS NULL OR NOT EXISTS (SELECT 1 FROM ' . TABLE_GEN2DIS . ' AS g2d WHERE g2d.diseaseid = ? AND g2d.geneid = t.geneid)) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge(array($nDiseaseID), $aVariantIDs), false)->fetchAllColumn();
            break;



            // FIXME
            // The following few lines contain testing code, that should be removed once we have solved the problem with fetchAll() not handling the number of results while a large number of arguments are sent.
            $tStart = microtime(true);
            $q = $_DB->prepare('SELECT SQL_NO_CACHE DISTINCT vog.id FROM lovd_KG_variants AS vog LEFT OUTER JOIN lovd_KG_variants_on_transcripts AS vot USING (id) LEFT OUTER JOIN lovd_KG_transcripts AS t ON (vot.transcriptid = t.id) LEFT OUTER JOIN lovd_KG_genes2diseases AS g2d USING (geneid) WHERE (g2d.diseaseid IS NULL OR !(g2d.diseaseid = 918)) LIMIT 50000');
            $q->execute($aVariantIDs);
            //$q->execute();
            var_dump(round(microtime(true) - $tStart, 5));
            $tStart = microtime(true);
            //$aVariantIDsFiltered = $q->fetchAllColumn();
            $aVariantIDsFiltered = $q->fetchAll(PDO::FETCH_COLUMN, 0);
            var_dump(round(microtime(true) - $tStart, 5));

            print('<BR>');
            mysql_connect('localhost', 'lovd', 'lovd_pw');
            mysql_select_db('lovd3_diagnostics');
            $tStart = microtime(true);
            $q = mysql_query('SELECT SQL_NO_CACHE DISTINCT vog.id FROM lovd_KG_variants AS vog LEFT OUTER JOIN lovd_KG_variants_on_transcripts AS vot USING (id) LEFT OUTER JOIN lovd_KG_transcripts AS t ON (vot.transcriptid = t.id) LEFT OUTER JOIN lovd_KG_genes2diseases AS g2d USING (geneid) WHERE (g2d.diseaseid IS NULL OR !(g2d.diseaseid = 918)) LIMIT 50000');
            var_dump(round(microtime(true) - $tStart, 5));
            $tStart = microtime(true);
            $aVariantIDsFiltered = array();
            while ($r = mysql_fetch_row($q)) {
                $aVariantIDsFiltered[] = $r[0];
            }
            var_dump(round(microtime(true) - $tStart, 5));
            exit;
            // FIXME



            break;
        case 'remove_intronic_distance_gt_2':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 2)) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_intronic_distance_gt_8':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 8)) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_intronic_distance_gt_20':
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 20)) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_missense_with_phylop_lte_2.5':
            // Als SNPs en ALS missense (dus exonic): phyloP>2.5 OF wobble base (3e base codon) bewaren
            //   (voor mezelf: wobble base posities hebben een lagere phyloP score, vandaar de controle)
            // ALS SNPs, en intronisch: phyloP>2.5 bewaren
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR (!(vog.type = "subst" AND vot.`VariantOnTranscript/GVS/Function` = "missense" AND vog.`VariantOnGenome/Conservation_score/PhyloP` <= 2.5 AND vot.position_c_start%3 != 0) AND !(vog.type = "subst" AND vot.`VariantOnTranscript/GVS/Function` = "intron" AND vog.`VariantOnGenome/Conservation_score/PhyloP` <= 2.5))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_not_imprinted':
            // The imprinted list could be looked up, but since the name can change and we know the ID, we'll just use that.
            $nDiseaseID = 931;
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vot.id AS UNSIGNED) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) INNER JOIN ' . TABLE_GEN2DIS . ' AS g2d USING (geneid) WHERE g2d.diseaseid = ? AND vot.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge(array($nDiseaseID), $aVariantIDs), false)->fetchAllColumn();
            break;
        case 'remove_not_in_gene_panel':
            // First, fetch disease ID from current individual. We will get the current individual by querying the database using the first variant.
            $aDiseaseIDs = $_DB->query('SELECT i2d.diseaseid FROM ' . TABLE_IND2DIS . ' AS i2d INNER JOIN ' . TABLE_SCREENINGS . ' AS s USING (individualid) INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) WHERE s2v.variantid = ?', array($aVariantIDs[0]))->fetchAllColumn();
            if ($aDiseaseIDs) {
                $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vot.id AS UNSIGNED) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) INNER JOIN ' . TABLE_GEN2DIS . ' AS g2d USING (geneid) WHERE g2d.diseaseid IN (?' . str_repeat(', ?', count($aDiseaseIDs) - 1) . ') AND vot.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge($aDiseaseIDs, $aVariantIDs), false)->fetchAllColumn();
            } else {
                // No disease. So no genes to select for. Just pretend this filter doesn't exist.
                $aVariantIDsFiltered = $aVariantIDs;
            }
            break;
        case 'apply_selected_gene_panels':
            // If no gene panels or custom panels are selected then don't do anything.
            // Regardless of success, we need to show the selected gene panels.
            $sGenePanelsInfo = getSelectedGenePanelsByRunID($nRunID);
            if (empty($_SESSION['analyses'][$nRunID]['custom_panel']) && empty($_SESSION['analyses'][$nRunID]['gene_panels'])) {
                $aVariantIDsFiltered = $aVariantIDs;
                break;
            }

            // If we are using a custom panel then load the genes.
            if (!empty($_SESSION['analyses'][$nRunID]['custom_panel'])) {
                $aCustomPanels = explode(', ', $_SESSION['analyses'][$nRunID]['custom_panel']);
            }

            // Load the selected gene panels into gene panels and blacklists.
            if (!empty($_SESSION['analyses'][$nRunID]['gene_panels'])) {
                $aGenePanels = $_DB->query('SELECT CASE gp.type WHEN "blacklist" THEN "blacklist" ELSE "gene_panel" END AS type, gp.id FROM ' . TABLE_GENE_PANELS . ' AS gp WHERE gp.id IN (?' . str_repeat(', ?', count($_SESSION['analyses'][$nRunID]['gene_panels'])-1) . ')', $_SESSION['analyses'][$nRunID]['gene_panels'])->fetchAllGroupColumn();
            }

            $bPanels = !empty($aGenePanels);
            $bGenePanels = !empty($aGenePanels['gene_panel']);
            $bBlackLists = !empty($aGenePanels['blacklist']);
            $bCustomPanels = !empty($aCustomPanels);
            $sWhereGenePanels = '';   // WHERE statement for the gene panels.
            $sWhereBlacklists = '';   // WHERE statement for the blacklists.
            $sWherePanelsSeparator = 'OR'; // The parts of the WHERE query of the panels and the custom panel are separated by ...?
            $sWhereCustomPanels = ''; // WHERE statement for the custom panels.
            $aSQL = array();          // Arguments to the query.
            // Using a blacklist is not necessary and complicates things a lot,
            // when we don't have a gene list, but we do have a custom list. The
            // custom list then takes the lead, and the blacklist has no
            // function. Better make this easier, by getting rid of it now.
            if (!$bGenePanels && $bCustomPanels) {
                $bPanels = $bBlackLists = false;
            }
            if (!$bPanels) {
                $sWhereGenePanels = 'TRUE'; // To not have to make the query too custom.
                if ($bCustomPanels) {
                    // To make sure the custom panel works while the gene
                    // panels are not used, we have to use AND instead of OR.
                    $sWherePanelsSeparator = 'AND';
                }
            } else {
                if ($bGenePanels) {
                    $sWhereGenePanels = 'gp.id IS NOT NULL';
                    $aSQL = array_merge($aSQL, $aGenePanels['gene_panel']); // For the JOIN.
                }
                if ($bBlackLists) {
                    $sWhereBlacklists = (!$sWhereGenePanels? '' : ' AND ') .
                        'NOT EXISTS (
                      SELECT 1 
                      FROM ' . TABLE_GP2GENE . ' AS bl2g
                        INNER JOIN ' . TABLE_GENE_PANELS . ' AS bl ON (bl2g.genepanelid = bl.id)
                      WHERE bl.id IN (?' . str_repeat(', ?', count($aGenePanels['blacklist'])-1) . ') AND t.geneid = bl2g.geneid)';
                    $aSQL = array_merge($aSQL, $aGenePanels['blacklist']);
                }
            }
            if ($bCustomPanels) {
                $sWhereCustomPanels = 't.geneid IN (?' . str_repeat(', ?', count($aCustomPanels)-1) . ')';
                $aSQL = array_merge($aSQL, $aCustomPanels);
            } else {
                $sWhereCustomPanels = 'FALSE'; // To not have to make the query too custom.
            }

            // Build up the query.
            $sSQL = 'SELECT DISTINCT CAST(vot.id AS UNSIGNED)
                     FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot
                       INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)' .
                (!$bGenePanels? '' : '
                       LEFT OUTER JOIN ' . TABLE_GP2GENE . ' AS gp2g ON (t.geneid = gp2g.geneid)
                       LEFT OUTER JOIN ' . TABLE_GENE_PANELS . ' AS gp ON (gp2g.genepanelid = gp.id AND gp.id IN (?' . str_repeat(', ?', count($aGenePanels['gene_panel'])-1) . '))') . '
                     WHERE (
                        (' . $sWhereGenePanels . $sWhereBlacklists . ')
                        ' . $sWherePanelsSeparator . ' ' . $sWhereCustomPanels . ')
                       AND vot.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')';
            $aSQL = array_merge($aSQL, $aVariantIDs);

            $aVariantIDsFiltered = $_DB->query($sSQL, $aSQL, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/dbSNP` IS NULL OR `VariantOnGenome/dbSNP` = "") AND (`VariantOnGenome/Frequency/1000G` IS NULL OR `VariantOnGenome/Frequency/1000G` = 0) AND (`VariantOnGenome/Frequency/GoNL` IS NULL OR `VariantOnGenome/Frequency/GoNL` = 0) AND (`VariantOnGenome/Frequency/EVS` IS NULL OR `VariantOnGenome/Frequency/EVS` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_gt_2':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G` IS NULL OR `VariantOnGenome/Frequency/1000G` <= 0.02) AND (`VariantOnGenome/Frequency/GoNL` IS NULL OR `VariantOnGenome/Frequency/GoNL` <= 0.02) AND (`VariantOnGenome/Frequency/EVS` IS NULL OR `VariantOnGenome/Frequency/EVS` <= 0.02) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_gt_3':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G` IS NULL OR `VariantOnGenome/Frequency/1000G` <= 0.03) AND (`VariantOnGenome/Frequency/GoNL` IS NULL OR `VariantOnGenome/Frequency/GoNL` <= 0.03) AND (`VariantOnGenome/Frequency/EVS` IS NULL OR `VariantOnGenome/Frequency/EVS` <= 0.03) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_1000G':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G` IS NULL OR `VariantOnGenome/Frequency/1000G` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_dbSNP':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/dbSNP` IS NULL OR `VariantOnGenome/dbSNP` = "") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_EVS':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/EVS` IS NULL OR `VariantOnGenome/Frequency/EVS` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_goNL':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/GoNL` IS NULL OR `VariantOnGenome/Frequency/GoNL` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'select_filtervcf_dot_or_pass':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Sequencing/Filter` IS NULL OR `VariantOnGenome/Sequencing/Filter` = "" OR `VariantOnGenome/Sequencing/Filter` = "." OR `VariantOnGenome/Sequencing/Filter` = "PASS") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'select_gatkcaller_ug_hc':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Sequencing/GATKcaller` REGEXP "[[:<:]]UG[[:>:]]" AND `VariantOnGenome/Sequencing/GATKcaller` REGEXP "[[:<:]]HC[[:>:]]") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'select_homozygous_or_compound_heterozygous':
            // FIXME: Problem: Compound heterozygous check means I need the allele column.
            // What do we do with fields where the allele is unknown (de novo?).
            // Currently: two variants in the same gene is enough to trigger the compound heterozygous case, but that is of course not really correct...
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot1 USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t1 ON (vot1.transcriptid = t1.id) WHERE (vog.allele = 3 OR EXISTS (SELECT vot2.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot2 INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t2 ON (vot2.transcriptid = t2.id) WHERE vot2.id != vog.id AND t1.geneid = t2.geneid AND vot2.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . '))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge($aVariantIDs, $aVariantIDs), false)->fetchAllColumn();
            break;
        default:
            // Filter not recognized... Oh, dear... We didn't define it yet?
            die(json_encode(array('result' => false, 'msg' => 'Filter \'' . $sFilter . '\' not recognized. Are you sure it\'s defined? If it is, this is an error in the software.')));
    }
    if ($aVariantIDsFiltered === false) {
        // Query error...
        die(json_encode(array('result' => false, 'msg' => 'Software error: Filter \'' . $sFilter . '\' returned a query error. Please tell support to check the logs.')));
    }
} else {
    $aVariantIDsFiltered = array();
}
$tEnd = microtime(true);
$nTimeSpent = round($tEnd - $tStart);

// Update database.
if (!$_DB->query('UPDATE ' . TABLE_ANALYSES_RUN_FILTERS . ' SET filtered_out = ?, run_time = ? WHERE runid = ? AND filterid = ?', array((count($aVariantIDs) - count($aVariantIDsFiltered)), $nTimeSpent, $nRunID, $sFilter), false)) {
    die(json_encode(array('result' => false, 'msg' => 'Software error: Error saving filter step results. Please tell support to check the logs.')));
}

// Now update the session.
$aVariantIDs = $aVariantIDsFiltered; // Will cascade into the $_SESSION variable.
array_shift($aFilters); // Will cascade into the $_SESSION variable.

// Done! Check if we need to run another filter.
if ($aFilters) {
    // Still more to do.
    // FIXME: This script now returns JSON as well as simple return values. Standardize this.
    die(json_encode(array('result' => true, 'sFilterID' => $sFilter, 'nVariantsLeft' => count($aVariantIDs), 'nTime' => lovd_convertSecondsToTime($nTimeSpent, 1), 'sGenePanelsInfo' => $sGenePanelsInfo, 'bDone' => false)));
} else {
    // Since we're done, save the results in the database.
    $q = $_DB->prepare('INSERT INTO ' . TABLE_ANALYSES_RUN_RESULTS . ' VALUES (?, ?)');
    $nVariants = count($aVariantIDs);
    foreach ($aVariantIDs as $nVariantID) {
        $q->execute(array($nRunID, $nVariantID));
    }

    // Now that we're done, clean up after ourselves...
    unset($_SESSION['analyses'][$nRunID]);
    // FIXME: This script now returns JSON as well as simple return values. Standardize this.
    die(json_encode(array('result' => true, 'sFilterID' => $sFilter, 'nVariantsLeft' => $nVariants, 'nTime' => lovd_convertSecondsToTime($nTimeSpent, 1), 'sGenePanelsInfo' => $sGenePanelsInfo, 'bDone' => true)));
}
?>
