<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-01
 * Modified    : 2016-03-24
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
define('TAB_SELECTED', 'genes');
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
    $_DATA->viewList('GenePanel', array(), false, false, (bool) ($_AUTH['level'] >= LEVEL_SUBMITTER));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /gene_panels/00001
    // View specific entry.

    if ($_AUTH['level'] >= LEVEL_SUBMITTER) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

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
        $aNavigation[CURRENT_PATH . '?manage_genes']    = array('menu_plus.png', 'Add gene(s) to gene panel', 1);
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
    // Add a menu item to allow the user to download the whole gene panel
    print('      <UL id="viewlistMenu_' . $sGPGViewListID . '" class="jeegoocontext jeegooviewlist">' . "\n" .
          '        <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'' . $sGPGViewListID . '\', function(){lovd_AJAX_viewListDownload(\'' . $sGPGViewListID . '\', true);});"><SPAN class="icon" style="background-image: url(gfx/menu_save.png);"></SPAN>Download gene panel</A></LI>' . "\n" .
          '      </UL>' . "\n\n");
    $_DATA->setRowLink($sGPGViewListID, CURRENT_PATH . '/{{geneid}}');
    $_DATA->viewList($sGPGViewListID, array(), false, false, true);

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /gene_panels?create
    // Create a new gene panel entry.

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

            // If we are a manager then we can update the PMID mandatory field
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
    define('PAGE_TITLE', 'Manage genes for gene panel entry #' . $nID);
    define('LOG_EVENT', 'GenePanelManage');

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
                } else {
                    // Needs an update, maybe. Only if something changed.
                    // updateEntry() will figure out if we actually need a query or not.
                    // Since we're versioned and many genes may be involved, we want to be sure.
                    $aData += array(
                        'edited_by' => $_AUTH['id'],
                        'edited_date' => $sDateNow,
                    );
                    $_DATA->updateEntry(array('genepanelid' => $nID, 'geneid' => $sGeneID), $aData, array_keys($aData));
                    // Mark gene as done, so we don't delete it after this loop.
                    unset($aGenesCurrentlyAssociated[$sGeneID]);
                }
            }

            // Now delete what was no longer selected.
            if ($aGenesCurrentlyAssociated) {
                // When not using deleteEntry(), we could simply run one query for all genes that were dropped.
                // However, for that we'd need to duplicate code handling the revision history.
                // So we're going to keep the code simple, in expense of some speed on large deletions.
                foreach (array_keys($aGenesCurrentlyAssociated) as $sGeneID) {
                    // FIXME: No reason passed. Should we demand one from our users?
                    $_DATA->deleteEntry(array('genepanelid' => $nID, 'geneid' => $sGeneID));
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
                );
        }
        ksort($aGenes); // So it will be resorted on a page reload.

    } else {
        // First time on form. Use current database contents.

        // Retrieve current genes, alphabetically ordered (makes it a bit easier to work with new forms).
        // FIXME: This is where the new fetchAllCombine() will make sense...
        $qGenes = $_DB->query(
            'SELECT gp2g.geneid, gp2g.geneid AS name, gp2g.transcriptid, gp2g.inheritance, gp2g.pmid, REPLACE(gp2g.remarks, "\r\n", " ") AS remarks, IFNULL(CONCAT("<OPTION value=\"\">-- select --</OPTION>", GROUP_CONCAT(CONCAT("<OPTION value=\"", t.id, "\">", t.id_ncbi, "</OPTION>") ORDER BY t.id_ncbi SEPARATOR "")), "<OPTION value=\"\">-- no transcripts available --</OPTION>") AS transcripts_HTML
             FROM ' . TABLE_GP2GENE . ' AS gp2g LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (gp2g.geneid = t.geneid)
             WHERE gp2g.genepanelid = ? GROUP BY gp2g.geneid ORDER BY gp2g.geneid', array($nID));
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
    $_DATA->setRowLink($sViewListID, 'javascript:lovd_addGene(\'{{ViewListID}}\', \'{{ID}}\', \'{{zData_transcripts_HTML}}\'); return false;');
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
    // Define the inheritance options list HTML string. Will be used in two places; in the HTML and the JS.
    $sInheritanceOptions = '<OPTION value="">-- select --</OPTION>';
    foreach ($aInheritances as $sInheritance) {
        $sInheritanceOptions .= '<OPTION value="' . $sInheritance . '">' . $sInheritance . '</OPTION>';
    }
    // Form & table.
    print('
      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">
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
            <TR id="tr_' . $sID . '">
              <TD>
                <INPUT type="hidden" name="genes[]" value="' . $sID . '">
              ' . $aGene['name'] . '</TD>
              <TD><SELECT name="transcriptids[]" style="width : 100%;">' . str_replace('"' . $aGene['transcriptid'] . '">', '"' . $aGene['transcriptid'] . '" selected>', $aGene['transcripts_HTML']) . '</SELECT></TD>
              <TD><SELECT name="inheritances[]">' . str_replace('"' . $aGene['inheritance'] . '">', '"' . $aGene['inheritance'] . '" selected>', $sInheritanceOptions) . '</SELECT></TD>
              <TD><INPUT type="text" name="pmids[]" value="' . $aGene['pmid'] . '" size="10"></TD>
              <TD><INPUT type="text" name="remarkses[]" value="' . $aGene['remarks'] . '" size="40"></TD>
              <TD width="30" align="right"><A href="#" onclick="lovd_removeGene(\'' . $sViewListID . '\', \'' . $sID . '\'); return false;"><IMG src="gfx/mark_0.png" alt="Remove" width="11" height="11" border="0"></A></TD></TR>');
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

    $aNavigation[CURRENT_PATH . '?edit']            = array('menu_edit.png', 'Edit gene information', 1);
    if ($_AUTH['level'] >= LEVEL_MANAGER) {
        $aNavigation[CURRENT_PATH . '?delete']      = array('cross.png', 'Remove gene entry', 1);
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

    lovd_requireAUTH();

    require ROOT_PATH . 'class/object_gene_panel_genes.php';
    require ROOT_PATH . 'inc-lib-form.php';
    $_DATA = new LOVD_GenePanelGene();

    $zData = $_DATA->loadEntry(array('genepanelid' => $nGenePanelID, 'geneid' => $sGeneID));

    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST, $zData);

        if (!lovd_error()) {
            $_DB->beginTransaction();

            // Query text.
            // FIXME: Instead of worrying about this, we'd like to use updateEntry() here, but that can't handle linking tables with two ID fields yet.
            $sSQLDate = date('Y-m-d H:i:s');
            $_POST['pmid'] = ($_POST['pmid']? $_POST['pmid'] : null);
            $_POST['transcriptid'] = ($_POST['transcriptid']? $_POST['transcriptid'] : null);
            $_POST['edited_by'] = $_AUTH['id'];

            $sSQL = 'UPDATE ' . TABLE_GP2GENE . ' SET transcriptid = ?, inheritance = ?, pmid = ?, remarks = ?, edited_by = ?, edited_date = ? WHERE genepanelid = ? and geneid = ?';
            $aSQL = array($_POST['transcriptid'], $_POST['inheritance'], $_POST['pmid'], $_POST['remarks'], $_POST['edited_by'], $sSQLDate, $nGenePanelID, $sGeneID);
            $q = $_DB->query($sSQL, $aSQL, true, true);
            // Get the data from the existing record in the revision table so as we can compare it against the changes.
            $aDataOld = $_DB->query('SELECT * FROM ' . TABLE_GP2GENE_REV . ' WHERE genepanelid = ? and geneid = ? ORDER BY valid_to DESC LIMIT 1', array($nGenePanelID, $sGeneID))->fetchAssoc();
            if (!$q) {
                lovd_writeLog('Error', LOG_EVENT, 'Gene entry ' . $sGeneID . ' in gene panel #' . $nGenePanelID . ' could not be edited');
                lovd_errorAdd('error', 'The selected gene could not be edited. Please contact your database administrator.');
            }
            // Update the record with the newest date which should  be 9999-12-31.
            $sSQLExistingRev = 'UPDATE ' . TABLE_GP2GENE_REV . ' SET valid_to = ? WHERE genepanelid = ? and geneid = ? ORDER BY valid_to DESC LIMIT 1';
            $aSQLExistingRev = array($sSQLDate, $nGenePanelID, $sGeneID);
            $qu = $_DB->query($sSQLExistingRev, $aSQLExistingRev, true, true);
            if (!$qu) {
                lovd_writeLog('Error', LOG_EVENT, 'Gene entry ' . $sGeneID . ' in gene panel #' . $nGenePanelID . ' could not be updated in the revision table');
                lovd_errorAdd('error', 'The selected gene could not be edited. Please contact your database administrator.');
            }
            $aSQLInsertRev = array (
                'genepanelid' => $nGenePanelID,
                'geneid' => $sGeneID,
                'transcriptid' => $_POST['transcriptid'],
                'inheritance' => $_POST['inheritance'],
                'pmid' => $_POST['pmid'],
                'remarks' => $_POST['remarks'],
                'created_by' => $zData['created_by'],
                'created_date' => $zData['created_date'],
                'edited_by' => $_POST['edited_by'],
                'edited_date' => $sSQLDate,
                'valid_from' => $sSQLDate,
            );
            // Find the differences between the old and new records.
            $aDiff = array_diff_assoc($aSQLInsertRev, $aDataOld);
            // Remove the columns we are not interested in.
            unset($aDiff['edited_by'], $aDiff['edited_date'], $aDiff['reason'], $aDiff['valid_to'], $aDiff['valid_from'], $aSQL['deleted'], $aSQL['deleted_by']);
            $sReason = (empty($aData['reason'])?'Record modified' . "\r\n":'Record modified: ' . $aData['reason'] . "\r\n");
            // Have we detected any differences?
            if (count($aDiff)){
                // If we have passed a reason then use this as the first line otherwise just say the record was modified
                // Loop through each of these differences and create a reason string
                foreach ($aDiff as $sKey => $sValue) {
                    $sReason .= $sKey . ': "' . $aDataOld[$sKey] . '" => "' . $sValue . '"' . "\r\n";
                }
            } else {
                $sReason .= '-';
            }
            $aSQLInsertRev['reason'] = $sReason;

            $sSQLInsertRev = 'INSERT INTO ' . TABLE_GP2GENE_REV . ' (' . implode(', ', array_keys($aSQLInsertRev)) . ') VALUES (?' . str_repeat(', ?', count($aSQLInsertRev) - 1) . ')';
            $qi = $_DB->query($sSQLInsertRev, array_values($aSQLInsertRev), true, true);
            if (!$qi) {
                lovd_writeLog('Error', LOG_EVENT, 'Gene entry ' . $sGeneID . ' in gene panel #' . $nGenePanelID . ' could not be added to the revision table');
                lovd_errorAdd('error', 'The selected gene could not be edited. Please contact your database administrator.');
            }

            if (lovd_error()) {
                // We have failed to edit this gene so throw an error message.
                $_DB->rollBack();
            } else {
                // All looks OK so lets commit these changes to the DB.
                $_DB->commit();
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
        if (!isset($_POST['reason']) || !trim($_POST['reason'])) {
            lovd_errorAdd('reason', 'Please fill in the \'Reason for removing this gene\' field.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Delete the gene.
            $_DB->beginTransaction();

            $q = $_DB->query('DELETE FROM ' . TABLE_GP2GENE . ' WHERE genepanelid = ? AND geneid = ?', array($nGenePanelID, $sGeneID), false);
            if (!$q) {
                // We have failed to delete this gene so throw an error message
                lovd_writeLog('Error', LOG_EVENT, 'Gene entry ' . $sGeneID . ' in gene panel #' . $nGenePanelID . ' could not be removed');
                unset($_POST['password']);
                lovd_errorAdd('error', 'The selected gene could not be removed from this list. Please contact your database administrator.');
            }
            $sReason = 'Record deleted: ' . ($_POST['reason']? $_POST['reason'] : '-');
            $sSQLExistingRev = 'UPDATE ' . TABLE_GP2GENE_REV . ' SET valid_to = ?, deleted = 1, deleted_by = ?, reason = CONCAT(reason,"\r\n", ?) WHERE genepanelid = ? and geneid = ? ORDER BY valid_to DESC LIMIT 1';
            $aSQLExistingRev = array(date('Y-m-d H:i:s'), $_AUTH['id'], $sReason, $nGenePanelID, $sGeneID);
            $qu = $_DB->query($sSQLExistingRev, $aSQLExistingRev, true, true);
            if (!$qu) {
                // We have failed to update this gene in the revision table so throw an error message
                lovd_writeLog('Error', LOG_EVENT, 'Gene entry ' . $sGeneID . ' in gene panel #' . $nGenePanelID . ' could not be updated in the revision table');
                unset($_POST['password']);
                lovd_errorAdd('error', 'The selected gene could not be updated in the revision table. Please contact your database administrator.');
            }
            if (lovd_error()) {
                // We have failed to delete this gene so roll back the committ
                $_DB->rollBack();
            } else {
                $_DB->commit();
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
            array('Reason for removing this gene', '', 'text', 'reason', 40),
            array('Enter your password for authorization', '', 'password', 'password', 20),
            array('', '', 'submit', ' Remove gene entry '),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");
    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'history') {
    // URL: /gene_panels/00001?history
    // Show the history for this gene panel.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'View history for gene panel #' . $nID);

    lovd_requireAUTH();

    $_T->printHeader();
    $_T->printTitle();





    $_T->printFooter();
    exit;
}





print('No condition met using the provided URL.');
?>
