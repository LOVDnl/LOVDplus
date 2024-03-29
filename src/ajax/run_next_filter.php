<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-06
 * Modified    : 2023-01-11
 * For LOVD+   : 3.0-29
 *
 * Copyright   : 2004-2023 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Anthony Marty <anthony.marty@unimelb.edu.au>
 *               Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
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
$rAnalysisRun = $_DB->q('SELECT CAST(id AS UNSIGNED), screeningid FROM ' . TABLE_ANALYSES_RUN . ' WHERE id = ?', array($_GET['runid']))->fetchRow();
if (!$rAnalysisRun) {
    die(json_encode(array('result' => false, 'message' => 'Analysis run not recognized. If the analysis is defined properly, this is an error in the software.')));
}
list($nRunID, $nScreeningID) = $rAnalysisRun;

// Check if session var exists.
if (empty($_SESSION['analyses'][$nRunID]) || empty($_SESSION['analyses'][$nRunID]['filters']) || !isset($_SESSION['analyses'][$nRunID]['IDsLeft'])) {
    die(json_encode(array('result' => false, 'message' => 'Analysis run data not found. It\'s either not your analysis run, it\'s already done, or you have been logged out.')));
}



// OK, let's start, get filter information.
$aFilters = &$_SESSION['analyses'][$nRunID]['filters'];
$sFilter = current($aFilters);

// Run filter, but only if there are variants left.
$aVariantIDs = &$_SESSION['analyses'][$nRunID]['IDsLeft'];

// Read filter configurations.
$sConfig = $_DB->q('SELECT config_json FROM ' . TABLE_ANALYSES_RUN_FILTERS . ' WHERE runid = ? AND filterid = ?', array($nRunID, $sFilter))->fetchColumn();
$aConfig = (empty($sConfig)? array() : json_decode($sConfig, true));

$tStart = microtime(true);
if ($aVariantIDs) {
    $aVariantIDsFiltered = false;
    switch ($sFilter) {
        // MGHA specific filters.
        // TODO MGHA AM - We really should separate these filters out into a site specific configuration area as most of them depend on custom columns.
        case 'remove_variant_priority_lte_3':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Variant_priority` > 3 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_gmaf_gt_2':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/1000Gp3/Frequency` IS NULL OR `VariantOnGenome/1000Gp3/Frequency` <= 0.02) AND (`VariantOnGenome/ExAC/Frequency/Adjusted` IS NULL OR `VariantOnGenome/ExAC/Frequency/Adjusted` <= 0.02) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_gmaf_exac_gt_0.1':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/ExAC/Frequency/Adjusted` IS NULL OR `VariantOnGenome/ExAC/Frequency/Adjusted` <= 0.001) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_gmaf_1000g':
        case 'remove_with_any_gmaf_1000gp3': // We rename it to make it clearer that this is 1000gp3. But, keep the old name for other instances already have analysis run under the old filter name.
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/1000Gp3/Frequency` IS NULL OR `VariantOnGenome/1000Gp3/Frequency` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_gmaf_exac':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/ExAC/Frequency/Adjusted` IS NULL OR `VariantOnGenome/ExAC/Frequency/Adjusted` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_deep_intronic_variants':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/Consequence_Type` IS NULL OR vot.`VariantOnTranscript/Consequence_Type` != "intron_variant") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_intronic_splice_region_variants':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/Consequence_Type` IS NULL OR vot.`VariantOnTranscript/Consequence_Type` != "splice_region_variant&intron_variant") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_utr_variants':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/Consequence_Type` IS NULL OR (vot.`VariantOnTranscript/Consequence_Type` != "3_prime_UTR_variant" AND vot.`VariantOnTranscript/Consequence_Type` != "5_prime_UTR_variant")) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_synonymous_variants':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/Consequence_Type` IS NULL OR vot.`VariantOnTranscript/Consequence_Type` != "synonymous_variant") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_low_impact_not_splice':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/Consequence_Impact` IS NULL OR vot.`VariantOnTranscript/Consequence_Impact` LIKE "%MODERATE%" OR vot.`VariantOnTranscript/Consequence_Impact` LIKE "%HIGH%" OR vot.`VariantOnTranscript/Consequence_Type` LIKE "%splice_region_variant%") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_low_impact_variants':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/Consequence_Impact` IS NULL OR vot.`VariantOnTranscript/Consequence_Impact` LIKE "%MODERATE%" OR vot.`VariantOnTranscript/Consequence_Impact` LIKE "%HIGH%") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'select_homozygous_or_potential_compound_het':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot1 USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t1 ON (vot1.transcriptid = t1.id) WHERE (vog.allele = 3 OR EXISTS (SELECT vot2.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot2 INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t2 ON (vot2.transcriptid = t2.id) WHERE vot2.id != vog.id AND t1.geneid = t2.geneid AND vot2.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . '))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge($aVariantIDs, $aVariantIDs), false)->fetchAllColumn();
            break;
        case 'select_homozygous_or_candidate_compound_het':
            // Note that this filter removes *all* heterozygous with allele = 0, even when there are multiple per gene. It leaves the paternal and maternal variants when other variants in the gene are present.
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot1 USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t1 ON (vot1.transcriptid = t1.id) WHERE (vog.allele = 3 OR (vog.allele = 10 AND EXISTS (SELECT vot2.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot2 INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t2 ON (vot2.transcriptid = t2.id) INNER JOIN ' . TABLE_VARIANTS . ' as vog2 ON (vot2.id = vog2.id) WHERE vot2.id != vog.id AND t1.geneid = t2.geneid AND vog2.allele != 10 AND vot2.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . '))) OR (vog.allele = 20 AND EXISTS (SELECT vot3.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot3 INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t3 ON (vot3.transcriptid = t3.id) INNER JOIN ' . TABLE_VARIANTS . ' as vog3 ON (vot3.id = vog3.id) WHERE vot3.id != vog.id AND t1.geneid = t3.geneid AND vog3.allele != 20 AND vot3.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge($aVariantIDs, $aVariantIDs, $aVariantIDs), false)->fetchAllColumn();
            break;
        case 'select_homozygous_or_confirmed_compound_het':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot1 USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t1 ON (vot1.transcriptid = t1.id) WHERE (vog.allele = 3 OR (vog.allele = 10 AND EXISTS (SELECT vot2.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot2 INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t2 ON (vot2.transcriptid = t2.id) INNER JOIN ' . TABLE_VARIANTS . ' as vog2 ON (vot2.id = vog2.id) WHERE vot2.id != vog.id AND t1.geneid = t2.geneid AND vog2.allele = 20 AND vot2.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . '))) OR (vog.allele = 20 AND EXISTS (SELECT vot3.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot3 INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t3 ON (vot3.transcriptid = t3.id) INNER JOIN ' . TABLE_VARIANTS . ' as vog3 ON (vot3.id = vog3.id) WHERE vot3.id != vog.id AND t1.geneid = t3.geneid AND vog3.allele = 10 AND vot3.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge($aVariantIDs, $aVariantIDs, $aVariantIDs), false)->fetchAllColumn();
            break;
        case 'remove_variants_hom_in_father':
            // This filter is created for Leiden, but we'll keep it next to its MGHA counterpart.
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Sequencing/Father/GenoType` IS NULL OR `VariantOnGenome/Sequencing/Father/GenoType` != "1/1") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_variants_hom_in_mother':
            // NOTE: Leiden is also using this filter. Don't change without consulting them!
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Sequencing/Mother/GenoType` IS NULL OR `VariantOnGenome/Sequencing/Mother/GenoType` != "1/1") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_variants_in_father':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Sequencing/Father/GenoType` IS NULL OR `VariantOnGenome/Sequencing/Father/GenoType` = "" OR `VariantOnGenome/Sequencing/Father/GenoType` = "0/0") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_variants_in_mother':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Sequencing/Mother/GenoType` IS NULL OR `VariantOnGenome/Sequencing/Mother/GenoType` = "" OR `VariantOnGenome/Sequencing/Mother/GenoType` = "0/0") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_obs_count_gte_1_percent':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT JOIN ' . TABLE_VARIANTS . ' AS ovog ON (vog.`VariantOnGenome/DBID` = ovog.`VariantOnGenome/DBID`) LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (ovog.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) WHERE vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ') GROUP BY vog.`VariantOnGenome/DBID` HAVING ((COUNT(DISTINCT s.individualid) - 1) / (SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS . ')) < 0.01', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_obs_count_gte_5_percent':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT JOIN ' . TABLE_VARIANTS . ' AS ovog ON (vog.`VariantOnGenome/DBID` = ovog.`VariantOnGenome/DBID`) LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (ovog.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) WHERE vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ') GROUP BY vog.`VariantOnGenome/DBID` HAVING ((COUNT(DISTINCT s.individualid) - 1) / (SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS . ')) < 0.05', $aVariantIDs, false)->fetchAllColumn();
            break;

        // Filters requested by Arthur at CTP.
        // Remove SNVs with QUAL < 50.
        case 'remove_snv_by_quality_lte_50':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`type` IS NULL OR `type` != "subst" OR (`VariantOnGenome/Sequencing/Quality` > 50 AND `type` = "subst")) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        // Remove InDels with QUAL < 500.
        case 'remove_indel_by_quality_lte_500':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`type` IS NULL OR (`type` NOT IN ("ins","del")) OR (`VariantOnGenome/Sequencing/Quality` > 500 AND `type` IN ("ins","del"))) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        // Remove variants with max gMAF (ExAC, EVS, 1000g) > 0.01, no value is assumed 0.
        case 'remove_with_any_gmaf_gt_1':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/1000Gp3/Frequency` IS NULL OR `VariantOnGenome/1000Gp3/Frequency` <= 0.01) AND (`VariantOnGenome/ExAC/Frequency/Adjusted` IS NULL OR `VariantOnGenome/ExAC/Frequency/Adjusted` <= 0.01) AND (`VariantOnGenome/Frequency/EVS/VEP/European_American` IS NULL OR `VariantOnGenome/Frequency/EVS/VEP/European_American` <= 0.01) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;

        // Seqliner does not have the new frequency columns.
        // Remove variants with max gMAF (EVS, 1000g) > 0.01, no value is assumed 0.
        // NOTE: seqliner data does not have ExAC
        case 'remove_with_any_gmaf_evs_1000g_gt_1':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G/VEP` IS NULL OR `VariantOnGenome/Frequency/1000G/VEP` <= 0.01) AND (`VariantOnGenome/Frequency/EVS/VEP/European_American` IS NULL OR `VariantOnGenome/Frequency/EVS/VEP/European_American` <= 0.01) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_gmaf_evs_1000gp3_gt_1':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/1000Gp3/Frequency` IS NULL OR `VariantOnGenome/1000Gp3/Frequency` <= 0.01) AND (`VariantOnGenome/Frequency/EVS/VEP/European_American` IS NULL OR `VariantOnGenome/Frequency/EVS/VEP/European_American` <= 0.01) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_gmaf_1000gp1':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G/VEP` IS NULL OR `VariantOnGenome/Frequency/1000G/VEP` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;

        case 'select_pharmacogenomics':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot1 USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t1 ON (vot1.transcriptid = t1.id)  
                                                WHERE ((chromosome = "X" AND `VariantOnGenome/DNA` = "g.153760805G>C") OR (t1.geneid IN ("TP53")) OR (`VariantOnGenome/dbSNP` IN ("rs1050828", "rs1050829", "rs1045642", "rs1056892", "rs716274", "rs121434568", "rs11615", "rs3212986", "rs396991", "rs1695", "rs1801133", "rs1801394", "rs4880", "rs1042522", "rs2228001", "rs25487"))) 
                                                AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;

        case 'select_pharmacogenomics_v2':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot1 USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t1 ON (vot1.transcriptid = t1.id)  
                                                WHERE (
                                                    (
                                                        chromosome = "X" AND `VariantOnGenome/DNA` = "g.153760805G>C"
                                                        OR
                                                        `VariantOnGenome/dbSNP` IN ("rs1050828", "rs1050829", "rs1045642", "rs1056892", "rs11615", "rs3212986", "rs1695", "rs1801133", "rs1801394", "rs4880", "rs2228001", "rs1799977")
                                                        OR
                                                        geneid IN ("TP53")
                                                    )
                                                    AND
                                                    (
                                                        `VariantOnGenome/dbSNP` NOT IN ("rs2909430", "rs35850753", "rs9895829", "rs1042522", "rs1642785")
                                                    )
                                                ) 
                                                AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;

        // Variant with ind ratio < 0.5.
        case 'remove_obs_count_ratio_gte_50':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT JOIN ' . TABLE_VARIANTS . ' AS ovog ON (vog.`VariantOnGenome/DBID` = ovog.`VariantOnGenome/DBID`) LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (ovog.id = s2v.variantid) LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) WHERE vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ') GROUP BY vog.`VariantOnGenome/DBID` HAVING ((COUNT(DISTINCT s.individualid) - 1) / (SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS . ')) < 0.5', $aVariantIDs, false)->fetchAllColumn();
            break;
        // End MGHA specific filters. You need to be careful when using anything below this line as it might not work with MGHA custom columns. The following filters are known to work:
        // chromosome_X, is_present_father_1, is_present_father_lte_4, is_present_mother_1, is_present_mother_lte_4, remove_by_quality_lte_100, select_homozygous_or_compound_heterozygous

        // Filters shared with LEIDEN.
        case 'chromosome_X':
        case 'select_variants_on_chr_x':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE chromosome = "X" AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'is_present_father_1':
        case 'select_variants_absent_in_father_high_conf':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Father/VarPresent` = 1 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'is_present_father_lte_4':
        case 'select_variants_absent_in_father_low_conf':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Father/VarPresent` <= 4 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'is_present_mother_1':
        case 'select_variants_absent_in_mother_high_conf':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Mother/VarPresent` = 1 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'is_present_mother_lte_4':
        case 'select_variants_absent_in_mother_low_conf':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Mother/VarPresent` <= 4 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_coding_synonymous':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR vot.`VariantOnTranscript/GVS/Function` != "coding-synonymous") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_utr3':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR vot.`VariantOnTranscript/GVS/Function` != "utr-3") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_utr5':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR vot.`VariantOnTranscript/GVS/Function` != "utr-5") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_utr_or_intronic':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR (vot.`VariantOnTranscript/GVS/Function` != "utr-3" AND vot.`VariantOnTranscript/GVS/Function` != "utr-5" AND !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 8))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_utr_or_intronic_gt_20':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR (vot.`VariantOnTranscript/GVS/Function` != "utr-3" AND vot.`VariantOnTranscript/GVS/Function` != "utr-5" AND !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 20))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_utr_or_intronic_or_synonymous':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR (vot.`VariantOnTranscript/GVS/Function` != "utr-3" AND vot.`VariantOnTranscript/GVS/Function` != "utr-5" AND !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 8) AND vot.`VariantOnTranscript/GVS/Function` != "coding-synonymous")) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_function_utr_or_intronic_gt_20_or_synonymous':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR (vot.`VariantOnTranscript/GVS/Function` != "utr-3" AND vot.`VariantOnTranscript/GVS/Function` != "utr-5" AND !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 20) AND vot.`VariantOnTranscript/GVS/Function` != "coding-synonymous")) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_indb_count_hc_gte_1':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/InhouseDB/Count/HC` IS NULL OR `VariantOnGenome/InhouseDB/Count/HC` < 1) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_indb_count_hc_gte_2':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/InhouseDB/Count/HC` IS NULL OR `VariantOnGenome/InhouseDB/Count/HC` < 2) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_indb_count_hc_gte_5':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/InhouseDB/Count/HC` IS NULL OR `VariantOnGenome/InhouseDB/Count/HC` < 5) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_indb_count_ug_gte_1':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/InhouseDB/Count/UG` IS NULL OR `VariantOnGenome/InhouseDB/Count/UG` < 1) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_indb_count_ug_gte_2':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/InhouseDB/Count/UG` IS NULL OR `VariantOnGenome/InhouseDB/Count/UG` < 2) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_indb_count_ug_gte_5':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/InhouseDB/Count/UG` IS NULL OR `VariantOnGenome/InhouseDB/Count/UG` < 5) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_quality_lte_15':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Quality` > 15 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_by_quality_lte_100':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Quality` > 100 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_in_gene_blacklist':
            // Implement the blacklists passed on to this filter.
            // Regardless of success, we need to show the selected blacklists.
            $aSelectedGenePanels = (empty($aConfig['blacklists'])? array() : $aConfig['blacklists']);

            $aGenes = array();
            foreach ($aSelectedGenePanels as $nGpID) {
                $aGenes = array_merge($aGenes, $aConfig['metadata'][$nGpID]['genes']);
            }

            // If no genes selected, then return all variants.
            if (empty($aGenes)) {
                $aVariantIDsFiltered = $aVariantIDs;
                break; // switch case statement break.
            }

            // Don't drop VOGs without a gene.
            $sSQL = 'SELECT DISTINCT CAST(vog.id AS UNSIGNED)
                     FROM ' . TABLE_VARIANTS . ' AS vog
                     LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id)
                     LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)
                     WHERE (t.geneid IS NULL OR t.geneid NOT IN (?' . str_repeat(', ?', count($aGenes)-1) . '))
                        AND vog.id IN (? ' . str_repeat(', ?', count($aVariantIDs) - 1) . ')';
            $aSQL = array_merge($aGenes, $aVariantIDs);
            $aVariantIDsFiltered = $_DB->q($sSQL, $aSQL, false)->fetchAllColumn();

            break;
        case 'remove_intronic_distance_gt_2':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 2)) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_intronic_distance_gt_8':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 8)) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_intronic_distance_gt_20':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR !(vot.`VariantOnTranscript/GVS/Function` = "intron" AND vot.`VariantOnTranscript/Distance_to_splice_site` > 20)) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_missense_with_phylop_lte_2.5':
            // Als SNPs en ALS missense (dus exonic): phyloP>2.5 OF wobble base (3e base codon) bewaren
            //   (voor mezelf: wobble base posities hebben een lagere phyloP score, vandaar de controle)
            // ALS SNPs, en intronisch: phyloP>2.5 bewaren
            // Added an IFNULL() around PhyloP to convert NULL into a high value to prevent it from being filtered out.
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE (vot.`VariantOnTranscript/GVS/Function` IS NULL OR (!(vog.type = "subst" AND vot.`VariantOnTranscript/GVS/Function` = "missense" AND IFNULL(vog.`VariantOnGenome/Conservation_score/PhyloP`, 99) <= 2.5 AND vot.position_c_start%3 != 0) AND !(vog.type = "subst" AND vot.`VariantOnTranscript/GVS/Function` = "intron" AND vog.`VariantOnGenome/Conservation_score/PhyloP` <= 2.5))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_not_imprinted':
            // The imprinted list could be looked up, but since the name can change and we know the ID, we'll just use that.
            $nDiseaseID = 931;
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vot.id AS UNSIGNED) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) INNER JOIN ' . TABLE_GEN2DIS . ' AS g2d USING (geneid) WHERE g2d.diseaseid = ? AND vot.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge(array($nDiseaseID), $aVariantIDs), false)->fetchAllColumn();
            break;
        case 'remove_not_in_gene_panel':
            // NOTE: THIS FILTER HAS BEEN DISABLED IN FAVOR OF THE apply_selected_gene_panels FILTER.
            // IT IS KEPT JUST SO THAT THE OLD ANALYSES DON'T BREAK, BUT IT SHOULDN'T FILTER ANYMORE.
            // Should you wish to re-enable it, remove the "false &&" from the if() below.

            // First, fetch disease ID from current individual. We will get the current individual by querying the database using the first variant.
            $aDiseaseIDs = $_DB->q('SELECT i2d.diseaseid FROM ' . TABLE_IND2DIS . ' AS i2d INNER JOIN ' . TABLE_SCREENINGS . ' AS s USING (individualid) INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) WHERE s2v.variantid = ?', array($aVariantIDs[0]))->fetchAllColumn();
            if (false && $aDiseaseIDs) {
                $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vot.id AS UNSIGNED) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) INNER JOIN ' . TABLE_GEN2DIS . ' AS g2d USING (geneid) WHERE g2d.diseaseid IN (?' . str_repeat(', ?', count($aDiseaseIDs) - 1) . ') AND vot.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge($aDiseaseIDs, $aVariantIDs), false)->fetchAllColumn();
            } else {
                // No disease. So no genes to select for. Just pretend this filter doesn't exist.
                $aVariantIDsFiltered = $aVariantIDs;
            }
            break;
        case 'apply_selected_gene_panels':
            // Regardless of success, we need to show the selected gene panels.
            $aSelectedGenePanels = (empty($aConfig['gene_panels'])? array() : $aConfig['gene_panels']);

            // General formula: (all selected genes - blacklist genes) + custom panel genes.
            $aGenes = array(
                'gene_panel' => array(), // Groups gene panels and mendeliome panels.
                'blacklist' => array(),
                'custom_panel' => array(),
            );

            // Get the list of genes in gene panels from 'metadata' in the $aConfig array.
            foreach ($aSelectedGenePanels as $sType => $aGpIDs) {
                foreach ($aGpIDs as $sGpID) {
                    $sKey = (isset($aGenes[$sType])? $sType : 'gene_panel');
                    $aGenes[$sKey] = array_merge($aGenes[$sKey], $aConfig['metadata'][$sGpID]['genes']);
                }
            }

            // If no gene panels selected, then return all variants.
            if (empty($aGenes['gene_panel']) && empty($aGenes['custom_panel']) && empty($aGenes['blacklist'])) {
                $aVariantIDsFiltered = $aVariantIDs;
                break; // switch case statement break.
            }

            // If ONLY blacklist is selected.
            if (empty($aGenes['gene_panel']) && empty($aGenes['custom_panel']) && !empty($aGenes['blacklist'])) {
                $sGeneSelection = 'NOT IN';
                $aSelectedGenes = $aGenes['blacklist'];
            } else {
                // General formula: (all selected genes - blacklist genes) + custom panel genes.
                $sGeneSelection = 'IN';
                $aSelectedGenes = array_diff($aGenes['gene_panel'], $aGenes['blacklist']);
                $aSelectedGenes = array_merge($aSelectedGenes, $aGenes['custom_panel']);
            }

            // (all selected genes - blacklist genes) + custom panel genes = 0.
            // We don't need to run the SQL query. Return no variants.
            if (empty($aSelectedGenes)) {
                $aVariantIDsFiltered = array();
                break; // switch case statement break.
            }

            // All other cases, we need to run the SQL query.
            $sSQL = 'SELECT DISTINCT CAST(vot.id AS UNSIGNED)
                     FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot
                     INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id)
                     WHERE t.geneid ' . $sGeneSelection . ' (?' . str_repeat(', ?', count($aSelectedGenes)-1) . ')
                        AND vot.id IN (? ' . str_repeat(', ?', count($aVariantIDs) - 1) . ')';

            $aSQL = array_merge($aSelectedGenes, $aVariantIDs);
            $aVariantIDsFiltered = $_DB->q($sSQL, $aSQL, false)->fetchAllColumn();

            break;
        case 'cross_screenings':
            if (empty($aConfig)) {
                // No config whatsoever means the configuration was skipped, so just go on and do nothing.
                $aVariantIDsFiltered = $aVariantIDs;
                break;
            }
            if (empty($aConfig['groups'])) {
                die(json_encode(array('result' => false, 'message' => 'Incomplete configuration for filter \'' . $sFilter . '\'.')));
            }

            $aVariantIDsFiltered = $aVariantIDs;

            // Loop through each group and narrow down the selected variant IDs after SQL of each group is run.
            foreach ($aConfig['groups'] as $aGroup) {
                // There is no need to filter further if there is no variant found here.
                if (empty($aVariantIDsFiltered)) {
                    break;
                }

                if (empty($aGroup['condition']) || empty($aGroup['grouping'])) {
                    die(json_encode(array('result' => false, 'message' => 'Incomplete configuration for filter \'' . $sFilter . '\'.')));
                }

                // Each item in $aGroup['screenings'] array is formatted screeningid:role.
                // Here we only need the screening ID.
                $aScreeningIDs = array();
                foreach ($aGroup['screenings'] as $sGroup) {
                    $aParts = explode(':', $sGroup);
                    $aScreeningIDs[] = $aParts[0];
                }

                // IN or NOT IN the variants in the group.
                switch(strtolower($aGroup['condition'])) {
                    case 'in':
                    case 'homozygous in':
                    case 'heterozygous in':
                        $sSQLCondition = 'IN';
                        break;
                    case 'not in':
                    case 'not homozygous in':
                        $sSQLCondition = 'NOT IN';
                        break;
                }

                // SQL Query to find all variants in the group.
                $sSQLVariantsInGroup = '
                  SELECT DISTINCT vog2.`VariantOnGenome/DBID`
                  FROM ' . TABLE_SCR2VAR . ' s2v2
                  INNER JOIN ' . TABLE_VARIANTS . ' vog2 ON (s2v2.variantid = vog2.id AND s2v2.screeningid IN (?' . str_repeat(', ?', count($aScreeningIDs)-1) . '))';

                // Heterozygous and Homozygous queries need additional condition.
                if (in_array(strtolower($aGroup['condition']), array('homozygous in', 'not homozygous in'))) {
                    if (lovd_verifyInstance('mgha', false)) {
                        $sSQLVariantsInGroup .= ' WHERE (vog2.`allele` = 0 AND vog2.`VariantOnGenome/Sequencing/Allele/Frequency` >= 1) OR (vog2.`allele` = 3)';
                    } else {
                        $sSQLVariantsInGroup .= ' WHERE  vog2.`allele` = 3';
                    }
                } elseif (in_array(strtolower($aGroup['condition']), array('heterozygous in'))) {
                    if (lovd_verifyInstance('mgha', false)) {
                        $sSQLVariantsInGroup .= ' WHERE (vog2.`allele` = 0 AND vog2.`VariantOnGenome/Sequencing/Allele/Frequency` < 1) OR (vog2.`allele` != 3)';
                    } else {
                        $sSQLVariantsInGroup .= ' WHERE  vog2.`allele` != 3';
                    }
                }

                // Additional query when screenings are grouped with 'AND' condition.
                if (strtolower($aGroup['grouping']) == 'and') {
                    $sSQLVariantsInGroup .= ' GROUP BY vog2.`VariantOnGenome/DBID` HAVING COUNT(DISTINCT screeningid) = ' . count($aScreeningIDs);
                }

                // Construct the full query.
                // NOTE:
                // - The use if 'SELECT *' in the subquery is to make the query a non-correlated query, therefore it will run faster.
                // - We no longer need to join it with TABLE_SCR2VAR because our variants is already limited to the list in $aVariantIDsFiltered.
                $sSQL = 'SELECT DISTINCT CAST(vog.id AS UNSIGNED)
                         FROM ' . TABLE_VARIANTS . ' vog 
                         WHERE vog.`VariantOnGenome/DBID` ' . $sSQLCondition . ' (SELECT * FROM (' . $sSQLVariantsInGroup . ') AS subquery)
                            AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDsFiltered) - 1) . ')';

                // If we add more queries in the future, we need to watch out for the order of the params.
                $aSQL = array_merge($aScreeningIDs, $aVariantIDsFiltered);
                $aVariantIDsFiltered = $_DB->q($sSQL, $aSQL, false)->fetchAllColumn();
            }

            break;
        case 'remove_with_any_frequency':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/dbSNP` IS NULL OR `VariantOnGenome/dbSNP` = "") AND (`VariantOnGenome/Frequency/1000G` IS NULL OR `VariantOnGenome/Frequency/1000G` = 0) AND (`VariantOnGenome/Frequency/GoNL` IS NULL OR `VariantOnGenome/Frequency/GoNL` = 0) AND (`VariantOnGenome/Frequency/EVS` IS NULL OR `VariantOnGenome/Frequency/EVS` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_gt_2':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G` IS NULL OR `VariantOnGenome/Frequency/1000G` <= 0.02) AND (`VariantOnGenome/Frequency/GoNL` IS NULL OR `VariantOnGenome/Frequency/GoNL` <= 0.02) AND (`VariantOnGenome/Frequency/EVS` IS NULL OR `VariantOnGenome/Frequency/EVS` <= 0.02) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_gt_3':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G` IS NULL OR `VariantOnGenome/Frequency/1000G` <= 0.03) AND (`VariantOnGenome/Frequency/GoNL` IS NULL OR `VariantOnGenome/Frequency/GoNL` <= 0.03) AND (`VariantOnGenome/Frequency/EVS` IS NULL OR `VariantOnGenome/Frequency/EVS` <= 0.03) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_1000G':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G` IS NULL OR `VariantOnGenome/Frequency/1000G` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_dbSNP':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/dbSNP` IS NULL OR `VariantOnGenome/dbSNP` = "") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_EVS':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/EVS` IS NULL OR `VariantOnGenome/Frequency/EVS` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_gnomAD':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/GnomAD` IS NULL OR `VariantOnGenome/Frequency/GnomAD` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_goNL':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/GoNL` IS NULL OR `VariantOnGenome/Frequency/GoNL` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_frequency_1000G_gt_2':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G` IS NULL OR `VariantOnGenome/Frequency/1000G` <= 0.02) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_frequency_1000G_gt_3':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G` IS NULL OR `VariantOnGenome/Frequency/1000G` <= 0.03) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_frequency_gnomAD_gt_2':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/GnomAD` IS NULL OR `VariantOnGenome/Frequency/GnomAD` <= 0.02) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_frequency_gnomAD_gt_3':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/GnomAD` IS NULL OR `VariantOnGenome/Frequency/GnomAD` <= 0.03) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'select_filtervcf_dot_or_pass':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Sequencing/Filter` IS NULL OR `VariantOnGenome/Sequencing/Filter` = "" OR `VariantOnGenome/Sequencing/Filter` = "." OR `VariantOnGenome/Sequencing/Filter` = "PASS") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'select_gatkcaller_ug_hc':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Sequencing/GATKcaller` REGEXP "[[:<:]]UG[[:>:]]" AND `VariantOnGenome/Sequencing/GATKcaller` REGEXP "[[:<:]]HC[[:>:]]") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'select_homozygous_or_compound_heterozygous':
            // NOTE: Filter is the same as MGHA's select_homozygous_or_potential_compound_het.
            // NOTE: This filter removes all variants that do not have a gene annotated, even if they're homozygous.
            // This is quite a weak implementation of the compound heterozygous check; variants are kept
            //  if they're homozygous or if at least one other variants exists in the same gene.
            // This filter does not rely on the allele field (besides the hom/het status).
            // If parent's GT values are known and the allele field can contain 10s and 20s (paternal/maternal alleles),
            //  then it's recommended to use a more stringent filter:
            //  select_homozygous_or_heterozygous_not_from_one_parent (Leiden)
            //  select_homozygous_or_candidate_compound_het (MGHA)
            //  select_homozygous_or_confirmed_compound_het (MGHA)
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot1 USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t1 ON (vot1.transcriptid = t1.id) WHERE (vog.allele = 3 OR EXISTS (SELECT vot2.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot2 INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t2 ON (vot2.transcriptid = t2.id) WHERE vot2.id != vog.id AND t1.geneid = t2.geneid AND vot2.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . '))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge($aVariantIDs, $aVariantIDs), false)->fetchAllColumn();
            break;
        case 'select_homozygous_or_heterozygous_not_from_one_parent':
            // This filter is a new implementation of Leiden's select_homozygous_or_compound_heterozygous.
            // This is a better implementation as it uses the allele field to check for compound heterozygozity.
            // Also, this filter deliberately does *not* remove genomic variants without a gene annotated.
            // Variants are kept if they're homozygous or
            //  don't have a gene or
            //  if they're heterozygous and
            //    grouped by gene, are not all from one parent (allele=10/11/20/21) and
            //    grouped by gene, are more than one.
            // Note that among a homozygous variant, a single heterozygous variant with allele = 0, will be discarded.
            // This filter is similar to the MGHA's select_homozygous_or_candidate_compound_het implementation,
            //  but has a simpler query buildup, does not discard genomic variants, and does not discard all
            //  heterozygous variants with allele = 0.
            $_DB->q('SET group_concat_max_len = 500000'); // Wouldn't likely need anything even close to this, but oh well.
            // We need to run an array_unique over it, because variants may be mapped to multiple genes.
            $aVariantIDsFiltered = array_unique(explode(',',
                $_DB->q('SELECT GROUP_CONCAT(ids) FROM (SELECT GROUP_CONCAT(DISTINCT CAST(vog.id AS UNSIGNED)) AS ids, GROUP_CONCAT(DISTINCT LEFT(vog.allele, 1) ORDER BY vog.allele) AS alleles, t.geneid FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) WHERE vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ') GROUP BY (vog.allele = "3"), t.geneid HAVING geneid IS NULL OR !(alleles IN ("1", "2") OR (ids NOT LIKE "%,%" AND alleles = "0")))A', $aVariantIDs, false)->fetchColumn()));
            break;

        // Filters for Lymphoma flagship (mgha_seq instance)
        case 'remove_no_cosmicid_intron_variant':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE ((`VariantOnGenome/COSMIC_IDs` IS NOT NULL AND `VariantOnGenome/COSMIC_IDs` != "") OR `VariantOnGenome/Consequence` != "intron_variant") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_no_cosmicid_5_prime_UTR_variant':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE ((`VariantOnGenome/COSMIC_IDs` IS NOT NULL AND `VariantOnGenome/COSMIC_IDs` != "") OR `VariantOnGenome/Consequence` != "5_prime_UTR_variant") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_no_cosmicid_3_prime_UTR_variant':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE ((`VariantOnGenome/COSMIC_IDs` IS NOT NULL AND `VariantOnGenome/COSMIC_IDs` != "") OR `VariantOnGenome/Consequence` != "3_prime_UTR_variant") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_no_cosmicid_synonymous_variant':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE ((`VariantOnGenome/COSMIC_IDs` IS NOT NULL AND `VariantOnGenome/COSMIC_IDs` != "") OR `VariantOnGenome/Consequence` != "synonymous_variant") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;

        // Filters for Lymphoma flagship (mgha_seq instance)
        case 'remove_no_cosmicid_intron_variant_v2':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE ((vot.`VariantOnTranscript/dbNSFP/COSMIC/ID` IS NOT NULL AND vot.`VariantOnTranscript/dbNSFP/COSMIC/ID` != "") OR vot.`VariantOnTranscript/Consequence_Type` != "intron_variant") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_no_cosmicid_5_prime_UTR_variant_v2':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE ((vot.`VariantOnTranscript/dbNSFP/COSMIC/ID` IS NOT NULL AND vot.`VariantOnTranscript/dbNSFP/COSMIC/ID` != "") OR vot.`VariantOnTranscript/Consequence_Type` != "5_prime_UTR_variant") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_no_cosmicid_3_prime_UTR_variant_v2':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE ((vot.`VariantOnTranscript/dbNSFP/COSMIC/ID` IS NOT NULL AND vot.`VariantOnTranscript/dbNSFP/COSMIC/ID` != "") OR vot.`VariantOnTranscript/Consequence_Type` != "3_prime_UTR_variant") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_no_cosmicid_synonymous_variant_v2':
            $aVariantIDsFiltered = $_DB->q('SELECT DISTINCT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot USING (id) WHERE ((vot.`VariantOnTranscript/dbNSFP/COSMIC/ID` IS NOT NULL AND vot.`VariantOnTranscript/dbNSFP/COSMIC/ID` != "") OR vot.`VariantOnTranscript/Consequence_Type` != "synonymous_variant") AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_tumour_alt_depth_lte_5':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Sequencing/Tumour/BI/Depth/Alt` is NULL OR `VariantOnGenome/Sequencing/Tumour/BI/Depth/Alt` < 0.000001 OR `VariantOnGenome/Sequencing/Tumour/BI/Depth/Alt` > 5) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_tumour_allele_freq_lte_3_percent':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Sequencing/Tumour/BI/Allele/Frequency` IS NULL OR `VariantOnGenome/Sequencing/Tumour/BI/Allele/Frequency` < 0.000001 OR `VariantOnGenome/Sequencing/Tumour/BI/Allele/Frequency` > 0.03) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_tumour_alt_depth_lte_20_no_somatic_score':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE ((`VariantOnGenome/Sequencing/Somatic/Score` IS NOT NULL AND `VariantOnGenome/Sequencing/Somatic/Score` != "") OR (`VariantOnGenome/Sequencing/Tumour/BI/Depth/Alt` IS NULL OR `VariantOnGenome/Sequencing/Tumour/BI/Depth/Alt` < 0.000001 OR `VariantOnGenome/Sequencing/Tumour/BI/Depth/Alt` > 20)) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_seq_qual_lte_100_no_somatic_score':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE ((`VariantOnGenome/Sequencing/Somatic/Score` IS NOT NULL AND `VariantOnGenome/Sequencing/Somatic/Score` != "") OR (`VariantOnGenome/Sequencing/Quality` IS NULL OR `VariantOnGenome/Sequencing/Quality` < 0.000001 OR `VariantOnGenome/Sequencing/Quality` > 100)) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_tumour_allele_freq_lte_10_percent_no_somatic_score':
            $aVariantIDsFiltered = $_DB->q('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE ((`VariantOnGenome/Sequencing/Somatic/Score` IS NOT NULL AND `VariantOnGenome/Sequencing/Somatic/Score` != "") OR (`VariantOnGenome/Sequencing/Tumour/BI/Allele/Frequency` IS NULL OR `VariantOnGenome/Sequencing/Tumour/BI/Allele/Frequency` < 0.000001 OR `VariantOnGenome/Sequencing/Tumour/BI/Allele/Frequency` > 0.1)) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        default:
            // Filter not recognized... Oh, dear... We didn't define it yet?
            die(json_encode(array('result' => false, 'message' => 'Filter \'' . $sFilter . '\' not recognized. Are you sure it\'s defined? If it is, this is an error in the software.')));
    }
    if ($aVariantIDsFiltered === false) {
        // Query error...
        die(json_encode(array('result' => false, 'message' => 'Software error: Filter \'' . $sFilter . '\' returned a query error. Please tell support to check the logs.')));
    }
} else {
    $aVariantIDsFiltered = array();
}
$tEnd = microtime(true);
$nTimeSpent = round($tEnd - $tStart);

// Update database.
if (!$_DB->q('UPDATE ' . TABLE_ANALYSES_RUN_FILTERS . ' SET filtered_out = ?, run_time = ? WHERE runid = ? AND filterid = ?', array((count($aVariantIDs) - count($aVariantIDsFiltered)), $nTimeSpent, $nRunID, $sFilter), false)) {
    die(json_encode(array('result' => false, 'message' => 'Software error: Error saving filter step results. Please tell support to check the logs.')));
}

// Now update the session.
$aVariantIDs = $aVariantIDsFiltered; // Will cascade into the $_SESSION variable.
array_shift($aFilters); // Will cascade into the $_SESSION variable.

// Done! Check if we need to run another filter.
if ($aFilters) {
    // Still more to do.
    // FIXME: This script now returns JSON as well as simple return values. Standardize this.
    die(json_encode(
        array(
            'result' => true,
            'sFilterID' => $sFilter,
            'nVariantsLeft' => count($aVariantIDs),
            'nTime' => lovd_convertSecondsToTime($nTimeSpent, 1),
            'sFilterConfig' => lovd_getFilterConfigHTML($nRunID, $sFilter),
            'bDone' => false
        )
    ));
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
    die(json_encode(
        array(
            'result' => true,
            'sFilterID' => $sFilter,
            'nVariantsLeft' => $nVariants,
            'nTime' => lovd_convertSecondsToTime($nTimeSpent, 1),
            'sFilterConfig' => lovd_getFilterConfigHTML($nRunID, $sFilter),
            'bDone' => true
        )
    ));
}
?>
