<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-27
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

// Set the variant effect value for selected variants from a ViewList.
if (!empty($_GET['id']) && $_AUTH && ACTION && in_array(ACTION, array_keys($_SETT['var_effect']))) {
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
    // IDs should lead to only one individual. IDs' individual should be editable by the user.
    if ($_AUTH['level'] < LEVEL_ADMIN) {
        $aIndividualIDs = $_DB->query('SELECT DISTINCT s.individualid FROM ' . TABLE_SCREENINGS . ' AS s INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) WHERE s2v.variantid IN (?' . str_repeat(', ?', count($aIDs) - 1) . ')', $aIDs)->fetchAllColumn();
        if (count($aIndividualIDs) > 1) {
            // Somehow the IDs passed are linked to two different individuals. Not allowed.
            die('9|IDs belong to multiple individuals.');
        }
        // Check rights of user on individual.
        if (!lovd_isAuthorized('individual', $aIndividualIDs[0])) {
            // Either returned false or 0. Both are bad in this case.
            die('9|No authorization to edit this individual.');
        }
    }

    $nSwitched = 0;
    $q = $_DB->query('UPDATE ' . TABLE_VARIANTS . ' SET effectid = CAST(CONCAT(?, RIGHT(effectid, 1)) AS UNSIGNED) WHERE id IN (?' . str_repeat(', ?', count($aIDs) - 1) . ')', array_merge(array(ACTION), $aIDs), false);
    if ($q) {
        $nSwitched = $q->rowCount();
        if ($_GET['id'] == 'selected') {
            $_SESSION['viewlists']['CustomVL_AnalysisRunResults_for_I_VE']['checked'] = array(); // To clean up.
        }
    }
    die((string) ($nSwitched > 0) . ' ' . $nSwitched);
}
?>
