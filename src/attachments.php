<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-04-07
 * Modified    : 2017-04-12
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : John-Paul Plazzer <johnpaul.plazzer@gmail.com>
 *               Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
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





if (PATH_COUNT == 2 && ACTION == 'download') {
    // /attachments/[attachment name]?download
    // Let user download attachments if they are logged in.

    lovd_requireAUTH(LEVEL_ANALYZER);

    // Get the location where we store attachments.
    $sFileName = $_PE[1];
    $sAbsoluteFileName = realpath($_INI['paths']['attachments'] . '/' . $sFileName);

    // Check if file actually exists.
    if (!is_readable($sAbsoluteFileName)) {
        $_T->printHeader(false);
        lovd_showInfoTable('File does not exist.', 'stop');
        $_T->printFooter();
        exit;
    }

    header('Content-Description: File Transfer');
    // FIXME: Add proper Content-Type?
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . $sFileName);
    header('Content-Length: ' . filesize($sAbsoluteFileName));
    readfile($sAbsoluteFileName);

    exit;
}





if (PATH_COUNT == 2 && ACTION == 'preview') {
    // /attachments/[image file name]?preview
    // Simply display image on the browser for quick preview.

    lovd_requireAUTH(LEVEL_ANALYZER);

    $sFileName = $_PE[1];
    $sAbsoluteFileName = realpath($_INI['paths']['attachments'] . '/' . $sFileName);

    define('PAGE_TITLE', 'Preview attachment');
    $_T->printHeader();
    $_T->printTitle();

    // Check if file actually exists.
    if (!is_readable($sAbsoluteFileName)) {
        lovd_showInfoTable('File does not exist.', 'stop');
        $_T->printFooter();
        exit;
    }

    // Only preview if it's an image.
    $aPathInfo = pathinfo($sFileName);
    if (strpos(array_search($aPathInfo['extension'], $_SETT['attachment_file_types']), 'image') !== 0) {
        lovd_showInfoTable('File is not an image.', 'stop');
        $_T->printFooter();
        exit;
    }

    $sFileUrl = lovd_getInstallURL() . 'attachments/' . basename($sFileName) . '?download';
    print('<IMG src="' . $sFileUrl . '" />');
    $_T->printFooter();

    exit;
}





if (PATH_COUNT == 2 && ACTION == 'delete') {
    // /attachments/[attachment name]?delete
    // Delete existing attachments.

    require ROOT_PATH . 'inc-lib-form.php';

    $sFileName = $_PE[1];
    list($sObject, $nID) = preg_split('/[:-]/', $sFileName);

    define('LOG_EVENT', 'AttachmentDelete');
    define('PAGE_TITLE', 'Delete attachment');
    $_T->printHeader();
    $_T->printTitle();

    // Analysis status should be "in progress" and $_AUTH level should be owner or higher.
    $nAnalysisStatus = $_DB->query(
        'SELECT s.analysis_statusid
         FROM ' . TABLE_SCREENINGS . ' AS s
         INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v 
           ON (s.id = s2v.screeningid AND s2v.variantid = ?)', array($nID))->fetchColumn();
    if ($nAnalysisStatus != ANALYSIS_STATUS_IN_PROGRESS || $_AUTH['level'] < LEVEL_OWNER) {
        lovd_showInfoTable('You are not authorised to delete this file.', 'stop');
        $_T->printFooter();
        exit;
    }

    if (POST)  {
        lovd_errorClean();

        // User had to enter his/her password for authorization.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Get the location where uploaded attachments are stored.
            $sAbsoluteFileName = realpath($_INI['paths']['attachments'] . '/' . $sFileName);

            // Delete the file permanently.
            if (file_exists($sAbsoluteFileName) && unlink($sAbsoluteFileName)) {
                lovd_showInfoTable('File deleted successfully.<BR>', 'success');
                if (strpos($nID, 'chr') === false) {
                    $sLogMessage = 'variant #' . $nID;
                } else {
                    $sLogMessage = 'summary annotation DBID #' . $nID;
                }
                lovd_writeLog('Event', LOG_EVENT, 'File ' . $sFileName . ' deleted for ' . $sLogMessage);
            } else {
                lovd_errorAdd('delete', 'Failed to delete attachment ' . $sFileName);
            }

            // Set this popup to close after a few seconds and then refresh the variant VE to show the new file has been deleted.
            if (!lovd_error() && isset($_GET['in_window'])) {
                // We're in a new window, refresh opener and close window.
                print('<SCRIPT type="text/javascript">setTimeout(\'opener.location.reload();self.close();\', 1000);</SCRIPT>' . "\n\n");
            }

            $_T->printFooter();
            exit;

        } else {
            unset($_POST['password']);
        }
    }



    lovd_errorPrint();

    print('      Please enter your password to confirm that you want to delete this attachment <B>' . $sFileName . '</B>' . "\n");
    $aForm =
        array(
            array('POST', '', '', '', '', '', ''),
            array('Enter your password for authorization', '', 'password', 'password', 20),
            'skip',
            array('', '', 'submit', 'Delete file')
        );

    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="POST">' . "\n");
    lovd_viewForm($aForm);
    print('</FORM>');

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ACTION == 'upload') {
    // URL: attachments/0000000001?upload
    // URL: attachments/0000000001?upload&summaryannotationid=chrX_000001
    // Upload an attachment.

    require ROOT_PATH . 'inc-lib-form.php';
    $_T->printHeader();

    $nID = sprintf('%010d', $_PE[1]);
    define('LOG_EVENT', 'AttachmentUpload');

    lovd_errorClean();

    // Analysis status should be "in progress" and $_AUTH level should be owner or higher.
    $nAnalysisStatus = $_DB->query(
        'SELECT s.analysis_statusid
         FROM ' . TABLE_SCREENINGS . ' AS s
         INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v 
           ON (s.id = s2v.screeningid AND s2v.variantid = ?)', array($nID))->fetchColumn();
    if ($nAnalysisStatus != ANALYSIS_STATUS_IN_PROGRESS || $_AUTH['level'] < LEVEL_OWNER) {
        lovd_showInfoTable('You are not authorised to upload an attachment for this screening.', 'stop');

        $_T->printFooter();
        exit;
    }

    if (empty($_INSTANCE_CONFIG['attachments'])) {
        lovd_showInfoTable('The attachment feature is turned off for this LOVD instance.', 'stop');
        $_T->printFooter();
        exit;
    }
    $saID = (!isset($_GET['summaryannotationid'])? '' : $_GET['summaryannotationid']);
    if ($_POST['mode'] == '' ) {
        lovd_errorAdd('mode', 'The file type is not set!');
    }

    // Calculate maximum uploadable file size.
    $nMaxSizeLOVD = 10*1024*1024; // 10MB LOVD limit for attachments.
    $nMaxSize = min(
        $nMaxSizeLOVD,
        $_SETT['attachment_max_size'],
        lovd_convertIniValueToBytes(ini_get('upload_max_filesize')),
        lovd_convertIniValueToBytes(ini_get('post_max_size')));

    if (POST) {
        // Form sent, first check the file itself.

        // If the file does not arrive (too big), it doesn't exist in $_FILES.
        if (empty($_FILES['import']) || ($_FILES['import']['error'] > 0 && $_FILES['import']['error'] < 4)) {
            lovd_errorAdd('import', 'There was a problem with the file transfer. Please try again. The file cannot be larger than ' . round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server') . '.');

        } elseif ($_FILES['import']['error'] == 4 || !$_FILES['import']['size']) {
            lovd_errorAdd('import', 'Please select a file to upload.');

        } elseif ($_FILES['import']['size'] > $nMaxSize) {
            lovd_errorAdd('import', 'The file cannot be larger than ' . round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server') . '.');

        } elseif ($_FILES['import']['error']) {
            // Various errors available from 4.3.0 or later.
            lovd_errorAdd('import', 'There was an unknown problem with receiving the file properly, possibly because of the current server settings. If the problem persists, please contact the database administrator.');
        }

        if (!lovd_error()) {
            // Find out the MIME-type of the uploaded file.
            // When it reports something not supported, mention what type was found so we can debug it.
            $sType = mime_content_type($_FILES['import']['tmp_name']);
            if (!isset($_SETT['attachment_file_types'][$sType])) { // Not all systems report the regular files as "text/plain"; also reported was "text/x-pascal; charset=us-ascii".
                lovd_errorAdd('import', 'Invalid file type, or file type not recognized. It seems to be of type "' . htmlspecialchars($sType) . '".');

            } else {
                // This array stores the file extensions and file types and whether the file is to be saved using nID or saID.
                $aFileTypes = $_INSTANCE_CONFIG['attachments'];

                // Generate the new file name based on the file type and the ID to be used.
                // First, check if the file type exists in the $aFiletypes array.
                $sFileName = $aFileTypes[$_POST['mode']]['linked_to'] . '_';
                if ($aFileTypes[$_POST['mode']]) {
                    if ($aFileTypes[$_POST['mode']]['linked_to'] == 'variant') {
                        $sFileName .= $nID . '-' . $_POST['mode'];
                    } elseif ($aFileTypes[$_POST['mode']]['linked_to'] == 'summary_annotation') {
                        if (empty($saID)) {
                            lovd_errorAdd('import', 'Summary annotation ID required for this file type.');
                        } else {
                            $sFileName .= $saID . '-' . $_POST['mode'];
                        }
                    } else {
                        lovd_errorAdd('import', 'Error: could not generate file name - missing ID value.');
                    }
                } else {
                    lovd_errorAdd('import', 'Error: Requested attachment type not recognised.');
                }
            }

            if (!lovd_error()) {
                $sNewFileName = $_INI['paths']['attachments'] . '/' . $sFileName . '-' . time() . '.' . $_SETT['attachment_file_types'][$sType];
                if (!move_uploaded_file($_FILES['import']['tmp_name'], $sNewFileName)) {
                    lovd_errorAdd('import', 'Failed to move uploaded file.');
                } else {
                    // Write to log.
                    lovd_writeLog('Event', LOG_EVENT, 'File ' . $sNewFileName . ' uploaded for variant #' . $nID . ' - DBID: ' . $saID);
                    lovd_showInfoTable('File uploaded successfully.', 'success', 600);

                    //  Set this popup to close after a few seconds and then refresh the variant VE to show the new file has been uploaded.
                    if (isset($_GET['in_window'])) {
                        // We're in a new window, refresh opener and close window.
                        print('  <SCRIPT type="text/javascript">setTimeout(\'window.location = document.referrer;\', 1000);</SCRIPT>' . "\n\n");
                    }

                    $_T->printFooter();
                    exit;
                }
            }
        }
    }

    lovd_errorPrint();
    $_T->printFooter();
}
?>
