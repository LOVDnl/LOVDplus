<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-16
 * Modified    : 2016-10-28
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
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





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /individuals
    // View all entries.

    // Managers are allowed to download this list...
    if ($_AUTH['level'] >= LEVEL_MANAGER) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

    define('PAGE_TITLE', 'View individuals');
    $_T->printHeader();
    $_T->printTitle();

    if (LOVD_plus) {
        lovd_requireAUTH();
    }

    // Hide confirmed analyses by default.
    $_GET['search_analysis_status'] = '!="Confirmed"';
    require ROOT_PATH . 'class/object_individuals.mod.php';
    $_DATA = new LOVD_IndividualMOD();
    $_DATA->setRowLink('Individuals', 'javascript:window.location.href=\'' . lovd_getInstallURL() . $_PE[0] . '/{{id}}/analyze/{{screeningid}}\'; return false');
    $_DATA->viewList('Individuals', array(), false, false, (bool) ($_AUTH['level'] >= LEVEL_MANAGER));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT >= 2 && ctype_digit($_PE[1]) && !ACTION && (PATH_COUNT == 2 || PATH_COUNT == 4 && $_PE[2] == 'analyze' && ctype_digit($_PE[3]))) {
    // URL: /individuals/00000001
    // URL: /individuals/00000001/analyze/0000000001
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
    require ROOT_PATH . 'class/object_individuals.mod.php';
    $_DATA = new LOVD_IndividualMOD($nID);
    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    if ($_AUTH && $_AUTH['level'] == LEVEL_ADMIN) {
        $aNavigation[$_PE[0] . '/' . $_PE[1] . '?edit']                     = array('menu_edit.png', 'Edit individual entry', 1);
        $aNavigation[$_PE[0] . '/' . $_PE[1] . '?edit_panels']              = array('menu_edit.png', 'Edit gene panels', 1);
        if ($_AUTH['level'] >= LEVEL_MANAGER) {
            $aNavigation['screenings?search_individualid=' . $nID] = array('menu_magnifying_glass.png', 'View screenings', 1);
        }
        if ($zData['statusid'] < STATUS_OK && $_AUTH['level'] >= LEVEL_CURATOR) {
            $aNavigation[$_PE[0] . '/' . $_PE[1] . '?publish']              = array('check.png', ($zData['statusid'] == STATUS_MARKED ? 'Remove mark from' : 'Publish (curate)') . ' individual entry', 1);
        }
        // You can only add phenotype information to this individual, when there are phenotype columns enabled.
        if ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_IND2DIS . ' AS i2d INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc USING(diseaseid) WHERE i2d.individualid = ?', array($nID))->fetchColumn()) {
            $aNavigation['phenotypes?create&amp;target=' . $nID] = array('menu_plus.png', 'Add phenotype information to individual', 1);
        }
        $aNavigation['screenings?create&amp;target=' . $nID]     = array('menu_plus.png', 'Add screening to individual', 1);
        if ($_AUTH['level'] >= LEVEL_CURATOR) {
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
    $_DATA->viewList('Logs_for_I_VE', array('name', 'event', 'del'), true);
    unset($_GET['page_size'], $_GET['search_event'], $_GET['search_entry_']);



    print('
          </TD>
          <TD width="50">&nbsp;</TD>
          <TD valign="top" id="screeningViewEntry">' . "\n");

    // If we're here, analyzing a screening, show the screening VE on the right.
    if ($nScreeningToAnalyze) {
        // Authorize the user for this screening, but specifically meant for the analysis.
        // For LEVEL_ANALYZER, this should activate LEVEL_OWNER for
        //   free screenings or screenings under analysis by this user.
        lovd_isAuthorized('screening_analysis', $nScreeningToAnalyze);

        require_once ROOT_PATH . 'class/object_screenings.mod.php';
        $_DATA = new LOVD_ScreeningMOD();
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
    require_once ROOT_PATH . 'class/object_screenings.mod.php';
    $_DATA = new LOVD_ScreeningMOD();
    $_DATA->setSortDefault('id');
    $_DATA->setRowID('Screenings_for_I_VE', 'Screening_{{screeningid}}');
    $_DATA->setRowLink('Screenings_for_I_VE', 'javascript:window.location.href=\'' . lovd_getInstallURL() . $_PE[0] . '/' . $nID . '/analyze/{{screeningid}}\'; return false');
    $_DATA->viewList('Screenings_for_I_VE', array(), true, true);
    unset($_GET['search_individualid']);





    // If we are analyzing a screening, highlight it.
    if ($nScreeningToAnalyze) {
        // First, let's check if this analysis belongs to this individual.
        if (!$_DB->query('SELECT COUNT(*) FROM ' . TABLE_SCREENINGS . ' WHERE id = ? AND individualid = ?', array($nScreeningToAnalyze, $nID))->fetchColumn()) {
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

        // The popover div for showing the gene panel selection form.
        print('
      <DIV id="gene_panel_selection" title="Select gene panels for analysis" style="display : none;">
        <FORM id="gene_panel_selection_form">
          <INPUT type="hidden" name="nScreeningID" value="">
          <INPUT type="hidden" name="nAnalysisID" value="">
          <INPUT type="hidden" name="nRunID" value="">
          <TABLE border="0" cellpadding="0" cellspacing="0" width="80%" align="center">');

        $sLastType = '';
        // Add each of the gene panels assigned to this individual to the form.
        foreach ($zData['gene_panels'] as $nKey => $aGenePanel) {
            // Create the gene panel type header.
            if ($sLastType == '' || $sLastType != $aGenePanel[2]) {
                print('
            <TR>
              <TD class="gpheader" colspan="2" ' . ($sLastType? '' : 'style="border-top: 0px"') . '>' . ucfirst(str_replace('_', ' ', $aGenePanel[2])) . '</TD>
            </TR>');
            }
            $sLastType = $aGenePanel[2];

            // Add the gene panel to the form.
            print('
            <TR>
              <TD><INPUT type="checkbox" name="gene_panel" value="' . $aGenePanel[0] . '"' . ($aGenePanel[2] == 'mendeliome'? '' : ' checked') . '></TD>
              <TD>' . $aGenePanel[1] . '</TD>
            </TR>');
        }

        // Add in the custom gene panel option.
        if ($zData['custom_panel']) {
            print('
            <TR>
              <TD class="gpheader" colspan="2">Custom panel</TD>
            </TR>
            <TR>
              <TD><INPUT type="checkbox" name="gene_panel" value="custom_panel" checked></TD>
              <TD>' . $zData['custom_panel'] . '</TD>
            </TR>');
        }

        print('
          </TABLE>
        </FORM>
      </DIV>' . "\n");

        // If we're ready to analyze, or if we are analyzing already, show analysis options.
        // Both already run analyses and analyses not yet run will be shown. Analyses already run are fetched differently, though.
        $zAnalysesRun    = $_DB->query('SELECT a.id, a.name, a.version, a.description, a.filters, IFNULL(MAX(arf.run_time)>-1, 0) AS analysis_run, ar.id AS runid,   ar.modified, GROUP_CONCAT(arf.filterid, ";", IFNULL(arf.filtered_out, "-"), ";", IFNULL(arf.run_time, "-") ORDER BY arf.filter_order SEPARATOR ";;") AS __run_filters
                                        FROM ' . TABLE_ANALYSES . ' AS a INNER JOIN ' . TABLE_ANALYSES_RUN . ' AS ar ON (a.id = ar.analysisid) INNER JOIN ' . TABLE_ANALYSES_RUN_FILTERS . ' AS arf ON (ar.id = arf.runid)
                                        WHERE ar.screeningid = ? GROUP BY ar.id ORDER BY ar.modified, ar.id', array($nScreeningToAnalyze))->fetchAllAssoc();
        $zAnalysesNotRun = $_DB->query('SELECT a.id, a.name, a.version, a.description, a.filters, 0                               AS analysis_run, 0     AS runid, 0 AS modified
                                        FROM ' . TABLE_ANALYSES . ' AS a
                                        WHERE a.id NOT IN (SELECT ar.analysisid
                                                           FROM ' . TABLE_ANALYSES_RUN . ' AS ar
                                                           WHERE ar.screeningid = ? AND ar.modified = 0) ORDER BY a.sortid, a.id', array($nScreeningToAnalyze))->fetchAllAssoc();
        $zAnalyses = array_merge($zAnalysesRun, array(''), $zAnalysesNotRun);
        // Fetch all (numeric) variant IDs of all variants that have a curation status. They will be shown by default, when no analysis has been selected yet.
        $aScreeningVariantIDs = $_DB->query('SELECT CAST(s2v.variantid AS UNSIGNED) FROM ' . TABLE_SCR2VAR . ' s2v INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id) WHERE s2v.screeningid = ? AND vog.curation_statusid IS NOT NULL', array($nScreeningToAnalyze))->fetchAllColumn();
        require ROOT_PATH . 'inc-lib-analyses.php';
        print('
      <DIV id="analyses">
        <TABLE id="analysesTable" border="0" cellpadding="0" cellspacing="0">
          <TR>');
        foreach ($zAnalyses as $key => $zAnalysis) {
            if (!$zAnalysis) {
                // This is the separation between run and non-run filters.
                if ($key) {
                    // We've got run filters on the left, and non-run filters on the right.
                    // Create division.
                    print('
            <TD class="divider">&nbsp;</TD>');
                }
                continue;
            }
            $aFilters = preg_split('/\s+/', $zAnalysis['filters']);
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
            print('
            <TD class="analysis" valign="top">
              <TABLE border="0" cellpadding="0" cellspacing="1" id="' . ($zAnalysis['runid']? 'run_' . $zAnalysis['runid'] : 'analysis_' . $zAnalysis['id']) . '" class="analysis ' . $sAnalysisClassName . '" onclick="' .
                ($zAnalysis['analysis_run']? 'lovd_showAnalysisResults(\'' . $zAnalysis['runid'] . '\');' : ($_AUTH['level'] < LEVEL_OWNER || $zScreening['analysis_statusid'] >= ANALYSIS_STATUS_CLOSED? '' : 'lovd_popoverGenePanelSelectionForm(\'' . $nScreeningToAnalyze . '\', \'' . $zAnalysis['id'] . '\'' . (!$zAnalysis['runid']? '' : ', \'' . $zAnalysis['runid'] . '\'') . ');')) . '">
                <TR>
                  <TH colspan="3">
                    <DIV style="position : relative">
                      ' . $zAnalysis['name'] . ' (v' . $zAnalysis['version'] . (!$zAnalysis['modified']? '' : ' modified') . ')' .
                ($_AUTH['level'] < LEVEL_OWNER || $zScreening['analysis_statusid'] >= ANALYSIS_STATUS_CLOSED? '' :
                // FIXME: Probably an Ajax call would be better maybe? The window opening with refresh is ugly... we could just let this table disappear when successful (which may not work for the last run analysis because of the divider)...
                (!$zAnalysis['runid']? '' : '
                      <IMG src="gfx/cross.png" alt="Remove" onclick="if(window.confirm(\'Are you sure you want to remove this analysis run? The variants will not be deleted.\')){lovd_openWindow(\'' . lovd_getInstallURL() . 'analyses/run/' . $zAnalysis['runid'] . '?delete&amp;in_window\', \'DeleteAnalysisRun\', 780, 400);} cancelParentEvent(event);" width="16" height="16" class="remove">') . '
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
                    $sFilterClassName = 'filter_skipped';
                } elseif ($zAnalysis['analysis_run']) {
                    list($nVariantsFiltered, $nTime) = $aFiltersRun[$sFilter];
                    if ($nVariantsFiltered != '-' && $nTime != '-') {
                        $nVariantsLeft -= $nVariantsFiltered;
                        $sFilterClassName = 'filter_completed';
                    }
                }

                // Display the information for the gene panels used in this analysis.
                $sGenePanelsInfo = '';
                if ($sFilter == 'apply_selected_gene_panels' && !empty($zAnalysis['runid']) && $zAnalysis['analysis_run']) {
                    // Check to see if this is the right filter to show the info under and that we actually have some gene panels or custom panel assigned.
                    $sGenePanelsInfo = getSelectedGenePanelsByRunID($zAnalysis['runid']);
                }

                print('
                <TR id="' . ($zAnalysis['runid']? 'run_' . $zAnalysis['runid'] : 'analysis_' . $zAnalysis['id']) . '_filter_' . preg_replace('/[^a-z0-9_]/i', '_', $sFilter) . '"' . (!$sFilterClassName? '' : ' class="' . $sFilterClassName . '"') . ' valign="top">
                  <TD>' . $sFilter . $sGenePanelsInfo . '</TD>
                  <TD>' . ($nTime == '-'? '-' : lovd_convertSecondsToTime($nTime, 1)) . '</TD>
                  <TD>' . ($nTime == '-'? '-' : $nVariantsLeft) . '</TD>
                </TR>');
            }
            print('
                <TR id="' . ($zAnalysis['runid']? 'run_' . $zAnalysis['runid'] : 'analysis_' . $zAnalysis['id']) . '_message" class="message">
                  <TD colspan="3">' . ($sAnalysisClassName == 'analysis_running analysis_half_run'? 'Analysis seems to have been interrupted' : ($sAnalysisClassName == 'analysis_run'? 'Click to see results' : 'Click to run this analysis')) . '</TD>
                </TR>
              </TABLE>
            </TD>');
        }
        print('
          </TR>
        </TABLE>
      </DIV>' . "\n\n");





        // This where the VL should be loaded. Since a VL needs quite a lot of HTML
        // that does not come out of the Ajax VL interface, we must initiate a VL here.
        // We make sure it returns no results to prevent useless database usage, but
        // like that it can easily be manipulated through Ajax to return results and
        // be visible whenever necessary.
        print('

      <SCRIPT type="text/javascript">
        function lovd_AJAX_viewEntryLoad () {
          $.get(\'ajax/viewentry.php\', {object: \'ScreeningMOD\', id: \'' . $nScreeningToAnalyze . '\'},
            function (sData) {
              if (sData.length > 2) {
                $(\'#screeningViewEntry\').html(\'\n\' + sData);
              }
            });
        }
      </SCRIPT>


      <DIV id="analysis_results_VL"' . ($aScreeningVariantIDs ? '' : ' style="display: none;"') . '>' . "\n");
        // The default behaviour is to show a viewlist with any variants that have a curation status.
        $_GET['search_variantid'] = (!$aScreeningVariantIDs ? '0' : implode($aScreeningVariantIDs, '|')); // Use all the variant IDs for variants with a curation status.
        $_GET['search_vog_effect'] = ''; // Needs search term on visible column to show the VL framework, though (saying "No results have been found that match your criteria").
        $_GET['search_runid'] = ''; // To create the input field, that the JS code is referring to, so we can switch to viewing analysis results.

        require ROOT_PATH . 'class/object_custom_viewlists.mod.php';
        // VOG needs to be first, so it groups by the VOG ID.
        $_DATA = new LOVD_CustomViewListMOD(array('VariantOnGenome', 'VariantOnTranscript', 'AnalysisRunResults'));
        // Define menu, to set pathogenicity flags of multiple variants in one go.
        $_DATA->setRowLink('CustomVL_AnalysisRunResults_for_I_VE', 'javascript:lovd_openWindow(\'' . lovd_getInstallURL() . 'variants/{{ID}}?&in_window\', \'VarVE_{{ID}}\', 1000); return false;');
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
        if ($_INI['instance']['name'] != 'mgha') {
            // Show the options to set variant effect from the viewlist.
            foreach ($_SETT['var_effect'] as $nEffectID => $sEffect) {
                print('        <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\', function(){$.get(\'ajax/set_variant_effect.php?' . $nEffectID . '&id=selected\', function(sResponse){if(sResponse.substring(0,1) == \'1\'){alert(\'Successfully set reported variant effect of \' + sResponse.substring(2) + \' variants to \\\'' . $sEffect . '\\\'.\');lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\');}else if(sResponse.substring(0,1) == \'9\'){alert(\'Error: \' + sResponse.substring(2));}}).error(function(){alert(\'Error while setting variant effect.\');});});"><SPAN class="icon" style="background-image: url(gfx/menu_edit.png);"></SPAN>Set variant effect to "' . $sEffect . '"</A></LI>' . "\n");
            }
        }
        if ($bCurationStatus) {
            // Links for setting the curation statuses, in a submenu.
            print('        <LI class="icon"><SPAN class="icon" style="background-image: url(gfx/menu_edit.png);"></SPAN>Set curation status' . "\n" . '          <UL>' . "\n");
            foreach ($_SETT['curation_status'] as $nCurationStatusID => $sCurationStatus) {
                print('            <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\', function(){$.get(\'ajax/set_curation_status.php?' . $nCurationStatusID . '&id=selected\', function(sResponse){if(sResponse.substring(0,1) == \'1\'){alert(\'Successfully set curation status of \' + sResponse.substring(2) + \' variants to \\\'' . $sCurationStatus . '\\\'.\');lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\');lovd_AJAX_viewEntryLoad();}else if(sResponse.substring(0,1) == \'9\'){alert(\'Error: \' + sResponse.substring(2));}}).error(function(){alert(\'Error while setting curation status.\');});});"><SPAN class="icon" style="background-image: url(gfx/menu_edit.png);"></SPAN>' . $sCurationStatus . '</A></LI>' . "\n");
            }
            print('            <LI class="icon"><A click="if(window.confirm(\'Are you sure you want to clear the selected variants curation status?\')){lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\', function(){$.get(\'ajax/set_curation_status.php?clear&id=selected\', function(sResponse){if(sResponse.substring(0,1) == \'1\'){alert(\'Successfully cleared the curation status of \' + sResponse.substring(2) + \' variants.\');lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\');lovd_AJAX_viewEntryLoad();}else if(sResponse.substring(0,1) == \'9\'){alert(\'Error: \' + sResponse.substring(2));}}).error(function(){alert(\'Error while setting curation status.\');});});}else{alert(\'The selected variants curation status have not been changed.\');}"><SPAN class="icon" style="background-image: url(gfx/cross.png);"></SPAN>Clear curation status</A></LI>' . "\n");
            print('          </UL>' . "\n" . '        </LI>' . "\n");
            // Links for setting the confirmation statuses, in a submenu.
            print('        <LI class="icon"><SPAN class="icon" style="background-image: url(gfx/menu_edit.png);"></SPAN>Set confirmation status' . "\n" . '          <UL>' . "\n");
            foreach ($_SETT['confirmation_status'] as $nConfirmationStatusID => $sConfirmationStatus) {
                print('            <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\', function(){$.get(\'ajax/set_confirmation_status.php?' . $nConfirmationStatusID . '&id=selected\', function(sResponse){if(sResponse.substring(0,1) == \'1\'){alert(\'Successfully set confirmation status of \' + sResponse.substring(2) + \' variants to \\\'' . $sConfirmationStatus . '\\\'.\');lovd_AJAX_viewListSubmit(\'CustomVL_AnalysisRunResults_for_I_VE\');lovd_AJAX_viewEntryLoad();}else if(sResponse.substring(0,1) == \'9\'){alert(\'Error: \' + sResponse.substring(2));}}).error(function(){alert(\'Error while setting confirmation status.\');});});"><SPAN class="icon" style="background-image: url(gfx/menu_edit.png);"></SPAN>' . $sConfirmationStatus . '</A></LI>' . "\n");
            }
            print('          </UL>' . "\n" . '        </LI>' . "\n");
        }
        print('      </UL>' . "\n\n");
        $_DATA->viewList('CustomVL_AnalysisRunResults_for_I_VE', array(), false, false, $bMenu);
        print('
          </DIV>');
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 4 && ctype_digit($_PE[1]) && $_PE[2] == 'analyze' && ctype_digit($_PE[3]) && ACTION == 'close') {
    // URL: /individuals/00000001/analyze/0000000001?close
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
    $zData = $_DB->query('SELECT *
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
        if ($_DB->query('UPDATE ' . TABLE_SCREENINGS . ' SET analysis_statusid = ?, analysis_approved_by = ?, analysis_approved_date = NOW() WHERE id = ?',
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
    // URL: /individuals/00000001/analyze/0000000001?open
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
    $zData = $_DB->query('SELECT *
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
        if ($_DB->query('UPDATE ' . TABLE_SCREENINGS . ' SET analysis_statusid = ?, analysis_approved_by = ?, analysis_approved_date = NOW() WHERE id = ?',
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
    // URL: /individuals
    // URL: /individuals/DMD
    // View all entries.

    if (!empty($_PE[1])) {
        $sGene = $_DB->query('SELECT id FROM ' . TABLE_GENES . ' WHERE id = ?', array(rawurldecode($_PE[1])))->fetchColumn();
        if ($sGene) {
            // We need the authorization call once we show the individuals with VARIANTS in gene X, not before!
//            lovd_isAuthorized('gene', $sGene); // To show non public entries.
            $_GET['search_genes_searched'] = '="' . $sGene . '"';
        } else {
            // Command or gene not understood.
            // FIXME; perhaps a HTTP/1.0 501 Not Implemented? If so, provide proper output (gene not found) and
            //   test if browsers show that output or their own error page. Also, then, use the same method at
            //   the bottom of all files, as a last resort if command/URL is not understood. Do all of this LATER.
            exit;
        }
    }

    // Managers are allowed to download this list...
    if ($_AUTH['level'] >= LEVEL_MANAGER) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

    define('PAGE_TITLE', 'View all individuals' . (isset($sGene)? ' with variants in gene ' . $sGene : ''));
    $_T->printHeader();
    $_T->printTitle();

    $aColsToHide = array('panelid', 'diseaseids');
    if (isset($sGene)) {
        $aColsToHide[] = 'genes_screened_';
        $aColsToHide[] = 'variants_in_genes_';
    }

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    $_DATA->viewList('Individuals', $aColsToHide, false, false,
                     (bool) ($_AUTH['level'] >= LEVEL_MANAGER), false, true);

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /individuals/00000001
    // View specific entry.

    $nID = sprintf('%08d', $_PE[1]);
    define('PAGE_TITLE', 'View individual #' . $nID);
    $_T->printHeader();
    $_T->printTitle();

    // Load appropriate user level for this individual.
    lovd_isAuthorized('individual', $nID);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual($nID);
    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    if ($_AUTH && $_AUTH['level'] >= LEVEL_OWNER) {
        $aNavigation[CURRENT_PATH . '?edit']                     = array('menu_edit.png', 'Edit individual entry', 1);
        if ($zData['statusid'] < STATUS_OK && $_AUTH['level'] >= LEVEL_CURATOR) {
            $aNavigation[CURRENT_PATH . '?publish']              = array('check.png', ($zData['statusid'] == STATUS_MARKED ? 'Remove mark from' : 'Publish (curate)') . ' individual entry', 1);
        }
        // You can only add phenotype information to this individual, when there are phenotype columns enabled.
        if ($_DB->query('SELECT COUNT(*) FROM ' . TABLE_IND2DIS . ' AS i2d INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc USING(diseaseid) WHERE i2d.individualid = ?', array($nID))->fetchColumn()) {
            $aNavigation['phenotypes?create&amp;target=' . $nID] = array('menu_plus.png', 'Add phenotype information to individual', 1);
        }
        $aNavigation['screenings?create&amp;target=' . $nID]     = array('menu_plus.png', 'Add screening to individual', 1);
        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            $aNavigation[CURRENT_PATH . '?delete']               = array('cross.png', 'Delete individual entry', 1);
        }
    }
    lovd_showJGNavigation($aNavigation, 'Individuals');

    print('<BR><BR>' . "\n\n");


    if (!empty($zData['phenotypes'])) {
        // List of phenotype entries associated with this person, per disease.
        $_GET['search_individualid'] = $nID;
        $_T->printTitle('Phenotypes', 'H4');
        // Repeat searching for diseases, since this individual might have phenotype entry for a disease he doesn't have.
        $zData['diseases'] = $_DB->query('SELECT id, symbol, name FROM ' . TABLE_DISEASES . ' WHERE id IN (?' . str_repeat(', ?', count($zData['phenotypes'])-1) . ')', $zData['phenotypes'])->fetchAllRow();
        require ROOT_PATH . 'class/object_phenotypes.php';
        foreach($zData['diseases'] as $aDisease) {
            list($nDiseaseID, $sSymbol, $sName) = $aDisease;
            if (in_array($nDiseaseID, $zData['phenotypes'])) {
                $_GET['search_diseaseid'] = $nDiseaseID;
                $_DATA = new LOVD_Phenotype($nDiseaseID);
                print('<B>' . $sName . ' (<A href="diseases/' . $nDiseaseID . '">' . $sSymbol . '</A>)</B>&nbsp;&nbsp;<A href="phenotypes?create&amp;target=' . $nID . '&amp;diseaseid=' . $nDiseaseID . '"><IMG src="gfx/plus.png"></A> Add phenotype for this disease');
                $_DATA->viewList('Phenotypes_for_I_VE_' . $nDiseaseID, array('phenotypeid', 'individualid', 'diseaseid'), true, true);
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
        $_DATA->viewList('Screenings_for_I_VE', array('screeningid', 'individualid', 'created_date', 'edited_date'), true, true);
        unset($_GET['search_individualid']);

        $_GET['search_screeningid'] = implode('|', $zData['screeningids']);
        print('<BR><BR>' . "\n\n");
        $_T->printTitle('Variants', 'H4');

        require ROOT_PATH . 'class/object_custom_viewlists.php';
        // VOG needs to be first, so it groups by the VOG ID.
        $_DATA = new LOVD_CustomViewList(array('VariantOnGenome', 'Scr2Var', 'VariantOnTranscript'));
        $_DATA->viewList('CustomVL_VOT_for_I_VE', array(), false, false, (bool) ($_AUTH['level'] >= LEVEL_MANAGER));
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /individuals?create
    // Create a new entry.

    define('PAGE_TITLE', 'Create a new individual information entry');
    define('LOG_EVENT', 'IndividualCreate');

    lovd_isAuthorized('gene', $_AUTH['curates']);
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
                        $q = $_DB->query('INSERT INTO ' . TABLE_IND2DIS . ' VALUES (?, ?)', array($nID, $nDisease), false);
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
    // URL: /individuals/00000001?edit
    // URL: /individuals/00000001?publish
    // Edit an entry.

    $nID = sprintf('%08d', $_PE[1]);
    define('PAGE_TITLE', 'Edit individual #' . $nID);
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
        if ($zData['active_diseases_']) {
            $_POST['active_diseases'] = explode(';', $zData['active_diseases_']);
        } else {
            // An array with an empty string as a value doesn't get past the checkFields() since '' is not a valid option.
            $_POST['active_diseases'] = array();
        }
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
                // FIXME; change owner to owned_by_ in the load entry query of object_individuals.php.
                $zData['owned_by_'] = $zData['owner'];
                $_SESSION['work']['edits']['individual'][$nID] = $zData;
            }

            // FIXME: implement versioning in updateEntry!
            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Get genes which are modified only when individual and variant status is marked or public.
            if ($zData['statusid'] >= STATUS_MARKED || $_POST['statusid'] >= STATUS_MARKED) {
                $aGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
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
            // Diseases the gene is currently linked to.
            // FIXME; we moeten afspraken op papier zetten over de naamgeving van velden, ik zou hier namelijk geen _ achter plaatsen.
            //   Een idee zou namelijk zijn om loadEntry()/viewEntry() automatisch velden te laten exploden afhankelijk van hun naam. Is dat wat?
            $aDiseases = explode(';', $zData['active_diseases_']);

            // Remove diseases.
            $aToRemove = array();
            foreach ($aDiseases as $nDisease) {
                if ($nDisease && !in_array($nDisease, $_POST['active_diseases'])) {
                    // User has requested removal...
                    $aToRemove[] = $nDisease;
                }
            }
            if ($aToRemove) {
                $q = $_DB->query('DELETE FROM ' . TABLE_IND2DIS . ' WHERE individualid = ? AND diseaseid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($nID), $aToRemove), false);
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
                if (!in_array($nDisease, $aDiseases)) {
                    // Add disease to gene.
                    $q = $_DB->query('INSERT IGNORE INTO ' . TABLE_IND2DIS . ' VALUES (?, ?)', array($nID, $nDisease), false);
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
        // Load connected diseases.
        $_POST['active_diseases'] = explode(';', $_POST['active_diseases_']);
        if ($zData['statusid'] < STATUS_HIDDEN) {
            $_POST['statusid'] = STATUS_OK;
        }
    }



    $_T->printHeader();
    $_T->printTitle();

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
    // URL: /individuals/00000001?edit_panels
    // Edit an individuals gene panels.

    $nID = sprintf('%08d', $_PE[1]);
    define('PAGE_TITLE', 'Edit gene panels for individual #' . $nID);
    define('LOG_EVENT', 'IndividualEditPanels');

    // Load appropriate user level for this individual.
    lovd_isAuthorized('individual', $nID);
    lovd_requireAUTH(LEVEL_OWNER);

    require ROOT_PATH . 'class/object_individuals.mod.php';
    $_DATA = new LOVD_IndividualMOD();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        // Checks to make sure a valid gene panel ID is used.
        if (!empty($_POST['gene_panels'])) {
            $aGenePanels = $_DB->query('SELECT id FROM ' . TABLE_GENE_PANELS)->fetchAllColumn();
            foreach ($_POST['gene_panels'] as $nGenePanelID) {
                if ($nGenePanelID && !in_array($nGenePanelID, $aGenePanels)) {
                    lovd_errorAdd('gene_panels', htmlspecialchars($nGenePanelID) . ' is not a valid gene panel ID.');
                }
            }
        }

        // Checks the genes added to the custom panel to ensure they exist within the database.
        if (!empty($_POST['custom_panel'])) {
            // Explode the custom panel genes into an array.
            $aGeneSymbols = array_filter(array_unique(preg_split('/(\s|[,;])+/', strtoupper($_POST['custom_panel']))));

            // Check if there are any genes left after cleaning up the gene symbol string.
            if (count($aGeneSymbols) > 0) {
                // Load the matching genes into an array.
                $aGenesInLOVD = $_DB->query('SELECT UPPER(id), id FROM ' . TABLE_GENES . ' WHERE id IN (?' . str_repeat(', ?', count($aGeneSymbols) - 1) . ')', $aGeneSymbols)->fetchAllCombine();
                // Loop through all the gene symbols in the array and check them for any errors.
                foreach ($aGeneSymbols as $nKey => $sGeneSymbol) {
                    // Check to see if this gene symbol has been found within the database.
                    if (isset($aGenesInLOVD[$sGeneSymbol])) {
                        // A correct gene symbol was found, so lets use that to remove any case issues.
                        $aGeneSymbols[$nKey] = $aGenesInLOVD[$sGeneSymbol];
                    } else {
                        // This gene symbol was not found in the database.
                        // It got uppercased by us, but we assume that will be OK.
                        lovd_errorAdd('custom_panel', 'The gene symbol ' . htmlspecialchars($sGeneSymbol) . ' can not be found within the database.');
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
                $q = $_DB->query('DELETE FROM ' . TABLE_IND2GP . ' WHERE individualid = ? AND genepanelid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($nID), $aToRemove), false);
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
                    $q = $_DB->query('INSERT IGNORE INTO ' . TABLE_IND2GP . ' VALUES (?, ?, ?, ?)', array($nID, $nGenePanel, $_AUTH['id'], $sDateNow), false);
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

        if(empty($_POST['gene_panels'])) {
            $_POST['gene_panels'] = array();
        }

        // Check to see if any gene panels are selected, if not then lets suggest some.
        if (empty($_POST['gene_panels']) && !empty($zData['active_diseases'])) {
            $aDiseases = $zData['active_diseases'];

            // Check to see if any gene panels share the individuals diseases.
            $aDefaultGenePanels = $_DB->query('SELECT DISTINCT gp.id, gp.name, gp.type FROM ' . TABLE_GENE_PANELS . ' AS gp JOIN ' . TABLE_GP2DIS . ' AS gp2d ON (gp.id = gp2d.genepanelid) WHERE gp2d.diseaseid IN (?' . str_repeat(', ?', count($aDiseases)-1) . ') ORDER BY gp.type DESC, gp.name ASC', array_values($aDiseases))->fetchAllAssoc();

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
    $aGenePanelsForm = $_DB->query('(SELECT "optgroup2" AS id, "Mendeliome" AS name, "mendeliome_header" AS type)
                                     UNION
                                    (SELECT "optgroup1", "Gene Panels", "gene_panel_header")
                                     UNION
                                    (SELECT "optgroup3", "Blacklist", "blacklist_header")
                                     UNION
                                    (SELECT CAST(id AS CHAR), name, type FROM ' . TABLE_GENE_PANELS . ')
                                     ORDER BY type DESC, name')->fetchAllCombine();
    $nGenePanels = count($aGenePanelsForm);
    foreach ($aGenePanelsForm as $nID => $sGenePanel) {
        $aGenePanelsForm[$nID] = lovd_shortenString($sGenePanel, 75);
    }
    $nGPFieldSize = ($nGenePanels < 20 ? $nGenePanels : 20);
    if (!$nGenePanels) {
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
    // URL: /individuals/00000001?delete
    // Drop specific entry.

    $nID = sprintf('%08d', $_PE[1]);
    define('PAGE_TITLE', 'Delete individual information entry ' . $nID);
    define('LOG_EVENT', 'IndividualDelete');

    // FIXME: What if individual also contains other user's data?
    lovd_isAuthorized('individual', $nID);
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_individuals.php';
    $_DATA = new LOVD_Individual();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text.
            // This also deletes the entries in TABLE_PHENOTYPES && TABLE_SCREENINGS && TABLE_SCR2VAR && TABLE_SCR2GENES.
            $_DB->beginTransaction();
            if (isset($_POST['remove_variants']) && $_POST['remove_variants'] == 'remove') {
                $aOutput = $_DB->query('SELECT id FROM ' . TABLE_SCREENINGS . ' WHERE individualid = ?', array($nID))->fetchAllColumn();
                if (count($aOutput)) {
                    $_DB->query('DELETE vog FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) WHERE s2v.screeningid IN (?' . str_repeat(', ?', count($aOutput) - 1) . ')', $aOutput);
                }
            }

            // Get genes which are modified only when individual and variant status is marked or public.
            if ($_POST['statusid'] >= STATUS_MARKED) {
                $aGenes = $_DB->query('SELECT DISTINCT t.geneid FROM ' . TABLE_TRANSCRIPTS . ' AS t ' .
                                      'INNER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vot.transcriptid = t.id) ' .
                                      'INNER JOIN ' . TABLE_VARIANTS . ' AS vog ON (vog.id = vot.id) ' .
                                      'INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.variantid = vog.id) ' .
                                      'INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) ' .
                                      'WHERE vog.statusid >= ? AND s.individualid = ?', array(STATUS_MARKED, $nID))->fetchAllColumn();
            }

            $_DATA->deleteEntry($nID);

            if ($_POST['statusid'] >= STATUS_MARKED && $aGenes) {
                // Change updated date for genes.
                lovd_setUpdatedDate($aGenes);
            }

            $_DB->commit();

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted individual information entry ' . $nID . ' (Owner: ' . $zData['owner'] . ')');

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

    $nVariants = $_DB->query('SELECT COUNT(DISTINCT s2v.variantid) FROM ' . TABLE_SCREENINGS . ' AS s LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) WHERE s.individualid = ? GROUP BY s.individualid', array($nID))->fetchColumn();
    $aOptions = array('remove' => 'Also remove all variants attached to this individual', 'keep' => 'Keep all attached variants as separate entries');

    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '45%', '14', '55%'),
                        array('Deleting individual information entry', '', 'print', '<B>' . $nID . ' (Owner: ' . $zData['owner'] . ')</B>'),
                        'skip',
                        array('', '', 'print', 'This individual entry has ' . ($nVariants? $nVariants : 0) . ' variant' . ($nVariants == 1? '' : 's') . ' attached.'),
          'variants' => array('What should LOVD do with the attached variants?', '', 'select', 'remove_variants', 1, $aOptions, false, false, false),
                        array('', '', 'note', '<B>All phenotypes and screenings attached to this individual will be automatically removed' . ($nVariants? ' regardless' : '') . '!!!</B>'),
     'variants_skip' => 'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete individual information entry'),
                      ));
    if (!$nVariants) {
        unset($aForm['variants'], $aForm['variants_skip']);
    }

    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}
?>
