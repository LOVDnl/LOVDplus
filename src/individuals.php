<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-16
 * Modified    : 2023-01-11
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2023 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Daan Asscheman <D.Asscheman@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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
require ROOT_PATH . 'inc-init.php';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





if (LOVD_plus && PATH_COUNT == 1 && !ACTION && !isset($_GET['search_analysis_status'])) {
    // URL: /individuals
    // LOVD+ only; Due to a bug fix where emptying the hash now properly resets a VL,
    //  all search terms in open fields must be provided in the $_GET to make sure they are kept.
    // So we must reload the individuals VL, with the filter in $_GET.
    // Hide confirmed analyses by default.
    header('Location: ' . lovd_getInstallURL() . CURRENT_PATH . '?search_analysis_status=' . urlencode('!="Confirmed"') .
        (empty($_GET)? '' : '&' . implode('&',
                array_map(function ($sKey)
                {
                    return $sKey . '=' . urlencode($_GET[$sKey]);
                }, array_keys($_GET)))));
    exit;
}





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /individuals
    // View all entries.

    // Managers are allowed to download this list...
    if ($_AUTH && $_AUTH['level'] >= LEVEL_MANAGER) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

    define('PAGE_TITLE', 'View individuals');
    $_T->printHeader();
    $_T->printTitle();

    if (LOVD_plus) {
        lovd_requireAUTH();
    }

    require ROOT_PATH . 'class/object_individuals.plus.php';
    $_DATA = new LOVD_IndividualPLUS();
    $_DATA->setRowLink('Individuals', CURRENT_PATH . '/{{id}}/analyze/{{screeningid}}');
    $aVLOptions = array(
        'show_options' => (bool) ($_AUTH['level'] >= LEVEL_MANAGER),
    );
    $_DATA->viewList('Individuals', $aVLOptions);

    $_T->printFooter();
    exit;
}





if (PATH_COUNT >= 2 && ctype_digit($_PE[1]) && !ACTION && (PATH_COUNT == 2 || PATH_COUNT == 4 && $_PE[2] == 'analyze' && ctype_digit($_PE[3]))) {
    // URL: /individuals/00000001
    // URL: /individuals/00000001/analyze/0000000001
    // View specific entry.

    $nID = sprintf('%08d', $_PE[1]);
    $nScreeningToAnalyze = (PATH_COUNT == 4? $_PE[3] : 0);
    define('PAGE_TITLE', 'View individual #' . $nID);
    $_T->printHeader();
    $_T->printTitle();

    if (LOVD_plus) {
        lovd_requireAUTH();
    } else {
        // Load appropriate user level for this individual.
        lovd_isAuthorized('individual', $nID);
    }

    // FIXME: This means, when the ID does not exist, we have an open table that doesn't close.
    print('      <TABLE cellpadding="0" cellspacing="0" border="0">
        <TR>
          <TD valign="top" width="1000">' . "\n");
    require ROOT_PATH . 'class/object_individuals.plus.php';
    $_DATA = new LOVD_IndividualPLUS($nID);
    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    if ($_AUTH) {
        // Authorized user is logged in. Provide tools.
        if ($_AUTH['level'] >= LEVEL_OWNER) {
            $aNavigation['individuals/' . $nID . '?curate' . (isset($_GET['in_window'])? '&amp;in_window' : '')] = array('menu_edit.png', 'Curate this individual', 1);
        }
        $aNavigation['javascript:lovd_openWindow(\'' . lovd_getInstallURL() . 'individuals/' . $nID . '?curation_log&in_window\', \'curation_log\', 1050, 450);'] = array('menu_clock.png', 'Show curation history', 1);
        $aNavigation[$_PE[0] . '/' . $_PE[1] . '?edit_panels']              = array('menu_edit.png', 'Edit gene panels', 1);
        if ($_AUTH['level'] >= $_SETT['user_level_settings']['delete_individual']) {
            $aNavigation[$_PE[0] . '/' . $_PE[1] . '?delete']               = array('cross.png', 'Delete individual entry', 1);
        }
    }
    lovd_showJGNavigation($aNavigation, 'Individuals');

    // Show a warning if no gene panels have been assigned to this individual.
    if (!count($zData['gene_panels'])) {
        print('<BR>');
        lovd_showInfoTable('No gene panels have been assigned to this individual, <A href="' . $_PE[0] . '/' . $_PE[1] . '?edit_panels">click here</A> to assign them.', 'warning', 600);
    }



    // Show logs related to closing and opening of analyses this individual.
    print('    <BR><BR>' . "\n\n");
    $_GET['page_size'] = 10;
    $_GET['search_event'] = 'AnalysisOpen|AnalysisClose';
    $_GET['search_entry_'] = '"individual ' . $nID . '"';
    $_T->printTitle('Log entries', 'H4');
    require_once ROOT_PATH . 'class/object_logs.php';
    $_DATA = new LOVD_Log();
    $aVLOptions = array(
        'cols_to_skip' => array('name', 'event', 'del'),
        'track_history' => false,
    );
    $_DATA->viewList('Logs_for_I_VE', $aVLOptions);
    unset($_GET['page_size'], $_GET['search_event'], $_GET['search_entry_']);



    print('
          </TD>
          <TD width="50">&nbsp;</TD>
          <TD valign="top" id="screeningViewEntry">' . "\n");

    // If we're here, analyzing a screening, show the screening VE on the right.
    require_once ROOT_PATH . 'class/object_screenings.plus.php';
    if ($nScreeningToAnalyze) {
        // Authorize the user for this screening, but specifically meant for the analysis.
        // For LEVEL_ANALYZER, this should activate LEVEL_OWNER for
        //   free screenings or screenings under analysis by this user.
        lovd_isAuthorized('screening_analysis', $nScreeningToAnalyze);

        $_DATA = new LOVD_ScreeningPLUS();
        $zScreening = $_DATA->viewEntry($nScreeningToAnalyze);
    }
    print('
          </TD>
        </TR>
      </TABLE>' . "\n\n");





    print('      <BR><BR>' . "\n\n");
    lovd_includeJS('inc-js-tooltip.php');

    // Show info table about data analysis.
    if (!$zData['variants']) {
        // Can't start.
        lovd_showInfoTable('Can\'t start analysis, still waiting for variant data to be uploaded.', 'stop', 600);
        $_T->printFooter();
        exit;
    }





    // Analysis is done per screening; show list of screenings, select one to actually start/view analyses.
    $_GET['search_individualid'] = $nID;
    $_T->printTitle('Screenings', 'H4');
    $_DATA = new LOVD_ScreeningPLUS();
    $_DATA->setSortDefault('id');
    $_DATA->setRowID('Screenings_for_I_VE', 'Screening_{{screeningid}}');
    $_DATA->setRowLink('Screenings_for_I_VE', $_PE[0] . '/' . $nID . '/analyze/{{screeningid}}');
    // Restrict the columns of this VL, if given.
    if (isset($_INSTANCE_CONFIG['viewlists']['Screenings_for_I_VE']['cols_to_show'])) {
        $_DATA->setViewListCols($_INSTANCE_CONFIG['viewlists']['Screenings_for_I_VE']['cols_to_show']);
    }
    $aVLOptions = array(
        'track_history' => false,
        'show_navigation' => false,
    );
    $_DATA->viewList('Screenings_for_I_VE', $aVLOptions);
    unset($_GET['search_individualid']);





    // If we are analyzing a screening, highlight it.
    if ($nScreeningToAnalyze) {
        // First, let's check if this analysis belongs to this individual.
        if (!$_DB->q('SELECT COUNT(*) FROM ' . TABLE_SCREENINGS . ' WHERE id = ? AND individualid = ?', array($nScreeningToAnalyze, $nID))->fetchColumn()) {
            lovd_showInfoTable('No such screening defined for this individual!', 'stop');
            $_T->printFooter();
            exit;
        }
?>
      <SCRIPT type="text/javascript">
        var nScreeningID;
        function lovd_highlightScreening ()
        {
            // Highlights the selected screening, by parsing the URL.
            $('#Screening_' + nScreeningID).attr('class', 'data bold');
        }

        $(function () {
            var aScreenings = window.location.href.split('/');
            nScreeningID = aScreenings[aScreenings.length-1];
            // Not so efficient, but oh well... Needs to be done this way to make sure it gets highlighted again if the VL is reloaded.
            // FIXME: Can we somehow hook this into the reloading of the VL?
            setInterval(lovd_highlightScreening, 250);
        });
      </SCRIPT>
    <?php

        // Show info table about data analysis.
        if ($zScreening['analysis_statusid'] == ANALYSIS_STATUS_WAIT || !$zScreening['variants_found_'] || !ctype_digit($zScreening['variants_found_'])) {
            // Can't start.
            lovd_showInfoTable('Can\'t start analysis, still waiting for variant data to be uploaded.', 'stop', 600);
            $_T->printFooter();
            exit;
        }





        lovd_includeJS('inc-js-analyses.php', 1);

        // If we're ready to analyze, or if we are analyzing already, show analysis options.
        // Analyses will be shown in separate tabs determined by the queries below.
        $zAnalysesRun    = $_DB->q('SELECT a.id, a.name, a.version, a.description, GROUP_CONCAT(DISTINCT a2af.filterid ORDER BY a2af.filter_order ASC SEPARATOR ";") AS _filters, IFNULL(MAX(arf.run_time)>-1, 0) AS analysis_run, ar.id AS runid,   ar.modified, GROUP_CONCAT(DISTINCT arf.filterid, ";", IFNULL(arf.filtered_out, "-"), ";", IFNULL(arf.run_time, "-") ORDER BY arf.filter_order SEPARATOR ";;") AS __run_filters
                                        FROM ' . TABLE_ANALYSES . ' AS a INNER JOIN ' . TABLE_ANALYSES_RUN . ' AS ar ON (a.id = ar.analysisid)
                                        INNER JOIN ' . TABLE_ANALYSES_RUN_FILTERS . ' AS arf ON (ar.id = arf.runid)
                                        INNER JOIN ' . TABLE_A2AF . ' AS a2af ON (ar.analysisid = a2af.analysisid)
                                        WHERE ar.screeningid = ? GROUP BY ar.id ORDER BY ar.modified, ar.id', array($nScreeningToAnalyze))->fetchAllAssoc();
        $zAnalysesGenePanel = $_DB->q('SELECT DISTINCT a.id, a.name, a.version, a.description, GROUP_CONCAT(DISTINCT a2af.filterid ORDER BY a2af.filter_order ASC SEPARATOR ";") AS _filters, 0                               AS analysis_run, 0     AS runid, 0 AS modified
                                        FROM ' . TABLE_ANALYSES . ' AS a
                                        INNER JOIN ' . TABLE_A2AF . ' AS a2af ON (a.id = a2af.analysisid)
                                        INNER JOIN ' . TABLE_GP2A . ' AS gp2a ON (a.id = gp2a.analysisid) 
                                        INNER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (gp2a.genepanelid = i2gp.genepanelid)
                                        WHERE i2gp.individualid = ? AND a.active = 1
                                        GROUP BY a.id ORDER BY a.sortid, a.id', array($nID))->fetchAllAssoc();
        $zAnalysesAll = $_DB->q('SELECT a.id, a.name, a.version, a.description, GROUP_CONCAT(a2af.filterid ORDER BY a2af.filter_order ASC SEPARATOR ";") AS _filters, 0                               AS analysis_run, 0     AS runid, 0 AS modified
                                        FROM ' . TABLE_ANALYSES . ' AS a
                                        INNER JOIN ' . TABLE_A2AF . ' AS a2af ON (a.id = a2af.analysisid)
                                        WHERE a.active = 1 
                                        GROUP BY a.id ORDER BY a.sortid, a.id')->fetchAllAssoc();
        $aFilterInfo = $_DB->q('SELECT id, name, description FROM ' . TABLE_ANALYSIS_FILTERS)->fetchAllGroupAssoc();
        // Fetch all (numeric) variant IDs of all variants that have a curation status. They will be shown by default, when no analysis has been selected yet.
        $aScreeningVariantIDs = $_DB->q('SELECT CAST(s2v.variantid AS UNSIGNED) FROM ' . TABLE_SCR2VAR . ' s2v INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id) WHERE s2v.screeningid = ? AND vog.curation_statusid IS NOT NULL', array($nScreeningToAnalyze))->fetchAllColumn();
        require ROOT_PATH . 'inc-lib-analyses.php';

        // Array storing all the possible tabs and info that goes with them. This should be in the order in which they need to be displayed.
        $aTabsInfo = array(
            'analyses_run' => array(
                'id' => 0, // Zero base tab id used by jQuery UI tabs.
                'prefix' => 'run_', // The prefix that is attached to the analysis id. DO NOT CHANGE THIS PREFIX FOR RUN ANALYSES AS IT IS HARD CODED IN AJAX.
                'name' => 'Run analyses', // The text that will appear within the tab navigation.
                'no_analyses_message' => 'No analyses have been run yet, click on a tab above to see the available analyses.', // The text that will appear if no analyses are found for this tab.
                'no_analyses_message_type' => 'information', // The type of message that will be displayed, see lovd_showInfoTable() for available types.
                'data' => $zAnalysesRun, // The analyses data used to display the analyses.
            ),
            'analyses_gene_panel' => array(
                'id' => 1,
                'prefix' => 'gp_',
                'name' => 'Gene panel analyses',
                'no_analyses_message' => 'There are no available analyses, please assign gene panels to this individual and be sure that the required analyses are assigned to those gene panels.',
                'no_analyses_message_type' => 'information',
                'data' => $zAnalysesGenePanel,
            ),
            'analyses_all' => array(
                'id' => 2,
                'prefix' => 'all_',
                'name' => 'All analyses',
                'no_analyses_message' => 'There are no analyses in the database! Analyses will need to be installed by an administrator before they can be used.',
                'no_analyses_message_type' => 'warning',
                'data' => $zAnalysesAll,
            ),
        );
        $sDefaultTabID = false; // The default tab to show when the page has loaded.
        $sTabMenu = '<UL>' . "\n";
        foreach ($aTabsInfo as $sTabKey => $aTabData) { // Create the tab menus using the tabs info array.
            $sTabMenu .= '          <LI><A href="' . CURRENT_PATH . '#' . $sTabKey . '">' . $aTabData['name'] . '</A></LI>' . "\n";
        }
        $sTabMenu .= '        </UL>' . "\n";

        print('
      <DIV id="analyses">
        ' . $sTabMenu);

        foreach ($aTabsInfo as $sTabKey => $aTabData) {
            print('        <DIV id="' . $sTabKey . '" style="overflow: auto;">
            ');

            print('
          <TABLE class="analysesTable" border="0" cellpadding="0" cellspacing="0">
            <TR>');

            if (!$aTabData['data']) { // Check if there are any analyses available, if not then display the appropriate message.
                print('<TD>');
                lovd_showInfoTable($aTabData['no_analyses_message'], $aTabData['no_analyses_message_type']);
                print('</TD>');
            } elseif ($sDefaultTabID === false) { // If this is the first tab with data then lets display that one when the page loads.
                $sDefaultTabID = $aTabData['id'];
            }

            foreach ($aTabData['data'] as $key => $zAnalysis) {
                $aFilters = explode(';', $zAnalysis['_filters']); // $_DATA->autoExplode was considered to be used here but there are existing examples of this implementation (run_analysis.php).
                $aFiltersRun = array();
                $aFiltersRunRaw = array();
                if (!empty($zAnalysis['__run_filters'])) {
                    list($aFiltersRunRaw) = $_DATA->autoExplode(array('__0' => $zAnalysis['__run_filters']));
                    foreach ($aFiltersRunRaw as $aFilter) {
                        $aFiltersRun[$aFilter[0]] = array($aFilter[1], $aFilter[2]);
                    }
                    $sFilterLastRun = $aFilter[0]; // For checking if this analysis is done or not ($aFilter came from the loop).
                }

                if ($zAnalysis['analysis_run']) {
                    // Was run complete?
                    if ($aFiltersRun[$sFilterLastRun][0] == '-') {
                        // It's not done... this is strange... half an analysis should normally not happen.
                        $sAnalysisClassName = 'analysis_running analysis_half_run';
                    } else {
                        $sAnalysisClassName = 'analysis_run';
                    }
                } else {
                    $sAnalysisClassName = 'analysis_not_run';
                }
                // ElementID for the table and prefix for the filter's element IDs.
                $sElementID = $aTabData['prefix'] . ($zAnalysis['runid']? $zAnalysis['runid'] : 'analysis_' . $zAnalysis['id']);

                // Select a js function to be executed when an analysis table is clicked.
                $sJSAction = '';

                // $aFilters contains ALL the filters this analysis has before it was modified.
                // $aFiltersRun contains all the filters that have been run or prepared to be run.
                // $aFiltersRun is always empty before a non-modified analysis is run, but it is pre-populated before a modified analysis is run.
                // Check for the gene panel filter; this will define if we'll ask for the gene panel when running the analysis.
                $bHasGenePanelFilter = (empty($aFiltersRun) && in_array('apply_selected_gene_panels', $aFilters)) || // Original analysis, gene panel filter is present.
                                        isset($aFiltersRun['apply_selected_gene_panels']); // Gene panel has been run or modified, and gene panel filter is present.
                $bHasAuthorization = ($_AUTH['level'] >= LEVEL_OWNER && $zScreening['analysis_statusid'] < ANALYSIS_STATUS_CLOSED);
                $bCanRemoveAnalysis = ($bHasAuthorization && ($zAnalysis['runid'] || $zAnalysis['modified']));
                if ($zAnalysis['analysis_run']) {
                    $sJSAction = 'lovd_showAnalysisResults(\''. $zAnalysis['runid'] .'\')';
                } elseif ($bHasAuthorization) {
                    // FIXME: code duplicated from analysis_runs.php when we first render analyses tables.
                    $sRunID = (!$zAnalysis['runid']? 0 : $zAnalysis['runid']);
                    $sJSAction = 'lovd_configureAnalysis(\''. $nScreeningToAnalyze  .'\', \''. $zAnalysis['id'] .'\', \'' . $sRunID . '\', this.id)';
                }

                print('
              <TD class="analysis" valign="top">
                <TABLE border="0" cellpadding="0" cellspacing="1" id="' . $sElementID . '" class="analysis ' . $sAnalysisClassName . '" onclick="' . $sJSAction . ';">
                  <TR>
                    <TH colspan="3">
                      <DIV style="position : relative" class="analysis-tools">' . $zAnalysis['name'] . ' (v' . $zAnalysis['version'] . (!$zAnalysis['modified']? '' : ' modified') . ')' .
                  (!$bHasAuthorization? '' : '
                        <IMG ' . ($bCanRemoveAnalysis? '' : 'style="display:none;" ') . 'src="gfx/cross.png" alt="Remove" onclick="$.get(\'ajax/analysis_runs.php/' . $zAnalysis['runid'] . '?delete\').fail(function(){alert(\'Error deleting analysis run, please try again later.\');}); cancelParentEvent(event);" width="16" height="16" class="remove">
                        <IMG src="gfx/menu_clone.png" alt="Duplicate" onclick="$.get(\'ajax/analysis_runs.php/' . $zAnalysis['runid'] . '?clone\').fail(function(){alert(\'Error duplicating analysis run, please try again later.\');}); cancelParentEvent(event);" width="16" height="16" class="clone">
                        <IMG src="gfx/menu_edit.png" alt="Modify" onclick="lovd_openWindow(\'' . lovd_getInstallURL() . 'analyses/' . ($zAnalysis['runid']? 'run/' . $zAnalysis['runid'] : $zAnalysis['id']) . '?modify&amp;in_window\', \'ModifyAnalysisRun\', 780, 400); cancelParentEvent(event);" width="16" height="16" class="modify">') . '
                        <IMG src="gfx/lovd_form_question.png" alt="" onmouseover="lovd_showToolTip(\'' . $zAnalysis['description'] . '\');" onmouseout="lovd_hideToolTip();" width="14" height="14" class="help" style="position: absolute; top: -4px; right: 0px;">
                      </DIV>
                    </TH>
                  </TR>
                  <TR>
                    <TD><B>Filter</B></TD>
                    <TD><B>Time</B></TD>
                    <TD><B>Var left</B></TD>
                  </TR>');
                $nVariantsLeft = $zScreening['variants_found_'];
                foreach ($aFilters as $sFilter) {
                    $sFilterClassName = '';
                    $nTime = '-';
                    if ($aFiltersRun && !isset($aFiltersRun[$sFilter])) {
                        // We are an analysis that has been run, or a modified analysis, and we didn't use this filter.
                        $sFilterClassName = 'filter_skipped';
                    } elseif ($zAnalysis['analysis_run']) {
                        // This analysis has been run.
                        list($nVariantsFiltered, $nTime) = $aFiltersRun[$sFilter];
                        if ($nVariantsFiltered != '-' && $nTime != '-') {
                            $nVariantsLeft -= $nVariantsFiltered;
                            $sFilterClassName = 'filter_completed';
                        }
                    }

                    // Check if we need to display the information for the filters used in this analysis.
                    $sFilterConfigInfo = lovd_getFilterConfigHTML($zAnalysis['runid'], $sFilter);

                    // Format the display of the filters.
                    if (isset($aFilterInfo[$sFilter]) && (!empty($aFilterInfo[$sFilter]['name']) || !empty($aFilterInfo[$sFilter]['description']))) {
                        // We'll show details of the filter if the name or the description is filled in.
                        $sFilterName = (!$aFilterInfo[$sFilter]['name']? $sFilter : $aFilterInfo[$sFilter]['name']);
                        $sToolTip = '<TABLE border="0" cellpadding="0" cellspacing="3" class="filterinfo"><TR><TH>Key:</TH><TD>' . $sFilter . '</TD></TR><TR><TH>Filter name:</TH><TD>' . str_replace("\r\n", ' ', htmlspecialchars($sFilterName)) . '</TD></TR><TR><TH>Description:</TH><TD>' . $aFilterInfo[$sFilter]['description'] . '</TD></TR></TABLE>';
                        $sFormattedFilter = '<SPAN class="filtername" onmouseover="lovd_showToolTip(\'' . htmlspecialchars($sToolTip) . '\', this, [30, 6]);">' . str_replace(' ', '&nbsp;', str_replace("\r\n", '<BR>', htmlspecialchars($sFilterName))) . '</SPAN>';
                    } else {
                        $sFormattedFilter = $sFilter;
                    }

                    print('
                  <TR id="' . $sElementID . '_filter_' . preg_replace('/[^a-z0-9_]/i', '_', $sFilter) . '"' . (!$sFilterClassName ? '' : ' class="' . $sFilterClassName . '"') . ' valign="top">
                    <TD class="filter_description">' . $sFormattedFilter . '<DIV class="filter-config-desc">' . $sFilterConfigInfo . '</DIV></TD>
                    <TD class="filter_time">' . ($nTime == '-'? '-' : lovd_convertSecondsToTime($nTime, 1)) . '</TD>
                    <TD class="filter_var_left">' . ($nTime == '-'? '-' : $nVariantsLeft) . '</TD>
                  </TR>');
                }
                print('
                  <TR id="' . $sElementID . '_message" class="message">
                    <TD colspan="3">' . ($sAnalysisClassName == 'analysis_running analysis_half_run'? 'Analysis seems to have been interrupted' : ($sAnalysisClassName == 'analysis_run'? 'Click to see results' : 'Click to run this analysis')) . '</TD>
                  </TR>
                </TABLE>
              </TD>');
            }
            print('
            </TR>
          </TABLE>
        </DIV>');
        }
        print('
      </DIV>' . "\n\n");





        // This where the VL should be loaded. Since a VL needs quite a lot of HTML
        // that does not come out of the Ajax VL interface, we must initiate a VL here.
        // We make sure it returns no results to prevent useless database usage, but
        // like that it can easily be manipulated through Ajax to return results and
        // be visible whenever necessary.
        print('

      <SCRIPT type="text/javascript">
        function lovd_AJAX_viewEntryLoad () {
          $.get(\'ajax/viewentry.php\', {object: \'ScreeningPLUS\', id: \'' . $nScreeningToAnalyze . '\'},
            function (sData) {
              if (sData.length > 2) {
                $(\'#screeningViewEntry\').html(\'\n\' + sData);
              }
            });
        }
        $("#analyses").tabs(' . ($sDefaultTabID === false? '' : '{active: ' . $sDefaultTabID . '}') . ');
      </SCRIPT>


      <DIV id="analysis_results_VL"' . ($aScreeningVariantIDs? '' : ' style="display: none;"') . '>' . "\n");
        // The default behaviour is to show a viewlist with any variants that have a curation status.
        $_GET['search_variantid'] = (!$aScreeningVariantIDs? '0' : implode('|', $aScreeningVariantIDs)); // Use all the variant IDs for variants with a curation status.
        $_GET['search_vog_effect'] = ''; // Needs search term on visible column to show the VL framework, though (saying "No results have been found that match your criteria").
        $_GET['search_runid'] = ''; // To create the input field, that the JS code is referring to, so we can switch to viewing analysis results.
        $_GET['order'] = 'vog_effect,DESC'; // To sort on the effect field first, showing pathogenic variants first.

        require ROOT_PATH . 'class/object_custom_viewlists.plus.php';
        // VOG needs to be first, so it groups by the VOG ID.
        $_DATA = new LOVD_CustomViewListPLUS(array('VariantOnGenome', 'ObservationCounts', 'VariantOnTranscript', 'AnalysisRunResults', 'GenePanels'));

        // For MGHA instances, we want to use double click to view detailed variant page. This is so that curators can copy data on the analysis results view list.
        if (lovd_verifyInstance('mgha', false)) {
            // Double click to open variant viewentry page.
            $_DATA->setRowLink('CustomVL_AnalysisRunResults_for_I_VE', 'javascript:$(this).dblclick(function() {lovd_openWindow(\'' . lovd_getInstallURL() . 'variants/{{ID}}?&in_window\', \'VarVE_{{ID}}\', 1270);}); return false;');
        } else {
            $_DATA->setRowLink('CustomVL_AnalysisRunResults_for_I_VE', 'javascript:lovd_openWindow(\'' . lovd_getInstallURL() . 'variants/{{ID}}?&in_window#{{zData_preferred_transcriptid}}\', \'VarVE_{{ID}}\', 1270); return false;');
        }

        // Define menu, to set pathogenicity flags of multiple variants in one go.
        $bMenu         = true; // Show the gear-menu, with which users can mark and label variants?
        $bCurationStatus = true; // Are users allowed to set the curation status of variants? Value is ignored when $bMenu = false.
        if (!($_AUTH['level'] >= LEVEL_OWNER && $zScreening['analysis_statusid'] < ANALYSIS_STATUS_CLOSED) &&
            !($_AUTH['level'] >= LEVEL_MANAGER && $zScreening['analysis_statusid'] < ANALYSIS_STATUS_WAIT_CONFIRMATION)) {
            $bMenu = false;
            if ($_AUTH['level'] >= LEVEL_ADMIN && $zScreening['analysis_statusid'] < ANALYSIS_STATUS_CONFIRMED) {
                $bMenu = true;
                $bCurationStatus = false;
            }
        }
        print('      <UL id="viewlistMenu_CustomVL_AnalysisRunResults_for_I_VE" class="jeegoocontext jeegooviewlist">' . "\n");
        if (!lovd_verifyInstance('mgha', false)) {
            // Show the options to set variant effect from the viewlist, in a submenu.
            print('        <LI class="icon"><SPAN class="icon" style="background-image: url(gfx/menu_edit.png);"></SPAN>Set proposed classification' . "\n" . '          <UL>' . "\n");
            foreach ($_SETT['var_effect'] as $nEffectID => $sEffect) {
                print('            <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\', function(){$.get(\'ajax/set_variant_effect.php?' . $nEffectID . '&id=selected\', function(sResponse){if(sResponse.substring(0,1) == \'1\'){alert(\'Successfully set reported variant effect of \' + sResponse.substring(2) + \' variants to \\\'' . $sEffect . '\\\'.\');lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\');}else if(sResponse.substring(0,1) == \'9\'){alert(\'Error: \' + sResponse.substring(2));}}).fail(function(){alert(\'Error while setting variant effect.\');});});"><SPAN class="icon" style="background-image: url(gfx/menu_edit.png);"></SPAN>' . $sEffect . '</A></LI>' . "\n");
            }
            print('          </UL>' . "\n" .
                  '        </LI>' . "\n");
            if ($_AUTH['level'] >= $_SETT['user_level_settings']['set_concluded_effect']) {
                print('        <LI class="icon"><SPAN class="icon" style="background-image: url(gfx/menu_edit.png);"></SPAN>Set final classification' . "\n" . '          <UL>' . "\n");
                foreach ($_SETT['var_effect'] as $nEffectID => $sEffect) {
                    print('            <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\', function(){$.get(\'ajax/set_variant_effect.php?' . $nEffectID . '&type=concluded&id=selected\', function(sResponse){if(sResponse.substring(0,1) == \'1\'){alert(\'Successfully set concluded variant effect of \' + sResponse.substring(2) + \' variants to \\\'' . $sEffect . '\\\'.\');lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\');}else if(sResponse.substring(0,1) == \'9\'){alert(\'Error: \' + sResponse.substring(2));}}).fail(function(){alert(\'Error while setting variant effect.\');});});"><SPAN class="icon" style="background-image: url(gfx/menu_edit.png);"></SPAN>' . $sEffect . '</A></LI>' . "\n");
                }
                print('          </UL>' . "\n" . '        </LI>' . "\n");
            }
        }
        if ($bCurationStatus) {
            // Links for setting the curation statuses, in a submenu.
            print('        <LI class="icon"><SPAN class="icon" style="background-image: url(gfx/menu_edit.png);"></SPAN>Set curation status' . "\n" . '          <UL>' . "\n");
            foreach ($_SETT['curation_status'] as $nCurationStatusID => $sCurationStatus) {
                print('            <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\', function(){$.get(\'ajax/set_curation_status.php?' . $nCurationStatusID . '&id=selected\', function(sResponse){if(sResponse.substring(0,1) == \'1\'){alert(\'Successfully set curation status of \' + sResponse.substring(2) + \' variants to \\\'' . $sCurationStatus . '\\\'.\');lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\');lovd_AJAX_viewEntryLoad();}else if(sResponse.substring(0,1) == \'9\'){alert(\'Error: \' + sResponse.substring(2));}}).fail(function(){alert(\'Error while setting curation status.\');});});"><SPAN class="icon" style="background-image: url(gfx/menu_edit.png);"></SPAN>' . $sCurationStatus . '</A></LI>' . "\n");
            }
            print('            <LI class="icon"><A click="if(window.confirm(\'Are you sure you want to clear the selected variants curation status?\')){lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\', function(){$.get(\'ajax/set_curation_status.php?clear&id=selected\', function(sResponse){if(sResponse.substring(0,1) == \'1\'){alert(\'Successfully cleared the curation status of \' + sResponse.substring(2) + \' variants.\');lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\');lovd_AJAX_viewEntryLoad();}else if(sResponse.substring(0,1) == \'9\'){alert(\'Error: \' + sResponse.substring(2));}}).fail(function(){alert(\'Error while setting curation status.\');});});}else{alert(\'The selected variants curation status have not been changed.\');}"><SPAN class="icon" style="background-image: url(gfx/cross.png);"></SPAN>Clear curation status</A></LI>' . "\n");
            print('          </UL>' . "\n" . '        </LI>' . "\n");
            // Links for setting the confirmation statuses, in a submenu.
            print('        <LI class="icon"><SPAN class="icon" style="background-image: url(gfx/menu_edit.png);"></SPAN>Set confirmation status' . "\n" . '          <UL>' . "\n");
            foreach ($_SETT['confirmation_status'] as $nConfirmationStatusID => $sConfirmationStatus) {
                print('            <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\', function(){$.get(\'ajax/set_confirmation_status.php?' . $nConfirmationStatusID . '&id=selected\', function(sResponse){if(sResponse.substring(0,1) == \'1\'){alert(\'Successfully set confirmation status of \' + sResponse.substring(2) + \' variants to \\\'' . $sConfirmationStatus . '\\\'.\');lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\');lovd_AJAX_viewEntryLoad();}else if(sResponse.substring(0,1) == \'9\'){alert(\'Error: \' + sResponse.substring(2));}}).fail(function(){alert(\'Error while setting confirmation status.\');});});"><SPAN class="icon" style="background-image: url(gfx/menu_edit.png);"></SPAN>' . $sConfirmationStatus . '</A></LI>' . "\n");
            }
            print('          </UL>' . "\n" . '        </LI>' . "\n");
        }
        print('      </UL>' . "\n\n");
        if (isset($_INSTANCE_CONFIG['viewlists']['CustomVL_AnalysisRunResults_for_I_VE']['default_sort'])) {
            $_DATA->setSortDefault($_INSTANCE_CONFIG['viewlists']['CustomVL_AnalysisRunResults_for_I_VE']['default_sort']);
        }
        // Restrict the columns of this VL, if given.
        if (isset($_INSTANCE_CONFIG['viewlists']['CustomVL_AnalysisRunResults_for_I_VE']['cols_to_show'])) {
            $_DATA->setViewListCols($_INSTANCE_CONFIG['viewlists']['CustomVL_AnalysisRunResults_for_I_VE']['cols_to_show']);
        }

        if (lovd_verifyInstance('mgha', false)) {
            print('<P>');
            lovd_showInfoTable('<STRONG>Double click</STRONG> on each row to view more information about the variant.', 'information');
            print('</P>');

        }

        $aVLOptions = array(
            'show_options' => $bMenu,
        );
        $_DATA->viewList('CustomVL_AnalysisRunResults_for_I_VE', $aVLOptions);
        print('
          </DIV>');
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && in_array(ACTION, array('curate', 'edit_remarks'))) {
    // URL: /individuals/00000001?curate
    // URL: /individuals/00000001?edit_remarks
    // Edit the curation data for an entry, hide all other fields.

    $nID = sprintf('%08d', $_PE[1]);
    define('PAGE_TITLE', 'Curate individual #' . $nID);
    define('LOG_EVENT', 'IndividualCuration');

    // Load appropriate user level for this individual.
    // Authorization depends on the screenings belonging to this individual.
    // For managers and up, there needs to be a screening available that is not
    //  yet set to ANALYSIS_STATUS_WAIT_CONFIRMATION. For Analyzers, there needs
    //  to be a screening available, not yet set to ANALYSIS_STATUS_CLOSED and
    //  either started by them or freely available. For the latter check, we let
    //  lovd_isAuthorized() decide, but we'll have to check the analysis status
    //  ourselves since lovd_isAuthorized() doesn't do this (yet).
    // FIXME: Should lovd_isAuthorized() check the status of the screening, and
    //  revoke editing authorization when the status is too high? We now have
    //  the check for screening status implemented in many different places.
    // Fetch only the analyses that we might have a successful authorization on.
    $zScreenings = $_DB->q('SELECT id FROM ' . TABLE_SCREENINGS . ' WHERE individualid = ? AND analysis_statusid < ?',
        array($nID, ($_AUTH['level'] >= LEVEL_MANAGER? ANALYSIS_STATUS_WAIT_CONFIRMATION : ANALYSIS_STATUS_CLOSED)))->fetchAllColumn();
    if (!$zScreenings) {
        // Manager or not, the screenings are closed (assuming there are any).
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('Unable to curate the individual, the analysis status requires a higher user level.', 'stop');
        $_T->printFooter();
        exit;
    }

    // If we get here, there are screenings to authorize against. Managers are
    //  always cool at this point, we need at least LEVEL_OWNER.
    if ($_AUTH && $_AUTH['level'] < LEVEL_OWNER) {
        foreach ($zScreenings as $nScreeningID) {
            lovd_isAuthorized('screening_analysis', $nScreeningID);
            // If we're authorized, we can skip the rest.
            if ($_AUTH['level'] >= LEVEL_OWNER) {
                break;
            }
        }
    }

    lovd_requireAUTH(LEVEL_OWNER); // Analyzer becomes Owner, if authorized.

    require ROOT_PATH . 'class/object_individuals.plus.php';
    $_DATA = new LOVD_IndividualPLUS();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        $_DATA->checkFields($_POST, $zData);

        if (!lovd_error()) {
            // Manual query, because updateEntry() empties the whole VOG.
            $sSQL = 'UPDATE ' . TABLE_INDIVIDUALS . ' SET id = id'; // Placeholder to make sure I can add ", ".
            $aSQL = array();

            $sCurationLog = ''; // Log to show any changes to the curation data.
            foreach ($_POST as $sCol => $val) {
                // Process any of the curation custom columns.
                if ($sCol == 'Individual/Remarks' || strpos($sCol, 'Individual/Curation/') !== false) {
                    $sSQL .= ', `' . $sCol . '` = ?';
                    $aSQL[] = $val;
                    if (trim($zData[$sCol]) != trim($_POST[$sCol])) {
                        // Log any changes to these custom curation columns.
                        $sCurationLog .= $sCol . ': "' .
                            (!trim($zData[$sCol])? '<empty>' : str_replace(array("\r", "\n", "\t"), array('\r', '\n', '\t'), $zData[$sCol])) .
                            '" => "' .
                            (!trim($_POST[$sCol])? '<empty>' : str_replace(array("\r", "\n", "\t"), array('\r', '\n', '\t'), $_POST[$sCol])) . '"' . "\n";
                    }
                }
            }

            $sSQL .= ' WHERE id = ?';
            $aSQL[] = $nID;
            $_DB->q($sSQL, array_values($aSQL), true, true);

            if ($sCurationLog) {
                // Write to log if any changes were detected.
                lovd_writeLog('Event', LOG_EVENT, 'Curated individual #' . $nID . "\n" . $sCurationLog);
            }

            // Thank the user...
            header('Location: ' . lovd_getInstallURL() . CURRENT_PATH . (isset($_GET['in_window'])? '?&in_window' : ''));
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
            unset($_POST['password']);
        }

    } else {
        // Load current values.
        foreach ($zData as $key => $val) {
            $_POST[$key] = $val;
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Hardcoded ACTION because when we're publishing, but we get the form on screen (i.e., something is wrong), we want this to be handled as a normal edit.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . (isset($_GET['in_window'])? '&amp;in_window' : '') . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
        $_DATA->getForm(),
        array(
            array('', '', 'print', '<INPUT type="submit" value="Curate individual">'),
        ));
    lovd_viewForm($aForm);

    print("\n" .
          '      </FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'curation_log') {
    // URL: /individuals/00000001?curation_log
    // Show logs for the changes of curation data for this individual.

    $nID = sprintf('%08d', $_PE[1]);
    define('PAGE_TITLE', 'Curation history for individual #' . $nID);
    $_GET['search_event'] = 'IndividualCuration';

    $_T->printHeader();
    $_T->printTitle();

    $_GET['page_size'] = 10;
    $_GET['search_entry_'] = '"individual #' . $nID . '"';

    require_once ROOT_PATH . 'class/object_logs.php';
    $_DATA = new LOVD_Log();
    $aVLOptions = array(
        'cols_to_skip' => array('name', 'event', 'del'),
        'track_history' => false,
    );
    $_DATA->viewList('Logs_for_IndividualCuration', $aVLOptions);

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 4 && ctype_digit($_PE[1]) && $_PE[2] == 'analyze' && ctype_digit($_PE[3]) && ACTION == 'close') {
    // URL: /individuals/00000001/analyze/0000000001?close
    // Close a specific analysis.

    $nIndividualID = sprintf('%08d', $_PE[1]);
    $nScreeningID = sprintf('%010d', $_PE[3]);
    define('PAGE_TITLE', 'Close analysis #' . $nScreeningID . ' of individual #' . $nIndividualID);
    define('LOG_EVENT', 'AnalysisClose');

    // If all is well, we have no output, and we redirect the user back.
    // Only in case of an error, we create some output here.

    // Load appropriate user level.
    lovd_isAuthorized('screening_analysis', $nScreeningID);
    lovd_requireAUTH(LEVEL_OWNER); // Analyzer becomes Owner, if authorized.



    // This code is for all levels of closing, since the work is so very similar.
    $zData = $_DB->q('SELECT *
                          FROM ' . TABLE_SCREENINGS . ' AS s
                          WHERE id = ? AND individualid = ?', array($nScreeningID, $nIndividualID))->fetchAssoc();
    if (!$zData) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such screening defined for this individual!', 'stop');
        $_T->printFooter();
        exit;
    }



    if ($zData['analysis_statusid'] < ANALYSIS_STATUS_IN_PROGRESS) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('Analysis has not started yet, can not close analysis!', 'stop');
        $_T->printFooter();
        exit;
    } elseif ($zData['analysis_statusid'] >= ANALYSIS_STATUS_CONFIRMED) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('Analysis has already fully been closed.', 'stop');
        $_T->printFooter();
        exit;
    } else {
        // Analysis can be closed.
        $aStatuses = array_keys($_SETT['analysis_status']);
        $iCurrentStatus = array_search($zData['analysis_statusid'], $aStatuses);
        if (!isset($aStatuses[$iCurrentStatus + 1])) {
            lovd_displayError(LOG_EVENT, 'Error: Next status not available, current status is ' . $zData['analysis_statusid']);
        }
        $nNextStatus = $aStatuses[$iCurrentStatus + 1];

        // Several final checks.
        if ($zData['analysis_statusid'] >= ANALYSIS_STATUS_WAIT_CONFIRMATION) {
            // Analysis was awaiting confirmation, and will now be set to confirmed.
            // This requires Admin access.
            lovd_requireAUTH(LEVEL_ADMIN);
        } elseif ($zData['analysis_statusid'] >= ANALYSIS_STATUS_CLOSED) {
            // Analysis was closed (or higher), and will now be set to awaiting confirmation (or higher).
            // This requires Manager access.
            lovd_requireAUTH(LEVEL_MANAGER);
        }

        // All good, let's process the closure.
        // The next few queries should all be run, or non should be run.
        $_DB->beginTransaction();
        if ($_DB->q('UPDATE ' . TABLE_SCREENINGS . ' SET analysis_statusid = ?, analysis_approved_by = ?, analysis_approved_date = NOW() WHERE id = ?',
            array($nNextStatus, $_AUTH['id'], $nScreeningID))) {
            $bLog = lovd_writeLog('Event', LOG_EVENT, 'Successfully increased analysis status to "' . $_SETT['analysis_status'][$nNextStatus] . '" for individual ' . $nIndividualID . ':' . $nScreeningID);
            if ($bLog) {
                $_DB->commit();

                // All good, we send the user back.
                header('Location: ' . lovd_getInstallURL() . CURRENT_PATH);
                exit;
            }
        }
        $_DB->rollBack();
    }



    // If we get here, there's been an issue...
    $_T->printHeader();
    $_T->printTitle();
    lovd_showInfoTable('Failed to close analysis.', 'stop', 600);
    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 4 && ctype_digit($_PE[1]) && $_PE[2] == 'analyze' && ctype_digit($_PE[3]) && ACTION == 'open') {
    // URL: /individuals/00000001/analyze/0000000001?open
    // Open a specific analysis.

    $nIndividualID = sprintf('%08d', $_PE[1]);
    $nScreeningID = sprintf('%010d', $_PE[3]);
    define('PAGE_TITLE', 'Open analysis #' . $nScreeningID . ' of individual #' . $nIndividualID);
    define('LOG_EVENT', 'AnalysisOpen');

    // If all is well, we have no output, and we redirect the user back.
    // Only in case of an error, we create some output here.

    // Load appropriate user level.
    lovd_isAuthorized('screening_analysis', $nScreeningID);
    lovd_requireAUTH(LEVEL_OWNER); // Analyzer becomes Owner, if authorized.



    // This code is for all levels of opening, since the work is so very similar.
    $zData = $_DB->q('SELECT *
                          FROM ' . TABLE_SCREENINGS . ' AS s
                          WHERE id = ? AND individualid = ?', array($nScreeningID, $nIndividualID))->fetchAssoc();
    if (!$zData) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such screening defined for this individual!', 'stop');
        $_T->printFooter();
        exit;
    }



    if ($zData['analysis_statusid'] >= ANALYSIS_STATUS_ARCHIVED) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('Analysis has already been archived, can not re-open analysis!', 'stop');
        $_T->printFooter();
        exit;
    } elseif ($zData['analysis_statusid'] <= ANALYSIS_STATUS_IN_PROGRESS) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('Analysis has already fully been opened.', 'stop');
        $_T->printFooter();
        exit;
    } else {
        // Analysis can be opened.
        $aStatuses = array_keys($_SETT['analysis_status']);
        $iCurrentStatus = array_search($zData['analysis_statusid'], $aStatuses);
        if (!isset($aStatuses[$iCurrentStatus - 1])) {
            lovd_displayError(LOG_EVENT, 'Error: Previous status not available, current status is ' . $zData['analysis_statusid']);
        }
        $nPreviousStatus = $aStatuses[$iCurrentStatus - 1];

        // Several final checks.
        if ($zData['analysis_statusid'] >= ANALYSIS_STATUS_CONFIRMED) {
            // Analysis was set to confirmed (or higher, shouldn't get here), and will now be set to awaiting confirmation.
            // This requires Admin access.
            lovd_requireAUTH(LEVEL_ADMIN);
        } elseif ($zData['analysis_statusid'] >= ANALYSIS_STATUS_WAIT_CONFIRMATION) {
            // Analysis was awaiting confirmation (or higher, shouldn't get here), and will now be set to closed.
            // This requires Manager access.
            lovd_requireAUTH(LEVEL_MANAGER);
        } elseif ($zData['analysis_statusid'] >= ANALYSIS_STATUS_CLOSED) {
            // Analysis was closed (or higher, shouldn't get here), and will now be set to in progress.
            // This requires Manager access, or Owner access with the
            //  additional requirement of being the one who closed the analysis.
            if (!($_AUTH['level'] == LEVEL_OWNER && $zData['analysis_approved_by'] == $_AUTH['id'])) {
                lovd_requireAUTH(LEVEL_MANAGER);
            }
        }

        // All good, let's process the opening.
        // The next few queries should all be run, or non should be run.
        $_DB->beginTransaction();
        // Choosing to overwrite the Approved By and Approved Date values as well,
        //  so that at least you can see who put the analysis in this state, and when.
        // You simply don't know the direction in which the status moved (up or down).
        if ($_DB->q('UPDATE ' . TABLE_SCREENINGS . ' SET analysis_statusid = ?, analysis_approved_by = ?, analysis_approved_date = NOW() WHERE id = ?',
            array($nPreviousStatus, $_AUTH['id'], $nScreeningID))) {
            $bLog = lovd_writeLog('Event', LOG_EVENT, 'Successfully decreased analysis status to "' . $_SETT['analysis_status'][$nPreviousStatus] . '" for individual ' . $nIndividualID . ':' . $nScreeningID);
            if ($bLog) {
                $_DB->commit();

                // All good, we send the user back.
                header('Location: ' . lovd_getInstallURL() . CURRENT_PATH);
                exit;
            }
        }
        $_DB->rollBack();
    }



    // If we get here, there's been an issue...
    $_T->printHeader();
    $_T->printTitle();
    lovd_showInfoTable('Failed to open analysis.', 'stop', 600);
    $_T->printFooter();
    exit;
}















if ((PATH_COUNT == 1 || (!empty($_PE[1]) && !ctype_digit($_PE[1]))) && !ACTION) {
    // URL: /individuals
    // URL: /individuals/DMD
    // View all entries.

    if (!empty($_PE[1])) {
        $sGene = $_DB->q('SELECT id FROM ' . TABLE_GENES . ' WHERE id = ?', array($_PE[1]))->fetchColumn();
        if ($sGene) {
            lovd_isAuthorized('gene', $sGene); // To show non public entries.
            $_GET['search_genes_searched'] = '="' . $sGene . '"';
        } else {
            // Command or gene not understood.
            // FIXME; perhaps a HTTP/1.0 501 Not Implemented? If so, provide proper output (gene not found) and
            //   test if browsers show that output or their own error page. Also, then, use the same method at
            //   the bottom of all files, as a last resort if command/URL is not understood. Do all of this LATER.
            exit;
        }
    }

    // Managers and authorized curators are allowed to download this list...
    if ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    $_T->printHeader();
    $_T->printTitle();

    $aColsToHide = array('panelid', 'diseaseids');
    if (isset($sGene)) {
        $aColsToHide[] = 'genes_screened_';
        $aColsToHide[] = 'variants_in_genes_';
    }

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    $aVLOptions = array(
        'cols_to_skip' => $aColsToHide,
        'show_options' => ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR),
        'find_and_replace' => true,
        'curate_set' => true,
        'merge_set' => true,
    );
    $_DATA->viewList('Individuals', $aVLOptions);

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /individuals/00000001
    // View specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());

    // Before we start the template here, do a quick check if this entry exists.
    // This is non-standard, we normally rely on viewEntry() to sort that out.
    // But this individual may have been merged, after sending the submission
    //  confirmation email. Check the logs if that is the case, and forward.
    if (!$_DB->q('SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?',
            array($nID))->fetchColumn()) {
        // Entry doesn't exist. Can we find a merge log entry?
        $nNewID = $_DB->q('
            SELECT RIGHT(log, ' . $_SETT['objectid_length'][$_PE[0]] . ')
            FROM ' . TABLE_LOGS . '
            WHERE name = ? AND event = ? AND log LIKE ?',
                array('Event', 'MergeEntries', 'Merged individual entry #' . $nID . ' into %'))->fetchColumn();
        if ($nNewID) {
            // HTTP 301 Moved Permanently.
            header('Location: ' . lovd_getInstallURL() . $_PE[0] . '/' . $nNewID . '?&redirected_after_merge', true, 301);
            exit;
        }
    }

    $_T->printHeader();
    $_T->printTitle();

    if (isset($_GET['redirected_after_merge'])) {
        // We got redirected after using an ID that got merged into this entry.
        lovd_showInfoTable(
            'Please note that you got redirected after using an Individual ID that no longer exists,' .
            ' because that entry got merged into this entry.', 'warning');
    }

    // Load appropriate user level for this individual.
    lovd_isAuthorized('individual', $nID);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual($nID);

    // Added the DIV to allow us reloading the VE using JS.
    print('      <DIV id="viewentryDiv">' . "\n");
    $zData = $_DATA->viewEntry($nID);
    print('      </DIV>' . "\n\n");

    $aNavigation = array();
    if ($_AUTH && $_AUTH['level'] >= LEVEL_OWNER) {
        $aNavigation[CURRENT_PATH . '?edit']                     = array('menu_edit.png', 'Edit individual entry', 1);
        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            if ($zData['statusid'] < STATUS_OK) {
                $aNavigation[CURRENT_PATH . '?publish']          = array('check.png', ($zData['statusid'] == STATUS_MARKED? 'Remove mark from' : 'Publish (curate)') . ' individual entry', 1);
            }
            $aNavigation['javascript:$.get(\'ajax/curate_set.php?bySubmission&id=' . $nID . '\').fail(function(){alert(\'Request failed. Please try again.\');});'] = array('check.png', 'Publish (curate) entire submission', 1);
        }
        // You can only add phenotype information to this individual, when there are phenotype columns enabled.
        if ($_DB->q('SELECT COUNT(*) FROM ' . TABLE_IND2DIS . ' AS i2d INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc USING(diseaseid) WHERE i2d.individualid = ?', array($nID))->fetchColumn()) {
            $aNavigation['phenotypes?create&amp;target=' . $nID] = array('menu_plus.png', 'Add phenotype information to individual', 1);
        }
        $aNavigation['screenings?create&amp;target=' . $nID]     = array('menu_plus.png', 'Add screening to individual', 1);
        $aNavigation['download/all/submission/' . $nID]          = array('menu_save.png', 'Download submission in LOVD3 format', 1);
        if ($_AUTH['level'] >= $_SETT['user_level_settings']['delete_individual']) {
            $aNavigation[CURRENT_PATH . '?delete']               = array('cross.png', 'Delete individual entry', 1);
        }
    }
    lovd_showJGNavigation($aNavigation, 'Individuals');

    print('<BR><BR>' . "\n\n");


    if (!empty($zData['phenotypes'])) {
        // List of phenotype entries associated with this person, per disease.
        $_GET['search_individualid'] = $nID;
        $_T->printTitle('Phenotypes', 'H4');
        // Repeat searching for diseases, since this individual might have a
        //  phenotype entry for a disease they don't have.
        $zData['diseases'] = $_DB->q('SELECT id, symbol, name FROM ' . TABLE_DISEASES . ' WHERE id IN (?' . str_repeat(', ?', count($zData['phenotypes'])-1) . ')', $zData['phenotypes'])->fetchAllRow();
        require ROOT_PATH . 'class/object_phenotypes.php';
        foreach ($zData['diseases'] as $aDisease) {
            list($nDiseaseID, $sSymbol, $sName) = $aDisease;
            if (in_array($nDiseaseID, $zData['phenotypes'])) {
                $_GET['search_diseaseid'] = $nDiseaseID;
                $_DATA = new LOVD_Phenotype($nDiseaseID);
                print('<B>' . $sName . ' (<A href="diseases/' . $nDiseaseID . '">' . $sSymbol . '</A>)</B>&nbsp;&nbsp;<A href="phenotypes?create&amp;target=' . $nID . '&amp;diseaseid=' . $nDiseaseID . '"><IMG src="gfx/plus.png"></A> Add phenotype for this disease');
                $aVLOptions = array(
                    'cols_to_skip' => array('phenotypeid', 'individualid', 'diseaseid'),
                    'track_history' => false,
                    'show_navigation' => false,
                );
                $_DATA->viewList('Phenotypes_for_I_VE_' . $nDiseaseID, $aVLOptions);
            }
        }
        unset($_GET['search_individualid']);
        unset($_GET['search_diseaseid']);
    } else {
        lovd_showInfoTable('No phenotypes found for this individual!', 'stop');
    }

    if (count($zData['screeningids'])) {
        $_GET['search_individualid'] = $nID;
        print('<BR><BR>' . "\n\n");
        $_T->printTitle('Screenings', 'H4');
        require ROOT_PATH . 'class/object_screenings.php';
        $_DATA = new LOVD_Screening();
        $_DATA->setSortDefault('id');
        $aScreeningVLOptions = array(
            'cols_to_skip' => array('screeningid', 'individualid', 'created_date', 'edited_date'),
            'track_history' => false,
            'show_navigation' => false,
            'show_options' => ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR),
            'merge_set' => true,
        );
        // This ViewList ID is checked in ajax/viewlist.php. Don't just change it.
        $_DATA->viewList('Screenings_for_I_VE', $aScreeningVLOptions);
        unset($_GET['search_individualid']);

        $_GET['search_screeningid'] = implode('|', $zData['screeningids']);
        print('<BR><BR>' . "\n\n");
        $_T->printTitle('Variants', 'H4');

        require ROOT_PATH . 'class/object_custom_viewlists.php';
        // VOG needs to be first, so it groups by the VOG ID.
        $_DATA = new LOVD_CustomViewList(array('VariantOnGenome', 'Scr2Var', 'VariantOnTranscript'));
        $aVariantVLOptions = array(
            'show_options' => ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR),
        );
        $_DATA->viewList('CustomVL_VOT_for_I_VE', $aVariantVLOptions);
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /individuals?create
    // Create a new entry.

    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'IndividualCreate');

    if ($_AUTH) {
        lovd_isAuthorized('gene', $_AUTH['curates']);
    }
    lovd_requireAUTH($_SETT['user_level_settings']['submit_new_data']);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('panelid', 'panel_size', 'owned_by', 'statusid', 'created_by', 'created_date'),
                            $_DATA->buildFields());

            // Prepare values.
            $_POST['owned_by'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['owned_by'] : $_AUTH['id']);
            $_POST['statusid'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['statusid'] : STATUS_IN_PROGRESS);
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created individual information entry ' . $nID);

            // Add diseases.
            $aSuccessDiseases = array();
            if (!empty($_POST['active_diseases']) && is_array($_POST['active_diseases'])) {
                foreach ($_POST['active_diseases'] as $nDisease) {
                    // Add disease to gene.
                    if ($nDisease) {
                        $q = $_DB->q('INSERT INTO ' . TABLE_IND2DIS . ' VALUES (?, ?)', array($nID, $nDisease), false);
                        if (!$q) {
                            // Silent error.
                            lovd_writeLog('Error', LOG_EVENT, 'Disease information entry ' . $nDisease . ' - could not be added to individual ' . $nID);
                        } else {
                            $aSuccessDiseases[] = $nDisease;
                        }
                    }
                }
            }

            if (count($aSuccessDiseases)) {
                lovd_writeLog('Event', LOG_EVENT, 'Disease entr' . (count($aSuccessDiseases) > 1? 'ies' : 'y') . ' successfully added to individual ' . $nID);
            }

            $_AUTH['saved_work']['submissions']['individual'][$nID] = array('id' => $nID, 'panel_size' => $_POST['panel_size']);
            lovd_saveWork();

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'submit/individual/' . $nID);

            $_T->printHeader();
            $_T->printTitle();

            lovd_showInfoTable('Successfully created the individual information entry!', 'success');

            $_T->printFooter();
            exit;
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    $_T->printHeader();
    $_T->printTitle();

    if (GET) {
        print('      To create a new individual information entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'submit', 'Create individual information entry'),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && in_array(ACTION, array('edit', 'publish'))) {
    // URL: /individuals/00000001?edit
    // URL: /individuals/00000001?publish
    // Edit an entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'IndividualEdit');

    // Load appropriate user level for this individual.
    lovd_isAuthorized('individual', $nID);
    if (ACTION == 'publish') {
        lovd_requireAUTH(LEVEL_CURATOR);
    } else {
        lovd_requireAUTH(LEVEL_OWNER);
    }

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    $bSubmit = (isset($_AUTH['saved_work']['submissions']['individual'][$nID]));

    // If we're publishing... pretend the form has been sent with a different status.
    if (GET && ACTION == 'publish') {
        $_POST = $zData;
        $_POST['statusid'] = STATUS_OK;
    }

    if (POST || ACTION == 'publish') {
        lovd_errorClean();

        $_DATA->checkFields($_POST, $zData);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('panelid', 'panel_size'),
                            (!$bSubmit || !empty($zData['edited_by'])? array('edited_by', 'edited_date') : array()),
                            $_DATA->buildFields());

            // Prepare values.
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $aFields[] = 'owned_by';
                $aFields[] = 'statusid';
            } elseif ($zData['statusid'] > STATUS_MARKED) {
                $aFields[] = 'statusid';
                $_POST['statusid'] = STATUS_MARKED;
            }
            // Only actually committed to the database if we're not in a submission, or when they are already filled in.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            if (!$bSubmit && !(GET && ACTION == 'publish')) {
                // Put $zData with the old values in $_SESSION for mailing.
                $_SESSION['work']['edits']['individual'][$nID] = $zData;
            }

            // FIXME: implement versioning in updateEntry!
            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Get genes which are modified only when individual and variant status is marked or public.
            if ($zData['statusid'] >= STATUS_MARKED || (isset($_POST['statusid']) && $_POST['statusid'] >= STATUS_MARKED)) {
                $aGenes = $_DB->q('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                                      'INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) ' .
                                      'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vog.id = vot.id) ' .
                                      'INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.variantid = vog.id) ' .
                                      'INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) ' .
                                      'WHERE vog.statusid >= ? AND s.individualid = ?', array(STATUS_MARKED, $nID))->fetchAllColumn();
                if ($aGenes) {
                    // Change updated date for genes.
                    lovd_setUpdatedDate($aGenes);
                }
            }

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited individual information entry ' . $nID);

            // Change linked diseases?
            // Remove diseases.
            $aToRemove = array();
            foreach ($zData['active_diseases'] as $nDisease) {
                if ($nDisease && !in_array($nDisease, $_POST['active_diseases'])) {
                    // User has requested removal...
                    $aToRemove[] = $nDisease;
                }
            }
            if ($aToRemove) {
                $q = $_DB->q('DELETE FROM ' . TABLE_IND2DIS . ' WHERE individualid = ? AND diseaseid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($nID), $aToRemove), false);
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' could not be removed from individual ' . $nID);
                } else {
                    lovd_writeLog('Event', LOG_EVENT, 'Disease entr' . (count($aToRemove) > 1? 'ies' : 'y') . ' successfully removed from individual ' . $nID);
                }
            }

            // Add diseases.
            $aSuccess = array();
            $aFailed = array();
            foreach ($_POST['active_diseases'] as $nDisease) {
                if (!in_array($nDisease, $zData['active_diseases'])) {
                    // Add disease to gene.
                    $q = $_DB->q('INSERT IGNORE INTO ' . TABLE_IND2DIS . ' VALUES (?, ?)', array($nID, $nDisease), false);
                    if (!$q) {
                        $aFailed[] = $nDisease;
                    } else {
                        $aSuccess[] = $nDisease;
                    }
                }
            }
            if ($aFailed) {
                // Silent error.
                lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aFailed) == 1? 'y' : 'ies') . ' ' . implode(', ', $aFailed) . ' could not be added to individual ' . $nID);
            }
            if (count($aSuccess)) {
                lovd_writeLog('Event', LOG_EVENT, 'Disease entr' . (count($aSuccess) > 1? 'ies' : 'y') . ' successfully added to individual ' . $nID);
            }

            // Thank the user...
            if ($bSubmit) {
                header('Refresh: 3; url=' . lovd_getInstallURL() . 'submit/individual/' . $nID);

                $_T->printHeader();
                $_T->printTitle();
                lovd_showInfoTable('Successfully edited the individual information entry!', 'success');

                $_T->printFooter();
            } elseif (GET && ACTION == 'publish') {
                // We'll skip the mailing. But of course only if we're sure no other changes were sent (therefore check GET).
                header('Location: ' . lovd_getInstallURL() . CURRENT_PATH);
            } else {
                header('Location: ' . lovd_getInstallURL() . 'submit/finish/individual/' . $nID . '?edit');
            }

            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
            unset($_POST['password']);
        }

    } else {
        // Load current values.
        $_POST = array_merge($_POST, $zData);
        if ($zData['statusid'] < STATUS_HIDDEN) {
            $_POST['statusid'] = STATUS_OK;
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    // If we're not the creator nor the owner, warn.
    if ($zData['created_by'] != $_AUTH['id'] && $zData['owned_by'] != $_AUTH['id']) {
        lovd_showInfoTable('Warning: You are editing data not created or owned by you. You are free to correct errors such as data inserted into the wrong field or typographical errors, but make sure that all other edits are made in consultation with the submitter. If you disagree with the submitter\'s findings, add a remark rather than removing or overwriting data.', 'warning', 760);
    }

    if (GET) {
        print('      To edit an individual information entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Hardcoded ACTION because when we're publishing, but we get the form on screen (i.e., something is wrong), we want this to be handled as a normal edit.
    print('      <FORM action="' . CURRENT_PATH . '?edit" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'print', '<INPUT type="submit" value="Edit individual information entry">' . ($bSubmit? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . 'submit/individual/' . $nID . '\'; return false;" style="border : 1px solid #FF4422;">' : '')),
                      ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit_panels') {
    // URL: /individuals/00000001?edit_panels
    // Edit an individuals gene panels.

    $nID = sprintf('%08d', $_PE[1]);
    define('PAGE_TITLE', 'Edit gene panels for individual #' . $nID);
    define('LOG_EVENT', 'IndividualEditPanels');

    // Load appropriate user level for this individual.
    // Authorization depends on the screenings belonging to this individual.
    // For managers and up, there needs to be a screening available that is not
    //  yet set to ANALYSIS_STATUS_WAIT_CONFIRMATION. For Analyzers, there needs
    //  to be a screening available, not yet set to ANALYSIS_STATUS_CLOSED and
    //  either started by them or freely available. For the latter check, we let
    //  lovd_isAuthorized() decide, but we'll have to check the analysis status
    //  ourselves since lovd_isAuthorized() doesn't do this (yet).
    // FIXME: Should lovd_isAuthorized() check the status of the screening, and
    //  revoke editing authorization when the status is too high? We now have
    //  the check for screening status implemented in many different places.
    // Fetch only the analyses that we might have a successful authorization on.
    $zScreenings = $_DB->q('SELECT id FROM ' . TABLE_SCREENINGS . ' WHERE individualid = ? AND analysis_statusid < ?',
        array($nID, ($_AUTH['level'] >= LEVEL_MANAGER? ANALYSIS_STATUS_WAIT_CONFIRMATION : ANALYSIS_STATUS_CLOSED)))->fetchAllColumn();
    if (!$zScreenings) {
        // Manager or not, the screenings are closed (assuming there are any).
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('Unable to assign gene panels, the analysis status requires a higher user level.', 'stop');
        $_T->printFooter();
        exit;
    }

    // If we get here, there are screenings to authorize against. Managers are
    //  always cool at this point, we need at least LEVEL_OWNER.
    if ($_AUTH && $_AUTH['level'] < LEVEL_OWNER) {
        foreach ($zScreenings as $nScreeningID) {
            lovd_isAuthorized('screening_analysis', $nScreeningID);
            // If we're authorized, we can skip the rest.
            if ($_AUTH['level'] >= LEVEL_OWNER) {
                break;
            }
        }
    }

    lovd_requireAUTH(LEVEL_OWNER); // Analyzer becomes Owner, if authorized.

    require ROOT_PATH . 'class/object_individuals.plus.php';
    $_DATA = new LOVD_IndividualPLUS();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        // Checks to make sure a valid gene panel ID is used.
        if (!empty($_POST['gene_panels'])) {
            $aGenePanels = $_DB->q('SELECT id FROM ' . TABLE_GENE_PANELS)->fetchAllColumn();
            foreach ($_POST['gene_panels'] as $nGenePanelID) {
                if ($nGenePanelID && !in_array($nGenePanelID, $aGenePanels)) {
                    lovd_errorAdd('gene_panels', htmlspecialchars($nGenePanelID) . ' is not a valid gene panel ID.');
                }
            }
        }

        // Checks the genes added to the custom panel to ensure they exist within the database.
        if (!empty($_POST['custom_panel'])) {
            // Explode the custom panel genes into an array.
            $aGeneSymbols = array_values(array_unique(array_filter(preg_split('/(\s|[,;])+/', strtoupper($_POST['custom_panel'])))));

            // Check if there are any genes left after cleaning up the gene symbol string.
            if (count($aGeneSymbols) > 0) {
                // Load the matching genes into an array.
                $aGenesInLOVD = $_DB->q('SELECT UPPER(id), id FROM ' . TABLE_GENES . ' WHERE id IN (?' . str_repeat(', ?', count($aGeneSymbols) - 1) . ')', $aGeneSymbols)->fetchAllCombine();
                // Loop through all the gene symbols in the array and check them for any errors.
                foreach ($aGeneSymbols as $nKey => $sGeneSymbol) {
                    // Check to see if this gene symbol has been found within the database.
                    if (isset($aGenesInLOVD[$sGeneSymbol])) {
                        // A correct gene symbol was found, so lets use that to remove any case issues.
                        $aGeneSymbols[$nKey] = $aGenesInLOVD[$sGeneSymbol];
                    } else {
                        // This gene symbol was not found in the database.
                        // It got uppercased by us, but we assume that will be OK.
                        lovd_errorAdd('custom_panel', 'The gene symbol ' . htmlspecialchars($sGeneSymbol) . ' can not be found within the database. Please try an alternative symbol.');
                    }
                }
                // Write the cleaned up custom gene panel back to POST so as to ensure the genes in the custom panel are stored with correct casing.
                $_POST['custom_panel'] = implode(', ', $aGeneSymbols);
            }
        }
        lovd_checkXSS();

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('custom_panel');
            $sDateNow = date('Y-m-d H:i:s');

            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited gene panels for individual ' . $nID);

            // Change linked gene panels.
            $aGenePanels = array();
            if ($zData['gene_panels']) {
                $aGenePanels = $zData['gene_panels'];
            }
            // If we have removed all gene panels from this individual then we need to set the $_POST['gene_panels'] to an empty array otherwise functions below will error.
            if (empty($_POST['gene_panels'])) {
                $_POST['gene_panels'] = array();
            }
            // Remove gene panels.
            $aToRemove = array();
            foreach ($aGenePanels as $nGenePanel) {
                if ($nGenePanel && !in_array($nGenePanel, $_POST['gene_panels'])) {
                    // User has requested removal...
                    $aToRemove[] = $nGenePanel;
                }
            }
            if ($aToRemove) {
                $q = $_DB->q('DELETE FROM ' . TABLE_IND2GP . ' WHERE individualid = ? AND genepanelid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($nID), $aToRemove), false);
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Gene panel entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' could not be removed from individual ' . $nID);
                }
            }

            // Add gene panels.
            $aSuccess = array();
            $aFailed = array();
            foreach ($_POST['gene_panels'] as $nGenePanel) {
                if (!in_array($nGenePanel, $aGenePanels)) {
                    // Add gene panel to individual.
                    $q = $_DB->q('INSERT IGNORE INTO ' . TABLE_IND2GP . ' VALUES (?, ?, ?, ?)', array($nID, $nGenePanel, $_AUTH['id'], $sDateNow), false);
                    if (!$q) {
                        $aFailed[] = $nGenePanel;
                    } else {
                        $aSuccess[] = $nGenePanel;
                    }
                }
            }
            if ($aFailed) {
                // Silent error.
                lovd_writeLog('Error', LOG_EVENT, 'Gene panel entr' . (count($aFailed) == 1? 'y' : 'ies') . ' ' . implode(', ', $aFailed) . ' could not be added to individual ' . $nID);
            }

            // Thank the user...
            if (!$aFailed) {
                header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);
            }
            $_T->printHeader();
            $_T->printTitle();
            if (!$aFailed) {
                lovd_showInfoTable('Successfully edited the gene panels for this individual!', 'success');
            } else {
                lovd_showInfoTable('Failed to edit the gene panels for this individual. Please see the <A href="logs">system logs</A> for more information.', 'stop');
            }

            $_T->printFooter();
            exit;
        }

    } else {
        // Not submitted, set default values for this form.
        $_POST = array_merge($_POST, $zData);

        if (empty($_POST['gene_panels'])) {
            $_POST['gene_panels'] = array();
        }

        // Check to see if any gene panels are selected, if not then lets suggest some.
        if (empty($_POST['gene_panels']) && !empty($zData['active_diseases'])) {
            $aDiseases = $zData['active_diseases'];

            // Check to see if any gene panels share the individuals diseases.
            $aDefaultGenePanels = $_DB->q('SELECT DISTINCT gp.id, gp.name, gp.type FROM ' . TABLE_GENE_PANELS . ' AS gp JOIN ' . TABLE_GP2DIS . ' AS gp2d ON (gp.id = gp2d.genepanelid) WHERE gp2d.diseaseid IN (?' . str_repeat(', ?', count($aDiseases)-1) . ') ORDER BY gp.type DESC, gp.name ASC', array_values($aDiseases))->fetchAllAssoc();

            if ($aDefaultGenePanels) {
                // Set the default gene panels in the form to these gene panels.
                foreach ($aDefaultGenePanels as $nKey => $aGenePanel) {
                    $_POST['gene_panels'][] = $aGenePanel['id'];
                }
            }
        }
    }

    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    print('      <DIV style="display: -webkit-flex; display: flex; flex-direction: row;">
        <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Get list of gene panels.
    // Note: CAST(id AS CHAR) is needed to preserve the zerofill.
    $aGenePanelsForm = $_DB->q('(SELECT "optgroup2" AS id, "Mendeliome" AS name, "mendeliome_header" AS type)
                                     UNION
                                    (SELECT "optgroup1", "Gene Panels", "gene_panel_header")
                                     UNION
                                    (SELECT "optgroup3", "Blacklist", "blacklist_header")
                                     UNION
                                    (SELECT CAST(id AS CHAR), name, type FROM ' . TABLE_GENE_PANELS . ')
                                     ORDER BY type DESC, name')->fetchAllCombine();
    $nGenePanels = count($aGenePanelsForm); // Also contains the gene panel groups!
    foreach ($aGenePanelsForm as $nID => $sGenePanel) {
        $aGenePanelsForm[$nID] = lovd_shortenString($sGenePanel, 75);
    }
    $nGPFieldSize = ($nGenePanels < 20? $nGenePanels : 20);
    // Obviously I could check if $nGenePanels is 3 here, but in case I ever forget to change that
    //  if I would change the structure of the option list, this would be better.
    if (!array_filter($aGenePanelsForm,
        function ($sKey) {
            return ctype_digit((string) $sKey);
        }, ARRAY_FILTER_USE_KEY)) {
        $aGenePanelsForm = array('' => 'No gene panel entries available');
        $nGPFieldSize = 1;
    }

    // Array which will make up the form table.
    $aForm =
        array(
            array('POST', '', '', '', '50%', '14', '50%'),
            'custom_panel' => array('Custom gene panel', 'Please insert any gene symbols here that you want to use as a custom gene panel, only for this individual.', 'textarea', 'custom_panel', 50, 2),
            'aGenePanels' => array('Assigned gene panels', '', 'select', 'gene_panels', $nGPFieldSize, $aGenePanelsForm, false, true, false),
            array('', '', 'print', '<INPUT type="submit" value="Edit gene panels">'),
        );
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    if (!empty($aDefaultGenePanels)) {
        $nDefaultGenePanels = count($aDefaultGenePanels);
        // Display a message to the user identifying the gene panels that have been assigned by default.
        $sDefaultGenePanelMsg = "\n" .
            '            <H4 class="LOVD">Default gene panels</H4>
            The following gene panel' . ($nDefaultGenePanels == 1? ' is' : 's were') . ' automatically assigned to this individual because ' . ($nDefaultGenePanels == 1? 'it is' : 'they are') . ' associated to (one of) the individual\'s diseases:<BR><BR>
            <TABLE border="0" cellpadding="1" cellspacing="1" width="90%" class="data" style="background: #dddddd; font-size: 13px;">
              <TR>
                <TH width="70%">Gene panel name</TH>
                <TH>Type</TH>
              </TR>' . "\n";
        // List all the gene panels found in a table.
        foreach ($aDefaultGenePanels as $nKey => $aGenePanel) {
            $sDefaultGenePanelMsg .= '              <TR>
                <TD>' . $aGenePanel['name'] . '</TD>
                <TD>' . ucfirst(str_replace('_', ' ', $aGenePanel['type'])) . '</TD>
              </TR>' . "\n";
        }
        $sDefaultGenePanelMsg .= '            </TABLE><BR>
            If you do not wish to assign these gene panels to this individual then please unselect them.<BR><BR>';

        print('          <DIV style="width: 500px; margin-left: 25px;">' . "\n");
        lovd_showInfoTable($sDefaultGenePanelMsg, 'information');
        print('          </DIV>');
    }
    print('    </DIV>');
    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /individuals/00000001?delete
    // Drop specific entry.

    $nID = lovd_getCurrentID();
    define('PAGE_TITLE', lovd_getCurrentPageTitle());
    define('LOG_EVENT', 'IndividualDelete');

    // FIXME: What if individual also contains other user's data?
    lovd_isAuthorized('individual', $nID);
    lovd_requireAUTH($_SETT['user_level_settings']['delete_individual']);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    $aVariantsRemovable = $_DB->q('
        SELECT s2v.variantid, MAX(s2.individualid)
        FROM lovd_v3_screenings AS s
          INNER JOIN lovd_v3_screenings2variants AS s2v ON (s.id = s2v.screeningid)
          LEFT OUTER JOIN lovd_v3_screenings2variants AS s2v2 ON (s2v.variantid = s2v2.variantid AND s2v.screeningid != s2v2.screeningid)
          LEFT OUTER JOIN lovd_v3_screenings AS s2 ON (s2v2.screeningid = s2.id AND s2.individualid != ?)
        WHERE s.individualid = ?
        GROUP BY s2v.variantid
        HAVING MAX(s2.individualid) IS NULL', array($nID, $nID))->fetchAllColumn();
    $nVariantsRemovable = count($aVariantsRemovable);

    if (!empty($_POST)) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        // User had to enter their password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text.
            $_DB->beginTransaction();

            // Search for effected genes before the deletion, else we can't find the link.
            // Get genes which are modified only when individual and variant status is marked or public.
            if ($zData['statusid'] >= STATUS_MARKED) {
                $aGenes = $_DB->q('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                    'INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) ' .
                    'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vog.id = vot.id) ' .
                    'INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.variantid = vog.id) ' .
                    'INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) ' .
                    'WHERE vog.statusid >= ? AND s.individualid = ?', array(STATUS_MARKED, $nID))->fetchAllColumn();
            }

            if (isset($_POST['remove_variants']) && $_POST['remove_variants'] == 'remove') {
                // This also deletes the entries in TABLE_SCR2VAR.
                $_DB->q('DELETE FROM ' . TABLE_VARIANTS . ' WHERE id IN (?' . str_repeat(', ?', count($aVariantsRemovable) - 1) . ')', $aVariantsRemovable);
            }

            // This also deletes the entries in TABLE_PHENOTYPES && TABLE_SCREENINGS && TABLE_SCR2VAR && TABLE_SCR2GENE.
            $_DATA->deleteEntry($nID);

            if ($zData['statusid'] >= STATUS_MARKED && $aGenes) {
                // Change updated date for genes.
                lovd_setUpdatedDate($aGenes);
            }

            $_DB->commit();

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted individual information entry ' . $nID . ' (Owner: ' . $zData['owned_by_'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0]);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the individual information entry!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password field!
            unset($_POST['password']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    $nVariants = $_DB->q('SELECT COUNT(DISTINCT s2v.variantid) FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) WHERE s.individualid = ? GROUP BY s.individualid', array($nID))->fetchColumn();
    $aOptions = array('remove' => 'Yes, remove ' . ($nVariantsRemovable == 1? 'this variant' : 'these variants') . ' attached to only this individual', 'keep' => 'No, keep ' . ($nVariantsRemovable == 1? 'this variant' : 'these variants') . ' as separate entries');

    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '40%', '14', '60%'),
                        array('Deleting individual information entry', '', 'print', '<B>' . $nID . ' (Owner: ' . htmlspecialchars($zData['owned_by_']) . ')</B>'),
                        'skip',
                        array('', '', 'print', 'This individual entry has ' . ($nVariants?: 0) . ' variant' . ($nVariants == 1? '' : 's') . ' attached.'),
'variants_removable' => array('', '', 'print', (!$nVariantsRemovable? 'No variants will be removed.' : '<B>' . $nVariantsRemovable . ' variant' . ($nVariantsRemovable == 1? '' : 's') . ' will be removed, because ' . ($nVariantsRemovable == 1? 'it is' : 'these are'). ' not attached to other individuals!!!</B>')),
          'variants' => array('Should LOVD remove ' . ($nVariantsRemovable == 1? 'this variant' : 'these ' . $nVariantsRemovable . ' variants') . '?', '', 'select', 'remove_variants', 1, $aOptions, false, false, false),
                        array('', '', 'note', '<B>All phenotypes and screenings attached to this individual will be automatically removed' . ($nVariants? ' regardless' : '') . '!!!</B>'),
     'variants_skip' => 'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete individual information entry'),
                      ));
    if (!$nVariantsRemovable) {
        if (!$nVariants) {
            unset($aForm['variants_removable']);
        }
        unset($aForm['variants'], $aForm['variants_skip']);
    }

    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
