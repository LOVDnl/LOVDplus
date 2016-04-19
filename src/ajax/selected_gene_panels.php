<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-19
 * Modified    : 2016-04-19
 * For LOVD    : 3.0-12
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Anthony Marty <anthony.marty@unimelb.edu.au>
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

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', '../');
}
require_once ROOT_PATH . 'inc-init.php';

if (!$_AUTH) {
    die(0);
}
$sRunMethod = '';
$sCustomPanel = '';
$aGenePanels = array();
// Work out how this script is being run.
if (!empty($_GET['runid']) && ctype_digit($_GET['runid'])) {
    // This is being run from ajax so lets get the data.
    $sRunMethod = 'ajax';
    $sCustomPanel = $_DB->query('SELECT custom_panel FROM ' . TABLE_ANALYSES_RUN . ' WHERE id = ?', array($_GET['runid']))->fetchColumn();
    $aGenePanels = $_DB->query('SELECT gp.id, gp.name, gp.type FROM ' . TABLE_AR2GP . ' AS ar2gp JOIN ' . TABLE_GENE_PANELS . ' AS gp on (ar2gp.genepanelid = gp.id) WHERE ar2gp.runid = ? ORDER BY gp.type DESC, gp.name ASC;', array($_GET['runid']))->fetchAllRow();

} else if (!empty($zAnalysis['__gene_panels']) || !empty($zAnalysis['custom_panel'])) {
    // This is being run inline so the data should already be available.
    $sRunMethod = 'inline';
    if (!empty($zAnalysis['custom_panel'])) {
        $sCustomPanel = $zAnalysis['custom_panel'];
    }
    if (!empty($zAnalysis['__gene_panels'])) {
        list($aGenePanels) = $_DATA->autoExplode(array('__0' => $zAnalysis['__gene_panels']));
    }

} else {
    // This script is not being run correctly.
    die(0);
}

$sGenePanelsInfo = '';
$aGenePanelsFormatted = array();
// Explode the gene panels into an array.
foreach ($aGenePanels as $aGenePanel) {
    // Add this gene panel to this type.
    $aGenePanelsFormatted[$aGenePanel[2]][] = array('id' => $aGenePanel[0], 'name' => $aGenePanel[1]);
}

foreach ($aGenePanelsFormatted as $sType => $aGenePanels) {
    // Format each of the gene panel types into the info table.
    $nGenePanelCount = count($aGenePanels);
    $sToolTop = '<DIV class=\'S11\'><B>' . ucfirst(str_replace('_', ' ', $sType)) . ($nGenePanelCount > 1? 's' : '') . '</B><BR>';

    foreach ($aGenePanels as $aGenePanel) {
        $sToolTop .= '<A href=\'gene_panels/' . $aGenePanel['id'] . '\'>' . $aGenePanel['name'] . '</A><BR>';
    }
    $sToolTop .= '</DIV>';
    $sGenePanelsInfo .= '<TR onmouseover="lovd_showToolTip(\'' . addslashes($sToolTop) . '\', this, [100, -10]);"><TD>' . $nGenePanelCount . '</TD><TD>' . ucfirst(str_replace('_', ' ', $sType)) . ($nGenePanelCount > 1? 's' : '') . '</TD><TD>&nbsp;</TD></TR>' . "\n";
}

if ($sCustomPanel) {
    // Add the custom panel info to the table.
    $aCustomPanelGenes = explode(', ', $sCustomPanel);
    $sToolTop = '<DIV class=\'S11\'><B>Custom panel</B><BR>' . $sCustomPanel . '</DIV>';
    $sGenePanelsInfo .= '<TR onmouseover="lovd_showToolTip(\'' . addslashes($sToolTop) . '\', this, [100, -10]);"><TD>1</TD><TD>Custom panel</TD><TD>(' . count($aCustomPanelGenes) . ' genes)</TD></TR>' . "\n";
}

// Layout for how the table should look like once the gene panels have been processed.
$sGenePanelsInfo = '<TABLE border="0" cellpadding="0" cellspacing="1" class="gpinfo">' . $sGenePanelsInfo . '</TABLE>';

if ($sRunMethod == 'ajax') {
    print($sGenePanelsInfo);
}

?>
