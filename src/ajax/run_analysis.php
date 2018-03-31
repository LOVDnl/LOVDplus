<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-05
 * Modified    : 2018-03-31
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2018 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-analyses.php';

// Require collaborator clearance.
if (!$_AUTH || $_AUTH['level'] < LEVEL_ANALYZER) {
    // If not authorized, die with error message.
    die(AJAX_NO_AUTH);
}

// Check if the data sent is correct or not.
if (!isset($_REQUEST['runid'])) {
    $_REQUEST['runid'] = 0;
}

if (empty($_REQUEST['screeningid']) || empty($_REQUEST['analysisid']) || !ctype_digit($_REQUEST['screeningid']) || !ctype_digit($_REQUEST['analysisid']) || !ctype_digit($_REQUEST['runid'])) {
    die(AJAX_DATA_ERROR);
}



// Find screening data, make sure we have the right to analyze this patient.
// MANAGER can always start an analysis, even when the individual's analysis hasn't been started by him.
$sFamilyColumns = '';
if (!empty($_INSTANCE_CONFIG['columns']['family'])) {
    foreach ($_INSTANCE_CONFIG['columns']['family'] as $sKey => $sColumn) {
        if (strpos($sColumn, 'Screening/') === 0) {
            $sFamilyColumns .= ', s.`' . $sColumn . '`';
        }
    }
}
$sSQL = 'SELECT i.id, i.fatherid, i.motherid, i.custom_panel' . $sFamilyColumns . ',
           GROUP_CONCAT(DISTINCT gp.id, ";", gp.name, ";", gp.type, ";",
             GREATEST(
               IFNULL(
                 (SELECT MAX(gp2g.valid_from)
                  FROM ' . TABLE_GP2GENE_REV . ' AS gp2g
                  WHERE gp2g.genepanelid = gp.id), gp.created_date),
               IFNULL(
                 (SELECT MAX(gp2g.valid_to)
                  FROM ' . TABLE_GP2GENE_REV . ' AS gp2g
                  WHERE gp2g.genepanelid = gp.id AND LEFT(gp2g.valid_to, 10) != "9999-12-31" AND gp2g.deleted = 1), gp.created_date)), ";"
             ORDER BY gp.type DESC, gp.name ASC SEPARATOR ";;") AS __gene_panels
         FROM ' . TABLE_INDIVIDUALS . ' AS i
           INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid)
           LEFT OUTER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i.id = i2gp.individualid)
           LEFT OUTER JOIN ' . TABLE_GENE_PANELS . ' AS gp ON (i2gp.genepanelid = gp.id) 
         WHERE s.id = ? AND s.analysis_statusid < ? AND (s.analysis_statusid = ? OR s.analysis_by ' . ($_AUTH['level'] >= LEVEL_MANAGER? 'IS NOT NULL' : '= ?') . ')';
$aSQL = array($_REQUEST['screeningid'], ANALYSIS_STATUS_CLOSED, ANALYSIS_STATUS_READY);
if ($_AUTH['level'] < LEVEL_MANAGER) {
    $aSQL[] = $_AUTH['id'];
}
$zIndividual = $_DB->query($sSQL, $aSQL)->fetchAssoc();
if (!$zIndividual) {
    die(AJAX_FALSE);
}

$zIndividual['gene_panels'] = array();
if (!empty($zIndividual['__gene_panels'])) {
    foreach (explode(";;", $zIndividual['__gene_panels']) as $sGenePanel) {
        list($sGpId, $sGpName, $sGpType, $sLastModified) = explode(";", $sGenePanel);
        $zIndividual['gene_panels'][$sGpType][$sGpId] = array('name' => $sGpName, 'last_modified' => $sLastModified);
    }
}

// Move custom_panel to $zIndividual['gene_panels'] to make further processing easier.
if (!empty($zIndividual['custom_panel'])) {
    // Use any non-digit values as ID.
    $zIndividual['gene_panels']['custom_panel'] = array('custom_panel' => array('name' => $zIndividual['custom_panel'], 'last_modified' => date('Y-m-d H:i:s')));
}



$zAnalysis = $zAnalysisRun = $zFilterConfig = false;
if ($_REQUEST['runid']) {
    // Check if the run exists and retrieve any existing configurations data of each filter.
    $zAnalysisRun = $_DB->query('SELECT ar.id, ar.analysisid, a.name, GROUP_CONCAT(arf.filterid ORDER BY arf.filter_order SEPARATOR ";") AS _filters
                                 FROM ' . TABLE_ANALYSES_RUN . ' AS ar
                                   INNER JOIN ' . TABLE_ANALYSES . ' AS a ON (ar.analysisid = a.id)
                                   INNER JOIN ' . TABLE_ANALYSES_RUN_FILTERS . ' AS arf ON (ar.id = arf.runid)
                                 WHERE ar.id = ? GROUP BY arf.runid', array($_REQUEST['runid']))->fetchAssoc();
    if (!$zAnalysisRun) {
        die('Analysis run not recognized. If the analysis run is defined properly, this is an error in the software.');
    }

    // Check if this analysis run has not started before. Can't start twice... (maybe a restart option is needed later?)
    if ($_DB->query('SELECT COUNT(*), IFNULL(MAX(arf.run_time)>-1, 0) AS analysis_run FROM ' . TABLE_ANALYSES_RUN . ' AS ar INNER JOIN ' . TABLE_ANALYSES_RUN_FILTERS . ' AS arf ON (ar.id = arf.runid) WHERE ar.id = ? GROUP BY ar.id HAVING analysis_run = 1', array($zAnalysisRun['id']))->fetchColumn()) {
        die('This analysis has already been performed on this screening.');
    }

    // Get the config_json of each filter. The JSON can be too big to be included in group_concat in the query that returns $zAnalysisRun query above.
    $zFilterConfig = $_DB->query('SELECT filterid, config_json FROM ' . TABLE_ANALYSES_RUN_FILTERS . ' WHERE runid = ?', array($_REQUEST['runid']))->fetchAllGroupAssoc();

} else {
    // Check if analysis exists.
    $zAnalysis = $_DB->query('SELECT a.id, a.name, GROUP_CONCAT(a2af.filterid ORDER BY a2af.filter_order SEPARATOR ";") AS _filters FROM ' . TABLE_ANALYSES . ' AS a INNER JOIN ' . TABLE_A2AF . ' AS a2af ON (a.id = a2af.analysisid) WHERE id = ? GROUP BY a.id', array($_REQUEST['analysisid']))->fetchAssoc();
    if (!$zAnalysis || !$zAnalysis['_filters']) {
        die('Analysis not recognized or no filters defined. If the analysis is defined properly, this is an error in the software.');
    }
}





if (ACTION == 'configure' && GET) {
    // To display a filter configuration form if the analysis to be run requires further configurations.

    header('Content-type: text/javascript; charset=UTF-8');

    $aFilters = (!empty($zAnalysisRun['_filters'])? explode(';', $zAnalysisRun['_filters']) : explode(';', $zAnalysis['_filters']));
    $sFiltersFormItems = ''; // The part of the form with filter options.
    $sJsCanSubmit  = 'true'; // String with JS checks that should evaluate to true to get form to submit.
    $sJsOtherFunctions = ''; // String with additional JS code for the form.

    foreach ($aFilters as $sFilter) {
        // Get the filter configuration already stored in the database.
        $aConfig = array();
        if (!empty($zFilterConfig[$sFilter]['config_json'])) {
            $aConfig = json_decode($zFilterConfig[$sFilter]['config_json'], true);
        }

        // Custom build filter configurations form case by case depending on the filter ID.
        switch($sFilter) {
            case 'apply_selected_gene_panels':
                // Display a notice that 'apply_selected_gene_panels' is selected for this analysis.
                $sFiltersFormItems .= '<H4>' . $sFilter . '</H4><BR>';

                // No gene panel has been added to this individual.
                if (empty($zIndividual['gene_panels']) && empty($zIndividual['custom_panel'])) {
                    $sFiltersFormItems .= '<P>There is no Gene Panel assigned to this individual. To continue running this analysis, please try one of the following options: </P>' .
                                          '<UL>' .
                                            '<LI>Add a gene panel to this individual, OR</LI>' .
                                            '<LI>Remove the apply_selected_gene_panels filter from this analysis, OR</LI>' .
                                            '<LI>Continue running this analysis without any gene panel selected.</LI>' .
                                          '</UL>';
                } else {
                    // This individual has at least one gene panel or custom panel.

                    // Define the order in which we want to display the gene panels in the form.
                    // Also set here if we want to check these checkboxes by default.
                    $aGpOrderOfDisplay = array(
                        'gene_panel' => true,
                        'blacklist' => true,
                        'mendeliome' => false,
                        'custom_panel' => true,
                    );
                    $sFiltersFormItems .= '<DIV class=\'filter-config\' id=\'filter-config-'. $sFilter . '\'><TABLE>';
                    foreach ($aGpOrderOfDisplay as $sGpType => $bDefaultSelect) {
                        if (!empty($zIndividual['gene_panels'][$sGpType])) {
                            $sFiltersFormItems .= '<TR><TD class=\'gpheader\' colspan=\'2\' style=\'border-top: 0px;\'>' . ucfirst(str_replace('_', ' ', $sGpType)) . '</TD></TR>';
                            foreach ($zIndividual['gene_panels'][$sGpType] as $sGpId => $aGpDetails) {
                                $sGpName = $aGpDetails['name'];
                                $sCheckboxToRunOldGp = '';
                                if (!empty($aConfig['metadata'][$sGpId])) {
                                    if ($sGpType == 'custom_panel') {
                                        // With custom gene panel, we don't have the record of last modified date.
                                        // We just have to compare the list of genes.
                                        $aFilterConfigGenes = $aConfig['metadata'][$sGpId]['genes'];
                                        $aCurrentCustomPanelGenes = preg_split('/[,;]\s?/', $sGpName); // Should be a simple explode on a semicolon if the data were stored that way.
                                        sort($aFilterConfigGenes);
                                        sort($aCurrentCustomPanelGenes);

                                        if ($aFilterConfigGenes !== $aCurrentCustomPanelGenes) {
                                            $sGpName = 'Custom panel';
                                            $sCheckboxToRunOldGp .= '<TR>' .
                                                                      '<TD></TD>' .
                                                                      '<TD><INPUT type=\'radio\' name=\'config[' . $sFilter . '][run_older_version][' . $sGpId . ']\' value=\'0\'> Current version: ' . implode(', ', $aCurrentCustomPanelGenes) . '</TD></TR>';
                                            $sCheckboxToRunOldGp .= '<TR>' .
                                                                      '<TD></TD>' .
                                                                      '<TD><INPUT type=\'radio\' name=\'config[' . $sFilter . '][run_older_version][' . $sGpId . ']\' value=\'1\' checked> The custom panel we copied from: ' . implode(', ', $aFilterConfigGenes) . '</TD></TR>';
                                        }
                                    } else {
                                        $sModified = lovd_getGenePanelLastModifiedDate($sGpId);
                                        // Show options to select current version or the version we cloned from.
                                        if ($sModified && $aConfig['metadata'][$sGpId]['last_modified'] < $sModified) {
                                            $sCheckboxToRunOldGp .= '<TR>' .
                                                                      '<TD></TD>' .
                                                                      '<TD><INPUT type=\'radio\' name=\'config[' . $sFilter . '][run_older_version][' . $sGpId . ']\' value=\'0\'> Current version</TD></TR>';
                                            $sCheckboxToRunOldGp .= '<TR>' .
                                                                      '<TD></TD>' .
                                                                      '<TD><INPUT type=\'radio\' name=\'config[' . $sFilter . '][run_older_version][' . $sGpId . ']\' value=\'1\' checked> Apply the version of this gene panel as of ' . $aConfig['metadata'][$sGpId]['last_modified'] . '</TD></TR>';
                                        }
                                    }
                                }

                                // By default, do we want to pre-select this gene panel.
                                $sChecked = (!$bDefaultSelect? '' : ' checked');
                                // Check if there is already existing configurations selected.
                                if (!empty($aConfig)) {
                                    // If we copied configurations from another analysis run,
                                    //  then overwrite default configurations.
                                    $sChecked = (empty($aConfig['metadata'][$sGpId])? '' : 'checked');
                                }

                                $sFiltersFormItems .= '<TR>' .
                                                        '<TD><INPUT type=\'checkbox\' name=\'config[' . $sFilter . '][gene_panels][' . $sGpType . '][]\' id=\'gene_panel_' . $sGpId . '\' value=\'' . $sGpId . '\'' . $sChecked . '></TD>' .
                                                        '<TD><LABEL for=\'gene_panel_'. $sGpId .'\'>' . $sGpName . '</LABEL></TD></TR>';
                                $sFiltersFormItems .= $sCheckboxToRunOldGp;
                            }
                        }
                    }

                    $sFiltersFormItems .= '</TABLE></DIV><BR>';
                }

                // Gene panels do not have required fields at the moment, so no real validator needed.
                $sJsOtherFunctions .= 'function isValidGenePanel() {return true;}';
                $sJsCanSubmit .= ' && isValidGenePanel()';

                break;
            case 'cross_screenings':
                $aConditions = $_SETT['filter_cross_screenings']['condition_list'];
                $aGrouping = $_SETT['filter_cross_screenings']['grouping_list'];
                $sLabIDColName = $_INSTANCE_CONFIG['columns']['lab_id'];

                // Find available screenings in the database, which are not archived yet.
                // Order by the LabID column and the screening ID. Note that the former has not been sanitized,
                //  so I'm preg_replacing() it, causing an error when invalid characters exist.
                $sSQL = 'SELECT s.id as screeningid, s.*, i.*
                         FROM ' . TABLE_SCREENINGS . ' AS s 
                           INNER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) 
                         WHERE s.id != ? AND s.analysis_statusid < ?
                         ORDER BY `' . preg_replace('/[^A-Za-z0-9\/_-]/', '', $sLabIDColName) . '`, s.id';
                $aSQL = array($_REQUEST['screeningid'], ANALYSIS_STATUS_ARCHIVED);
                $zScreenings = $_DB->query($sSQL, $aSQL)->fetchAllAssoc();

                // Build the strings to represent the other screenings.
                $aScreenings = array(); // All useful screenings in the database; ['screeningid:role_name'] => 'label';
                $aRelatives = array();  // Relatives of current individual; ['screeningid:role_name'] => 'label';
                // Note that we store $aRelatives separately to make sure they sort to the top of the list.
                foreach ($zScreenings as $zScreening) {
                    $sKey = $zScreening['screeningid'] . ':';

                    $sMatchedRole = '';
                    // Try different methods of linking individuals.
                    if ($zScreening['individualid'] == $zIndividual['id']) {
                        // Annotate screenings from same individual.
                        $sMatchedRole = 'same patient';

                    } elseif ($zScreening['individualid'] == $zIndividual['fatherid']) {
                        // Annotate screenings from the father using LOVD3 style fatherid field.
                        $sMatchedRole = 'father';

                    } elseif ($zScreening['individualid'] == $zIndividual['motherid']) {
                        // Annotate screenings from the mother using LOVD3 style motherid field.
                        $sMatchedRole = 'mother';

                    } elseif (!empty($_INSTANCE_CONFIG['columns']['family'])) {
                        // Find role descriptions if sample has family relation with this screening.
                        // Loop all the custom columns that this instance use to identify family relationships.
                        foreach ($_INSTANCE_CONFIG['columns']['family'] as $sRole => $sColumn) {
                            if ($zScreening[$sLabIDColName] == $zIndividual[$sColumn]) {
                                // This individual's lab ID matches the value in
                                //  the analyzed individual's family role column.
                                $sMatchedRole = $sRole;

                                break; // We can stop once we find one. Assuming one role per screening.
                            }
                        }
                    }
                    if ($sMatchedRole) {
                        $sKey .= $sMatchedRole;
                        // Store name of role in $zScreening as well, so that
                        //  the format_screening_name function can use it.
                        $zScreening['role'] = $sMatchedRole;

                        $aRelatives[$sKey] = '';
                    }


                    // Format display name of each screening. Defaults to the lab ID.
                    $sText = $zScreening[$sLabIDColName];

                    // If this instance has its own formatter, use it.
                    if (!empty($_INSTANCE_CONFIG['cross_screenings']['format_screening_name']) &&
                        is_callable($_INSTANCE_CONFIG['cross_screenings']['format_screening_name'])) {
                        $sText = $_INSTANCE_CONFIG['cross_screenings']['format_screening_name']($zScreening);
                    }

                    $aScreenings[$sKey] = $sText;
                    if (isset($aRelatives[$sKey])) {
                        $aRelatives[$sKey] = $sText;
                    }
                }

                // We want screenings of the relatives of this patient to be sorted on top.
                $aScreenings = $aRelatives + $aScreenings;

                // Print form.
                $sFiltersFormItems .= '<H4>' . $sFilter . '</H4><BR>' .
                                      '<DIV class=\'filter-config\' id=\'filter-config-' . $sFilter . '\'>' .
                                      '<BUTTON type=\'button\' class=\'btn-right\' id=\'btn-add-group\'>+ Add group</BUTTON>';

                $sValue = (empty($aConfig['description'])? '' : 'value=\'' . $aConfig['description'] . '\'');
                $sFiltersFormItems .= '<LABEL>Description *</LABEL> &nbsp; <INPUT class=\'required\' name=\'config[' . $sFilter . '][description]\' ' . $sValue . '>';

                $sMsgSelectScreeningsFirst = 'Select variants from this screening that are *';
                $sMsgSelectScreenings = 'Then select variants <STRONG>from the results of the above selection</STRONG> that are *';
                $nGroups = (!isset($aConfig['groups'])? 0 : count($aConfig['groups']));

                // Always print at least one group if there is no existing configurations in the database.
                // Otherwise, loop through the configurations data to see how many groups had been saved.
                for ($i = 0; $i == 0 || $i < $nGroups; $i++) {
                    $sGroupId = 'filter-config-' . $sFilter . '-' . $i;
                    $sFiltersFormItems .= '<DIV class=\'filter-cross-screening-group\' id=\'' . $sGroupId . '\'>';
                    $sMsg = ($i == 0? $sMsgSelectScreeningsFirst : $sMsgSelectScreenings);

                    // Build up form to define the conditions how variants of this screening are compared to the selected group.
                    $sFiltersFormItems .= '<TABLE>' .
                                          '<TR><TD colspan=\'2\'><LABEL class=\'label-info\'>' . $sMsg . '</LABEL>' .

                                          // First group should never be deleted.
                                          '<SPAN ' . ($i == 0? 'style=\'display: none;\'' : '') . ' class=\'filter-cross-screening-delete-group\' data-group=\'' . $sGroupId . '\'>' .
                                            '<IMG alt=\'Remove this group\' src=\'gfx/cross.png\'>' .
                                          '</SPAN>';

                    // Drop down menu that provide options to find variants that exist, does not exist, homozygous, etc in the selected screenings.
                    $sFiltersFormItems .= '</TD></TR>' .
                                          '<TR><TD><SELECT class=\'required\' name=\'config[' . $sFilter . '][groups][' . $i . '][condition]\'>';
                    foreach ($aConditions as $sValue => $sLabel) {
                        $sSelected = (empty($aConfig) || $sValue != $aConfig['groups'][$i]['condition']? '' : " selected='selected'");
                        $sFiltersFormItems .= '<OPTION value=\'' . $sValue . '\'' . $sSelected . '>' . $sLabel . '</OPTION>';
                    }
                    $sFiltersFormItems .= '</SELECT>';

                    // Drop down menu that provide options on how to group among selected screenings within a group.
                    $sFiltersFormItems .= '&nbsp;<SELECT class=\'required\' name=\'config[' . $sFilter . '][groups][' . $i . '][grouping]\'>';
                    foreach ($aGrouping as $sValue => $sLabel) {
                        $sSelected = (empty($aConfig) || $sValue != $aConfig['groups'][$i]['grouping']? '' : " selected='selected'");
                        $sFiltersFormItems .= '<OPTION value=\'' . $sValue . '\'' . $sSelected . '>' . $sLabel . '</OPTION>';
                    }
                    $sFiltersFormItems .= '</SELECT></TD></TR>' .
                                          '<TR><TD colspan=\'2\'><LABEL>the following screenings *</LABEL></TD></TR>';

                    // The list of available screenings in the database.
                    $sFiltersFormItems .= '<TR><TD><SELECT class=\'required\' id=\'select-screenings-' . $i . '\' name=\'config[' . $sFilter . '][groups][' . $i . '][screenings][]\' multiple=\'true\'>';
                    foreach ($aScreenings as $sScreeningID => $sText) {
                        $sSelected = (empty($aConfig) || !in_array($sScreeningID, $aConfig['groups'][$i]['screenings'])? '' : " selected='selected'");
                        $sFiltersFormItems .= '<OPTION value=\'' . $sScreeningID . '\'' . $sSelected . '>' . $sText . '</OPTION>';
                    }
                    $sFiltersFormItems .= '</SELECT></TD></TR></TABLE></DIV>';
                }

                // End of form.
                $sFiltersFormItems .= '</DIV>';

                // Javascripts to make the form more user friendly.
                $sFiltersFormItems .= '<SCRIPT type=\'text/javascript\'>';
                for ($i=0; $i == 0 || $i < $nGroups; $i++) {
                    $sFiltersFormItems .= '$(\'#select-screenings-' . $i . '\').select2({ width: \'555px\'});';
                }

                // Function to update the form when 'Remove group' button is clicked.
                $sFiltersFormItems .= 'var funcRemoveGroup = function() {' .
                                        'var sGroupId = $(this).attr(\'data-group\'); $(\'#\' + sGroupId).remove();' .
                                        '$(\'#configure_analysis_dialog\').trigger(\'change\');' .
                                      '};' .
                                      // We need to call this function for all existing groups.
                                      '$(\'.filter-cross-screening-delete-group\').bind(\'click\', funcRemoveGroup);';

                // Function to update the form when 'Add group' button is clicked.
                $sFiltersFormItems .= 'var numGroups = $(\'.filter-cross-screening-group\').length;' .
                                      '$(\'#btn-add-group\').click(function() {' .
                                        'var elemFilterConfig = $(\'#filter-config-' . $sFilter . '\');' .
                                        // Copy the first group of html form already loaded by php.
                                        'var elemGroup = $(\'#filter-config-' . $sFilter . '-0\').clone().attr(\'id\', \'filter-config-' . $sFilter . '-\' + numGroups);' .
                                        // Rename select-screenings-0 to a new id.
                                        'elemGroup.find(\'[data-group]\').attr(\'data-group\', \'filter-config-' . $sFilter . '-\' + numGroups).show();' .
                                        'elemGroup.find(\'#select-screenings-0\').attr(\'id\', \'select-screenings-\' + numGroups);' .
                                        // Remove the input box created by the select2 plugin.
                                        'elemGroup.find(\'.select2\').remove();' .
                                        // Subsequent groups have different labels.
                                        'elemGroup.find(\'.label-info\').html(\'' . $sMsgSelectScreenings . '\');' .
                                        // Rename 'name' attributes based on how many groups we already have.
                                        'elemGroup.find(\'[name]\').each(function(i, e) { var oldName = $(e).attr(\'name\'); var newName = oldName.replace(\'[groups][0]\', \'[groups][\' + numGroups + \']\'); $(e).attr(\'name\', newName);});' .
                                        // Reset values of the form in the first group that we copy from.
                                        'elemGroup.find(\'[selected]\').each(function(i, e) { $(e).removeAttr(\'selected\'); });' .
                                        // Append this new group into the form.
                                        'elemFilterConfig.append(elemGroup);' .
                                        // We need to call this again for the newly created group.
                                        '$(\'.filter-cross-screening-delete-group\').bind(\'click\', funcRemoveGroup);' .
                                        '$(\'#select-screenings-\' + numGroups).select2({ width: \'555px\'});' .
                                        '$(\'#configure_analysis_dialog\').trigger(\'change\');' .
                                        'numGroups += 1;' .
                                      '});' .
                                      '</SCRIPT>';

                // Here we set the logic that controls whether we have all the
                //  required fields for the cross screenings configuration form.
                $sJsOtherFunctions .= 'function isValidCrossScreenings () {' .
                                        'var bValid = true;' .
                                        // Allow submission if there is only one group and it has nothing filled in.
                                        // This allows somebody to just submit the form without making a choice.
                                        'if ($(\'#filter-config-cross_screenings ul li\').length == 1) { return true; } ' .
                                        '$(\'#filter-config-cross_screenings .required\').each(function(i, e) {' .
                                          'if (!e.value.length) {bValid = false; return;}' .
                                        '});' .
                                        'return bValid;' .
                                      '}';

                // The 'submit' button can only be enabled if it has passed the validation of all filters.
                $sJsCanSubmit .= ' && isValidCrossScreenings()';

                break;
            default:
        }
    }

    // Non filter specific inputs.
    $aInputs = array(
        'analysisid' => $_REQUEST['analysisid'],
        'screeningid' => $_REQUEST['screeningid'],
        'runid' => $_REQUEST['runid'],
        'elementid' => $_REQUEST['elementid']
    );

    $sFormRequiredInputs = '';
    foreach ($aInputs as $sName => $sValue) {
        $sFormRequiredInputs .= '<INPUT type=\'hidden\' name=\'' . $sName . '\' value=\'' . $sValue . '\'>';
    }

    if (empty($sFiltersFormItems)) {
        // If no further configuration is required, simply pass the minimum required inputs.
        print('$.post("' . CURRENT_PATH . '?' . ACTION . '", ' . json_encode($aInputs) . ');');
        exit;
    }

    // If further configuration is required, build modal to take the configuration inputs.
    // Implement an CSRF protection by working with tokens.
    $_SESSION['csrf_tokens']['run_analysis_configure'] = md5(uniqid());
    $sForm  = '<FORM id=\'configure_analysis_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'' . $_SESSION['csrf_tokens']['run_analysis_configure'] . '\'><BR>' .
              $sFiltersFormItems .
              $sFormRequiredInputs .
              '</FORM>';

    // If we get here, we want to show the dialog for sure.
    print('// Make sure we have and show the dialog.
    if (!$("#configure_analysis_dialog").length) {
        $("body").append("<DIV id=\'configure_analysis_dialog\'></DIV>");
    }
    if (!$("#configure_analysis_dialog").hasClass("ui-dialog-content") || !$("#configure_analysis_dialog").dialog("isOpen")) {
        $("#configure_analysis_dialog").dialog({draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:true,hide:"fade",modal:true});
    }
    ');

    // Set JS variables and objects.
    print($sJsOtherFunctions . '
    var oButtonFormSubmit  = {"Submit":function () { $.post("' . CURRENT_PATH . '?' . ACTION . '", $("#configure_analysis_form").serialize()); }};
    var oButtonCancel = {"Cancel":function () { $(this).dialog("close"); }};
    var oButtonClose  = {"Close":function () { $(this).dialog("close"); }};
    ');

    // Display the form, and put the right buttons in place.
    // To update validation rules of each filter, update the isValid...() function of each filter set in $sJsOtherFunctions variable.
    print('
    $("#configure_analysis_dialog").html("' . $sForm . '<BR>");

    // Select the right buttons.
    $("#configure_analysis_dialog").dialog({title: "Configure Analysis", buttons: $.extend({}, oButtonFormSubmit, oButtonCancel)});
    
    var sInfo = \'<SPAN id="filter-config-info"><EM>* Please fill in all required fields</EM></SPAN>\';
    if ($("#filter-config-info").length === 0) {
        $(".ui-dialog-buttonpane").append(sInfo);
    }

    $("#configure_analysis_dialog").change(function() {
        if (' . $sJsCanSubmit . ') {
            var bCanSubmit = "enable";
            $("#filter-config-info").hide();
        } else {
            var bCanSubmit = "disable";
            $("#filter-config-info").show();
        }
        $(".ui-dialog-buttonpane button:contains(\'Submit\')").button(bCanSubmit);
    });
    
    // Ensure we call form validation functions when form is loaded for the first time. 
    $("#configure_analysis_dialog").trigger("change");
    ');
    exit;
}





if (ACTION == 'configure' && POST) {
    header('Content-type: text/javascript; charset=UTF-8');
    $aFormConfig = (empty($_REQUEST['config'])? array() : $_REQUEST['config']);

    // TODO: This is a good place for us to do our form inputs validation or data cleanup.
    if (!empty($_REQUEST['config']) && (empty($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_tokens']['run_analysis_configure'])) {
        die('alert("Error while sending data, possible security risk. Please reload the page, and try again.");');
    }

    // Data cleanup.
    if (!empty($aFormConfig)) {
        foreach (array_keys($aFormConfig) as $sFilter) {
            switch($sFilter) {
                case 'apply_selected_gene_panels':
                    // Get the saved configurations in the database.
                    $aDbConfig = array();
                    if (!empty($zFilterConfig[$sFilter]['config_json'])) {
                        $aDbConfig = json_decode($zFilterConfig[$sFilter]['config_json'], true);
                    }

                    // Configurations submitted by the users through the form.
                    if (empty($aFormConfig[$sFilter]['gene_panels'])) {
                        $aFormConfig[$sFilter]['gene_panels'] = array();
                    }

                    // Remove custom_panel because we don't need it in this SQL query.
                    $aGenePanelIds = array();
                    foreach ($aFormConfig[$sFilter]['gene_panels'] as $sType => $aGpIds) {
                        if (is_array($aGpIds)) {
                            $aGenePanelIds = array_merge($aGenePanelIds, array_filter($aGpIds, 'ctype_digit'));
                        }
                    }

                    // Now, we need to populate the metadata. There are 2 possibilities:
                    // 1. Use the metadata from the analysis we cloned these
                    //    configurations from if the user chooses to do so.
                    // 2. Use the list of genes assigned to this gene panel in the database now.
                    $aFormConfig[$sFilter]['metadata'] = array();

                    // 1. If user chooses to use the older version of the gene panel.
                    //    (the exact same set of genes this gene panel had when this analysis we cloned from was run)
                    $aUseOlderGenePanels = array();
                    // But, prevent notice when this setting is not sent.
                    // The notice will break the JS and make the analysis fail to run.
                    if (!isset($aFormConfig[$sFilter]['run_older_version'])) {
                        $aFormConfig[$sFilter]['run_older_version'] = array();
                    }
                    foreach ($aFormConfig[$sFilter]['run_older_version'] as $sGpId => $bUseOlderVersion) {
                        if (!empty($aFormConfig[$sFilter]['run_older_version'][$sGpId])) {
                            $aFormConfig[$sFilter]['metadata'][$sGpId] = $aDbConfig['metadata'][$sGpId];
                            $aUseOlderGenePanels[] = $sGpId;
                        }
                    }

                    // 2. Use the list of genes assigned to this gene panel in the database now.
                    // First, remove the gene panels whose data we do not need to be fetched from database.
                    $aGenePanelIds = array_values(array_diff($aGenePanelIds, $aUseOlderGenePanels));
                    if (!empty($aGenePanelIds)) {
                        // Increase DB limits to allow concatenation of large number of gene IDs.
                        $_DB->query('SET group_concat_max_len = 500000');
                        $sSQL = 'SELECT gp.id, gp.name, GROUP_CONCAT(gp2g.geneid SEPARATOR ";") AS _genes
                                 FROM ' . TABLE_GP2GENE . ' AS gp2g
                                   INNER JOIN ' . TABLE_GENE_PANELS . ' AS gp ON (gp2g.genepanelid = gp.id)
                                 WHERE gp2g.genepanelid IN (? ' . str_repeat(', ?', count($aGenePanelIds)-1) . ')
                                 GROUP BY gp.id';
                        $zGenePanels = $_DB->query($sSQL, $aGenePanelIds)->fetchAllAssoc();

                        // Populate data for each gene panel.
                        foreach ($zGenePanels as $zGenePanel) {
                            $aFormConfig[$sFilter]['metadata'][$zGenePanel['id']] = array(
                                'last_modified' => lovd_getGenePanelLastModifiedDate($zGenePanel['id']),
                                'name' => $zGenePanel['name'],
                                'genes' => explode(';', $zGenePanel['_genes']),
                            );
                        }
                    }

                    // Populate data for custom panel.
                    // We know we only have one custom panel for now.
                    // But, we store them as an array so that it follows the same storage format of other gene panels.
                    // Let's not make assumption about what array key we use to store this one custom panel in case it is changed.
                    // So, just loop through it like any other array.
                    if (!empty($aFormConfig[$sFilter]['gene_panels']['custom_panel'])) {
                        foreach ($aFormConfig[$sFilter]['gene_panels']['custom_panel'] as $sGpId) {
                            if (empty($aFormConfig[$sFilter]['run_older_version'][$sGpId])) {
                                $aFormConfig[$sFilter]['metadata'][$sGpId] = array(
                                    // Should be a simple explode on a semicolon if the data were stored that way.
                                    'genes' => preg_split('/[,;]\s?/', $zIndividual['gene_panels']['custom_panel'][$sGpId]['name']),
                                    'last_modified' => date('Y-m-d H:i:s'), // We cannot get the data of when custom panel is last modified. Fill in with current date for now.
                                    'name' => '' // placeholder, just to keep metadata structure consistent.
                                );
                            }
                        }
                    }

                    // We don't need to save this in the json.
                    unset($aFormConfig[$sFilter]['run_older_version']);

                    break;
                case 'cross_screenings':
                    // Reset index otherwise json_encode would be converted it to object instead of array.
                    $aFormConfig[$sFilter]['groups'] = array_values($aFormConfig[$sFilter]['groups']);

                    // In case we don't have any data (one group only, no screenings), drop the whole config.
                    if (count($aFormConfig[$sFilter]['groups']) == 1
                        && empty($aFormConfig[$sFilter]['groups'][0]['screenings'])) {
                        unset($aFormConfig[$sFilter]);
                    }
                    break;
            }
        }
    }

    // Now that we know we have all our required inputs for all filters, we can pass it to 'run' to insert/update database entries
    print('
    $("#configure_analysis_dialog").dialog("close");
    lovd_runAnalysis(\'' . $_REQUEST['screeningid'] . '\', \'' . $_REQUEST['analysisid'] . '\', \'' . $_REQUEST['runid'] . '\', \'' . $_REQUEST['elementid'] . '\', ' . json_encode($aFormConfig) . ');
    ');
    exit;
}





if (ACTION == 'run') {
    $aConfig = json_decode($_REQUEST['config'], true);
    $sCustomPanel = '';
    $aSelectedGenePanels = (empty($aConfig['apply_selected_gene_panels']['gene_panels']) ? array() : $aConfig['apply_selected_gene_panels']['gene_panels']);

    // Merge gene_panel and mendeliome gene panels.
    $aGenePanels = (!empty($aSelectedGenePanels['gene_panel'])? $aSelectedGenePanels['gene_panel'] : array());
    $aGenePanels = (!empty($aSelectedGenePanels['mendeliome'])? array_merge($aGenePanels, $aSelectedGenePanels['mendeliome']) : $aGenePanels);

    $sCustomPanel = '';
    if (!empty($aSelectedGenePanels['custom_panel']))  {
        foreach ($aSelectedGenePanels['custom_panel'] as $sKey) {
            // FIXME: It would make more sense to use a standard separator like ";".
            $sCustomPanel .= implode(', ',  $aConfig['apply_selected_gene_panels']['metadata'][$sKey]['genes']);
        }
    }

    // All checked. Update individual. We already have checked that we're allowed to analyze this one. So just update the settings, if not already done before.
    define('LOG_EVENT', 'AnalysisRun');
    $_DB->beginTransaction();
    $_DB->query('UPDATE ' . TABLE_SCREENINGS . ' SET analysis_statusid = ?, analysis_by = ?, analysis_date = NOW() WHERE id = ? AND (analysis_statusid = ? OR analysis_by IS NULL OR analysis_date IS NULL)', array(ANALYSIS_STATUS_IN_PROGRESS, $_AUTH['id'], $_REQUEST['screeningid'], ANALYSIS_STATUS_READY));


    if (!$_REQUEST['runid']) {
        // Create analysis in database.
        $q = $_DB->query('INSERT INTO ' . TABLE_ANALYSES_RUN . ' VALUES (NULL, ?, ?, 0, ?, ?, NOW())', array($zAnalysis['id'], $_REQUEST['screeningid'], $sCustomPanel, $_AUTH['id']));
        if (!$q) {
            $_DB->rollBack();
            die('Failed to create analysis run in the database. If the analysis is defined properly, this is an error in the software.');
        }
        $nRunID = (int) $_DB->lastInsertId(); // (int) is to prevent zerofill from messing things up.

        // Insert filters...
        $aFilters = explode(';', $zAnalysis['_filters']);
        foreach ($aFilters as $i => $sFilter) {
            $sFilterConfig = (empty($aConfig[$sFilter])? NULL : json_encode($aConfig[$sFilter]));
            $q = $_DB->query('INSERT INTO ' . TABLE_ANALYSES_RUN_FILTERS . ' (runid, filterid, config_json, filter_order) VALUES (?, ?, ?, ?)', array($nRunID, $sFilter, $sFilterConfig, ($i+1)));
            if (!$q) {
                $_DB->rollBack();
                die('Failed to create analysis run filter in the database. If the analysis is defined properly, this is an error in the software.');
            }
        }
    } else {
        $nRunID = (int) $_REQUEST['runid']; // (int) is to prevent zerofill from messing things up.
        // Update the existing analyses run record to store the custom panel genes.
        $_DB->query('UPDATE ' . TABLE_ANALYSES_RUN . ' SET custom_panel = ? WHERE id = ?', array($sCustomPanel, $nRunID));

        // Insert into database the new configurations.
        $aFilters = explode(';', $zAnalysisRun['_filters']);
        foreach ($aFilters as $sFilter) {
            $sFilterConfig = (empty($aConfig[$sFilter])? NULL : json_encode($aConfig[$sFilter]));
            $q = $_DB->query('UPDATE ' . TABLE_ANALYSES_RUN_FILTERS . ' SET config_json = ? WHERE filterid = ? AND runid = ?', array($sFilterConfig, $sFilter, $nRunID));
            if (!$q) {
                $_DB->rollBack();
                die('Failed to update analysis run filter in the database. If the analysis is defined properly, this is an error in the software.');
            }
        }
    }

    $_DB->commit();

    // Write to log...
    lovd_writeLog('Event', LOG_EVENT, 'Started analysis run ' . str_pad($nRunID, 5, '0', STR_PAD_LEFT) . ($zAnalysis? ' (' . $zAnalysis['name'] : ' based on ' . $zAnalysisRun['analysisid'] . ' (' . $zAnalysisRun['name']) . ') on individual ' . $zIndividual['id'] . ':' . str_pad($_REQUEST['screeningid'], 10, '0', STR_PAD_LEFT) . ' with filter(s) \'' . implode('\', \'', $aFilters) . '\'');





    // Get info for analysis and store in session.
    if (empty($_SESSION['analyses'])) {
        $_SESSION['analyses'] = array();
    }

    // Store analysis information in the session.
    $_SESSION['analyses'][$nRunID] =
        array(
            'screeningid' => (int) $_REQUEST['screeningid'], // (int) is to prevent zerofill from messing things up.
            'filters' => $aFilters,
            'IDsLeft' => array(),
            'custom_panel' => $sCustomPanel,
            'gene_panels' => $aGenePanels,
        );

    // Collect variant IDs and store in session.
    $_SESSION['analyses'][$nRunID]['IDsLeft'] = $_DB->query('SELECT DISTINCT CAST(s2v.variantid AS UNSIGNED) FROM ' . TABLE_SCR2VAR . ' AS s2v WHERE s2v.screeningid = ?', array($_REQUEST['screeningid']))->fetchAllColumn();

    // Instruct page to start running filters in sequence.
    die(AJAX_TRUE . ' ' . str_pad($nRunID, 5, '0', STR_PAD_LEFT));
}
?>
