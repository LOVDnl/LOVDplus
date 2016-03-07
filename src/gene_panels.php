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

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /genes_panels
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
    // URL: /genes_panels/00001
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
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully created the gene panel entry!', 'success');
            print('      <SCRIPT type="text/javascript">setTimeout(\'window.location.href=\\\'' . lovd_getInstallURL() . CURRENT_PATH . '/' . $nID . '\\\';\', 3000);</SCRIPT>' . "\n\n");
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
    // Drop specific entry.

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
                lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' could not be removed from gene panel ' . $nID);
            } else {
                lovd_writeLog('Event', LOG_EVENT, 'Disease information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' successfully removed from gene ' . $nID);
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
            lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aFailed) == 1? 'y' : 'ies') . ' ' . implode(', ', $aFailed) . ' could not be added to gene panel ' . $nID);
        } elseif ($aSuccess) {
            lovd_writeLog('Event', LOG_EVENT, 'Disease information entr' . (count($aSuccess) == 1? 'y' : 'ies') . ' ' . implode(', ', $aSuccess) . ' successfully added to gene panel ' . $nID);
        }

        // Thank the user...
        header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('Successfully edited the gene panel entry!', 'success');

        $_T->printFooter();
        exit;

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





if (PATH_COUNT == 3 && preg_match('/^[a-z][a-z0-9#@-]+$/i', rawurldecode($_PE[2])) && !ACTION) {
    // URL: /genes_panels/00001/BRCA1
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
    // URL: /genes_panels/00001/BRCA1?delete
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
// URL: /genes_panels/00001/BRCA1?edit
// Edit specific gene panel gene entry.

    $nID = sprintf('%05d', $_PE[1]);
    $sID = rawurldecode($_PE[2]);
    define('PAGE_TITLE', 'Edit gene ' . $sID . ' in gene panel #' . $nID);

    lovd_requireAUTH();
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_gene_panel_genes.php';

    $_T->printFooter();
    exit;

}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'create') {
    // URL: /gene_panels/00001?create
    // Add genes to a gene panel

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Add genes to gene panel entry #' . $nID);
    define('LOG_EVENT', 'GenePanelGeneAdd');

    lovd_requireAUTH();
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_gene_panel_genes.php';

    $_T->printFooter();
    exit;

}

print('No condition met using the provided URL.');

?>
