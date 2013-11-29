<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-06
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
if (empty($_GET['runid']) || !ctype_digit($_GET['runid'])) {
    die(AJAX_DATA_ERROR);
}



// Check if run exists.
$nRunID = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_ANALYSES_RUN . ' WHERE id = ?', array($_GET['runid']))->fetchColumn();
if (!$nRunID) {
    die('Analysis run not recognized. If the analysis is defined properly, this is an error in the software.');
}

// Check if session var exists.
if (empty($_SESSION['analyses'][$nRunID]) || empty($_SESSION['analyses'][$nRunID]['filters']) || empty($_SESSION['analyses'][$nRunID]['IDsLeft'])) {
    die('Analysis run data not found. It\'s either not your analysis run, it\'s already done, or you have been logged out.');
}



// OK, let's start, get filter information.
$aFilters = &$_SESSION['analyses'][$nRunID]['filters'];
list(,$sFilter) = each($aFilters);

// Run filter, but only if there are variants left.
$aVariantIDs = &$_SESSION['analyses'][$nRunID]['IDsLeft'];
$tStart = microtime(true);
if ($aVariantIDs) {
    switch ($sFilter) {
        case 'chromosome_X':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE chromosome = "X" AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'is_present_father_1':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Father/VarPresent` = 1 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'is_present_mother_1':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Mother/VarPresent` = 1 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'read_perc_in_mother_lt_75':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/Sequencing/Mother/Depth/Alt/Fraction` < 75 AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/dbSNP` IS NULL OR `VariantOnGenome/dbSNP` = "") AND (`VariantOnGenome/Frequency/1000G` IS NULL OR `VariantOnGenome/Frequency/1000G` = 0) AND (`VariantOnGenome/Frequency/GoNL` IS NULL OR `VariantOnGenome/Frequency/GoNL` = 0) AND (`VariantOnGenome/Frequency/EVS` IS NULL OR `VariantOnGenome/Frequency/EVS` = 0) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
            break;
        case 'remove_with_any_frequency_gt_3':
            $aVariantIDsFiltered = $_DB->query('SELECT CAST(id AS UNSIGNED) FROM ' . TABLE_VARIANTS . ' WHERE (`VariantOnGenome/Frequency/1000G` IS NULL OR `VariantOnGenome/Frequency/1000G` <= 0.03) AND (`VariantOnGenome/Frequency/GoNL` IS NULL OR `VariantOnGenome/Frequency/GoNL` <= 0.03) AND (`VariantOnGenome/Frequency/EVS` IS NULL OR `VariantOnGenome/Frequency/EVS` <= 0.03) AND id IN (?' . str_repeat(', ?', count($aVariantIDs) - 1) . ')', $aVariantIDs, false)->fetchAllColumn();
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
