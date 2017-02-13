<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-05-03
 * Modified    : 2016-05-25
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Anthony Marty <anthony.marty@unimelb.edu.au>
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}





function getSelectedGenePanelsByRunID ($nRunID)
{
    // This function will construct a table of information about the selected gene panels for an analysis.
    global $_DB;

    $sGenePanelsInfo = '';
    $aGenePanelsFormatted = array();

    // Load up the gene panel data.
    $sCustomPanel = $_DB->query('SELECT custom_panel FROM ' . TABLE_ANALYSES_RUN . ' WHERE id = ?', array($nRunID))->fetchColumn();
    $aGenePanels = $_DB->query('SELECT gp.id, gp.name, gp.type FROM ' . TABLE_AR2GP . ' AS ar2gp INNER JOIN ' . TABLE_GENE_PANELS . ' AS gp on (ar2gp.genepanelid = gp.id) WHERE ar2gp.runid = ? ORDER BY gp.type DESC, gp.name ASC', array($nRunID))->fetchAllGroupAssoc();

    foreach ($aGenePanels as $nGenePanelID => $aGenePanel) {
        // Group the gene panels together and make sub arrays to contain the gene panel information.
        $aGenePanelsFormatted[$aGenePanel['type']][] = array('id' => $nGenePanelID, 'name' => $aGenePanel['name']);
    }

    foreach ($aGenePanelsFormatted as $sType => $aGenePanels) {
        // Format each of the gene panel types into the info table.
        $sDisplayText = '';
        $nGenePanelCount = count($aGenePanels);
        $sToolTip = '<B>' . ucfirst(str_replace('_', '&nbsp;', $sType)) . ($nGenePanelCount > 1? 's' : '') . '</B><BR>';

        foreach ($aGenePanels as $aGenePanel) {
            $sToolTip .= '<A href="gene_panels/' . $aGenePanel['id'] . '">' . str_replace(' ', '&nbsp;', addslashes($aGenePanel['name'])) . '</A><BR>';
            $sDisplayText = $aGenePanel['name'];
        }

        // If there is more than 1 of each type of gene panel selected, then display the summary.
        if ($nGenePanelCount > 1) {
            $sDisplayText = $nGenePanelCount . ' ' . ucfirst(str_replace('_', ' ', $sType)) . 's';
        }

        $sGenePanelsInfo .= '<TR onmouseover="lovd_showToolTip(\'' . htmlspecialchars($sToolTip) . '\', this, [100, -10]);"><TD>' . $sDisplayText . '</TD><TD>&nbsp;</TD></TR>' . "\n";
    }

    if ($sCustomPanel) {
        // Add the custom panel info to the table.
        $sToolTip = '<B>Custom&nbsp;panel</B><BR>' . $sCustomPanel;
        $aCustomPanelGenes = explode(', ', $sCustomPanel);

        // If there is less than 5 genes in a custome gne panel, simply display all the gene symbols.
        $sCustomPanelDisplayText = (count($aCustomPanelGenes) <= 5? ': ' . $sCustomPanel : '(' . count($aCustomPanelGenes) . ' genes)');
        $sGenePanelsInfo .= '<TR onmouseover="lovd_showToolTip(\'' . htmlspecialchars($sToolTip) . '\', this, [100, -10]);"><TD>Custom panel '. $sCustomPanelDisplayText .'</TD></TR>' . "\n";
    }

    if (!$sGenePanelsInfo) {
        // Display a message if there is gene panel selected for this analysis.
        $sGenePanelsInfo = '<TR><TD>No gene panels selected</TD></TR>';
    }

    // Layout for how the table should look like once the gene panels have been processed.
    $sGenePanelsInfo = '<TABLE border="0" cellpadding="0" cellspacing="1" class="gpinfo">' . $sGenePanelsInfo . '</TABLE>';

    return $sGenePanelsInfo;
}
?>
