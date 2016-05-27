<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-05-04
 * Modified    : 2016-05-04
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



if (PATH_COUNT == 2 && ACTION == 'create') {
//if ( ACTION == 'create') {
    // URL: /summary_annotations/chrX_000030?create
    // Create a new summary annotation entry.

    $DBID = sprintf('%s', $_PE[1]);
    $nVariantID = $_GET['variant_id'];

    define('PAGE_TITLE', 'Create a new summary annotation entry');
    define('LOG_EVENT', 'SummaryAnnotationCreate');

    lovd_requireAUTH(LEVEL_ANALYZER);


    require ROOT_PATH . 'class/object_general_annotations.php';
    $_DATA = new LOVD_GeneralAnnotation();
    require ROOT_PATH . 'inc-lib-form.php';

    //if (!empty($_POST)) {
    if (empty($_POST)) {
            lovd_errorClean();
            //$_DATA->checkFields($_POST);

            if (!lovd_error()) {
                // Fields to be used.
                $aFields = array( 'id', 'effectid', 'created_by', 'created_date');

                // Prepare values.
                $_POST['id'] = $DBID;
                $_POST['effectid'] = 11;
                $_POST['created_by'] = $_AUTH['id'];
                $_POST['created_date'] = date('Y-m-d H:i:s');

                $nID = $_DATA->insertEntry($_POST, $aFields);

                // Write to log...
                lovd_writeLog('Event', LOG_EVENT, 'Created summary annotation entry ' . $nID . ' - ' . $_POST['name']);

                header('Refresh: 0; url=' . lovd_getInstallURL() . CURRENT_PATH . '?edit&variant_id=' . $nVariantID);

            //    $_T->printHeader();
            //    $_T->printTitle();
           //     lovd_showInfoTable('Successfully created the summary annotation entry!', 'success');

            //    $_T->printFooter();
                exit;
            }

        } else {
            $_DATA->setDefaultValues();
        }

}




print('No condition met using the provided URL.');
?>