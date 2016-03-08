<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-05-01
 * Modified    : 2016-03-02
 * For LOVD    : 3.0-12
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

// Set the variant to be confirmed (or not), for selected variants from a ViewList.
if (!empty($_GET['id']) && $_AUTH && ACTION && in_array(ACTION, array('set', 'unset'))) {
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
    if ($_AUTH['level'] < LEVEL_MANAGER) {
        $aScreeningIDs = $_DB->query('SELECT DISTINCT s2v.screeningid FROM ' . TABLE_SCR2VAR . ' AS s2v WHERE s2v.variantid IN (?' . str_repeat(', ?', count($aIDs) - 1) . ')', $aIDs)->fetchAllColumn();
        if (count($aScreeningIDs) > 1) {
            // Somehow the IDs passed are linked to two different screenings. Not allowed.
            die('9|IDs belong to multiple screenings.');
        }
        // Check rights of user on screening.
        if (!lovd_isAuthorized('screening_analysis', $aScreeningIDs[0])) {
            // Either returned false or 0. Both are bad in this case.
            die('9|No authorization to edit this screening.');
        }
    }

    $nSwitched = 0;
    $q = $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET to_be_confirmed = CAST(? AS UNSIGNED) WHERE id IN (?' . str_repeat(', ?', count($aIDs) - 1) . ')', array_merge(array((ACTION == 'set'? 1 : 0)), $aIDs), false);
    if ($q) {
        $nSwitched = $q->rowCount();
        if ($_GET['id'] == 'selected') {
            $_SESSION['viewlists']['CustomVL_AnalysisRunResults_for_I_VE']['checked'] = array(); // To clean up.
        }
    }
    die((string) ($nSwitched > 0) . ' ' . $nSwitched);
}
?>
