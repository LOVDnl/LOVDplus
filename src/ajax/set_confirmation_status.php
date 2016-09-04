<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-08-12
 * Modified    : 2016-08-12
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Anthony Marty <anthony.marty@unimelb.edu.au>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

// Set the confirmation status for selected variants from a ViewList.
if (!empty($_GET['id']) && $_AUTH && ACTION !== false && isset($_SETT['confirmation_status'][ACTION])) {
    // The easiest thing to do is just run the query, and just dump the result.
    if ($_GET['id'] == 'selected') {
        $aIDs = array_values($_SESSION['viewlists']['CustomVL_AnalysisRunResults_for_I_VE']['checked']);
    } elseif (is_array($_GET['id'])) {
        $aIDs = $_GET['id'];
    } else {
        $aIDs = array($_GET['id']);
    }

    // Nobody should be able to hack their way through this. We might just assume that the user is authorized for editing the
    // selections in the SESSION array because the interface should have checked, but let's not make any assumptions here.
    // IDs should lead to only one screening. IDs' screening/analysis should be owned by the user.
    $zScreenings = $_DB->query('SELECT s.* FROM ' . TABLE_SCREENINGS . ' AS s INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) WHERE s2v.variantid IN (?' . str_repeat(', ?', count($aIDs) - 1) . ') GROUP BY s.id', $aIDs)->fetchAllAssoc();
    $bAuthorized = lovd_isAuthorized('screening_analysis', $zScreenings[0]['id']);
    if ($_AUTH['level'] < LEVEL_MANAGER) {
        if (count($zScreenings) > 1) {
            // Somehow the IDs passed are linked to two different screenings. Not allowed.
            die('9|IDs belong to multiple screenings.');
        }
        // Check rights of user on screening.
        if (!$bAuthorized) {
            // Either returned false or 0. Both are bad in this case.
            die('9|No authorization to edit this screening.');
        }
    }
    if (!($_AUTH['level'] >= LEVEL_OWNER && $zScreenings[0]['analysis_statusid'] < ANALYSIS_STATUS_CLOSED) &&
        !($_AUTH['level'] >= LEVEL_MANAGER && $zScreenings[0]['analysis_statusid'] < ANALYSIS_STATUS_WAIT_CONFIRMATION)) {
        die('9|Unable to update variants, the analysis status requires a higher user level.');
    }

    $nSwitched = 0;
    $q = $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET confirmation_statusid = CAST(? AS UNSIGNED) WHERE id IN (?' . str_repeat(', ?', count($aIDs) - 1) . ')', array_merge(array(ACTION), $aIDs), false);
    if ($q) {
        $nSwitched = $q->rowCount();
        if ($_GET['id'] == 'selected') {
            $_SESSION['viewlists']['CustomVL_AnalysisRunResults_for_I_VE']['checked'] = array(); // To clean up.
        }
    }
    foreach ($aIDs as $nID) {
        // Write to log...
        lovd_writeLog('Event', 'ConfirmationStatus', 'Updated confirmation status for variant #' . $nID . ' to "' . $_SETT['confirmation_status'][ACTION] . '".');
    }
    die((string) ($nSwitched > 0) . ' ' . $nSwitched);
}
?>
