<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-02-13
 * Modified    : 2017-02-17
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
header('Content-type: text/javascript; charset=UTF-8');

// Check for basic format.
if (PATH_COUNT != 3 || !ctype_digit($_PE[2]) || !in_array(ACTION, array('delete', 'clone'))) {
    die('alert("Error while sending data.");');
}

$nID = sprintf('%0' . $_SETT['objectid_length']['analysisruns'] . 'd', $_PE[2]);

// Require LEVEL_OWNER or higher (return value: 1).
if (!$_AUTH || !lovd_isAuthorized('analysisrun', $nID)) {
    // If not authorized, die with error message.
    die('alert("Lost your session. Please log in again.");');
}

// Now get the analysis run's data, and check the screening's status.
// We can't do anything if the screening has been closed, so then we'll fail here.
$sSQL = 'SELECT a.*, ar.*, s.individualid
         FROM ' . TABLE_ANALYSES_RUN . ' AS ar
           INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (ar.screeningid = s.id)
           INNER JOIN ' . TABLE_ANALYSES . ' AS a ON (ar.analysisid = a.id)
         WHERE ar.id = ? AND s.analysis_statusid < ?';
$aSQL = array($nID, ANALYSIS_STATUS_CLOSED);

$zData = $_DB->query($sSQL, $aSQL)->fetchAssoc();
if (!$zData) {
    die('alert("No such ID or not allowed!");');
}





// If we get here, we want to show the dialog for sure.
print('// Make sure we have and show the dialog.
if (!$("#analysis_run_dialog").length) {
    $("body").append("<DIV id=\'analysis_run_dialog\'></DIV>");
}
if (!$("#analysis_run_dialog").hasClass("ui-dialog-content") || !$("#analysis_run_dialog").dialog("isOpen")) {
    $("#analysis_run_dialog").dialog({draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:true,hide:"fade",modal:true});
}


');

// Implement an CSRF protection by working with tokens.
$sFormClone  = '<FORM id=\'analysis_run_clone_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'{{CSRF_TOKEN}}\'>Are you sure you want to duplicate this analysis run?<BR></FORM>';
$sFormDelete = '<FORM id=\'analysis_run_delete_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'{{CSRF_TOKEN}}\'>Are you sure you want to remove this analysis run? The variants will not be deleted.<BR></FORM>';

// Set JS variables and objects.
print('
var oButtonFormClone  = {"Duplicate":function () { $.post("' . CURRENT_PATH . '?' . ACTION . '", $("#analysis_run_clone_form").serialize()); }};
var oButtonFormDelete = {"Delete":function () { $.post("' . CURRENT_PATH . '?' . ACTION . '", $("#analysis_run_delete_form").serialize()); }};
var oButtonCancel = {"Cancel":function () { $(this).dialog("close"); }};
var oButtonClose  = {"Close":function () { $(this).dialog("close"); }};


');





if (ACTION == 'clone' && GET) {
    // Request confirmation.
    // We do this in two steps, not only because we'd like the user to confirm, but also to prevent CSRF.

    $_SESSION['csrf_tokens']['analysis_run_clone'] = md5(uniqid());
    $sFormClone = str_replace('{{CSRF_TOKEN}}', $_SESSION['csrf_tokens']['analysis_run_clone'], $sFormClone);

    // Display the form, and put the right buttons in place.
    print('
    $("#analysis_run_dialog").html("' . $sFormClone . '<BR>");

    // Select the right buttons.
    $("#analysis_run_dialog").dialog({title: "Duplicate Analysis Run" ,buttons: $.extend({}, oButtonFormClone, oButtonCancel)});
    ');
    exit;
}





if (ACTION == 'clone' && POST) {
    // Process the clone form.
    // We do this in two steps, not only because we'd like the user to confirm, but also to prevent CSRF.
    define('LOG_EVENT', 'AnalysisRunClone');

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_tokens']['analysis_run_clone']) {
        die('alert("Error while sending data, possible security risk. Please reload the page, and try again.");');
    }

    $zData['filters'] = $_DB->query('SELECT filterid FROM ' . TABLE_ANALYSES_RUN_FILTERS . ' WHERE runid = ? ORDER BY filter_order', array($nID))->fetchAllColumn();
    $nFilters = count($zData['filters']);
    $_DB->beginTransaction();
    // First, copy the analysis run.
    $_DB->query('INSERT INTO ' . TABLE_ANALYSES_RUN . ' (analysisid, screeningid, modified, created_by, created_date) VALUES (?, ?, ?, ?, NOW())',
        array($zData['analysisid'], $zData['screeningid'], $zData['modified'], $_AUTH['id']));
    $nNewRunID = $_DB->lastInsertId();

    // Now insert filters.
    foreach ($zData['filters'] as $nOrder => $sFilter) {
        $nOrder ++; // Let order begin by 1, not 0.
        $_DB->query('INSERT INTO ' . TABLE_ANALYSES_RUN_FILTERS . ' (runid, filterid, filter_order) VALUES (?, ?, ?)', array($nNewRunID, $sFilter, $nOrder));
    }

    $nPaddedNewRunID = str_pad($nNewRunID, $_SETT['objectid_length']['analysisruns'], '0', STR_PAD_LEFT);
    if ($_DB->commit()) {
        // If we get here, it all succeeded.
        // Write to log...
        lovd_writeLog('Event', LOG_EVENT, 'Created analysis run ' . $nPaddedNewRunID . ' based on ' . $nID . ' (' . $zData['name'] . ') on individual ' . $zData['individualid'] . ':' . $zData['screeningid'] . ' with all filters cloned');
    } else {
        die('alert("Failed to duplicate analysis run.\n' . htmlspecialchars($_DB->formatError()) . '");');
    }

    // Just close the display, and clone the table.
    print('
    $("#analysis_run_dialog").dialog("close");
    // Store the table\'s HTML, replacing all old IDs with the new ID.
    var sNewAnalysis = $("#run_' . $nID . '").parent().html().split("' . $nID . '").join("' . $nPaddedNewRunID . '");
    // Get the whole TD and duplicate, then overwrite the duplicate because of the new IDs.
    // We do a complete copy anyway, because we need the TD\'s information as well.
    $("#run_' . $nID . '").parent().clone().insertAfter($("#run_' . $nID . '").parent()).html(sNewAnalysis);
    // Now, make modifications to the new table to make it look and function like a new analysis.
    $("#run_' . $nPaddedNewRunID . '").parent().attr("style", ""); // Prevent the grey background to be duplicated, in case it\'s there.
    $("#run_' . $nPaddedNewRunID . '").removeClass("analysis_run").addClass("analysis_not_run");
    $("#run_' . $nPaddedNewRunID . ' tr.filter_completed td.filter_time").html("-");
    $("#run_' . $nPaddedNewRunID . ' tr.filter_completed td.filter_var_left").html("-");
    $("#run_' . $nPaddedNewRunID . ' tr.filter_completed").removeClass("filter_completed");
    $("#run_' . $nPaddedNewRunID . ' tr.message td").html("Click to run this analysis");
    
    // Update gene panel description to un-run state.
    sGenePanelFilterName = $("#run_' . $nPaddedNewRunID . '_filter_apply_selected_gene_panels td:eq(0)").html();
    $("#run_' . $nPaddedNewRunID . '_filter_apply_selected_gene_panels td:eq(0)").html(sGenePanelFilterName.replace(/<table.+/, ""));

    // Define which JS function to run when clicking this table.
    if ($("#run_' . $nPaddedNewRunID . '_filter_apply_selected_gene_panels").length == 0 
        || $("#run_' . $nPaddedNewRunID . '_filter_apply_selected_gene_panels").hasClass("filter_skipped")) {
        // If there gene panel filter is not active or is not included in this analysis.
        var sAction = "lovd_runAnalysis";
        var sGenePanel = ", undefined";
    } else {
        var sAction = "lovd_popoverGenePanelSelectionForm";
        var sGenePanel = "";
    }
    
    $("#run_' . $nPaddedNewRunID . '").attr("onclick", sAction + "(\'' . $zData['screeningid'] . '\', \'' . $zData['analysisid'] . '\', \'' . $nPaddedNewRunID . '\', this.id" + sGenePanel + ")");
    ');
    exit;
}





if (ACTION == 'delete' && GET) {
    // Request confirmation.
    // We do this in two steps, not only because we'd like the user to confirm, but also to prevent CSRF.

    $_SESSION['csrf_tokens']['analysis_run_delete'] = md5(uniqid());
    $sFormDelete = str_replace('{{CSRF_TOKEN}}', $_SESSION['csrf_tokens']['analysis_run_delete'], $sFormDelete);

    // Display the form, and put the right buttons in place.
    print('
    $("#analysis_run_dialog").html("' . $sFormDelete . '<BR>");

    // Select the right buttons.
    $("#analysis_run_dialog").dialog({title: "Delete Analysis Run", buttons: $.extend({}, oButtonFormDelete, oButtonCancel)});
    ');
    exit;
}





if (ACTION == 'delete' && POST) {
    // Process delete form.
    // We do this in two steps, not only because we'd like the user to confirm, but also to prevent CSRF.
    define('LOG_EVENT', 'AnalysisRunDelete');

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] != $_SESSION['csrf_tokens']['analysis_run_delete']) {
        die('alert("Error while sending data, possible security risk. Please reload the page, and try again.");');
    }

    // This also deletes the entries in TABLE_ANALYSES_RUN_FILTERS && TABLE_ANALYSES_RUN_RESULTS.
    if (!$_DB->query('DELETE FROM ' . TABLE_ANALYSES_RUN . ' WHERE id = ?', array($nID), false)) {
        die('alert("Failed to delete analysis run.\n' . htmlspecialchars($_DB->formatError()) . '");');
    }

    // Write to log...
    lovd_writeLog('Event', LOG_EVENT, 'Deleted analysis run ' . $nID . ' on individual ' . $zData['individualid'] . ':' . $zData['screeningid']);

    // Just close the display, and remove the table.
    print('
    $("#analysis_run_dialog").dialog("close");
    $("#run_' . $nID . '").fadeOut(500, function () { $(this).remove(); });
    ');
    exit;
}
?>
