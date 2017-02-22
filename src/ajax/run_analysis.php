<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-05
 * Modified    : 2016-05-13
 * For LOVD    : 3.0-15
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

// Require collaborator clearance.
if (!$_AUTH || $_AUTH['level'] < LEVEL_ANALYZER) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

// Check if the data sent is correct or not.
if (!isset($_GET['runid'])) {
    $_GET['runid'] = 0;
}
if (empty($_GET['screeningid']) || empty($_GET['analysisid']) || !ctype_digit($_GET['screeningid']) || !ctype_digit($_GET['analysisid']) || !ctype_digit($_GET['runid'])) {
    die(AJAX_DATA_ERROR);
}



// Find screening data, make sure we have the right to analyze this patient.
// MANAGER can always start an analysis, even when the individual's analysis hasn't been started by him.
$sSQL = 'SELECT i.id, i.custom_panel FROM ' . TABLE_INDIVIDUALS . ' AS i INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) WHERE s.id = ? AND s.analysis_statusid < ? AND (s.analysis_statusid = ? OR s.analysis_by ' . ($_AUTH['level'] >= LEVEL_MANAGER? 'IS NOT NULL' : '= ?') . ')';
$aSQL = array($_GET['screeningid'], ANALYSIS_STATUS_CLOSED, ANALYSIS_STATUS_READY);
if ($_AUTH['level'] < LEVEL_MANAGER) {
    $aSQL[] = $_AUTH['id'];
}
$zIndividual = $_DB->query($sSQL, $aSQL)->fetchAssoc();
if (!$zIndividual) {
    die(AJAX_FALSE);
}

$zAnalysis = $zAnalysisRun = false;
if ($_GET['runid']) {
    // Check if the run exists.
    $zAnalysisRun = $_DB->query('SELECT ar.id, ar.analysisid, a.name, GROUP_CONCAT(arf.filterid ORDER BY arf.filter_order SEPARATOR ";") AS _filters FROM ' . TABLE_ANALYSES_RUN . ' AS ar INNER JOIN ' . TABLE_ANALYSES . ' AS a ON (ar.analysisid = a.id) INNER JOIN ' . TABLE_ANALYSES_RUN_FILTERS . ' AS arf ON (ar.id = arf.runid) WHERE ar.id = ?', array($_GET['runid']))->fetchAssoc();
    if (!$zAnalysisRun) {
        die('Analysis run not recognized. If the analysis run is defined properly, this is an error in the software.');
    }

    // Check if this analysis run has not started before. Can't start twice... (maybe a restart option is needed later?)
    if ($_DB->query('SELECT COUNT(*), IFNULL(MAX(arf.run_time)>-1, 0) AS analysis_run FROM ' . TABLE_ANALYSES_RUN . ' AS ar INNER JOIN ' . TABLE_ANALYSES_RUN_FILTERS . ' AS arf ON (ar.id = arf.runid) WHERE ar.id = ? GROUP BY ar.id HAVING analysis_run = 1', array($zAnalysisRun['id']))->fetchColumn()) {
        die('This analysis has already been performed on this screening.');
    }

} else {
    // Check if analysis exists.
    $zAnalysis = $_DB->query('SELECT a.id, a.name, GROUP_CONCAT(a2af.filterid ORDER BY a2af.filter_order SEPARATOR ";") AS _filters FROM ' . TABLE_ANALYSES . ' AS a INNER JOIN ' . TABLE_A2AF . ' AS a2af ON (a.id = a2af.analysisid) WHERE id = ? GROUP BY a.id', array($_GET['analysisid']))->fetchAssoc();
    if (!$zAnalysis || !$zAnalysis['_filters']) {
        die('Analysis not recognized or no filters defined. If the analysis is defined properly, this is an error in the software.');
    }
}

$sCustomPanel = '';
$aGenePanels = array();
// Process any gene panels that may have been passed.
if (!empty($_GET['gene_panels'])) {
    $aGenePanels = array_values($_GET['gene_panels']);
    if(($nKey = array_search('custom_panel', $aGenePanels)) !== false) {
        // If the custom panel has been selected then record this and remove from array.
        $sCustomPanel = $zIndividual['custom_panel'];
        unset($aGenePanels[$nKey]);
    }
}





// All checked. Update individual. We already have checked that we're allowed to analyze this one. So just update the settings, if not already done before.
define('LOG_EVENT', 'AnalysisRun');
$_DB->beginTransaction();
$_DB->query('UPDATE ' . TABLE_SCREENINGS . ' SET analysis_statusid = ?, analysis_by = ?, analysis_date = NOW() WHERE id = ? AND (analysis_statusid = ? OR analysis_by IS NULL OR analysis_date IS NULL)', array(ANALYSIS_STATUS_IN_PROGRESS, $_AUTH['id'], $_GET['screeningid'], ANALYSIS_STATUS_READY));

if (!$_GET['runid']) {
    // Create analysis in database.
    $q = $_DB->query('INSERT INTO ' . TABLE_ANALYSES_RUN . ' VALUES (NULL, ?, ?, 0, ?, ?, NOW())', array($zAnalysis['id'], $_GET['screeningid'], $sCustomPanel, $_AUTH['id']));
    if (!$q) {
        $_DB->rollBack();
        die('Failed to create analysis run in the database. If the analysis is defined properly, this is an error in the software.');
    }
    $nRunID = (int) $_DB->lastInsertId(); // (int) is to prevent zerofill from messing things up.

    // Insert filters...
    $aFilters = explode(';', $zAnalysis['_filters']);
    foreach ($aFilters as $i => $sFilter) {
        $q = $_DB->query('INSERT INTO ' . TABLE_ANALYSES_RUN_FILTERS . ' (runid, filterid, filter_order) VALUES (?, ?, ?)', array($nRunID, $sFilter, ($i+1)));
        if (!$q) {
            $_DB->rollBack();
            die('Failed to create analysis run filter in the database. If the analysis is defined properly, this is an error in the software.');
        }
    }
} else {
    $nRunID = (int) $_GET['runid']; // (int) is to prevent zerofill from messing things up.
    $aFilters = explode(';', $zAnalysisRun['_filters']);
    // Update the existing analyses run record to store the custom panel genes.
    $_DB->query('UPDATE ' . TABLE_ANALYSES_RUN . ' SET custom_panel = ? WHERE id = ?', array($sCustomPanel, $nRunID));
}

// Process the selected gene panels.
// FIXME: This will fail if we already have the run ID in the database. That can
// happen, when somehow the analysis run was created, but didn't start (JS error?).
foreach ($aGenePanels as $nKey => $nGenePanelID) {
    // Write the gene panels selected to the analyses_run2gene_panel table.
    $q = $_DB->query('INSERT INTO ' . TABLE_AR2GP . ' VALUES (?, ?)', array($nRunID, $nGenePanelID));
    if (!$q) {
        $_DB->rollBack();
        die('Failed to store the gene panels for this analysis. This may be a temporary error, or an error in the software.');
    }
}

$_DB->commit();

// Write to log...
lovd_writeLog('Event', LOG_EVENT, 'Started analysis run ' . str_pad($nRunID, 5, '0', STR_PAD_LEFT) . ($zAnalysis? ' (' . $zAnalysis['name'] : ' based on ' . $zAnalysisRun['analysisid'] . ' (' . $zAnalysisRun['name']) . ') on individual ' . $zIndividual['id'] . ':' . str_pad($_GET['screeningid'], 10, '0', STR_PAD_LEFT) . ' with filter(s) \'' . implode('\', \'', $aFilters) . '\'');





// Get info for analysis and store in session.
if (empty($_SESSION['analyses'])) {
    $_SESSION['analyses'] = array();
}
// Store analysis information in the session.
$_SESSION['analyses'][$nRunID] =
    array(
        'screeningid' => (int) $_GET['screeningid'], // (int) is to prevent zerofill from messing things up.
        'filters' => $aFilters,
        'IDsLeft' => array(),
        'custom_panel' => $sCustomPanel,
        'gene_panels' => $aGenePanels,
    );

// Collect variant IDs and store in session.
$_SESSION['analyses'][$nRunID]['IDsLeft'] = $_DB->query('SELECT DISTINCT CAST(s2v.variantid AS UNSIGNED) FROM ' . TABLE_SCR2VAR . ' AS s2v WHERE s2v.screeningid = ?', array($_GET['screeningid']))->fetchAllColumn();

// Instruct page to start running filters in sequence.
die(AJAX_TRUE . ' ' . str_pad($nRunID, 5, '0', STR_PAD_LEFT));
?>
