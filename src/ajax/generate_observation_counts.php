<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-01-12
 * Modified    : 2017-01-12
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
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

if (!empty($_POST['nVariantId'])) {
    $nID = $_POST['nVariantId'];
} else {
    $aResults = array('error' => 'Failed to upload Observation Counts data');
    print(json_encode($aResults));
    exit;
}

if (!lovd_isAuthorized('variant', $nID)) {
    $aResults = array('error' => 'You do not have permission to generate Observation Counts for this variant.');
    print(json_encode($aResults));
    exit;
}

require_once ROOT_PATH . 'class/ObservationCounts.php';
//$aColumns = (!empty($_INSTANCE_CONFIG['observation_counts']['columns'])? $_INSTANCE_CONFIG['observation_counts']['columns'] : array());
//$aCategories = (!empty($_INSTANCE_CONFIG['observation_counts']['categories'])? $_INSTANCE_CONFIG['observation_counts']['categories'] : array());
$aSettings = (!empty($_INSTANCE_CONFIG['observation_counts'])? $_INSTANCE_CONFIG['observation_counts'] : array());
$zObsCount = new LOVD_ObservationCounts($nID);
$aData = $zObsCount->buildData($aSettings);

if (empty($aData) && !$zObsCount->canUpdateData()) {
    $aResults = array('error' => 'Current analysis status or your user permission does not allow Observation Counts data to be updated.');
    print(json_encode($aResults));
    exit;
}

$aResults = array('success' => array(
    'data' => json_encode($zObsCount->getData()),
    'timestamp' => date('d M Y h:ia', $zObsCount->getTimeGenerated())
));

print(json_encode($aResults));