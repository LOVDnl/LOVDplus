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
$sViewListID = 'GeneList';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /genes_lists
    // View all entries.

    // Submitters are allowed to download this list...
    if ($_AUTH['level'] >= LEVEL_SUBMITTER) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

    define('PAGE_TITLE', 'View all gene lists');
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_gene_lists.php';
    $_DATA = new LOVD_GeneList();
    $_DATA->viewList($sViewListID, array(), false, false, (bool) ($_AUTH['level'] >= LEVEL_SUBMITTER));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /genes_lists/00001
    // View specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'View gene list #' . $nID);
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_gene_lists.php';
    $_DATA = new LOVD_GeneList();
    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    if ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR) {
        // Authorized user is logged in. Provide tools.
        $aNavigation[CURRENT_PATH . '?edit']            = array('menu_edit.png', 'Edit gene list information', 1);
        $aNavigation[CURRENT_PATH . '?create']          = array('menu_plus.png', 'Add gene(s) to gene list', 1);
        if ($_AUTH['level'] >= LEVEL_MANAGER) {
            $aNavigation[CURRENT_PATH . '?delete']      = array('cross.png', 'Delete gene list entry', 1);
        }
    }
    lovd_showJGNavigation($aNavigation, 'Genes');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /gene_lists/00001?delete
    // Drop specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Delete gene list entry #' . $nID);
    define('LOG_EVENT', 'GeneListDelete');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'class/object_gene_lists.php';
    $_DATA = new LOVD_GeneList();
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
            // This also deletes the entries in gl2dis and gl2gene.
            $_DATA->deleteEntry($nID);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted gene list entry ' . $nID . ' - ' . $zData['id'] . ' (' . $zData['name'] . ')');

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . $_PE[0]);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the gene list entry!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }

    $_T->printHeader();
    $_T->printTitle();

    lovd_showInfoTable('This will delete the <B>' . $zData['name'] . '</B> gene list and unlink all the genes and diseases assigned to it. This action cannot be undone.', 'warning');

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
        array(
            array('POST', '', '', '', '40%', '14', '60%'),
            array('Deleting gene list entry', '', 'print', $zData['id'] . ' (' . $zData['name'] . ')'),
            'skip',
            array('Enter your password for authorization', '', 'password', 'password', 20),
            array('', '', 'submit', 'Delete gene list entry'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /gene_lists?create
    // Create a new entry.

    define('PAGE_TITLE', 'Create a new gene list entry');
    define('LOG_EVENT', 'GeneListCreate');

    lovd_requireAUTH(LEVEL_SUBMITTER);

    require ROOT_PATH . 'class/object_gene_lists.php';
    $_DATA = new LOVD_GeneList();
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
            lovd_writeLog('Event', LOG_EVENT, 'Created gene list entry ' . $nID . ' - ' . $_POST['name']);

            // Add diseases.
            $aSuccessDiseases = array();
            if (!empty($_POST['active_diseases']) && is_array($_POST['active_diseases'])) {
                foreach ($_POST['active_diseases'] as $nDisease) {
                    // Add disease to gene.
                    if ($nDisease) {
                        $q = $_DB->query('INSERT INTO ' . TABLE_GL2DIS . ' VALUES (?, ?)', array($nID, $nDisease), false);
                        if (!$q) {
                            // Silent error.
                            lovd_writeLog('Error', LOG_EVENT, 'Disease information entry ' . $nDisease . ' - could not be added to gene list ' . $nID);
                        } else {
                            $aSuccessDiseases[] = $nDisease;
                        }
                    }
                }
            }

            if (count($aSuccessDiseases)) {
                lovd_writeLog('Event', LOG_EVENT, 'Disease entr' . (count($aSuccessDiseases) > 1 ? 'ies' : 'y') . ' successfully added to gene list ' . $nID . ' - ' . $_POST['name']);
            }

            // Thank the user...
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully created the gene list entry!', 'success');
            print('      <SCRIPT type="text/javascript">setTimeout(\'window.location.href=\\\'' . lovd_getInstallURL() . CURRENT_PATH . '/' . $nID . '\\\';\', 3000);</SCRIPT>' . "\n\n");
            $_T->printFooter();
            exit;

        }

    }

    $_T->printHeader();
    $_T->printTitle();

        print('      To create a new gene list entry, please fill out the form below.<BR>' . "\n" .
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
            array('', '', 'submit', 'Create gene list entry'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}






if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /gene_lists/00001?edit
    // Drop specific entry.

    $nID = sprintf('%05d', $_PE[1]);
    define('PAGE_TITLE', 'Edit gene list entry #' . $nID);
    define('LOG_EVENT', 'GeneListEdit');

    // Require manager clearance.
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_gene_lists.php';
    $_DATA = new LOVD_GeneList();
    // Increase the max group_concat() length, so that gene lists linked to many many diseases still have all diseases mentioned here.
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
        lovd_writeLog('Event', LOG_EVENT, 'Edited gene list entry ' . $nID . ' - ' . $_POST['name']);

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
            $q = $_DB->query('DELETE FROM ' . TABLE_GL2DIS . ' WHERE geneid = ? AND diseaseid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($zData['id']), $aToRemove), false);
            if (!$q) {
                // Silent error.
                lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' could not be removed from gene list ' . $nID);
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
                $q = $_DB->query('INSERT IGNORE INTO ' . TABLE_GL2DIS . ' VALUES (?, ?)', array($nID, $nDisease), false);
                if (!$q) {
                    $aFailed[] = $nDisease;
                } else {
                    $aSuccess[] = $nDisease;
                }
            }
        }
        if ($aFailed) {
            // Silent error.
            lovd_writeLog('Error', LOG_EVENT, 'Disease information entr' . (count($aFailed) == 1? 'y' : 'ies') . ' ' . implode(', ', $aFailed) . ' could not be added to gene list ' . $nID);
        } elseif ($aSuccess) {
            lovd_writeLog('Event', LOG_EVENT, 'Disease information entr' . (count($aSuccess) == 1? 'y' : 'ies') . ' ' . implode(', ', $aSuccess) . ' successfully added to gene list ' . $nID);
        }

        // Thank the user...
        header('Refresh: 3; url=' . lovd_getInstallURL() . CURRENT_PATH);

        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('Successfully edited the gene list entry!', 'success');

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
            array('', '', 'submit', 'Edit gene list entry'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}

print('No condition met using the provided URL.');

?>
