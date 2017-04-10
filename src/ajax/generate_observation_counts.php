<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-01-12
 * Modified    : 2017-03-17
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

if (!empty($_POST['nVariantID'])) {
    $nID = $_POST['nVariantID'];
} else {
    print '<TABLE width="600" class="data">
             <TR><TH style="font-size : 13px;">Observation Counts</TH></TR>
             <TR><TD>Failed to upload Observation Counts data</TD></TR>
           </TABLE>';
    exit;
}

require_once ROOT_PATH . 'class/observation_counts.php';
$aSettings = (!empty($_INSTANCE_CONFIG['observation_counts'])? $_INSTANCE_CONFIG['observation_counts'] : array());
$zObsCount = new LOVD_ObservationCounts($nID);
$aData = $zObsCount->buildData($aSettings);

print($zObsCount->display($aSettings));
