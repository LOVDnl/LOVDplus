<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-05
 * Modified    : 2013-11-06
 * For LOVD    : 3.0-09
 *
 * Copyright   : 2004-2013 Leiden University Medical Center; http://www.LUMC.nl/
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
if (empty($_GET['individualid']) || empty($_GET['analysisid']) || !ctype_digit($_GET['individualid']) || !ctype_digit($_GET['analysisid'])) {
    die(AJAX_DATA_ERROR);
}



// Find individual data, make sure we have the right to analyze this patient.
$zIndividual = $_DB->query('SELECT id FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ? AND (analysis_statusid = ? OR analysis_by = ?)', array($_GET['individualid'], ANALYSIS_STATUS_READY, $_AUTH['id']))->fetchAssoc();
if (!$zIndividual) {
    die(AJAX_FALSE);
}

// Check if analysis exists.
$zAnalysis = $_DB->query('SELECT id, filters FROM ' . TABLE_ANALYSES . ' WHERE id = ?', array($_GET['analysisid']))->fetchAssoc();
if (!$zAnalysis || !$zAnalysis['filters']) {
    die('Analysis not recognized or no filters defined. If the analysis is defined properly, this is an error in the software.');
}

// Check if this analysis has not been run before. If so, return a specific error. If somehow things don't complete, we should maybe just delete them and run again.
//   Otherwise, this check needs to be modified.
if ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_ANALYSES_RUN . ' AS ar WHERE ar.analysisid = ? AND ar.individualid = ? AND modified = 0', array($zAnalysis['id'], $zIndividual['id']))->fetchColumn()) {
    die('This analysis has already been performed on this individual.');
}





// All checked. Lock individual.
$_DB->beginTransaction();
if (!$_DB->query('UPDATE ' . TABLE_INDIVIDUALS . ' SET analysis_statusid = ?, analysis_by = ?, analysis_date = NOW() WHERE id = ? AND (analysis_statusid = ? OR analysis_by = ?)', array(ANALYSIS_STATUS_IN_PROGRESS, $_AUTH['id'], $zIndividual['id'], ANALYSIS_STATUS_READY, $_AUTH['id']))->rowCount()) {
    die(AJAX_FALSE);
}

// Create analysis in database.
$q = $_DB->query('INSERT INTO ' . TABLE_ANALYSES_RUN . ' VALUES (NULL, ?, ?, 0, ?, NOW())', array($zAnalysis['id'], $zIndividual['id'], $_AUTH['id']));
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
$_DB->commit();





// Get info for analysis and store in session.
if (empty($_SESSION['analyses'])) {
    $_SESSION['analyses'] = array();
}
// Store individualid and filters in session.
$_SESSION['analyses'][$nRunID] =
    array(
        'individualid' => (int) $zIndividual['id'], // (int) is to prevent zerofill from messing things up.
        'filters' => $aFilters,
        'IDsLeft' => array()
    );

// Collect variant IDs and store in session.
$_SESSION['analyses'][$nRunID]['IDsLeft'] = $_DB->query('SELECT DISTINCT CAST(s2v.variantid AS UNSIGNED) FROM ' . TABLE_SCR2VAR . ' AS s2v INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) WHERE s.individualid = ?', array($zIndividual['id']))->fetchAllColumn();

// Instruct page to start running filters in sequence.
die(AJAX_TRUE . ' ' . $nRunID);
?>
