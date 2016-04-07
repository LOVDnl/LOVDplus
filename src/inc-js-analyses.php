<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-05
 * Modified    : 2014-04-02
 * For LOVD    : 3.0-10
 *
 * Copyright   : 2004-2014 Leiden University Medical Center; http://www.LUMC.nl/
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

define('ROOT_PATH', './');
header('Content-type: text/javascript; charset=UTF-8');
header('Expires: ' . date('r', time()+(180*60)));
require ROOT_PATH . 'inc-lib-init.php';

// Find out whether or not we're using SSL.
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' && !empty($_SERVER['SSL_PROTOCOL'])) {
    // We're using SSL!
    define('PROTOCOL', 'https://');
} else {
    define('PROTOCOL', 'http://');
}
?>
function lovd_resetAfterFailedRun (sClassName)
{
    // Resets the table after a failed run attempt.

    if (typeof(sClassName) == 'undefined') {
        return false;
    }

    $('#' + sClassName).attr('class', 'analysis analysis_not_run');
    $('#' + sClassName + '_message td')
        .html($('#' + sClassName + '_message td').attr('htmlold'))
        .attr('htmlold', '');
    $('#' + sClassName)
        .attr('onclick', $('#' + sClassName).attr('onclickold'))
        .attr('onclickold', '');
    return true;
}





function lovd_runAnalysis (nScreeningID, nAnalysisID, nRunID)
{
    // Starts the analysis of the given screening.

    if (typeof(nScreeningID) == 'undefined' || typeof(nAnalysisID) == 'undefined') {
        alert('Incorrect argument(s) passed to runAnalysis function.');
        return false;
    }
    if (typeof(nRunID) == 'undefined') {
        nRunID = 0;
    }

    $.get('<?php echo lovd_getInstallURL(); ?>ajax/run_analysis.php?screeningid=' + escape(nScreeningID) + '&analysisid=' + escape(nAnalysisID) + '&runid=' + escape(nRunID),
        function () {
            // Remove onClick handler and change class of table, to visually show that it's running.
            // But first check if we're dealing with a modified run or an unmodified analysis.
            if ($('#run_' + nRunID).length > 0) {
                // Run was already started (usually a modified run).
                sClassName = 'run_' + nRunID;
            } else {
                // Analysis started from page.
                sClassName = 'analysis_' + nAnalysisID;
            }

            $('#' + sClassName)
                .attr('onclickold', $('#' + sClassName).attr('onclick'))
                .attr('onclick', '');
            $('#' + sClassName).attr('class', 'analysis analysis_running');
            $('#' + sClassName + '_message td')
                .attr('htmlold', $('#' + sClassName + '_message td').html())
                .html('Running analysis... <IMG src="gfx/ajax_analysis_running.gif" alt="Running analysis..." width="16" height="11" style="position: relative; top: 2px; right: -10px;">');
        })
        .done(
            function (data) {
                if (data == '0') {
                    // Failure, reset table.
                    lovd_resetAfterFailedRun(sClassName);
                    alert('Screening not valid or no authorization to start a new analysis. Refreshing the page...');
                    location.reload();
                    return false;
                } else if (oRegExp = /1\s(\d+)/.exec(data)) {
                    // Success! We're running...
                    // Now call the script that will start filtering.
                    var nRunID = oRegExp[1];
                    return lovd_runNextFilter(nAnalysisID, nRunID);
                } else if (data == '8') {
                    // Failure, reset table.
                    lovd_resetAfterFailedRun(sClassName);
                    alert('Lost your session. Please log in again.');
                } else if (data == '9') {
                    // Failure, reset table.
                    lovd_resetAfterFailedRun(sClassName);
                    alert('Error while sending data. Please try again.\nIf this error persists, please contact support.');
                } else {
                    // Some other error.
                    lovd_resetAfterFailedRun(sClassName);
                    alert(data);
                }})
        .fail(
            function (data) {
                // Failure, reset table.
                lovd_resetAfterFailedRun(sClassName);
                alert('Failed to start analysis. Please try again.\nIf this error persists, please contact support.');
                return false;
            });
    return true;
}





function lovd_runNextFilter (nAnalysisID, nRunID)
{
    // Calls the script to run the next filter.

    if (typeof(nAnalysisID) == 'undefined' || typeof(nRunID) == 'undefined') {
        alert('Incorrect argument(s) passed to runNextFilter function.');
        return false;
    }

    if ($('#run_' + nRunID).length > 0) {
        // Run was already started (usually a modified run).
        sClassName = 'run_' + nRunID;
    } else {
        // Analysis started from page.
        sClassName = 'analysis_' + nAnalysisID;
    }

    $.get('<?php echo lovd_getInstallURL(); ?>ajax/run_next_filter.php?runid=' + escape(nRunID))
        .done(
            function (data) {
                if (data == '0') {
                    // Failure, we're in trouble, reload view.
                    alert('Filter step not valid or no authorization to start a new filter step. Refreshing the page...');
                    location.reload();

                } else if (oRegExp = /1\s([a-z0-9_.]+)\s(\d+)\s([^\s]+)(\sdone)?$/i.exec(data)) {
                    // Success! Mark line and continue to the next, or stop if we're done...
                    var sFilterID     = oRegExp[1].replace(/[^a-z0-9_]/ig, '_');
                    var nVariantsLeft = oRegExp[2];
                    var nTime         = oRegExp[3];
                    var bDone         = (typeof(oRegExp[4]) != 'undefined');
                    oTR = $('#' + sClassName + '_filter_' + sFilterID);
                    oTR.attr('class', 'filter_completed');
                    oTR.children('td:eq(1)').html(nTime);
                    oTR.children('td:eq(2)').html(nVariantsLeft);

                    if (!bDone) {
                        return lovd_runNextFilter(nAnalysisID, nRunID);
                    }

                    // And also, we're done...
                    $('#' + sClassName).attr('class', 'analysis analysis_run');
                    $('#' + sClassName + '_message td').html('Click to see results');

                    // Table should get a new ID, maybe also the filter lines (although those IDs are not used anymore).
                    $('#' + sClassName).attr('id', 'run_' + nRunID);
                    // Also fix onclick.
                    $('#run_' + nRunID).attr('onclick', 'lovd_showAnalysisResults(\'' + nRunID + '\');');
                    $('#run_' + nRunID).attr('onclickold', '');
                    // Fix link to modify analysis run.
                    sOnClickLink = $('#run_' + nRunID + ' img.modify').attr('onclick');
                    $('#run_' + nRunID + ' img.modify').attr('onclick', sOnClickLink.replace('analyses/' + nAnalysisID, 'analyses/run/' + nRunID));

                    // Now load the VL.
                    lovd_showAnalysisResults(nRunID);
                    return true;

                } else if (data == '8') {
                    // Failure, we're in trouble, reload view.
                    alert('Lost your session. Please log in again.');
                    location.reload();

                } else if (data == '9') {
                    // Failure, we're in trouble, reload view.
                    alert('Error while sending data. Please try again.\nIf this error persists, please contact support.');
                    location.reload();

                } else {
                    // Some other error.
                    alert(data);
                    location.reload();
                }})
        .fail(
            function (data) {
                // Failure, we're in trouble, reload view.
                alert('Failed to start analysis. Please try again.\nIf this error persists, please contact support.');
                location.reload();
            });
    return false;
}





function lovd_popoverGenePanelSelectionForm (nScreeningID, nAnalysisID, nRunID)
{
    // Makes the gene panel selection form dialog visible and prepares it for the user

    // Popup the gene panel selection form dialog
    $("#gene_panel_selection").dialog(
        {
        open: function() {
            $("#run_analysis").focus();
        },
        draggable:false,
        resizable:false,
        minWidth:400,
        show:"fade",
        closeOnEscape:true,
        hide:"fade",
        modal:true,
        buttons:[
            {
                id: "run_analysis",
                text: "Run analysis",
                click: function () {
                    lovd_processGenePanelSelectionForm();
                    $(this).dialog("close");
                }
            }
            ]
        }
    );

    // Add the values passed to this function as hidden inputs unless no value was passed
    if (typeof(nScreeningID) != 'undefined') {
        $('#gene_panel_selection_form').prepend('<input type="hidden" name="nScreeningID" value="' + nScreeningID + '" />');
    }
    if (typeof(nAnalysisID) != 'undefined') {
        $('#gene_panel_selection_form').prepend('<input type="hidden" name="nAnalysisID" value="' + nAnalysisID + '" />');
    }
    if (typeof(nRunID) != 'undefined') {
        $('#gene_panel_selection_form').prepend('<input type="hidden" name="nRunID" value="' + nRunID + '" />');
    }
}





function lovd_processGenePanelSelectionForm ()
{
    // Processes the popover form that selects the gene panels for a particular analysis

    // Get all the values from the checked checkboxes and read them into an array
    aSelectedGenePanels = $("#gene_panel_selection_form input:checkbox:checked").map(function(){
        return $(this).val();
    }).get();

    // Read in the hidden form values
    nScreeningID = $("#gene_panel_selection_form input[name=nScreeningID]").val();
    nAnalysisID = $("#gene_panel_selection_form input[name=nAnalysisID]").val();
    nRunID = $("#gene_panel_selection_form input[name=nRunID]").val();

<!--    console.log(aSelectedGenePanels, nScreeningID, nAnalysisID, nRunID);-->

    // Call lovd_runAnalysis and pass all the extra values
<!--    TODO AM Need to pass the selected gene array to this function.-->
    lovd_runAnalysis(nScreeningID, nAnalysisID, nRunID);
}





function lovd_showAnalysisResults (nRunID)
{
    // Calls the ViewList to refresh and load the given Run ID's results.

    // Set loading image, and show VL Div.
    $('#viewlistDiv_CustomVL_AnalysisRunResults_for_I_VE').html('<IMG src="gfx/ajax_loading.gif" alt="Loading..." width="100" height="100">');
    $('#analysis_results_VL').show();

    // Set new search value.
    $('#viewlistForm_CustomVL_AnalysisRunResults_for_I_VE').children('input[name="search_runid"]').attr('value', nRunID);
    // Sometimes it's disabled...
    $('#viewlistForm_CustomVL_AnalysisRunResults_for_I_VE').children('input[name="search_runid"]')[0].disabled = false;

    // The ViewList normally loads only hidden input fields for skipped columns (like runid). But we want
    // to have a default filter on variant effect, and can only do that if we have a search field.
    if (!$('#viewlistForm_CustomVL_AnalysisRunResults_for_I_VE').children('input[name="search_vog_effect"]').length) {
        $('#viewlistForm_CustomVL_AnalysisRunResults_for_I_VE').prepend('<INPUT type="hidden" name="search_vog_effect" value="!-">');
    } else {
        $('#viewlistForm_CustomVL_AnalysisRunResults_for_I_VE').children('input[name="search_vog_effect"]').attr('value', '!-');
    }

    // Mark the currently selected filter.
    $('#analysesTable td').css('background', '');
    $('#run_' + nRunID).parent().css('background', '#DDD');

    lovd_AJAX_viewListSubmit('CustomVL_AnalysisRunResults_for_I_VE');
}
