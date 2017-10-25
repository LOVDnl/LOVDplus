<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-05-03
 * Modified    : 2017-08-04
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Anthony Marty <anthony.marty@unimelb.edu.au>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
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




function getGenePanelLastModifiedDate($sGpId) {
    global $_DB;

    $sSQL = 'SELECT valid_from FROM ' . TABLE_GP2GENE_REV . ' WHERE genepanelid = ? ORDER BY valid_from DESC LIMIT 1';
    $aSQL = array($sGpId);
    $sModified = $_DB->query($sSQL, $aSQL)->fetchColumn();

    return $sModified;
}




function getSelectedFilterConfig ($nRunID, $sFilterID)
{
    // This function gathers the configuration of the selected filter run, and returns
    //  the text that should be printed below the filter in the analysis display.
    global $_DB, $_SETT;

    // Read filter configurations.
    $sConfig = $_DB->query('SELECT config_json FROM ' . TABLE_ANALYSES_RUN_FILTERS . ' WHERE runid = ? AND filterid = ?', array($nRunID, $sFilterID))->fetchColumn();
    $aConfig = (empty($sConfig)? array() : json_decode($sConfig, true));

    switch ($sFilterID) {
        case 'apply_selected_gene_panels':
            return getSelectedGenePanelsByRunID($aConfig, $nRunID);
            break;

        case 'cross_screenings':
            $aConditions = $_SETT['filter_cross_screenings']['condition_list'];
            $aGrouping = $_SETT['filter_cross_screenings']['grouping_list'];

            $sToolTip = '';
            $sConfigText = '';

            if (!empty($aConfig['description'])) {
                // Collect all screening IDs so that we can run just one SQL query to retrieve screening data.
                $aScreeningIDs = array();
                foreach ($aConfig['groups'] as $aGroup) {
                    foreach ($aGroup['screenings'] as $sKey => $sScreening) {
                        list($nScreeningID) = explode(':', $sScreening);
                        $aScreeningIDs[] = $nScreeningID;
                    }
                }

                // Find available screenings.
                $sSQL = 'SELECT s.*, i.`Individual/Sample_ID`
                         FROM ' . TABLE_SCREENINGS . ' AS s 
                         JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) 
                         WHERE s.id IN (?' . str_repeat(', ?', count($aScreeningIDs)-1) . ')';
                $aSQL = $aScreeningIDs;

                $zScreenings = $_DB->query($sSQL, $aSQL)->fetchAllAssoc();
                $aScreenings = array();
                foreach ($zScreenings as $aScreening) {
                    $aScreenings[$aScreening['id']] = $aScreening;
                }

                foreach ($aConfig['groups'] as $aGroup) {
                    if (empty($sToolTip)) {
                        $sToolTip .= 'Select variants from this screening that are';
                    } else {
                        $sToolTip .= '<BR>Then select variants <strong>from the results of the above selection</strong> that are';
                    }

                    $sToolTip .= ' ' . $aConditions[$aGroup['condition']] .
                                 ' ' . $aGrouping[$aGroup['grouping']] .
                                 '<UL>';
                    foreach ($aGroup['screenings'] as $sScreening) {
                        list($nScreeningID, $sRole) = explode(':', $sScreening);
                        $nScreeningText = (!$sRole? '' : $sRole . ': ') . $aScreenings[$nScreeningID]['Individual/Sample_ID'];
                        $sToolTip .= '<LI>' . $nScreeningText . '</LI>';
                    }
                    $sToolTip .= '</UL>';
                }

                $sConfigText = '<TABLE><TR onmouseover="lovd_showToolTip(\'' . htmlspecialchars($sToolTip) . '\', this, [100, -10]);"><TD>' .
                    $aConfig['description'] .
                    '</TD></TR></TABLE>';
            }

            return $sConfigText;
            break;

        default:
            return '';
    }
}






function getSelectedGenePanelsByRunID ($aConfig, $nRunID)
{
    // This function will construct a table of information about the selected gene panels for an analysis.
    global $_DB;

    // Only need to display gene panels information if there are gene panels selected.
    $sGenePanelsInfo = '';
    if (!empty($aConfig['gene_panels'])) {
        //    Example gene panel config:
        //    It has two main fields:
        //      - gene_panels: the list of gene panels used subgrouped by gene panel type
        //      - metadata: data about each gene panel keyed by gene panel ID or 'custom_panel' for custom panel (it does not have a db id)
        //    This structure allow easy access to the details of each gene panel once we know the gene panel id
        //
        //    {
        //       "gene_panels":{
        //          "gene_panel":[
        //             "00001"
        //          ],
        //          "custom_panel":[
        //             "custom_panel"
        //          ]
        //       },
        //       "metadata":{
        //          "00001":{
        //             "last_modified":"2017-04-12 07:21:10",
        //             "name" : "Special Gene Panel",
        //             "genes":[
        //                "ABCC6",
        //                "ABCC8"
        //             ]
        //          },
        //          "custom_panel":{
        //             "name" : "",
        //             "genes":[
        //                "SOX2"
        //             ],
        //             "last_modified":"2017-08-04 13:46:06"
        //          }
        //       }
        //    }

        // Get the gene panel ids used by this filter.
        $aGpIds = array_keys($aConfig['metadata']);
        // Keep only gene panels that are identified using their database gene panel IDs.
        // Basically, remove the custom panel, if present.
        $aGpIds = array_values(array_filter($aGpIds, 'ctype_digit'));

        if (!empty($aGpIds)) {
            $aGpNames = $_DB->query('SELECT id, name FROM ' . TABLE_GENE_PANELS . ' WHERE id IN (?'. str_repeat(', ?', count($aGpIds)-1) . ')', $aGpIds)->fetchAllGroupAssoc();
        }
        if (!empty($aConfig['gene_panels']['custom_panel'])) {
            $aGpNames['custom_panel'] = array('name' => 'Custom Panel');
        }

        foreach ($aConfig['gene_panels'] as $sType => $aGenePanelIds) {
            switch($sType) {
                case 'custom_panel':
                    // Add the custom panel info to the table.
                    $aCustomPanelGenes = $aConfig['metadata']['custom_panel']['genes'];
                    $sCustomPanel = implode(', ', $aCustomPanelGenes);
                    $sToolTip = '<B>Custom&nbsp;panel</B><BR>' . $sCustomPanel;

                    // If there are less than 5 genes in a custom gene panel, simply display all the gene symbols.
                    $sCustomPanelDisplayText = (count($aCustomPanelGenes) <= 5? ': ' . $sCustomPanel : '(' . count($aCustomPanelGenes) . ' genes)');
                    $sGenePanelsInfo .= '<TR onmouseover="lovd_showToolTip(\'' . htmlspecialchars($sToolTip) . '\', this, [100, -10]);"><TD>Custom panel '. $sCustomPanelDisplayText .'</TD></TR>' . "\n";
                    break;
                default:
                    // Format each of the gene panel types into the info table.
                    $sDisplayText = '';
                    $nGenePanelCount = count($aGenePanelIds);
                    $sToolTip = '<B>' . ucfirst(str_replace('_', '&nbsp;', $sType)) . ($nGenePanelCount > 1? 's' : '') . '</B>';
                    $sToolTip .= ' <A onclick="lovd_showGenes(' . $nRunID . ')">Click for details</A><BR>';

                    foreach ($aGenePanelIds as $aGenePanelId) {
                        // Add the gene panel name to the tooltip and the text to show. We might shorten the text to show later.
                        $sGpName = (empty($aConfig['metadata'][$aGenePanelId]['name'])? $aGpNames[$aGenePanelId]['name'] : $aConfig['metadata'][$aGenePanelId]['name']);
                        $sToolTip .=  str_replace(' ', '&nbsp;', addslashes($sGpName)) . '<BR>';
                        $sDisplayText .= (!$sDisplayText? '' : ', ') . $sGpName;
                    }

                    // If there is more than 1 of each type of gene panel selected, then display the summary.
                    if ($nGenePanelCount > 1) {
                        $sDisplayText = $nGenePanelCount . ' ' . ucfirst(str_replace('_', ' ', $sType)) . 's';
                    }

                    $sGenePanelsInfo .= '<TR onmouseover="lovd_showToolTip(\'' . htmlspecialchars($sToolTip) . '\', this, [100, -10]);"><TD>' . $sDisplayText . '</TD><TD>&nbsp;</TD></TR>' . "\n";
            }
        }
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
