<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-02-13
 * Modified    : 2017-02-13
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
if (PATH_COUNT != 3 || !ctype_digit($_PE[2]) || !in_array(ACTION, array('delete'))) {
    die('alert("Error while sending data.");');
}

// Require LEVEL_OWNER or higher (return value: 1).
if (!$_AUTH || !lovd_isAuthorized('analysisrun', $_PE[2])) {
    // If not authorized, die with error message.
    die('alert("Lost your session. Please log in again.");');
}

$nID = sprintf('%0' . $_SETT['objectid_length']['analysisruns'] . 'd', $_PE[2]);
define('LOG_EVENT', 'AnalysisRunDelete');

// Now get the analysis run's data, and check the screening's status.
// We can't do anything if the screening has been closed, so then we'll fail here.
$sSQL = 'SELECT ar.*, s.individualid
         FROM ' . TABLE_ANALYSES_RUN . ' AS ar
           INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (ar.screeningid = s.id)
         WHERE ar.id = ? AND s.analysis_statusid < ?';
$aSQL = array($nID, ANALYSIS_STATUS_CLOSED);

$zData = $_DB->query($sSQL, $aSQL)->fetchAssoc();
if (!$zData) {
    die('alert("No such ID or not allowed!");');
}





// If we get here, we want to show the dialog for sure.
print('// Make sure we have and show the dialog.
if (!$("#analysis_run_dialog").length) {
    $("body").append("<DIV id=\'analysis_run_dialog\' title=\'' . ucfirst(ACTION) . ' Analysis Run\'></DIV>");
}
if (!$("#analysis_run_dialog").hasClass("ui-dialog-content") || !$("#analysis_run_dialog").dialog("isOpen")) {
    $("#analysis_run_dialog").dialog({draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:true,hide:"fade",modal:true});
}


');

// Implement an CSRF protection by working with tokens.
$sFormDelete    = '<FORM id=\'analysis_run_delete_form\'><INPUT type=\'hidden\' name=\'csrf_token\' value=\'{{CSRF_TOKEN}}\'>Are you sure you want to remove this analysis run? The variants will not be deleted.<BR></FORM>';

// Set JS variables and objects.
print('
var oButtonFormDelete = {"Delete":function () { $.post("' . CURRENT_PATH . '?' . ACTION . '", $("#analysis_run_delete_form").serialize()); }};
var oButtonCancel  = {"Cancel":function () { $(this).dialog("close"); }};
var oButtonClose   = {"Close":function () { $(this).dialog("close"); }};


');





if (ACTION == 'delete' && GET) {
    // Request confirmation.
    // We do this in two steps, not only because we'd like the user to confirm, but also to prevent CSRF.

    $_SESSION['csrf_tokens']['analysis_run_delete'] = md5(uniqid());
    $sFormDelete = str_replace('{{CSRF_TOKEN}}', $_SESSION['csrf_tokens']['analysis_run_delete'], $sFormDelete);

    // Display the form, and put the right buttons in place.
    print('
    $("#analysis_run_dialog").html("' . $sFormDelete . '<BR>");

    // Select the right buttons.
    $("#analysis_run_dialog").dialog({buttons: $.extend({}, oButtonFormDelete, oButtonCancel)});
    ');
    exit;
}





if (ACTION == 'delete' && POST) {
    // Process delete form.
    // We do this in two steps, not only because we'd like the user to confirm, but also to prevent CSRF.

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
