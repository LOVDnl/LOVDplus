<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-01
 * Modified    : 2016-03-01
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Anthony Marty <anthony.marty@unimelb.edu.au>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
$sViewListID = 'GenePanel';

// TODO Modify the log entries to include URLS to the affected records

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
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
    $_DATA->viewList($sViewListID, array(), false, false, (bool) ($_AUTH['level'] >= LEVEL_SUBMITTER));

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
    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    if ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR) {
        // Authorized user is logged in. Provide tools.
        $aNavigation[CURRENT_PATH . '?edit']            = array('menu_edit.png', 'Edit gene panel information', 1);
        $aNavigation[CURRENT_PATH . '?create']          = array('menu_plus.png', 'Add gene(s) to gene panel', 1);
        if ($_AUTH['level'] >= LEVEL_MANAGER) {
            $aNavigation[CURRENT_PATH . '?delete']      = array('cross.png', 'Delete gene panel entry', 1);
        }
    }
    lovd_showJGNavigation($aNavigation, 'GenePanel');

    // Display the genes in this gene panel
    print('<BR><BR>' . "\n\n");
    $_T->printTitle('Genes in gene panel', 'H4');
    require ROOT_PATH . 'class/object_gene_panel_genes.php';
    $_DATA = new LOVD_GenePanelGene();
    // Only show the genes in this gene panel by setting the genepanelid to the current gene panel id
    $_GET['search_genepanelid'] = $nID;
    $sGPGViewListID = 'GenePanelGene';
    $_DATA->setRowLink($sGPGViewListID, CURRENT_PATH . '/{{geneid}}');
    $_DATA->viewList($sGPGViewListID, array(), false, false, true);

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /gene_panels/00001?delete
    // Drop specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Delete gene panel entry #' . $nID);
    define('LOG_EVENT', 'GenePanelDelete');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_gene_panels.php';
    $_DATA = new LOVD_GenePanel();
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
            // This also deletes the entries in gp2dis and gp2gene.
            $_DATA->deleteEntry($nID);

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

    lovd_showInfoTable('This will delete the <B>' . $zData['name'] . '</B> gene panel and unlink all the genes and diseases assigned to it. This action cannot be undone.', 'warning');

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
        array(
            array('POST', '', '', '', '40%', '14', '60%'),
            array('Deleting gene panel entry', '', 'print', $zData['id'] . ' (' . $zData['name'] . ')'),
            'skip',
            array('Enter your password for authorization', '', 'password', 'password', 20),
            array('', '', 'submit', 'Delete gene panel entry'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /gene_panels?create
    // Create a new entry.

    define('PAGE_TITLE', 'Create a new gene panel entry');
    define('LOG_EVENT', 'GenePanelCreate');

    lovd_requireAUTH();

    require ROOT_PATH . 'class/object_gene_panels.php';
    $_DATA = new LOVD_GenePanel();
    require ROOT_PATH . 'inc-lib-form.php';

    if (!empty($_POST)) {
        lovd_errorClean();


        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array('name', 'description', 'type', 'remarks', 'cohort', 'phenotype_group', 'created_by', 'created_date');

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

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH . '/' . $nID . '?manage_genes');

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully created the gene panel entry!', 'success');
            $_T->printFooter();
            exit;
        }
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

    lovd_requireAUTH();

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
            $aFields = array('name', 'description', 'remarks', 'cohort', 'phenotype_group', 'edited_by', 'edited_date');

            // Prepare values.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited gene panel entry ' . $nID . ' - ' . $_POST['name']);

            // Change linked diseases?
            // Diseases the gene is currently linked to.

            // Remove diseases.
            $aToRemove = array();
            foreach ($zData['active_diseases'] as $nDisease) {
                if ($nDisease && !in_array($nDisease, $_POST['active_diseases'])) {
                    // User has requested removal...
                    $aToRemove[] = $nDisease;
                }
            }

            if ($aToRemove) {
                $q = $_DB->query('DELETE FROM ' . TABLE_GP2DIS . ' WHERE geneid = ? AND diseaseid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($zData['id']), $aToRemove), false);
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aToRemove) == 1 ? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' could not be removed from gene panel ' . $nID);
                } else {
                    lovd_writeLog('Event', LOG_EVENT, 'Disease information entr' . (count($aToRemove) == 1 ? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' successfully removed from gene ' . $nID);
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





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'manage_genes') {
    // URL: /gene_panels/00001?manage_genes
    // Manage genes in a gene panel.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Manage genes for gene panel entry #' . $nID);
    define('LOG_EVENT', 'GenePanelManage');
    define('TAB_SELECTED', 'genes');

    lovd_requireAUTH(LEVEL_ADMIN);

    if (!$_DB->query('SELECT COUNT(*) FROM ' . TABLE_GENE_PANELS . ' WHERE id = ?', array($nID))->fetchColumn()) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such ID!', 'stop');
        $_T->printFooter();
        exit;
    }

    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

/*******************************************************************************************************************
        // Preventing notices...
        // $_POST['curators'] stores the IDs of the users that are supposed to go in TABLE_CURATES.
        if (empty($_POST['curators']) || !is_array($_POST['curators'])) {
            $_POST['curators'] = array();
        }
        // $_POST['allow_edit'] stores the IDs of the users that are allowed to edit variants in this gene (the curators).
        if (empty($_POST['allow_edit']) || !is_array($_POST['allow_edit'])) {
            $_POST['allow_edit'] = array();
        }
        // $_POST['shown'] stores whether or not the curator is shown on the screen.
        if (empty($_POST['shown']) || !is_array($_POST['shown'])) {
            $_POST['shown'] = array();
        }

        // MUST select at least one curator!
        if (empty($_POST['curators']) || empty($_POST['allow_edit']) || empty($_POST['shown'])) {
            lovd_errorAdd('', 'Please select at least one curator that is allowed to edit <I>and</I> is shown on the gene home page!');
        } else {
            // Of the selected persons, at least one should be shown AND able to edit!
            $bCurator = false;
            foreach($_POST['curators'] as $nUserID) {
                if (in_array($nUserID, $_POST['allow_edit']) && in_array($nUserID, $_POST['shown'])) {
                    $bCurator = true;
                    break;
                }
            }
            if (!$bCurator) {
                lovd_errorAdd('', 'Please select at least one curator that is allowed to edit <I>and</I> is shown on the gene home page!');
            }
        }

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        } elseif ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            // User had to enter his/her password for authorization.
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }



        if (!lovd_error()) {
            // What's by far the most efficient code-wise is just insert/update all we've got and delete everything else.
            $_DB->beginTransaction();

            foreach ($_POST['curators'] as $nOrder => $nUserID) {
                $nOrder ++; // Since 0 is the first key in the array.
                // FIXME; Managers are authorized to add other managers or higher as curators, but should not be able to restrict other manager's editing rights, or hide these users as curators.
                //   Implementing this check on this level means we need to query the database to get all user levels again, defeating this optimalisation below.
                //   Taking away the editing rights/visibility of managers or the admin by a manager is restricted in the interface, so it's not critical to solve now.
                //   I'm being lazy, I'm not implementing the check here now. However, it *is* a bug and should be fixed later.
                if (ACTION == 'authorize') {
                    // FIXME; Is using REPLACE not a lot easier?
                    $_DB->query('INSERT INTO ' . TABLE_CURATES . ' VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE allow_edit = VALUES(allow_edit), show_order = VALUES(show_order)', array($nUserID, $sID, (int) in_array($nUserID, $_POST['allow_edit']), (in_array($nUserID, $_POST['shown'])? $nOrder : 0)));
                    // FIXME; Without detailed user info we can't include elaborate logging. Would we want that anyway?
                    //   We could rapport things here more specifically because mysql_affected_rows() tells us if there has been an update (2) or an insert (1) or nothing changed (0).
                } else {
                    // Just sort and update visibility!
                    $_DB->query('UPDATE ' . TABLE_CURATES . ' SET show_order = ? WHERE geneid = ? AND userid = ?', array((in_array($nUserID, $_POST['shown'])? $nOrder : 0), $sID, $nUserID));
                }
            }

            // Now everybody should be updated. Remove whoever should no longer be in there.
            $_DB->query('DELETE FROM c USING ' . TABLE_CURATES . ' AS c, ' . TABLE_USERS . ' AS u WHERE c.userid = u.id AND c.geneid = ? AND c.userid NOT IN (?' . str_repeat(', ?', count($_POST['curators']) - 1) . ') AND (u.level < ? OR u.id = ?)', array_merge(array($sID), $_POST['curators'], array($_AUTH['level'], $_AUTH['id'])));

            // If we get here, it all succeeded.
            $_DB->commit();

            // Write to log...
            $sMessage = 'Updated curator list for the ' . $sID . ' gene';
            lovd_writeLog('Event', LOG_EVENT, $sMessage);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully updated the curator list!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
*///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    }





    $_T->printHeader();
    $_T->printTitle();

    // Now, build $aGenes, which contains info about the genes currently selected (from DB or, if available, POST!).
    $aGenes = array();
    if (!empty($_POST['genes'])) {
        // Form has already been sent. We're here because of errors. Use $_POST.
        // Retrieve data for selected genes.
        // FIXME; Do we need to change all IDs to integers because of possibly loosing the prepended zero's? Cross-browser check to verify?
        $zGenes = $_DB->query('SELECT g.id, GROUP_CONCAT(CONCAT(t.id, ";", t.id_ncbi) ORDER BY t.id_ncbi SEPARATOR ";;") AS transcripts FROM ' . TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (g.id = t.geneid) WHERE g.id IN (?' . str_repeat(', ?', count($_POST['genes'])-1) . ') GROUP BY g.id', $_POST['genes'])->fetchAllCombine();
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
                    'transcripts' => explode(';;', $zGenes[$sID]),
                    'inheritance' => (!isset($_POST['inheritances'][$nKey])? '' : $_POST['inheritances'][$nKey]),
                    'id_omim' => (!isset($_POST['id_omims'][$nKey])? '' : $_POST['id_omims'][$nKey]), // Simplicity over grammar...
                    'pmid' => (!isset($_POST['pmids'][$nKey])? '' : $_POST['pmids'][$nKey]),
                    'remarks' => (!isset($_POST['remarkses'][$nKey])? '' : $_POST['remarkses'][$nKey]), // Some LOTR here just for fun...
                );
        }
        ksort($aGenes); // So it will be resorted on a page reload.

    } else {
        // First time on form. Use current database contents.

        // Retrieve current genes, alphabetically ordered (makes it a bit easier to work with new forms).
        // FIXME: This is where the new fetchAllCombine() will make sense...
        $qGenes = $_DB->query(
            'SELECT gp2g.geneid, gp2g.geneid AS name, gp2g.transcriptid, gp2g.inheritance, gp2g.id_omim, gp2g.pmid, gp2g.remarks
             FROM ' . TABLE_GP2GENE . ' AS gp2g
             WHERE gp2g.genepanelid = ? ORDER BY gp2g.geneid', array($nID));
        while ($z = $qGenes->fetchAssoc()) {
            $aGenes[$z['geneid']] = $z;
        }
    }



    lovd_errorPrint();

    // Show viewList() of gene panel genes. We'd like to remove all genes that are already selected,
    //  but we can't properly do that. GET has a limit, and IE only allows some 2KB in there.
    // So after some 200 genes, the negative selection filter will fail.
    require ROOT_PATH . 'class/object_genes.php';
    $_DATA = new LOVD_Gene();
    lovd_showInfoTable('The following genes are configured in this LOVD. Click on one to add it to this gene panel.', 'information');
    $_GET['page_size'] = 10;
    $sViewListID = 'GenePanels_ManageGenes'; // Create known viewListID for the JS functions().
    $_DATA->setRowLink($sViewListID, 'javascript:lovd_addGene(\'{{ViewListID}}\', \'{{ID}}\', \'{{zData_transcripts}}\'); return false;');
    $_DATA->viewList($sViewListID, array(), true);



    // Show curators, to sort and to select whether or not they can edit.
    print('      <BR><BR>' . "\n\n");

    lovd_showInfoTable('All genes below have been selected for this gene panel.<BR>To remove a gene from this list, click the red cross on the far right of the line.', 'information');

    $aInheritances =
        array(
            'Autosomal Recessive',
            'Dominant',
            'X-Linked',
        );
    // Form & table.
    print('
      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">
        <TABLE id="gene_list" class="data" border="0" cellpadding="0" cellspacing="1" width="900">
          <TR>
            <TH>Symbol</TH>
            <TH>Transcript</TH>
            <TH>Inheritance</TH>
            <TH>OMIM ID</TH>
            <TH>PMID</TH>
            <TH>Remarks</TH>
            <TH width="30">&nbsp;</TH></TR>');
    // Now loop the items in the order given.
    foreach ($aGenes as $sID => $aGene) {
        print('
          <TR id="tr_' . $sID . '">
            <TD>
              <INPUT type="hidden" name="genes[]" value="' . $sID . '">
              ' . $aGene['name'] . '</TD>
            <TD><SELECT name="transcriptids[]" style="width : 100%;"><OPTION value="">-- select --</OPTION>');
        $aTranscripts = array_map('explode', array_fill(0, count($aGene['transcripts']), ';'), $aGene['transcripts']);
        foreach ($aTranscripts as $aTranscript) {
            print('<OPTION value="' . $aTranscript[0] . '"' . ($aGene['transcriptid'] != $aTranscript[0]? '' : ' selected') . '>' . $aTranscript[1] . '</OPTION>');
        }
        print('</TD>
            <TD><SELECT name="inheritances[]"><OPTION value="">-- select --</OPTION>');
        foreach ($aInheritances as $sInheritance) {
            print('<OPTION value="' . $sInheritance . '"' . ($aGene['inheritance'] != $sInheritance? '' : ' selected') . '>' . $sInheritance . '</OPTION>');
        }
        print('</TD>
            <TD><INPUT type="text" name="id_omims[]" value="' . $aGene['id_omim'] . '" size="10"></TD>
            <TD><INPUT type="text" name="pmids[]" value="' . $aGene['pmid'] . '" size="10"></TD>
            <TD><INPUT type="text" name="remarkses[]" value="' . str_replace(array("\r", "\n", '  '), ' ', $aGene['remarks']) . '" size="30"></TD>
            <TD width="30" align="right"><A href="#" onclick="lovd_removeGene(\'' . $sViewListID . '\', \'' . $sID . '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A></TD></TR>');
    }
    print('
        </TABLE><BR>' . "\n");

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

        objGenes = document.getElementById('gene_list');
        oTR = document.createElement('TR');
        oTR.id = 'tr_' + sID;
        oTR.innerHTML =
            '<TD><INPUT type="hidden" name="genes[]" value="' + sID + '">' + sID + '</TD>' +
            '<TD><SELECT name="transcriptids[]"><OPTION value="">test</OPTION></TD>' +
            '<TD><SELECT name="inheritances[]"><OPTION value="">test</OPTION></TD>' +
            '<TD><INPUT type="text" name="id_omims[]" value="" size="10"></TD>' +
            '<TD><INPUT type="text" name="pmids[]" value="" size="10"></TD>' +
            '<TD><INPUT type="text" name="remarkses[]" value="" size="30"></TD>' +
            '<TD width="30" align="right"><A href="#" onclick="lovd_removeGene(\'' + sViewListID + '\', \'' + sID + '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A></TD>';
        objGenes.appendChild(oTR);
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
    $zData = $_DATA->viewEntry($nGenePanelID . ',' . $sGeneID);

    $aNavigation = array();

    $aNavigation[CURRENT_PATH . '?edit']            = array('menu_edit.png', 'Edit gene information', 1);
    if ($_AUTH['level'] >= LEVEL_MANAGER) {
        $aNavigation[CURRENT_PATH . '?delete']      = array('cross.png', 'Remove gene entry', 1);
    }

    lovd_showJGNavigation($aNavigation, 'GenePanelGene');



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

    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_gene_panel_genes.php';
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
            // Delete the gene
            $q = $_DB->query('DELETE FROM ' . TABLE_GP2GENE . ' WHERE genepanelid = ? AND geneid = ?', array($nGenePanelID, $sGeneID), false);
            if (!$q) {
                // We have failed to delete this gene so throw an error message
                lovd_writeLog('Error', LOG_EVENT, 'Gene entry ' . $sGeneID . ' in gene panel #' . $nGenePanelID . ' could not be removed');
                unset($_POST['password']);
                lovd_errorAdd('error', 'The selected gene could not be removed from this list. Please contact your database administrator.');
            } else {
                lovd_writeLog('Event', LOG_EVENT, 'Deleted gene entry ' . $sGeneID . ' from gene panel #' . $nGenePanelID);

                // Thank the user...
                header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0] . '/' . $_PE[1]);

                $_T->printHeader();
                $_T->printTitle();
                lovd_showInfoTable('Successfully remove the gene from this gene panel!', 'success');
                $_T->printFooter();
                exit;
            }

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
            array('Enter your password for authorization', '', 'password', 'password', 20),
            array('', '', 'submit', 'Remove gene entry'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");
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

    lovd_requireAUTH();

    require ROOT_PATH . 'class/object_gene_panel_genes.php';
    require ROOT_PATH . 'inc-lib-form.php';
    $_DATA = new LOVD_GenePanelGene();

    $zData = $_DATA->loadEntry($nGenePanelID, $sGeneID);

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST, $zData);

        if (!lovd_error()) {

            global $_DB;

            // Query text.
            $sSQL = 'UPDATE ' . TABLE_GP2GENE . ' SET transcriptid = ?, inheritance = ?, id_omim = ?, pmid = ?, remarks = ?, edited_by = ?, edited_date = ? WHERE genepanelid = ? and geneid = ?';
            $aSQL = array($_POST['transcriptid'], $_POST['inheritance'], $_POST['id_omim']?$_POST['id_omim']:null, $_POST['pmid']?$_POST['pmid']:null, $_POST['remarks'], $_AUTH['id'], date('Y-m-d H:i:s'), $nGenePanelID, $sGeneID);
            $q = $_DB->query($sSQL, $aSQL, true, true);
            if (!$q) {
                // We have failed to edit this gene so throw an error message
                lovd_writeLog('Error', LOG_EVENT, 'Gene entry ' . $sGeneID . ' in gene panel #' . $nGenePanelID . ' could not be edited');
                lovd_errorAdd('error', 'The selected gene could not be edited. Please contact your database administrator.');
            } else {

                header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

                $_T->printHeader();
                $_T->printTitle();
                lovd_showInfoTable('Successfully edited the gene entry!', 'success');

                $_T->printFooter();
                exit;
            }
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





print('No condition met using the provided URL.');
?>
