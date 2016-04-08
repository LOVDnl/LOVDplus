<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-06
 * Modified    : 2015-11-20
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2014 Leiden University Medical Center; http://www.LUMC.nl/
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
    die('Analysis run not recognized. If the analysis is defined properly, this is an error in the software.');
}

// Check if session var exists.
if (empty($_SESSION['analyses'][$nRunID]) || empty($_SESSION['analyses'][$nRunID]['filters']) || !isset($_SESSION['analyses'][$nRunID]['IDsLeft'])) {
    die('Analysis run data not found. It\'s either not your analysis run, it\'s already done, or you have been logged out.');
}



// OK, let's start, get filter information.
$aFilters = &$_SESSION['analyses'][$nRunID]['filters'];
list(,$sFilter) = each($aFilters);

// Run filter, but only if there are variants left.
$aVariantIDs = &$_SESSION['analyses'][$nRunID]['IDsLeft'];
//sleep(2);
$tStart = microtime(true);
if ($aVariantIDs) {
    $aVariantIDsFiltered = false;
    switch ($sFilter) {
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
            if (empty($_SESSION['analyses'][$nRunID]['custom_panel']) && empty($_SESSION['analyses'][$nRunID]['gene_panels'])) {
                $aVariantIDsFiltered = $aVariantIDs;
                break;
            }

            // If we are using a custom panel then load the genes.
            if (empty($_SESSION['analyses'][$nRunID]['custom_panel'])) {
                $sCustomPanel = $_DB->query('SELECT i.custom_panel FROM ' . TABLE_INDIVIDUALS . ' AS i INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) WHERE s2v.variantid = ?', array($aVariantIDs[0]))->fetchColumn();
                $aCustomPanels = explode(', ', $sCustomPanel);
            }

            // Load the selected gene panels into gene panels and blacklists.
            if (!empty($_SESSION['analyses'][$nRunID]['gene_panels'])) {
                $aGenePanels = $_DB->query('SELECT gp.id, gp.type FROM ' . TABLE_GENE_PANELS . ' AS gp WHERE gp.type != "blacklist" and gp.id IN (?' . str_repeat(', ?', count($_SESSION['analyses'][$nRunID]['gene_panels'])-1) . ') ORDER BY gp.type DESC, gp.name ASC', array_values($_SESSION['analyses'][$nRunID]['gene_panels']))->fetchAllCombine();
                $aBlacklists = $_DB->query('SELECT gp.id, gp.type FROM ' . TABLE_GENE_PANELS . ' AS gp WHERE gp.type = "blacklist" and gp.id IN (?' . str_repeat(', ?', count($_SESSION['analyses'][$nRunID]['gene_panels'])-1) . ') ORDER BY gp.type DESC, gp.name ASC', array_values($_SESSION['analyses'][$nRunID]['gene_panels']))->fetchAllCombine();
            }
            
            // Build up the query.
            $q = 'SELECT DISTINCT CAST(vot.id AS UNSIGNED), t.geneid FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) LEFT OUTER JOIN ' . TABLE_GP2GENE . ' AS gp2g ON (t.geneid = gp2g.geneid) WHERE ((';
            $aParam = array();

            // Gene panels.
            if (!empty($aGenePanels)) {
                $q .= 'gp2g.genepanelid IN (?' . str_repeat(', ?', count($aGenePanels)-1) . ')';
                $aParam = array_merge(array_values($aParam), array_keys($aGenePanels));
            } else {
                $q .= 'TRUE';
            }

            $q .= ' AND ';

            // Blacklists.
            if (empty($aBlacklists) || (empty($aGenePanels) && !empty($aCustomPanels))) {
                // If we do not have a black list OR
                // we have a blacklist and a custom panel without a gene panel then don't use the blacklist.
                $q .= 'TRUE';
            } else {
                $q .= 'NOT EXISTS(SELECT * FROM lovd_gene_panels2genes AS bl WHERE bl.genepanelid IN (?' . str_repeat(', ?', count($aBlacklists)-1) . ') AND t.geneid = bl.geneid)';
                $aParam = array_merge(array_values($aParam), array_keys($aBlacklists));
            }

            $q .= ')';

            // Custom panel.
            if (!empty($aCustomPanels)) {
                if ((empty($aBlacklists) && !empty($aGenePanels)) || (!empty($aBlacklists && !empty($aGenePanels)))) {
                    // If we don't have a blacklist but we do have a gene panel OR
                    // if we have a blacklist and a gene panel then we use OR.
                    $q .= ' OR ';
                } else {
                    $q .= ' AND ';
                }
                $q .= 't.geneid IN (?' . str_repeat(', ?', count($aCustomPanels)-1) . ')';
                $aParam = array_merge(array_values($aParam), array_values($aCustomPanels));
            }

            // Add the existing variants to the end of the query.
            $q .= ') AND vot.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')';

//            die($q . print_r($aParam));

            $aVariantIDsFiltered = $_DB->query($q, array_merge($aParam, $aVariantIDs), false)->fetchAllColumn();

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
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Sequencing/GATKcaller` = "UG,HC") AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'select_homozygous_or_compound_heterozygous':
            // FIXME: Problem: Compound heterozygous check means I need the allele column.
            // What do we do with fields where the allele is unknown (de novo?).
            // Currently: two variants in the same gene is enough to trigger the compound heterozygous case, but that is of course not really correct...
            $aVariantIDsFiltered = $_DB->query('SELECT DISTINCT CAST(vog.id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot1 USING (id) INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t1 ON (vot1.transcriptid = t1.id) WHERE (vog.allele = 3 OR EXISTS (SELECT vot2.id FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot2 INNER JOIN ' . TABLE_TRANSCRIPTS . ' AS t2 ON (vot2.transcriptid = t2.id) WHERE vot2.id != vog.id AND t1.geneid = t2.geneid AND vot2.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . '))) AND vog.id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', array_merge($aVariantIDs, $aVariantIDs), false)->fetchAllColumn();
            break;
        default:
            // Filter not recognized... Oh, dear... We didn't define it yet?
            die('Filter \'' . $sFilter . '\' not recognized. Are you sure it\'s defined? If it is, this is an error in the software.');
    }
    if ($aVariantIDsFiltered === false) {
        // Query error...
        die('Software error: Filter \'' . $sFilter . '\' returned a query error. Please tell support to check the logs.');
    }
} else {
    $aVariantIDsFiltered = array();
}
$tEnd = microtime(true);
$nTimeSpent = round($tEnd - $tStart);

// Update database.
if (!$_DB->query('UPDATE ' . TABLE_ANALYSES_RUN_FILTERS . ' SET filtered_out = ?, run_time = ? WHERE runid = ? AND filterid = ?', array((count($aVariantIDs) - count($aVariantIDsFiltered)), $nTimeSpent, $nRunID, $sFilter), false)) {
    die('Software error: Error saving filter step results. Please tell support to check the logs.');
}

// Now update the session.
$aVariantIDs = $aVariantIDsFiltered; // Will cascade into the $_SESSION variable.
array_shift($aFilters); // Will cascade into the $_SESSION variable.

// Done! Check if we need to run another filter.
if ($aFilters) {
    // Still more to do.
    die(AJAX_TRUE . ' ' . $sFilter . ' ' . count($aVariantIDs) . ' ' . lovd_convertSecondsToTime($nTimeSpent, 1));
} else {
    // Since we're done, save the results in the database.
    $q = $_DB->prepare('INSERT INTO ' . TABLE_ANALYSES_RUN_RESULTS . ' VALUES (?, ?)');
    $nVariants = count($aVariantIDs);
    foreach ($aVariantIDs as $nVariantID) {
        $q->execute(array($nRunID, $nVariantID));
    }

    // Now that we're done, clean up after ourselves...
    unset($_SESSION['analyses'][$nRunID]);
    die(AJAX_TRUE . ' ' . $sFilter . ' ' . $nVariants . ' ' . lovd_convertSecondsToTime($nTimeSpent, 1) . ' done');
}
?>
