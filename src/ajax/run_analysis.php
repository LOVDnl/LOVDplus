<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-05
 * Modified    : 2014-02-05
 * For LOVD    : 3.0-10
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

// Require collaborator clearance.
if (!$_AUTH || $_AUTH['level'] < LEVEL_COLLABORATOR) {
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
$zIndividual = $_DB->query('SELECT i.id FROM ' . TABLE_INDIVIDUALS . ' AS i INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) WHERE s.id = ? AND (i.analysis_statusid = ? OR i.analysis_by = ?)', array($_GET['screeningid'], ANALYSIS_STATUS_READY, $_AUTH['id']))->fetchAssoc();
if (!$zIndividual) {
    die(AJAX_FALSE);
}

if ($_GET['runid']) {
    // Check if the run exists.
    $zAnalysisRun = $_DB->query('SELECT id, GROUP_CONCAT(filterid SEPARATOR ";") AS _filters FROM ' . TABLE_ANALYSES_RUN . ' AS ar INNER JOIN ' . TABLE_ANALYSES_RUN_FILTERS . ' AS arf ON (ar.id = arf.runid) WHERE id = ?', array($_GET['runid']))->fetchAssoc();
    if (!$zAnalysisRun) {
        die('Analysis run not recognized. If the analysis run is defined properly, this is an error in the software.');
    }

    // Check if this analysis run has not started before. Can't start twice... (maybe a restart option is needed later?)
    if ($_DB->query('SELECT COUNT(*), IFNULL(MAX(arf.run_time)>-1, 0) AS analysis_run FROM ' . TABLE_ANALYSES_RUN . ' AS ar INNER JOIN ' . TABLE_ANALYSES_RUN_FILTERS . ' AS arf ON (ar.id = arf.runid) WHERE ar.id = ? GROUP BY ar.id HAVING analysis_run = 1', array($zAnalysisRun['id']))->fetchColumn()) {
        die('This analysis has already been performed on this screening.');
    }

} else {
    // Check if analysis exists.
    $zAnalysis = $_DB->query('SELECT id, filters FROM ' . TABLE_ANALYSES . ' WHERE id = ?', array($_GET['analysisid']))->fetchAssoc();
    if (!$zAnalysis || !$zAnalysis['filters']) {
        die('Analysis not recognized or no filters defined. If the analysis is defined properly, this is an error in the software.');
    }

    // Check if this analysis has not been run before. If so, return a specific error. If somehow things don't complete, we should maybe just delete them and run again.
    //   Otherwise, this check needs to be modified.
    if ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_ANALYSES_RUN . ' AS ar WHERE ar.analysisid = ? AND ar.screeningid = ? AND modified = 0', array($zAnalysis['id'], $_GET['screeningid']))->fetchColumn()) {
        die('This analysis has already been performed on this screening.');
    }
}





// All checked. Update individual. We already have checked that we're allowed to analyze this one. So just update the settings, if not already done before.
$_DB->beginTransaction();
$_DB->query('UPDATE ' . TABLE_INDIVIDUALS . ' SET analysis_statusid = ?, analysis_by = ?, analysis_date = NOW() WHERE id = ? AND (analysis_statusid = ? OR analysis_by IS NULL OR analysis_date IS NULL)', array(ANALYSIS_STATUS_IN_PROGRESS, $_AUTH['id'], $zIndividual['id'], ANALYSIS_STATUS_READY));

if (!$_GET['runid']) {
    // Create analysis in database.
    $q = $_DB->query('INSERT INTO ' . TABLE_ANALYSES_RUN . ' VALUES (NULL, ?, ?, 0, ?, NOW())', array($zAnalysis['id'], $_GET['screeningid'], $_AUTH['id']));
    if (!$q) {
        $_DB->rollBack();
        die('Failed to create analysis run in the database. If the analysis is defined properly, this is an error in the software.');
    }
    $nRunID = (int) $_DB->lastInsertId(); // (int) is to prevent zerofill from messing things up.

    // Insert filters...
    $aFilters = preg_split('/\s+/', $zAnalysis['filters']);
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
}
$_DB->commit();





// Get info for analysis and store in session.
if (empty($_SESSION['analyses'])) {
    $_SESSION['analyses'] = array();
}
// Store screeningid and filters in session.
$_SESSION['analyses'][$nRunID] =
    array(
        'screeningid' => (int) $_GET['screeningid'], // (int) is to prevent zerofill from messing things up.
        'filters' => $aFilters,
        'IDsLeft' => array()
    );

// Collect variant IDs and store in session.
$_SESSION['analyses'][$nRunID]['IDsLeft'] = $_DB->query('SELECT DISTINCT CAST(s2v.variantid AS UNSIGNED) FROM ' . TABLE_SCR2VAR . ' AS s2v WHERE s2v.screeningid = ?', array($_GET['screeningid']))->fetchAllColumn();

// Instruct page to start running filters in sequence.
die(AJAX_TRUE . ' ' . str_pad($nRunID, 5, '0', STR_PAD_LEFT));
?>
