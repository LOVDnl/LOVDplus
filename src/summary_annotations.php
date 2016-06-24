<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-05-04
 * Modified    : 2016-06-06
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


if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





if (PATH_COUNT == 2 && ACTION == 'edit') {
    // URL: /summary_annotations/chrX_000030?edit
    // Edit a specific entry.

    $DBID = sprintf('%s', $_PE[1]);
    $nVariantID = $_GET['variant_id'];


    define('PAGE_TITLE', 'Edit summary annotations for variant ' . $DBID);
    define('LOG_EVENT', 'SummaryAnnotationEdit');

    lovd_requireAUTH(LEVEL_ANALYZER);

    require ROOT_PATH . 'class/object_summary_annotations.php';
    $_DATA = new LOVD_SummaryAnnotation();

    $zData = $_DATA->loadEntry($DBID);
    require ROOT_PATH . 'inc-lib-form.php';
    if (!empty($_POST)) {
        lovd_errorClean();

        $_DATA->checkFields($_POST, $zData);
        if (!lovd_error()) {
            // Fields to be used.

            $aFields = array_merge(
                array('effectid', 'edited_by', 'edited_date'),
                $_DATA->buildFields());

            // Prepare values.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');
        
            $_DATA->updateEntry($DBID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited summary annotation entry - ' . $DBID);

        }

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'variants/' . $nVariantID . (isset($_GET['in_window'])? '?&in_window' : ''));

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully edited the summary annotation entry!', 'success');

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
    lovd_includeJS('inc-js-custom_links.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '&variant_id=' . $nVariantID . (isset($_GET['in_window'])? '&amp;in_window' : '') . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
        $_DATA->getForm(),
        array(
            array('', '', 'submit', 'Edit summary annotation entry'),
        ));
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ACTION == 'create') {
    // URL: /summary_annotations/chrX_000030?create
    // Create a new summary annotation entry.

    $DBID = sprintf('%s', $_PE[1]);
    $nVariantID = $_GET['variant_id'];

    define('PAGE_TITLE', 'Create a new summary annotation entry');
    define('LOG_EVENT', 'SummaryAnnotationCreate');

    lovd_requireAUTH(LEVEL_ANALYZER);

    require ROOT_PATH . 'class/object_summary_annotations.php';
    $_DATA = new LOVD_SummaryAnnotation();

    // Fields to be used.
    $aFields = array( 'id', 'effectid', 'created_by', 'created_date');

    // Prepare values.
    $_POST['id'] = $DBID;
    $_POST['created_by'] = $_AUTH['id'];
    $_POST['created_date'] = date('Y-m-d H:i:s');

    $_DATA->insertEntry($_POST, $aFields);

    // Write to log...
    lovd_writeLog('Event', LOG_EVENT, 'Created summary annotation entry - ' . $DBID);
    header('Refresh: 0; url=' . lovd_getInstallURL() . CURRENT_PATH . '?edit&variant_id=' . $nVariantID . (isset($_GET['in_window'])? '&in_window' : ''));
    exit;
}





if (PATH_COUNT == 2 && ACTION == 'history') {
    // URL: /summary_annotations/chrX_000030?history
    // Show the history for this summary annotation record.

    $sID = $_PE[1];
    define('PAGE_TITLE', 'View history for summary annotation record #' . $sID);

    lovd_requireAUTH();

    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_summary_annotations.rev.php';
    $_DATA = new LOVD_SummaryAnnotationREV();
    $_GET['search_id'] = $sID;
    $_DATA->viewList('SummaryAnnotationREV');

    $_T->printFooter();
    exit;
}





print('No condition met using the provided URL.');
?>