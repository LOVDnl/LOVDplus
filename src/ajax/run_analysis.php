<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-05
 * Modified    : 2016-05-13
 * For LOVD    : 3.0-15
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
$sSQL = 'SELECT i.id, i.custom_panel';
$sSQL .= (!lovd_verifyInstance('mgha', false)? '' : ', s.`Screening/Mother/Sample_ID`, s.`Screening/Father/Sample_ID`');
$sSQL .= ', GROUP_CONCAT(DISTINCT gp.id, ";", gp.name, ";", gp.type ORDER BY gp.type DESC, gp.name ASC SEPARATOR ";;") AS __gene_panels
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
        $aGenePanel = explode(";", $sGenePanel);
        $zIndividual['gene_panels'][$aGenePanel[0]] = $aGenePanel;
    }
}



$zAnalysis = $zAnalysisRun = false;
if ($_REQUEST['runid']) {
    // Check if the run exists.
    $zAnalysisRun = $_DB->query('SELECT ar.id, ar.analysisid, a.name, GROUP_CONCAT(arf.filterid ORDER BY arf.filter_order SEPARATOR ";") AS _filters FROM ' . TABLE_ANALYSES_RUN . ' AS ar INNER JOIN ' . TABLE_ANALYSES . ' AS a ON (ar.analysisid = a.id) INNER JOIN ' . TABLE_ANALYSES_RUN_FILTERS . ' AS arf ON (ar.id = arf.runid) WHERE ar.id = ?', array($_REQUEST['runid']))->fetchAssoc();
    if (!$zAnalysisRun) {
        die('Analysis run not recognized. If the analysis run is defined properly, this is an error in the software.');
    }

    // Check if this analysis run has not started before. Can't start twice... (maybe a restart option is needed later?)
    if ($_DB->query('SELECT COUNT(*), IFNULL(MAX(arf.run_time)>-1, 0) AS analysis_run FROM ' . TABLE_ANALYSES_RUN . ' AS ar INNER JOIN ' . TABLE_ANALYSES_RUN_FILTERS . ' AS arf ON (ar.id = arf.runid) WHERE ar.id = ? GROUP BY ar.id HAVING analysis_run = 1', array($zAnalysisRun['id']))->fetchColumn()) {
        die('This analysis has already been performed on this screening.');
    }

} else {
    // Check if analysis exists.
    $zAnalysis = $_DB->query('SELECT a.id, a.name, GROUP_CONCAT(a2af.filterid ORDER BY a2af.filter_order SEPARATOR ";") AS _filters FROM ' . TABLE_ANALYSES . ' AS a INNER JOIN ' . TABLE_A2AF . ' AS a2af ON (a.id = a2af.analysisid) WHERE id = ? GROUP BY a.id', array($_REQUEST['analysisid']))->fetchAssoc();
    if (!$zAnalysis || !$zAnalysis['_filters']) {
        die('Analysis not recognized or no filters defined. If the analysis is defined properly, this is an error in the software.');
    }
}





if (ACTION == 'configure' && GET) {
    header('Content-type: text/javascript; charset=UTF-8');
    $aFilters = explode(';', (isset($zAnalysisRun['_filters'])? $zAnalysisRun['_filters']: $zAnalysis['_filters']));
    $sFiltersFormItems = '';
    foreach ($aFilters as $sFilter) {
        switch($sFilter) {
            case 'apply_selected_gene_panels':
                // Display a notice that 'apply_selected_gene_panels' is selected for this analysis
                $sFiltersFormItems .= '<H4>' . $sFilter . '</H4><BR>';

                //  No gene panel has been added to this individual.
                if (empty($zIndividual['gene_panels']) && empty($zIndividual['custom_panel'] )) {
                    $sFiltersFormItems .= '<P>There is no Gene Panel assigned to this individual. To continue running this analysis, please try one of the following options: </P>';
                    $sFiltersFormItems .= '<UL>';
                    $sFiltersFormItems .= '<LI>Add a gene panel to this individual, OR</LI>';
                    $sFiltersFormItems .= '<LI>Remove the apply_selected_gene_panels filter from this analysis, OR</LI>';
                    $sFiltersFormItems .= '<LI>Continue running this analysis without any gene panel selected.</LI>';
                    $sFiltersFormItems .= '</UL>';
                } else {
                    // This individual has at least one gene panel or custom panel.

                    $sFiltersFormItems .= '<DIV class=\'filter-config\' id=\'filter-config-'. $sFilter . '\'><TABLE>';
                    $sLastType = '';
                    foreach ($zIndividual['gene_panels'] as $nKey => $aGenePanel) {
                        // Create the gene panel type header.
                        if ($sLastType == '' || $sLastType != $aGenePanel[2]) {
                            $sFiltersFormItems .= '<TR><TD class=\'gpheader\' colspan=\'2\' ' . ($sLastType? '' : 'style=\'border-top: 0px\'') . '>' . ucfirst(str_replace('_', ' ', $aGenePanel[2])) . '</TD></TR>';
                        }
                        $sLastType = $aGenePanel[2];

                        // Add the gene panel to the form.
                        $sFiltersFormItems .= '<TR><TD><INPUT type=\'checkbox\' name=\'config[' . $sFilter . '][gene_panel][]\' id=\'gene_panel_' . $nKey . '\' value=\'' . $aGenePanel[0] . '\'' . ($aGenePanel[2] == 'mendeliome'? '' : ' checked') . ' /></TD><TD><LABEL for=\'gene_panel_'. $nKey .'\'>' . $aGenePanel[1] . '</LABEL></TD></TR>';
                    }

                    // Add in the custom gene panel option.
                    if ($zIndividual['custom_panel']) {
                        $sFiltersFormItems .= '<TR><TD class=\'gpheader\' colspan=\'2\'>Custom panel</TD></TR>';
                        $sFiltersFormItems .= '<TR><TD><INPUT type=\'checkbox\' name=\'config[' . $sFilter . '][gene_panel][]\' value=\'custom_panel\' id=\'custom_panel\' checked></TD>';
                        $sFiltersFormItems .= '<TD><LABEL for=\'custom_panel\'>' . $zIndividual['custom_panel'] . '</LABEL></TD></TR>';
                    }

                    $sFiltersFormItems .= '</TABLE></DIV><BR>';
                }

                break;
            case 'cross_screenings':
                $aConditions = array(
                    'NOT IN',
                    'NOT Homozygous IN',
                    'IN',
                    'Homozygous IN',
                    'Heterozygous IN'
                );

                $aGrouping = array(
                    '',
                    'AND',
                    'OR'
                );

                // Find available screenings.
                $sSQL = 'SELECT s.*, i.`Individual/Sample_ID`
                         FROM ' . TABLE_SCREENINGS . ' AS s 
                         JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) 
                         WHERE s.id != ?';
                $aSQL = array($_REQUEST['screeningid']);

                $zScreenings = $_DB->query($sSQL, $aSQL)->fetchAllAssoc();
                $aScreenings = array();
                $aRelatives = array();
                foreach ($zScreenings as $zScreening) {
                    $sText = $zScreening['Individual/Sample_ID'];
                    if (lovd_verifyInstance('mgha', false)) {
                        $sText .= ' ' . $zScreening['Screening/Batch'] . ' ' . $zScreening['Screening/Pipeline/Run_ID'];

                        if ($zScreening['Individual/Sample_ID'] == $zIndividual['Screening/Mother/Sample_ID']) {
                            $sText = 'Mother: ' . $sText;
                            $aRelatives[$zScreening['id']] = $sText;
                        }
                    }

                    $aScreenings[$zScreening['id']] = $sText;
                }

                // We want screenings of the relatives of this patient to be sorted on top.
                $aScreenings = $aRelatives + $aScreenings;

                // Print form.
                $sFiltersFormItems .= '<H4>' . $sFilter . '</H4><BR>';

                $sFiltersFormItems .= '<DIV class=\'filter-config\' id=\'filter-config-'. $sFilter . '\'>';
                $sFiltersFormItems .= '<TR><TD><BUTTON type=\'button\' class=\'btn-right\' id=\'btn-add-group\'>+ Add group</BUTTON></TD></TR>';

                $sFiltersFormItems .= '<TABLE><TR><TD><LABEL>Description</LABEL></TD><TD><INPUT name=\'config[' . $sFilter . '][description]\' /></TD></TR></TABLE>';
                $sFiltersFormItems .= '<DIV class=\'filter-cross-screening-group\' id=\'filter-config-'. $sFilter .'-0\'>';

                // Conditions variants of this screening against the selected group
                $sFiltersFormItems .= '<TABLE><TR><TD><LABEL>Condition</LABEL></TD><TD><SELECT name=\'config[' . $sFilter . '][groups][0][condition]\'>';
                foreach ($aConditions as $sCondition) {
                    $sFiltersFormItems .= '<OPTION value=\'' . $sCondition . '\'>' . $sCondition . '</OPTION>';
                }
                $sFiltersFormItems .= '</SELECT></TD></TR>';

                // The list of available screenings
                $sFiltersFormItems .= '<TR><TD><LABEL>Screenings</LABEL></TD><TD><SELECT name=\'config[' . $sFilter . '][groups][0][screenings][]\' multiple=\'true\'>';
                foreach ($aScreenings as $sScreeningID => $sText) {
                    $sFiltersFormItems .= '<OPTION value=\'' . $sScreeningID . '\'>' . $sText . '</OPTION>';
                }
                $sFiltersFormItems .= '</SELECT></TD></TR>';

                // How to group among selected screenings within a group
                $sFiltersFormItems .= '<TR><TD><LABEL>Grouping</LABEL></TD><TD><SELECT name=\'config[' . $sFilter . '][groups][0][grouping]\'>';
                foreach ($aGrouping as $sCondition) {
                    $sFiltersFormItems .= '<OPTION value=\'' . $sCondition . '\'>' . $sCondition . '</OPTION>';
                }
                $sFiltersFormItems .= '</SELECT></TD></TR></TABLE></DIV>';

                // TODO: how to display screenings to be selected

                $sFiltersFormItems .= '</TABLE></DIV>';

                $sFiltersFormItems .= '<SCRIPT>';
                $sFiltersFormItems .= '$(\'#btn-add-group\').click(function() {';
                $sFiltersFormItems .= 'var numGroups = $(\'.filter-cross-screening-group\').length;';
                $sFiltersFormItems .= 'var elemFilterConfig = $(\'#filter-config-' . $sFilter . '\');';
                $sFiltersFormItems .= 'var elemGroup = $(\'#filter-config-' . $sFilter . '-0\').clone().attr(\'id\', \'#filter-config-' . $sFilter . '-\' + numGroups);';
                $sFiltersFormItems .= 'elemGroup.find(\'[name]\').each(function(i, e) { var oldName = $(e).attr(\'name\'); var newName = oldName.replace(\'[groups][0]\', \'[groups][\' + numGroups + \']\'); $(e).attr(\'name\', newName)});';
                $sFiltersFormItems .= 'elemFilterConfig.append(elemGroup);';
                $sFiltersFormItems .= '});';
                $sFiltersFormItems .= '</SCRIPT>';

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
        $sFormRequiredInputs .= '<INPUT type=\'hidden\' name=\''. $sName .'\' value=\''. $sValue .'\' />';
    }

    if (empty($sFiltersFormItems)) {
        // If no further configuration required, simply pass the minimum required inputs
        print('$.post("' . CURRENT_PATH . '?' . ACTION . '", '. json_encode($aInputs) .' );');
        exit;
    }

    // If further configuration required, build modal to take the configuration inputs.
    // Implement an CSRF protection by working with tokens.
    $_SESSION['csrf_tokens']['run_analysis_configure'] = md5(uniqid());
    $sForm  = '<FORM id=\'configure_analysis_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'' . $_SESSION['csrf_tokens']['run_analysis_configure'] . '\'><BR>';
    $sForm .= $sFiltersFormItems;
    $sForm .= $sFormRequiredInputs;
    $sForm .= '</FORM>';

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
    print('
    var oButtonFormSubmit  = {"Submit":function () { $.post("' . CURRENT_PATH . '?' . ACTION . '", $("#configure_analysis_form").serialize()); }};
    var oButtonCancel = {"Cancel":function () { $(this).dialog("close"); }};
    var oButtonClose  = {"Close":function () { $(this).dialog("close"); }};
    ');

    // Display the form, and put the right buttons in place.
    print('
    $("#configure_analysis_dialog").html("' . $sForm . '<BR>");

    // Select the right buttons.
    $("#configure_analysis_dialog").dialog({title: "Configure Analysis" ,buttons: $.extend({}, oButtonFormSubmit, oButtonCancel)});
    ');
    exit;
}





if (ACTION == 'configure' && POST) {
    header('Content-type: text/javascript; charset=UTF-8');
    $aConfig = (empty($_REQUEST['config'])? array() : $_REQUEST['config']);

    // TODO: This is a good place for us to do our form inputs validation
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_tokens']['run_analysis_configure']) {
        die('alert("Error while sending data, possible security risk. Please reload the page, and try again.");');
    }

    // Now that we know we have all our required inputs for all filters, we can pass it to 'run' to insert/update database entries
    print('
    $("#configure_analysis_dialog").dialog("close");
    lovd_runAnalysis (\'' . $_REQUEST['screeningid'] . '\', \'' . $_REQUEST['analysisid'] . '\', \'' . $_REQUEST['runid'] . '\', \'' . $_REQUEST['elementid'] . '\', ' . json_encode($aConfig) . ');
    ');
}





if (ACTION == 'run') {
    $aConfig = json_decode($_REQUEST['config'], true);
    $sCustomPanel = '';
    $aGenePanels = array();
    // Process any gene panels that may have been passed.
    if (!empty($aConfig['apply_selected_gene_panels']['gene_panel'])) {
        $aGenePanels = array_values($aConfig['apply_selected_gene_panels']['gene_panel']);
        if(($nKey = array_search('custom_panel', $aGenePanels)) !== false) {
            // If the custom panel has been selected then record this and remove from array.
            $sCustomPanel = $zIndividual['custom_panel'];
            unset($aGenePanels[$nKey]);
        }
        unset($aConfig['apply_selected_gene_panels']);
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
            $sFilterConfig = (empty($aConfig[$sFilter]) ? NULL : json_encode($aConfig[$sFilter]));
            $q = $_DB->query('INSERT INTO ' . TABLE_ANALYSES_RUN_FILTERS . ' (runid, filterid, config_json, filter_order) VALUES (?, ?, ?, ?)', array($nRunID, $sFilter, $sFilterConfig, ($i+1)));
            if (!$q) {
                $_DB->rollBack();
                die('Failed to create analysis run filter in the database. If the analysis is defined properly, this is an error in the software.');
            }
        }
    } else {
        $nRunID = (int) $_REQUEST['runid']; // (int) is to prevent zerofill from messing things up.
        $aFilters = explode(';', $zAnalysisRun['_filters']);
        // Update the existing analyses run record to store the custom panel genes.
        $_DB->query('UPDATE ' . TABLE_ANALYSES_RUN . ' SET custom_panel = ? WHERE id = ?', array($sCustomPanel, $nRunID));
        
        // Insert into database the new configurations.
        foreach ($aFilters as $i => $sFilter) {
            $sFilterConfig = (empty($aConfig[$sFilter]) ? NULL : json_encode($aConfig[$sFilter]));
            $q = $_DB->query('UPDATE ' . TABLE_ANALYSES_RUN_FILTERS . ' SET config_json = ? WHERE filterid = ?', array($sFilterConfig, $sFilter));
            if (!$q) {
                $_DB->rollBack();
                die('Failed to update analysis run filter in the database. If the analysis is defined properly, this is an error in the software.');
            }
        }
    }

    // Process the selected gene panels.
    // FIXME: This will fail if we already have the run ID in the database. That can
    // happen, when somehow the analysis run was created, but didn't start (JS error?).
    foreach ($aGenePanels as $nKey => $nGenePanelID) {
        // Write the gene panels selected to the analyses_run2gene_panel table.
        $q = $_DB->query('INSERT INTO ' . TABLE_AR2GP . ' VALUES (?, ?)', array($nRunID, $nGenePanelID));
        if (!$q) {
            $_DB->rollBack();
            die('Failed to store the gene panels for this analysis. This may be a temporary error, or an error in the software.');
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
