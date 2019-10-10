<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-01
 * Modified    : 2019-10-10
 * For LOVD    : 3.0-22
 *
 * Copyright   : 2004-2019 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Anthony Marty <anthony.marty@unimelb.edu.au>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               John-Paul Plazzer <johnpaul.plazzer@gmail.com>
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
// TODO Modify the log entries to include URLS to the affected records

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





// Function is used in only one place, doesn't really use the advantages that
//  functions bring (namespace, nesting, ...), perhaps just merge into the code?
function displayGenePanelHistory ($nID, $sFromDate, $sToDate)
{
    // Shows the gene panel history for a certain gene panel ID, for the given date range.
    global $_DB;

    // Fill in the time if we don't have it already.
    // Format has already been checked, now we can just check for the length.
    // Todo: if this function is called from somewhere else in future, should it not also check the format itself?
    if (strlen($sFromDate) == 10) {
        $sFromDate .= ' 00:00:00'; // Set the 'from' date as the first second of the selected day.
    }
    if (strlen($sToDate) == 10) {
        $sToDate .= ' 23:59:59'; // Set the 'to' date as the last second of the selected day.
    }

    // Query to get the gene panel revisions.
    $aGenePanelRevs = $_DB->query('SELECT * FROM ' . TABLE_GENE_PANELS_REV . ' WHERE id = ? AND valid_from >= ? ORDER BY valid_from ASC', array($nID, $sFromDate))->fetchAll();
    $nCount = 0; // The number of Gene Panel revisions (modifications) in date range, not counting the "Created record" revision.

    // FIXME: Could we replace this with a Gene Panel Rev VL? There is quite some more info needed, though, so might not work...
    foreach ($aGenePanelRevs as $aGenePanelRev) {
        if ($aGenePanelRev['valid_from'] <= $sToDate) {
            // The revision's valid_from date is used to determine if an event (record created, record modified) happens in the selected date range.
            // The revision's valid_to date doesn't matter for these events, for the purpose of showing the history.
            $aChanges[$nCount][0] = $aGenePanelRev['reason'];
            $aChanges[$nCount][1] = $aGenePanelRev['valid_from'];
            $nCount ++;
        }
    }

    if ($aGenePanelRevs[0]['created_date'] >= $sFromDate && $aGenePanelRevs[0]['created_date'] <= $sToDate) {
        // This gene panel was created within the given date range. Don't include that entry as a "difference".
        $nCount --;
    }

    // If the To Date is earlier than when the gene panel was created, then notify user.
    if ($sToDate < $aGenePanelRevs[0]['created_date']) {
        lovd_showInfoTable('This gene panel did not exist yet in the given date range. It was created ' . $aGenePanelRevs[0]['created_date'] . '.', 'information');
    } elseif ($nCount == 0) {
        // The "modification" count is zero, so nothing changed.
        lovd_showInfoTable('Information about this gene panel has not changed between the given dates.', 'information');
    } else {
        // Display the gene panel revisions.
        print('
        <TABLE border="0" cellpadding="0" cellspacing="1" width="750" class="data" style="font-size : 13px;">   
          <TR>
            <TH>Changes to Gene Panel information</TH>
            <TH width="150">Date</TH>');

        // FIXME: Could we replace this with a Gene Panel Rev VL? There is quite some more info needed, though, so might not work...
        foreach ($aChanges as $aChange) {
            // The revision's valid_from date is used to determine if an event (record created, record modified) happens in the selected date range.
            // The revision's valid_to date doesn't matter for these events, for the purpose of showing the history.
            print('
          <TR>
            <TD>' . nl2br($aChange[0]) . '</TD>
            <TD>' . $aChange[1] . '</TD>
          </TR>' . "\n");
        }

        print('        </TABLE><BR>' . "\n\n");
    }

    // This more complex query can handle the case of a gene that is added, removed, then added again.
    // Also, exclude genes where valid_from and valid_to do not overlap with the selected data range because they are not relevant and would produce wrong results.
    $aGenePanelGeneRevs = $_DB->query('SELECT geneid, MIN(valid_from) AS valid_from, MAX(valid_to) AS valid_to FROM ' . TABLE_GP2GENE_REV . ' WHERE genepanelid = ? AND (valid_to >= ? and valid_from <= ?) GROUP BY geneid', array($nID, $sFromDate, $sToDate))->fetchAll();
    $nAddedCount = 0; // Number of genes that have been added between selected date range.
    $nRemovedCount = 0; // Number of genes that have been removed between selected date range.

    $aAddedGenes = array();
    $aRemovedGenes = array();
    // Display the gene panel gene revisions for the genepanel between two dates.
    foreach ($aGenePanelGeneRevs as $aGenePanelGeneRev) {
        if ($aGenePanelGeneRev['valid_from'] >= $sFromDate && $aGenePanelGeneRev['valid_to'] >= $sToDate) {
            // Added Genes: These are genes which were created between the from date and to date and are still valid after to date.
            $aAddedGenes[$nAddedCount] = $aGenePanelGeneRev['geneid'];
            $nAddedCount ++;
        } elseif ($aGenePanelGeneRev['valid_from'] <= $sFromDate && $aGenePanelGeneRev['valid_to'] <= $sToDate) {  // Removed Genes: these are genes which existed at the fromDate but not after the toDate.
            $aRemovedGenes[$nRemovedCount] = $aGenePanelGeneRev['geneid'];
            $nRemovedCount ++;
        }
    }

    if ($nAddedCount == 0 && $nRemovedCount == 0) {
        lovd_showInfoTable('Genes in this gene panel have not changed between the given dates.', 'information');
    } else {
        // Display the gene panel gene revisions for the genepanel between two dates.
        print('    <TABLE border="0" cellpadding="0" cellspacing="0" width="750">
      <TR valign="top">' . "\n");
        foreach (array(1, 0) as $i) {
            print('        <TD>
          <TABLE border="0" cellpadding="0" cellspacing="1" width="365" class="data" style="font-size : 13px;' . ($i? '' : ' margin-left : 20px;') . '">
            <TR>
              <TH>' . ($i? 'Added Genes' : 'Removed Genes') . '</TH>
            </TR>' . "\n");
            foreach ($aAddedGenes as $sAddedGene) {
                print((!$i? '' : '            <TR>
              <TD>' . $sAddedGene . '</TD>
            </TR>' . "\n"));
            }
            foreach ($aRemovedGenes as $aRemovedGene) {
                print(($i? '' : '            <TR>
              <TD>' . $aRemovedGene . ' </TD>
            </TR>' . "\n"));
            }
            print('          </TABLE>
        </TD>' . "\n");
        }
        print('      </TR>
    </TABLE>' . "\n");
    }
}





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /gene_panels
    // View all entries.

    // Submitters are allowed to download this panel...
    if ($_AUTH['level'] >= LEVEL_SUBMITTER) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

    define('PAGE_TITLE', 'View all gene panels');
    $_T->printHeader();
    $_T->printTitle();
    lovd_requireAUTH();

    require ROOT_PATH . 'class/object_gene_panels.php';
    $_DATA = new LOVD_GenePanel();
    $_DATA->viewList('GenePanel', array('show_options' => true));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /gene_panels/00001
    // View specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'View gene panel #' . $nID);
    $_T->printHeader();
    $_T->printTitle();
    lovd_requireAUTH();

    require ROOT_PATH . 'class/object_gene_panels.php';
    $_DATA = new LOVD_GenePanel();
    // Increase the max group_concat() length, so that gene panels linked to many many diseases still have all diseases mentioned here.
    $_DB->query('SET group_concat_max_len = 150000');
    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    if ($_AUTH) {
        // Authorized user is logged in. Provide tools.
        if ($_AUTH['level'] >= $_SETT['user_level_settings']['genepanels_edit']) {
            $aNavigation[CURRENT_PATH . '?edit'] = array('menu_edit.png', 'Edit gene panel information', 1);
        }
        if ($_AUTH['level'] >= $_SETT['user_level_settings']['genepanels_manage_genes']) {
            $aNavigation[CURRENT_PATH . '?manage_genes'] = array('menu_plus.png', 'Manage gene panel\'s genes', 1);
        }
        if ($_AUTH['level'] >= LEVEL_ANALYZER) {
            $aNavigation[CURRENT_PATH . '?history']      = array('menu_clock.png', 'View differences between two dates', 1);
            $aNavigation[CURRENT_PATH . '?history_full'] = array('menu_clock.png', 'View full history of genes in this gene panel', 1);
            $aNavigation['download/' . CURRENT_PATH]     = array('menu_save.png', 'Download this gene panel and its genes', 1);
        }
        if ($_AUTH['level'] >= $_SETT['user_level_settings']['genepanels_delete']) {
            $aNavigation[CURRENT_PATH . '?delete'] = array('cross.png', 'Delete gene panel entry', 1);
        }
    }
    lovd_showJGNavigation($aNavigation, 'GenePanel');

    // Display the genes in this gene panel.
    print('<BR><BR>' . "\n\n");
    $_T->printTitle('Genes in gene panel', 'H4');
    require ROOT_PATH . 'class/object_gene_panel_genes.php';
    $_DATA = new LOVD_GenePanelGene();
    // Only show the genes in this gene panel by setting the genepanelid to the current gene panel id.
    $_GET['search_genepanelid'] = $nID;
    $sGPGViewListID = 'GenePanelGene';
    // Add a menu item to allow the user to download the whole gene panel.
    print('      <UL id="viewlistMenu_' . $sGPGViewListID . '" class="jeegoocontext jeegooviewlist">' . "\n" .
          '        <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'' . $sGPGViewListID . '\', function(){lovd_AJAX_viewListDownload(\'' . $sGPGViewListID . '\', true);});"><SPAN class="icon" style="background-image: url(gfx/menu_save.png);"></SPAN>Download gene panel\'s genes</A></LI>' . "\n" .
          '      </UL>' . "\n\n");
    $_DATA->setRowLink($sGPGViewListID, CURRENT_PATH . '/{{geneid}}');
    $_DATA->viewList($sGPGViewListID, array('show_options' => true));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /gene_panels?create
    // Create a new gene panel entry.

    define('PAGE_TITLE', 'Create a new gene panel entry');
    define('LOG_EVENT', 'GenePanelCreate');

    lovd_requireAUTH($_SETT['user_level_settings']['genepanels_create']);

    require ROOT_PATH . 'class/object_gene_panels.php';
    $_DATA = new LOVD_GenePanel();
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();
        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('name', 'description', 'type', 'remarks', 'created_by', 'created_date');

            // If we are a manager then we can update the PMID mandatory field.
            if ($_AUTH['level'] >= LEVEL_MANAGER) {
                $aFields[] = 'pmid_mandatory';
            }

            // Prepare values.
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created gene panel entry ' . $nID . ' - ' . $_POST['name']);

            // Add diseases.
            $aSuccessDiseases = array();
            if (!empty($_POST['active_diseases']) && is_array($_POST['active_diseases'])) {
                foreach ($_POST['active_diseases'] as $nDisease) {
                    // Add disease to gene.
                    if ($nDisease) {
                        $q = $_DB->query('INSERT INTO ' . TABLE_GP2DIS . ' VALUES (?, ?)', array($nID, $nDisease), false);
                        if (!$q) {
                            // Silent error.
                            lovd_writeLog('Error', LOG_EVENT, 'Disease information entry ' . $nDisease . ' - could not be added to gene panel ' . $nID);
                        } else {
                            $aSuccessDiseases[] = $nDisease;
                        }
                    }
                }
            }

            if (count($aSuccessDiseases)) {
                lovd_writeLog('Event', LOG_EVENT, 'Disease entr' . (count($aSuccessDiseases) > 1 ? 'ies' : 'y') . ' successfully added to gene panel ' . $nID . ' - ' . $_POST['name']);
            }

            // Add analyses.
            $aSuccessAnalyses = array();
            if (!empty($_POST['active_analyses']) && is_array($_POST['active_analyses'])) {
                foreach ($_POST['active_analyses'] as $nAnalysisID) {
                    // Add analyses to gene.
                    if ($nAnalysisID) {
                        $q = $_DB->query('INSERT INTO ' . TABLE_GP2A . ' VALUES (?, ?)', array($nID, $nAnalysisID), false);
                        if (!$q) {
                            // Silent error.
                            lovd_writeLog('Error', LOG_EVENT, 'Analysis information entry ' . $nAnalysisID . ' - could not be added to gene panel ' . $nID);
                        } else {
                            $aSuccessAnalyses[] = $nAnalysisID;
                        }
                    }
                }
            }

            if (count($aSuccessAnalyses)) {
                lovd_writeLog('Event', LOG_EVENT, 'Analysis entr' . (count($aSuccessAnalyses) > 1 ? 'ies' : 'y') . ' successfully added to gene panel ' . $nID . ' - ' . $_POST['name']);
            }

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH . '/' . $nID . '?manage_genes');

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully created the gene panel entry!', 'success');
            $_T->printFooter();
            exit;
        }

    } else {
        $_DATA->setDefaultValues();
    }



    $_T->printHeader();
    $_T->printTitle();

    print('      To create a new gene panel entry, please fill out the form below.<BR>' . "\n" .
          '      <BR>' . "\n\n");

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
        $_DATA->getForm(),
        array(
            array('', '', 'submit', 'Create gene panel entry'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /gene_panels/00001?edit
    // Edit a specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Edit gene panel entry #' . $nID);
    define('LOG_EVENT', 'GenePanelEdit');

    lovd_requireAUTH($_SETT['user_level_settings']['genepanels_edit']);

    require ROOT_PATH . 'class/object_gene_panels.php';
    $_DATA = new LOVD_GenePanel();
    // Increase the max group_concat() length, so that gene panels linked to many many diseases still have all diseases mentioned here.
    $_DB->query('SET group_concat_max_len = 150000');
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';
    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST, $zData);
        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('name', 'description', 'remarks', 'edited_by', 'edited_date');

            // If we are a manager then we can update the PMID mandatory field.
            if ($_AUTH['level'] >= LEVEL_MANAGER) {
                $aFields[] = 'pmid_mandatory';
            }

            // Prepare values.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited gene panel entry ' . $nID . ' - ' . $_POST['name']);

            // Change linked diseases?
            // Diseases the gene panel is currently linked to.

            // Remove diseases.
            $aToRemove = array();
            foreach ($zData['active_diseases'] as $nDisease) {
                if ($nDisease && !in_array($nDisease, $_POST['active_diseases'])) {
                    // User has requested removal...
                    $aToRemove[] = $nDisease;
                }
            }

            if ($aToRemove) {
                $q = $_DB->query('DELETE FROM ' . TABLE_GP2DIS . ' WHERE genepanelid = ? AND diseaseid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($zData['id']), $aToRemove), false);
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aToRemove) == 1 ? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' could not be removed from gene panel ' . $nID);
                } else {
                    lovd_writeLog('Event', LOG_EVENT, 'Disease information entr' . (count($aToRemove) == 1 ? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' successfully removed from gene panel ' . $nID);
                }
            }

            // Add diseases.
            $aSuccess = array();
            $aFailed = array();
            foreach ($_POST['active_diseases'] as $nDisease) {
                if ($nDisease && !in_array($nDisease, $zData['active_diseases'])) {
                    // Add disease to gene.
                    $q = $_DB->query('INSERT IGNORE INTO ' . TABLE_GP2DIS . ' VALUES (?, ?)', array($nID, $nDisease), false);
                    if (!$q) {
                        $aFailed[] = $nDisease;
                    } else {
                        $aSuccess[] = $nDisease;
                    }
                }
            }
            if ($aFailed) {
                // Silent error.
                lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aFailed) == 1 ? 'y' : 'ies') . ' ' . implode(', ', $aFailed) . ' could not be added to gene panel ' . $nID);
            } elseif ($aSuccess) {
                lovd_writeLog('Event', LOG_EVENT, 'Disease information entr' . (count($aSuccess) == 1 ? 'y' : 'ies') . ' ' . implode(', ', $aSuccess) . ' successfully added to gene panel ' . $nID);
            }

            // Change linked analyses?
            // Analyses the gene panel is currently linked to.

            // Remove analyses.
            $aToRemove = array();
            foreach ($zData['active_analyses'] as $nAnalysisID) {
                if ($nAnalysisID && !in_array($nAnalysisID, $_POST['active_analyses'])) {
                    // User has requested removal...
                    $aToRemove[] = $nAnalysisID;
                }
            }

            if ($aToRemove) {
                $q = $_DB->query('DELETE FROM ' . TABLE_GP2A . ' WHERE genepanelid = ? AND analysisid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($zData['id']), $aToRemove), false);
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Analysis information entr' . (count($aToRemove) == 1 ? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' could not be removed from gene panel ' . $nID);
                } else {
                    lovd_writeLog('Event', LOG_EVENT, 'Analysis information entr' . (count($aToRemove) == 1 ? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' successfully removed from gene panel ' . $nID);
                }
            }

            // Add analyses.
            $aSuccess = array();
            $aFailed = array();
            foreach ($_POST['active_analyses'] as $nAnalysisID) {
                if ($nAnalysisID && !in_array($nAnalysisID, $zData['active_analyses'])) {
                    // Add analyses to gene.
                    $q = $_DB->query('INSERT IGNORE INTO ' . TABLE_GP2A . ' VALUES (?, ?)', array($nID, $nAnalysisID), false);
                    if (!$q) {
                        $aFailed[] = $nAnalysisID;
                    } else {
                        $aSuccess[] = $nAnalysisID;
                    }
                }
            }
            if ($aFailed) {
                // Silent error.
                lovd_writeLog('Error', LOG_EVENT, 'Analysis information entr' . (count($aFailed) == 1 ? 'y' : 'ies') . ' ' . implode(', ', $aFailed) . ' could not be added to gene panel ' . $nID);
            } elseif ($aSuccess) {
                lovd_writeLog('Event', LOG_EVENT, 'Analysis information entr' . (count($aSuccess) == 1 ? 'y' : 'ies') . ' ' . implode(', ', $aSuccess) . ' successfully added to gene panel ' . $nID);
            }

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited the gene panel entry!', 'success');

            $_T->printFooter();
            exit;
        }
    } else {
        // Load current values.
        $_POST = array_merge($_POST, $zData);
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
        $_DATA->getForm(),
        array(
            array('', '', 'submit', 'Edit gene panel entry'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /gene_panels/00001?delete
    // Drop specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Delete gene panel entry #' . $nID);
    define('LOG_EVENT', 'GenePanelDelete');

    // Require admin clearance.
    lovd_requireAUTH($_SETT['user_level_settings']['genepanels_delete']);

    require ROOT_PATH . 'class/object_gene_panels.php';
    $_DATA = new LOVD_GenePanel();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    // Check if this gene panel has already been assigned to an individual, so we can not delete it.
    if ($zData['individuals'] > 0) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('This gene panel can not be deleted as it has already been assigned to ' . $zData['individuals'] . ' individual' . ($zData['individuals'] == 1? '' : 's') . '.', 'stop');
        $_T->printFooter();
        exit;
    }

    if (!empty($_POST)) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }
        if (!isset($_POST['reason']) || !trim($_POST['reason'])) {
            lovd_errorAdd('reason', 'Please fill in the \'Reason for removing this gene panel\' field.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // This also deletes the entries in gp2dis and gp2gene.
            $_DATA->deleteEntry($nID, $_POST['reason']);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted gene panel entry ' . $nID . ' - ' . $zData['id'] . ' (' . $zData['name'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0]);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the gene panel entry!', 'success');
            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_showInfoTable('This will delete the <B>' . $zData['name'] . '</B> gene panel and unlink all the genes, diseases and analyses assigned to it. This action cannot be undone.', 'warning');

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
        array(
            array('POST', '', '', '', '40%', '14', '60%'),
            array('Deleting gene panel entry', '', 'print', $zData['id'] . ' (' . $zData['name'] . ')'),
            'skip',
            array('Reason for removing this gene panel', '', 'text', 'reason', 40),
            array('Enter your password for authorization', '', 'password', 'password', 20),
            array('', '', 'submit', 'Delete gene panel entry'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'manage_genes') {
    // URL: /gene_panels/00001?manage_genes
    // Manage genes in a gene panel.

    $nID = sprintf('%05d', $_PE[1]);
    define('LOG_EVENT', 'GenePanelManage');

    lovd_requireAUTH($_SETT['user_level_settings']['genepanels_manage_genes']);
    $bRemovableGenes = ($_AUTH['level'] >= $_SETT['user_level_settings']['genepanels_genes_delete']);

    $zData = $_DB->query('SELECT * FROM ' . TABLE_GENE_PANELS . ' WHERE id = ?', array($nID))->fetchAssoc();
    if (!$zData) {
        define('PAGE_TITLE', 'Manage genes for gene panel entry #' . $nID);
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such ID!', 'stop');
        $_T->printFooter();
        exit;
    }
    define('PAGE_TITLE', 'Manage genes for gene panel: ' . htmlspecialchars($zData['name']));
    $aSelectedGenes = array(); // Genes mass-imported from other sources (gene statistics or the modal window).
    $aKnownGeneSymbols = array(); // For the modal window: Known gene symbols.
    $aUnknownGeneSymbols = array(); // For the modal window: Unknown gene symbols.
    if (!empty($_GET['select_genes_from']) && (empty($_SESSION['viewlists'][$_GET['select_genes_from']]['checked']) || count($_SESSION['viewlists'][$_GET['select_genes_from']]['checked']) == 0)) {
        // A viewlistid has been specified with the intention of adding selected genes in that viewlistid but there are no selected genes or the viewlistid is incorrect.
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('There are no genes selected to add to this gene panel!', 'stop');
        $_T->printFooter();
        exit;
    } elseif (!empty($_GET['select_genes_from'])) {
        // Selected genes in the viewlist are added to this array for further processing.
        $aSelectedGenes = $_SESSION['viewlists'][$_GET['select_genes_from']]['checked'];
    }

    require ROOT_PATH . 'inc-lib-form.php';

    if (POST && !empty($_POST['hidden_genes_list'])) {
        // This form has been sent with genes pasted into the modal window. Process them, don't run normal checks.

        // Handle lists separated by new lines, spaces, commas and semicolons.
        // Trim the whitespace, remove duplicates and remove empty array elements.
        $aGeneSymbols = array_filter(array_unique(array_map('trim', preg_split('/(\s|[,;])+/', $_POST['hidden_genes_list']))));

        // Check if there are any genes left after cleaning up the gene symbol string.
        if (count($aGeneSymbols) > 0) {
            // Load the genes and alternative names into an array.
            $aGenesInLOVD = $_DB->query('SELECT UPPER(id), id FROM ' . TABLE_GENES)->fetchAllCombine();
            // Loop through all the gene symbols in the array and check them for any errors.
            foreach ($aGeneSymbols as $sGeneSymbol) {
                $sGeneSymbolUpper = strtoupper($sGeneSymbol);
                // Check to see if this gene symbol has been found within the database.
                if (isset($aGenesInLOVD[$sGeneSymbolUpper])) {
                    // A correct gene symbol was found, so lets use that to remove any case issues.
                    $aKnownGeneSymbols[] = $aGenesInLOVD[$sGeneSymbolUpper];
                } else {
                    // This gene symbol was not found in the database.
                    $aUnknownGeneSymbols[] = $sGeneSymbol;
                }
            }
        }

        // If no gene symbols got rejected, just fill them in.
        if ($aKnownGeneSymbols && !$aUnknownGeneSymbols) {
            // Just in case, don't overwrite anything if we have something in $aSelectedGenes.
            $aSelectedGenes = array_merge($aSelectedGenes, $aKnownGeneSymbols);
        }

    } elseif (POST) {
        lovd_errorClean();

        // Preventing notices...
        // $_POST['genes'] stores the IDs of the genes that are supposed to go in TABLE_GENE_PANELS2GENES.
        if (empty($_POST['genes']) || !is_array($_POST['genes'])) {
            $_POST['genes'] = array();
        }
        // $_POST['transcriptids'] stores the IDs of the transcripts associated with the selected genes.
        if (empty($_POST['transcriptids']) || !is_array($_POST['transcriptids'])) {
            $_POST['transcriptids'] = array();
        }
        // $_POST['inheritances'] stores the inheritance values of the selected genes.
        if (empty($_POST['inheritances']) || !is_array($_POST['inheritances'])) {
            $_POST['inheritances'] = array();
        }
        // $_POST['pmids'] stores the PMIDs selected as relevant for the selected genes.
        if (empty($_POST['transcriptids']) || !is_array($_POST['pmids'])) {
            $_POST['pmids'] = array();
        }
        // $_POST['remarkses'] stores the remarks associated with the selected genes.
        if (empty($_POST['remarkses']) || !is_array($_POST['remarkses'])) {
            $_POST['remarkses'] = array();
        }

        // Mandatory fields.
        // Check if this gene panel has the option set that the PMID field may not be empty.
        if ($zData['pmid_mandatory']) {
            // PMIDs are mandatory. Check if every gene has one.
            $nGenes = count($_POST['genes']);
            for ($i = 0; $i < $nGenes; $i ++) {
                if (empty($_POST['pmids'][$i])) {
                    lovd_errorAdd('', 'Please fill in all of the \'PMID\' fields.');
                }
            }
        }

        // If the PMID ID has been filled in, but it's just a zero, complain as well.
        // We won't check if it actually exists, but it has to be a bit meaningful.
        foreach ($_POST['pmids'] as $nPMID) {
            if ($nPMID !== '' && !preg_match('/^[1-9]\d{6,}$/', $nPMID)) {
                // The PMIDs of the last 25 years all are 8 digits, but just
                // in case we're referring to something really, really old...
                lovd_errorAdd('', 'The PubMed ID has to be at least seven digits long and cannot start with a \'0\'.');
            }
        }

        // Password is always mandatory.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        } elseif ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            // User had to enter his/her password for authorization.
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }



        if (!lovd_error()) {
            // We'll need to run inserts for what's new, updates for what's already there, and deletes for what's removed.
            // However, the insertEntry(), updateEntry() and deleteEntry() functions have their own transactions, which break this part here.
            // FIXME: For now, we'll just work *directly* in the data table, instead of considering the history.
            // This is just to get a working example, mergable with the other branches. From there on, we'll fix things.
            // FIXME: Should we make a summary of what's created or deleted before we process the edit?
            //  Or do we consider the chance of mistakes too little?

            require ROOT_PATH . 'class/object_gene_panel_genes.php';
            $_DATA = new LOVD_GenePanelGene();
            $_DB->beginTransaction();
            // Get list of currently associated genes. Note that the genes are keys, to speed things up.
            $aGenesCurrentlyAssociated = $_DB->query('SELECT geneid, 1 FROM ' . TABLE_GP2GENE . ' WHERE genepanelid = ?', array($nID))->fetchAllCombine();
            $sDateNow = date('Y-m-d H:i:s');
            foreach ($_POST['genes'] as $nKey => $sGeneID) {
                // Build up array for insertEntry() and updateEntry();
                $aData = array(
                    'genepanelid' => $nID,
                    'geneid' => $sGeneID,
                    'transcriptid' => (empty($_POST['transcriptids'][$nKey])? NULL : $_POST['transcriptids'][$nKey]),
                    'inheritance' => $_POST['inheritances'][$nKey],
                    'pmid' => $_POST['pmids'][$nKey],
                    'remarks' => $_POST['remarkses'][$nKey],
                );
                if (!isset($aGenesCurrentlyAssociated[$sGeneID])) {
                    // Needs an insert. This will also take care of the revision table.
                    $aData += array(
                        'created_by' => $_AUTH['id'],
                        'created_date' => $sDateNow,
                    );
                    $_DATA->insertEntry($aData, array_keys($aData));
                    lovd_writeLog('Event', 'GenePanelGeneCreate', 'Created gene entry ' . $sGeneID . ' in gene panel #' . $nID);
                } else {
                    // Needs an update, maybe. Only if something changed.
                    // updateEntry() will figure out if we actually need a query or not.
                    // Since we're versioned and many genes may be involved, we want to be sure.
                    $aData += array(
                        'edited_by' => $_AUTH['id'],
                        'edited_date' => $sDateNow,
                    );
                    $nUpdated = $_DATA->updateEntry(array('genepanelid' => $nID, 'geneid' => $sGeneID), $aData, array_keys($aData));
                    // Only create a log if something was updated, the updateEntry will return -1 if nothing was updated.
                    if ($nUpdated != -1) {
                        lovd_writeLog('Event', 'GenePanelGeneEdit', 'Edited gene entry ' . $sGeneID . ' in gene panel #' . $nID);
                    };
                    // Mark gene as done, so we don't delete it after this loop.
                    unset($aGenesCurrentlyAssociated[$sGeneID]);
                }
            }

            // Now delete what was no longer selected.
            if ($aGenesCurrentlyAssociated && $bRemovableGenes) {
                // When not using deleteEntry(), we could simply run one query for all genes that were dropped.
                // However, for that we'd need to duplicate code handling the revision history.
                // So we're going to keep the code simple, in expense of some speed on large deletions.
                foreach (array_keys($aGenesCurrentlyAssociated) as $sGeneID) {
                    // FIXME: No reason passed. Should we demand one from our users?
                    $_DATA->deleteEntry(array('genepanelid' => $nID, 'geneid' => $sGeneID));
                    lovd_writeLog('Event', 'GenePanelGeneDelete', 'Deleted gene entry ' . $sGeneID . ' from gene panel #' . $nID);
                }
            }

            // If we get here, it all succeeded.
            $_DB->commit();

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Updated gene list for the gene panel #' . $nID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully updated the gene panel gene list!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }





    $_T->printHeader();
    $_T->printTitle();

    // Now, build $aGenes, which contains info about the genes currently selected (from DB or, if available, POST!).
    $aGenes = array();
    $_DB->query('SET group_concat_max_len = 10240'); // Make sure you can deal with long transcript lists.
    if (!empty($_POST['genes'])) {
        // Form has already been sent. We're here because of errors. Use $_POST.
        // Retrieve data for selected genes.
        // FIXME; Do we need to change all IDs to integers because of possibly loosing the prepended zero's? Cross-browser check to verify?
        $zGenes = $_DB->query(
            'SELECT g.id, IFNULL(CONCAT("<OPTION value=\"\">-- select --</OPTION>", GROUP_CONCAT(CONCAT("<OPTION value=\"", t.id, "\">", t.id_ncbi, "</OPTION>") ORDER BY t.id_ncbi SEPARATOR "")), "<OPTION value=\"\">-- no transcripts available --</OPTION>") AS transcripts_HTML
             FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid)
             WHERE g.id IN (?' . str_repeat(', ?', count($_POST['genes'])-1) . ')
             GROUP BY g.id', $_POST['genes'])->fetchAllCombine();
        // Get the order right and add more information.
        foreach ($_POST['genes'] as $nKey => $sID) {
            if (!isset($zGenes[$sID])) {
                // Gene does not exist in the database. We're not even bothering to complain here.
                continue;
            }
            $aGenes[$sID] =
                array(
                    'name' => $sID, // More doesn't fit...
                    'transcriptid' => (!isset($_POST['transcriptids'][$nKey])? '' : $_POST['transcriptids'][$nKey]),
                    'transcripts_HTML' => $zGenes[$sID],
                    'inheritance' => (!isset($_POST['inheritances'][$nKey])? '' : $_POST['inheritances'][$nKey]),
                    'pmid' => (!isset($_POST['pmids'][$nKey])? '' : $_POST['pmids'][$nKey]),
                    'remarks' => (!isset($_POST['remarkses'][$nKey])? '' : $_POST['remarkses'][$nKey]), // Some LOTR here just for fun...
                    'vlgene' => 0, // A flag to determine if the row is to be highlighted green.
                );
        }
        ksort($aGenes); // So it will be resorted on a page reload.

    } else {
        // First time on form. Use current database contents.

        // Retrieve current genes, alphabetically ordered (makes it a bit easier to work with new forms).
        // FIXME: This is where the new fetchAllCombine() will make sense...
        $qGenes = $_DB->query(
            'SELECT gp2g.geneid, gp2g.geneid AS name, gp2g.transcriptid, gp2g.inheritance, gp2g.pmid, REPLACE(gp2g.remarks, "\r\n", " ") AS remarks, IFNULL(CONCAT("<OPTION value=\"\">-- select --</OPTION>", GROUP_CONCAT(CONCAT("<OPTION value=\"", t.id, "\">", t.id_ncbi, "</OPTION>") ORDER BY t.id_ncbi SEPARATOR "")), "<OPTION value=\"\">-- no transcripts available --</OPTION>") AS transcripts_HTML, 0 AS vlgene
             FROM ' . TABLE_GP2GENE . ' AS gp2g LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (gp2g.geneid = t.geneid)
             WHERE gp2g.genepanelid = ? GROUP BY gp2g.geneid ORDER BY gp2g.geneid', array($nID));
        while ($z = $qGenes->fetchAssoc()) {
            $aGenes[$z['geneid']] = $z;
        }
    }

    if ($aSelectedGenes) {
        // Prepend the selected genes from the viewlist. Build an array of these selected genes.
        $qGenes = $_DB->query(
            'SELECT g.id AS geneid, g.id AS name, null AS transcriptid, "" AS inheritance, null AS pmid, "" AS remarks, IFNULL(CONCAT("<OPTION value=\"\">-- select --</OPTION>", GROUP_CONCAT(CONCAT("<OPTION value=\"", t.id, "\">", t.id_ncbi, "</OPTION>") ORDER BY t.id_ncbi SEPARATOR "")), "<OPTION value=\"\">-- no transcripts available --</OPTION>") AS transcripts_HTML, 1 AS vlgene
             FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid)
             WHERE g.id IN (?' . str_repeat(', ?', count($aSelectedGenes)-1) . ') GROUP BY g.id ORDER BY g.id', array_values($aSelectedGenes));
        while ($z = $qGenes->fetchAssoc()) {
            if (empty($aGenes[$z['geneid']])) {
                // If this gene is already in the gene panel then do not overwrite the gene data in the array.
                $aVLGenes[$z['geneid']] = $z;
            }
            // TODO AM Do we want to notify the user that this gene already existed within the gene panel?
        }
        if (!empty($aVLGenes) && count($aVLGenes) > 0) {
            // Merge these genes to the start of the existing genes.
            $aGenes = array_merge($aVLGenes, $aGenes);
        }
    }



    lovd_errorPrint();

    // Show viewList() of gene panel genes. We'd like to remove all genes that are already selected,
    //  but we can't properly do that. GET has a limit, and IE only allows some 2KB in there.
    // So after some 200 genes, the negative selection filter will fail.
    require ROOT_PATH . 'class/object_genes.php';
    $_DATA = new LOVD_Gene();



    lovd_showInfoTable('The following genes are configured in this LOVD+. Click on one to add it to this gene panel.<BR><B>Click on this box to quickly add multiple genes to this gene panel.</B>', 'information', 950,
        'javascript:$(\'#div_dialog_genes_list\').dialog({draggable:false,resizable:false,minWidth:600,show:\'fade\',closeOnEscape:true,hide:\'fade\',modal:true,buttons:{\'Verify\':function () { $(\'#hidden_genes_list\').val($(\'#genes_list\').val()); $(\'#form_manage_genes\').submit(); },\'Cancel\':function () { $(this).dialog(\'close\'); }}});');

    if (true) {
        // We either have no genes yet, or we have sent them, but there was a problem.
        print('
      <DIV id=\'div_dialog_genes_list\' title=\'Add list of genes to this gene panel\' style="display: none;">
        Please fill in your list of gene symbols; one per line or separated by commas, semicolons or spaces. Then, press &quot;Verify&quot; to check them.<BR>
        
          <TEXTAREA rows="5" cols="60" name="genes_list" id="genes_list">' . htmlentities(implode(', ', $aKnownGeneSymbols)) . '</TEXTAREA><BR><BR>
          ' . (!$aUnknownGeneSymbols? '' :
            '<B style="color: red;">The following gene' . (count($aUnknownGeneSymbols) == 1? ' is' : 's are') . ' are not (yet) present in LOVD+:</B><BR>' .
            implode(', ', $aUnknownGeneSymbols) . '<BR><BR>' .
            'LOVD+ normally creates genes automatically when processing input files, so in general this means that no input files have been seen yet with data in ' . (count($aUnknownGeneSymbols) == 1? 'this gene' : 'these genes') . '. ' .
            'It is also possible that genes have been created using a different symbol.<BR>' .
            'Feel free to edit the list above if needed and click &quot;Verify&quot; to try again.') . '
          
        </DIV>' . "\n\n");
        if ($aUnknownGeneSymbols) {
            // Open the dialog already.
            print('
      <SCRIPT type="text/javascript">
        $("#div_dialog_genes_list").dialog({draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:true,hide:"fade",modal:true,buttons:{"Verify":function () { $("#hidden_genes_list").val($("#genes_list").val()); $("#form_manage_genes").submit(); },"Cancel":function () { $(this).dialog("close"); }}});
      </SCRIPT>' . "\n\n");
        }
    }
    if ($aKnownGeneSymbols && !$aUnknownGeneSymbols) {
        // Genes symbols have been processed correctly.
        print('
      <DIV id=\'div_dialog_genes_list_confirm\' title=\'Gene' . (count($aKnownGeneSymbols) == 1? '' : 's') . ' successfully added\' style="display: none;">
        Please check the settings, fill in your password at the bottom of the page and press &quot;Save gene panel&quot; to save the changes.
      </DIV>
      <SCRIPT type="text/javascript">
        $("#div_dialog_genes_list_confirm").dialog({draggable:false,resizable:false,minWidth:600,show:"fade",closeOnEscape:true,hide:"fade",modal:true,buttons:{"Close":function () { $(this).dialog("close"); }}});
        $("#genes_list").val(""); // Empty form.
      </SCRIPT>' . "\n\n");
    }



    $_GET['page_size'] = 10;
    $sViewListID = 'GenePanels_ManageGenes'; // Create known viewListID for the JS functions().
    $_DATA->setRowLink($sViewListID, 'javascript:lovd_addGene(\'{{ViewListID}}\', \'{{ID}}\', \'{{zData_transcripts_HTML}}\'); return false;');
    $_DATA->viewList($sViewListID, array('track_history' => false));



    // Show curators, to sort and to select whether or not they can edit.
    print('      <BR><BR>' . "\n\n");

    lovd_showInfoTable('All genes below have been selected for this gene panel.<BR>' .
        (!$bRemovableGenes?
            'Only higher level users can also remove genes from this gene panel. If you believe a certain gene should be removed, please ask a ' .
            $_SETT['user_levels'][$_SETT['user_level_settings']['genepanels_genes_delete']] . ' to do so.' :
            'To remove a gene from this list, click the red cross on the far right of the line.'), 'information', 950);

    $aInheritances =
        array(
            'Autosomal Recessive',
            'Dominant',
            'X-Linked',
        );
    // Define the inheritance options list HTML string. Will be used in two places; in the HTML and the JS.
    $sInheritanceOptions = '<OPTION value="">-- select --</OPTION>';
    foreach ($aInheritances as $sInheritance) {
        $sInheritanceOptions .= '<OPTION value="' . $sInheritance . '">' . $sInheritance . '</OPTION>';
    }
    // Form & table.
    print('
      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post" id="form_manage_genes">
        <INPUT type="hidden" name="hidden_genes_list" id="hidden_genes_list" value="">
        <DIV style="width : 950px; height : 250px; overflow : auto;">
        <TABLE id="gene_list" class="data" border="0" cellpadding="0" cellspacing="1" width="900">
          <THEAD>
            <TR>
              <TH>Symbol</TH>
              <TH>Transcript</TH>
              <TH>Inheritance</TH>
              <TH>PMID</TH>
              <TH>Remarks</TH>
              <TH width="30">&nbsp;</TH></TR></THEAD>
          <TBODY>');
    // Now loop the items in the order given.
    foreach ($aGenes as $sID => $aGene) {
        print('
            <TR id="tr_' . $sID . '"' . (!$aGene['vlgene'] ? '' : ' class="colGreen"') . '>
              <TD>
                <INPUT type="hidden" name="genes[]" value="' . $sID . '">
              ' . $aGene['name'] . '</TD>
              <TD><SELECT name="transcriptids[]" style="width : 100%;">' . str_replace('"' . $aGene['transcriptid'] . '">', '"' . $aGene['transcriptid'] . '" selected>', $aGene['transcripts_HTML']) . '</SELECT></TD>
              <TD><SELECT name="inheritances[]">' . str_replace('"' . $aGene['inheritance'] . '">', '"' . $aGene['inheritance'] . '" selected>', $sInheritanceOptions) . '</SELECT></TD>
              <TD><INPUT type="text" name="pmids[]" value="' . $aGene['pmid'] . '" size="10"></TD>
              <TD><INPUT type="text" name="remarkses[]" value="' . $aGene['remarks'] . '" size="40"></TD>
              <TD width="30" align="right">' . (!$bRemovableGenes? '' : '<A href="#" onclick="lovd_removeGene(\'' . $sViewListID . '\', \'' . $sID . '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A>') . '</TD></TR>');
    }
    print('
          </TBODY></TABLE></DIV><BR>' . "\n");

    // Array which will make up the form table.
    $aForm = array(
        array('POST', '', '', '', '0%', '0', '100%'),
        array('', '', 'print', 'Enter your password for authorization'),
        array('', '', 'password', 'password', 20),
        array('', '', 'print', '<INPUT type="submit" value="Save gene panel">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . CURRENT_PATH . '\'; return false;" style="border : 1px solid #FF4422;">'),
    );
    lovd_viewForm($aForm);
    print("\n" .
          '      </FORM>' . "\n\n");

?>
<SCRIPT type='text/javascript'>
    function lovd_addGene (sViewListID, sID, sTranscripts)
    {
        // Verify that entry doesn't already exist.
        if (document.getElementById('tr_' + sID)) {
            alert('This gene has already been added to this panel.');
            return false;
        }

        // Copies the gene to the selected block.
        objViewListF = document.getElementById('viewlistForm_' + sViewListID);
        objElement = document.getElementById(sID);
        objElement.style.cursor = 'progress';
        // Mark gene somewhat as selected. Whatever I tried with delays and animations, it doesn't work.
        // This is hardly functional (it isn't kept obviously), but it's something.
        // FIXME: If we'd have a function that's run at the end of each VL load, then we can have them marked again.
        //   Build this, maybe, if it's not too slow with a large number of genes?
        $(objElement).addClass('del');

        objGenes = document.getElementById('gene_list');
        oTR = document.createElement('TR');
        oTR.id = 'tr_' + sID;
        oTR.className = 'colGreen';
        oTR.innerHTML =
            '<TD><INPUT type="hidden" name="genes[]" value="' + sID + '">' + sID + '</TD>' +
            '<TD><SELECT name="transcriptids[]" style="width : 100%;">' + sTranscripts + '</SELECT></TD>' +
            '<TD><SELECT name="inheritances[]"><?php echo $sInheritanceOptions; ?></SELECT></TD>' +
            '<TD><INPUT type="text" name="pmids[]" value="" size="10"></TD>' +
            '<TD><INPUT type="text" name="remarkses[]" value="" size="40"></TD>' +
            '<TD width="30" align="right"><A href="#" onclick="lovd_removeGene(\'' + sViewListID + '\', \'' + sID + '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A></TD>';
        $(objGenes).select('tbody').prepend(oTR);
        $(objGenes).parent().scrollTop(0);
        objElement.style.cursor = '';

        return true;
    }



    function lovd_removeGene (sViewListID, sID)
    {
        // Removes the gene from the block of selected entries.
        objViewListF = document.getElementById('viewlistForm_' + sViewListID);
        objTR = document.getElementById('tr_' + sID);

        // Remove from block, simply done (no fancy animation).
        objTR.parentNode.removeChild(objTR);

        return true;
    }
</SCRIPT>

    <?php
    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($_PE[2])) && !ACTION) {
    // URL: /gene_panels/00001/BRCA1
    // View specific gene panel gene entry.

    $nGenePanelID = sprintf('%05d', $_PE[1]);
    $sGeneID = rawurldecode($_PE[2]);
    define('PAGE_TITLE', 'View gene ' . $sGeneID . ' in gene panel #' . $nGenePanelID);

    lovd_requireAUTH();
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_gene_panel_genes.php';
    $_DATA = new LOVD_GenePanelGene();
    $zData = $_DATA->viewEntry(array('genepanelid' => $nGenePanelID, 'geneid' => $sGeneID));

    $aNavigation = array();

    if ($_AUTH['level'] >= $_SETT['user_level_settings']['genepanels_genes_edit']) {
        $aNavigation[CURRENT_PATH . '?edit'] = array('menu_edit.png', 'Edit gene information', 1);
    }
    if ($_AUTH['level'] >= $_SETT['user_level_settings']['genepanels_genes_delete']) {
        $aNavigation[CURRENT_PATH . '?delete'] = array('cross.png', 'Remove gene entry', 1);
    }

    lovd_showJGNavigation($aNavigation, 'GenePanelGene');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($_PE[2])) && ACTION == 'edit') {
    // URL: /gene_panels/00001/BRCA1?edit
    // Edit specific gene panel gene entry.

    $nGenePanelID = sprintf('%05d', $_PE[1]);
    $sGeneID = rawurldecode($_PE[2]);
    define('PAGE_TITLE', 'Edit gene ' . $sGeneID . ' in gene panel #' . $nGenePanelID);
    define('LOG_EVENT', 'GenePanelGeneEdit');

    lovd_requireAUTH($_SETT['user_level_settings']['genepanels_genes_edit']);

    require ROOT_PATH . 'class/object_gene_panel_genes.php';
    require ROOT_PATH . 'inc-lib-form.php';
    $_DATA = new LOVD_GenePanelGene();

    $zData = $_DATA->loadEntry(array('genepanelid' => $nGenePanelID, 'geneid' => $sGeneID));

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST, $zData);

        if (!lovd_error()) {
            $sDateNow = date('Y-m-d H:i:s');
            // Build up array for updateEntry();
            $aData = array(
                'genepanelid' => $nGenePanelID,
                'geneid' => $sGeneID,
                'transcriptid' => (empty($_POST['transcriptid'])? NULL : $_POST['transcriptid']),
                'inheritance' => $_POST['inheritance'],
                'pmid' => $_POST['pmid'],
                'remarks' => $_POST['remarks'],
                'edited_by' => $_AUTH['id'],
                'edited_date' => $sDateNow,
            );

            $_DATA->updateEntry(array('genepanelid' => $nGenePanelID, 'geneid' => $sGeneID), $aData, array_keys($aData));

            lovd_writeLog('Event', LOG_EVENT, 'Edited gene entry ' . $sGeneID . ' in gene panel #' . $nGenePanelID);

            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited the gene entry!', 'success');

            $_T->printFooter();
            exit;
        }
    } else {
        // Load current values.
        $_POST = array_merge($_POST, $zData);
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();
    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
        $_DATA->getForm(),
        array(
            array('', '', 'submit', 'Edit gene entry'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($_PE[2])) && ACTION == 'delete') {
    // URL: /gene_panels/00001/BRCA1?delete
    // Drop specific gene panel gene entry.

    $nGenePanelID = sprintf('%05d', $_PE[1]);
    $sGeneID = rawurldecode($_PE[2]);
    define('PAGE_TITLE', 'Remove gene ' . $sGeneID . ' from gene panel #' . $nGenePanelID);
    define('LOG_EVENT', 'GenePanelGeneDelete');

    lovd_requireAUTH($_SETT['user_level_settings']['genepanels_genes_delete']);

    require ROOT_PATH . 'class/object_gene_panel_genes.php';
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }
        if (!isset($_POST['reason']) || !trim($_POST['reason'])) {
            lovd_errorAdd('reason', 'Please fill in the \'Reason for removing this gene\' field.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Delete the gene.
            $_DATA = new LOVD_GenePanelGene();
            $_DATA->deleteEntry(array('genepanelid' => $nGenePanelID, 'geneid' => $sGeneID), $_POST['reason']);

            lovd_writeLog('Event', LOG_EVENT, 'Deleted gene entry ' . $sGeneID . ' from gene panel #' . $nGenePanelID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0] . '/' . $_PE[1]);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully remove the gene from this gene panel!', 'success');
            $_T->printFooter();
            exit;
        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_showInfoTable('This will remove the <B>' . $sGeneID . '</B> gene from gene panel #' . $nGenePanelID . '. It will not delete the gene from LOVD, only unlink it from this gene panel and remove any extra data you have stored here. This action cannot be undone.', 'warning');

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
        array(
            array('POST', '', '', '', '40%', '14', '60%'),
            array('Removing gene entry', '', 'print', $sGeneID . ' from gene panel #' . $nGenePanelID ),
            'skip',
            array('Reason for removing this gene', '', 'text', 'reason', 40),
            array('Enter your password for authorization', '', 'password', 'password', 20),
            array('', '', 'submit', ' Remove gene entry '),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");
    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'history_full') {
    // URL: /gene_panels/00001?history_full
    // Show the history for this gene panel.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'View full history for gene panel #' . $nID);

    lovd_requireAUTH();

    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_gene_panel_genes.rev.php';
    $_DATA = new LOVD_GenePanelGeneREV();
    $_GET['search_genepanelid'] = $nID;
    $_DATA->viewList('GenePanelGeneREV');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'history') {
    // URL: /gene_panels/00001?history
    // URL: /gene_panels/00001?history&from=2013-09-06&to=2016-06-02
    // URL: /gene_panels/00001?history&from=2013-09-06%2014:15:09&to=2016-06-02%2016:27:29
    // Show the history of this gene panel between two dates.

    // When no dates are given, take defaults and refresh.
    $bReload = false;
    require ROOT_PATH . 'inc-lib-form.php';
    if (empty($_GET['from']) || (!lovd_matchDate($_GET['from']) && !lovd_matchDate($_GET['from'], true))) {
        // From date empty or rejected. Set to t=0.
        $_GET['from'] = date('Y-m-d', 0);
        $bReload = true;
    }
    if (empty($_GET['to']) || (!lovd_matchDate($_GET['to']) && !lovd_matchDate($_GET['to'], true))) {
        // To date empty or rejected. Set to t=time().
        $_GET['to'] = date('Y-m-d');
        $bReload = true;
    }
    if ($bReload) {
        // One of the times had to be filled in by us, let's reload.
        header('Location: ' . lovd_getInstallURL() . CURRENT_PATH . '?' . ACTION . '&from=' . $_GET['from'] . '&to=' . $_GET['to']);
        exit;
    }

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'View changes to gene panel #' . $nID . ' between dates');

    lovd_requireAUTH();

    $_T->printHeader();
    $_T->printTitle();

    print('    <FORM action="' . CURRENT_PATH . '?history" method="get" id="dateRangeForm" onsubmit="lovd_changeDateRange(\'gene_panels/' . $nID . '?history\'); return false;">
      <TABLE border="0" cellpadding="10" cellspacing="1" width="750" class="data" style="font-size : 13px;">
        <TR>
          <TD>
            <SPAN>From date: <INPUT type="text" id="fromDate" readonly="true" value="' . $_GET['from'] . '" style="font-size : 13px;"></SPAN>
            <SPAN>To date: <INPUT type="text" id="toDate" readonly="true" value="' . $_GET['to'] . '" style="font-size : 13px;"></SPAN>
            <INPUT type="submit" value="View changes" style="font-size : 13px;">
          </TD>
        </TR>
      </TABLE>
    </FORM><BR>' . "\n");

    displayGenePanelHistory($nID, $_GET['from'], $_GET['to']);

    // Add JS for support of the date picker.
?>
    <SCRIPT type="text/javascript">
        <!--
        $(function() {
            $("#fromDate").datepicker({
                changeYear: true,
                yearRange: '1970:<?php echo date('Y'); ?>',
                numberOfMonths: 3,
                stepMonths: 3,
                dateFormat: 'yy-mm-dd',
                maxDate: $("#toDate").val(),
                onClose: function (selectedDate) {
                    $("#toDate").datepicker("option", "minDate", selectedDate);
                }
            });
            $("#toDate").datepicker({
                changeYear: true,
                yearRange: '1970:<?php echo date('Y'); ?>',
                numberOfMonths: 3,
                stepMonths: 3,
                dateFormat: 'yy-mm-dd',
                minDate: $("#fromDate").val(),
                onClose: function (selectedDate) {
                    $("#fromDate").datepicker("option", "maxDate", selectedDate);
                }
            });
        });
        function lovd_changeDateRange (sUrl) {
            var aDateFields = document.getElementById("dateRangeForm");

            if (aDateFields.elements[0].value == '' || aDateFields.elements[1].value == '') {
                alert("Both dates must be entered!");
            } else {
                // Change the URL, allowing the user to go back to the gene panel screen.
                window.location = sUrl + '&from=' + aDateFields.elements[0].value  + '&to=' + aDateFields.elements[1].value;
            }
        }
        //-->
    </SCRIPT>

<?php
    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'add') {
// URL: /gene_panels?add
// Show all gene panels so as the user can select which one to add the selected genes into.

    define('PAGE_TITLE', 'Select gene panel to add selected genes to');
    define('LOG_EVENT', 'GenePanelSelect');

    $_T->printHeader();
    $_T->printTitle();

    lovd_requireAUTH();

    if (!empty($_GET['select_genes_from'])) {
        $sViewListID = $_GET['select_genes_from'];
    } else {
        // We have not been provided with a viewlistid.
        lovd_showInfoTable('Must supply a view list ID!', 'stop');
        $_T->printFooter();
        exit;
    }

    if (empty($_SESSION['viewlists'][$sViewListID]['checked']) || count($_SESSION['viewlists'][$sViewListID]['checked']) == 0) {
        // We have a viewlistid but there are no selected genes.
        lovd_showInfoTable('No genes have been selected!', 'stop');
        $_T->printFooter();
        exit;
    }

    require ROOT_PATH . 'class/object_gene_panels.php';
    $_DATA = new LOVD_GenePanel();
    // Set the row link URL to point to the gene panel genes management along with the required $_GET values.
    $_DATA->setRowLink('GenePanelSelect', 'javascript:window.location.href=\'' . lovd_getInstallURL() . 'gene_panels/{{id}}?manage_genes&select_genes_from=' . $sViewListID . '\'; return false');
    $_DATA->viewList('GenePanelSelect', array('show_options' => true));

    $_T->printFooter();
    exit;
}





print('No condition met using the provided URL.');
?>
