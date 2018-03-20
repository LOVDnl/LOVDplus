<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-05
 * Modified    : 2018-03-20
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2018 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Anthony Marty <anthony.marty@unimelb.edu.au>
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
function lovd_resetAfterFailedRun (sElementID)
{
    // Resets the table after a failed run attempt.

    if (typeof(sElementID) == 'undefined') {
        return false;
    }

    $('#' + sElementID).attr('class', 'analysis analysis_not_run');
    $('#' + sElementID + '_message td')
        .html($('#' + sElementID + '_message td').attr('htmlold'))
        .attr('htmlold', '');
    $('#' + sElementID)
        .attr('onclick', $('#' + sElementID).attr('onclickold'))
        .attr('onclickold', '');
    return true;
}





function lovd_configureAnalysis (nScreeningID, nAnalysisID, nRunID, sElementID)
{
    // Display the modal to enter configurations for different filters.
    $.get('<?php echo lovd_getInstallURL(); ?>ajax/run_analysis.php?configure&elementid=' + escape(sElementID) + '&screeningid=' + escape(nScreeningID) + '&analysisid=' + escape(nAnalysisID) + '&runid=' + escape(nRunID),
    function (data) {
        if (data == '0') {
            // Failure, AJAX_FALSE.
            alert('Screening not valid or no authorization to start a new analysis. Refreshing the page...');
            location.reload();
            return false;
        } else if (data == '8') {
            // Failure, AJAX_NO_AUTH.
            alert('Lost your session. Please log in again.');
        } else if (data == '9') {
            // Failure, AJAX_DATA_ERROR.
            alert('Error while sending data. Please try again.\nIf this error persists, please contact support.');
        }
    });
}





function lovd_runAnalysis (nScreeningID, nAnalysisID, nRunID, sElementID, aConfig)
{
    // When no filters are selected that have configuration, aConfig is undefined.
    if (typeof(aConfig) == 'undefined') {
        aConfig = '';
    }

    // Starts the analysis of the given screening.
    if (typeof(nScreeningID) == 'undefined' || typeof(nAnalysisID) == 'undefined') {
        alert('Incorrect argument(s) passed to runAnalysis function.');
        return false;
    }
    if (nRunID == '') {
        nRunID = 0;
    }

    var aData = {
        'screeningid' : escape(nScreeningID),
        'analysisid' : escape(nAnalysisID),
        'runid' : escape(nRunID),
        'config' : JSON.stringify(aConfig)
    };

    $.post('<?php echo lovd_getInstallURL(); ?>ajax/run_analysis.php?run', aData,
        function () {
            // Remove onClick handler and change class of table, to visually show that it's running.
            $('#' + sElementID)
                .attr('onclickold', $('#' + sElementID).attr('onclick'))
                .attr('onclick', '');
            $('#' + sElementID).attr('class', 'analysis analysis_running');
            $('#' + sElementID + '_message td')
                .attr('htmlold', $('#' + sElementID + '_message td').html())
                .html('Running analysis... <IMG src="gfx/ajax_analysis_running.gif" alt="Running analysis..." width="16" height="11" style="position: relative; top: 2px; right: -10px;">');
        })
        .done(
            function (data) {
                if (data == '0') {
                    // Failure, reset table.
                    lovd_resetAfterFailedRun(sElementID);
                    alert('Screening not valid or no authorization to start a new analysis. Refreshing the page...');
                    location.reload();
                    return false;
                } else if (oRegExp = /1\s(\d+)/.exec(data)) {
                    // Success! We're running...
                    // Now call the script that will start filtering.
                    var nRunID = oRegExp[1];
                    return lovd_runNextFilter(nAnalysisID, nRunID, sElementID);
                } else if (data == '8') {
                    // Failure, reset table.
                    lovd_resetAfterFailedRun(sElementID);
                    alert('Lost your session. Please log in again.');
                } else if (data == '9') {
                    // Failure, reset table.
                    lovd_resetAfterFailedRun(sElementID);
                    alert('Error while sending data. Please try again.\nIf this error persists, please contact support.');
                } else {
                    // Some other error.
                    lovd_resetAfterFailedRun(sElementID);
                    alert(data);
                }})
        .fail(
            function (data) {
                // Failure, reset table.
                lovd_resetAfterFailedRun(sElementID);
                alert('Failed to start analysis. Please try again.\nIf this error persists, please contact support.');
                return false;
            });
    return true;
}





function lovd_runNextFilter (nAnalysisID, nRunID, sElementID)
{
    // Calls the script to run the next filter.

    if (typeof(nAnalysisID) == 'undefined' || typeof(nRunID) == 'undefined') {
        alert('Incorrect argument(s) passed to runNextFilter function.');
        return false;
    }

    $.get('<?php echo lovd_getInstallURL(); ?>ajax/run_next_filter.php?runid=' + escape(nRunID))
        .done(
            function (data) {
                // The results from the filter are passed back as an int status code or a
                // JSON string from the run_next_filter.php page, load them into an object.
                var dataObj = $.parseJSON(data);
                if (data == '0') {
                    // Failure, we're in trouble, reload view.
                    alert('Filter step not valid or no authorization to start a new filter step. Refreshing the page...');
                    location.reload();
                } else if (dataObj.result == false) {
                    alert(dataObj.message);
                    location.reload();
                } else if (dataObj.result) {
                    // Success! Mark line and continue to the next, or stop if we're done...
                    oTR = $('#' + sElementID + '_filter_' + dataObj.sFilterID.replace(/[^a-z0-9_]/i, '_'));
                    oTR.attr('class', 'filter_completed');
                    oTR.children('td:eq(1)').html(dataObj.nTime);
                    oTR.children('td:eq(2)').html(dataObj.nVariantsLeft);

                    // For filters with configuration, show the details underneath the filter.
                    if (dataObj.sFilterConfig.length) {
                        oTR.find('div.filter-config-desc').html(dataObj.sFilterConfig);
                    }

                    if (!dataObj.bDone) {
                        return lovd_runNextFilter(nAnalysisID, nRunID, sElementID);
                    }

                    // And also, we're done...
                    $('#' + sElementID).attr('class', 'analysis analysis_run');
                    $('#' + sElementID + '_message td').html('Click to see results');

                    // Table should get a new ID.
                    $('#' + sElementID).attr('id', 'run_' + nRunID);
                    // Replace all the filter IDs with the new run ID.
                    var sNewFilterIDs = $('#run_' + nRunID).html().split(sElementID).join('run_' + nRunID);
                    $('#run_' + nRunID).html(sNewFilterIDs);
                    // Also fix onclick.
                    $('#run_' + nRunID).attr('onclick', 'lovd_showAnalysisResults(\'' + nRunID + '\');');
                    $('#run_' + nRunID).attr('onclickold', '');
                    // Fix link to modify analysis run.
                    sOnClickLink = $('#run_' + nRunID + ' img.modify').attr('onclick');
                    if (sOnClickLink) {
                        $('#run_' + nRunID + ' img.modify').attr('onclick', sOnClickLink.replace('analyses/' + nAnalysisID, 'analyses/run/' + nRunID));
                    }

                    // Replace all URLs that still have the run ID '0' to use the new run ID, all in one go.
                    var sNewAnalysis = $('#run_' + nRunID).html().split('/0?').join('/' + nRunID + '?');
                    $('#run_' + nRunID).html(sNewAnalysis);

                    // Display the hidden "remove" link.
                    $('#run_' + nRunID + ' div.analysis-tools img.remove').show();

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





function lovd_popoverGenePanelSelectionForm (nScreeningID, nAnalysisID, nRunID, clicked_id)
{
    // Makes the gene panel selection form dialog visible and prepares it for the user.

    // Popup the gene panel selection form dialog.
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

    // Add the values passed to this function to the hidden inputs.
    $("#gene_panel_selection_form input[name=nScreeningID]").val(nScreeningID);
    $("#gene_panel_selection_form input[name=nAnalysisID]").val(nAnalysisID);
    $("#gene_panel_selection_form input[name=nRunID]").val(nRunID);
    $("#gene_panel_selection_form input[name=sElementID]").val(clicked_id);
}





function lovd_processGenePanelSelectionForm ()
{
    // Processes the popover form that selects the gene panels for a particular analysis.

    // Get all the values from the checked checkboxes and read them into an array.
    aSelectedGenePanels = $("#gene_panel_selection_form input:checkbox:checked").map(function(){
        return $(this).val();
    }).get();

    // Read in the hidden form values.
    nScreeningID = $("#gene_panel_selection_form input[name=nScreeningID]").val();
    nAnalysisID = $("#gene_panel_selection_form input[name=nAnalysisID]").val();
    nRunID = $("#gene_panel_selection_form input[name=nRunID]").val();
    sElementID = $("#gene_panel_selection_form input[name=sElementID]").val();

    // Call lovd_runAnalysis and pass all the extra values.
    lovd_runAnalysis(nScreeningID, nAnalysisID, nRunID, sElementID, aSelectedGenePanels);
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
    $('#viewlistForm_CustomVL_AnalysisRunResults_for_I_VE').children('input[name="search_curation_statusid"]').remove();
    $('#viewlistForm_CustomVL_AnalysisRunResults_for_I_VE').children('input[name="search_variantid"]').remove();

    // Mark the currently selected filter.
    $('table.analysesTable td').css('background', '');
    $('#run_' + nRunID).parent().css('background', '#DDD');

    lovd_AJAX_viewListSubmit('CustomVL_AnalysisRunResults_for_I_VE');
}





function lovd_showGenes (nRunID)
{
    $.get('ajax/analysis_runs.php/' + nRunID + '?showGenes');
}
