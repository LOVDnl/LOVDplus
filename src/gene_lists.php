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

    var_dump($zData);

    $_T->printFooter();
    exit;
}

print('No condition met using the provided URL.');

?>
