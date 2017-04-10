<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-04-07
 * Modified    : 2017-04-07
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





if ($_PE[1] == 'curation_files' && ACTION == 'download') {
    // /uploads/curation_files/[curation file name]?download
    // Let user download curation files if they are logged in.
    lovd_requireAUTH(LEVEL_ANALYZER);

    // Get the location where we store curation files.
    $sCurationFilesPath =  $_INI['paths']['uploaded_files'];
    $sFileName = $_PE[2];
    $sAbsoluteFileName = realpath($sCurationFilesPath . '/' . $sFileName);
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . $sFileName);
    header('Content-Length: ' . filesize($sAbsoluteFileName));
    readfile($sAbsoluteFileName);

    exit;
}





if ($_PE[1] == 'curation_files' && ACTION == 'preview') {
    // /uploads/curation_files/[image file name]?preview
    // Simply display image on the browser for quick preview.

    lovd_requireAUTH(LEVEL_ANALYZER);
    $sCurationFileName = $_PE[2];
    define('PAGE_TITLE', 'Preview curation file');
    $_T->printHeader();
    $_T->printTitle();
    $sFileUrl = lovd_getInstallURL() . 'uploads/curation_files/' . basename($sCurationFileName) . '?download';
    print('<IMG src="' . $sFileUrl . '" />');
    $_T->printFooter();

    exit;
}





if ($_PE[1] == 'curation_files' && ACTION == 'remove') {
    // /uploads/curation_files/[image file name]?remove
    // Delete existing curation files.
    require ROOT_PATH . 'inc-lib-form.php';
    $sCurationFileName = $_PE[2];
    list($nID) = explode('-', $sCurationFileName);
    define('LOG_EVENT', 'CurationFileRemove');
    define('PAGE_TITLE', 'Delete curation file');
    $_T->printHeader();
    $_T->printTitle();

    $bAuthorised = false;

    $sSQL = 'SELECT s.analysis_statusid
             FROM ' . TABLE_SCREENINGS . ' AS s
             INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v 
             ON (s.id = s2v.screeningid AND s2v.variantid = ?)';
    $aResult = $_DB->query($sSQL, array($nID))->fetchAssoc();

    if ($aResult['analysis_statusid'] == ANALYSIS_STATUS_IN_PROGRESS) {
        if ($_AUTH['level'] >= LEVEL_OWNER) {
            $bAuthorised = true;
        }
    }

    if (!$bAuthorised) {
        lovd_errorAdd('delete', 'You are not authorised to delete this file');
        lovd_errorPrint();
        $_T->printFooter();
        exit;
    }

    print('<P>Please enter your password to confirm that you want to delete this curation file <STRONG>' . $sCurationFileName . '</STRONG></P>' . "\n");
    $aForm =
        array(
            array('POST', '', '', '', '', '', ''),
            array('Enter your password for authorization', '', 'password', 'password', 20),
            'skip',
            array('', '', 'submit', 'Delete file')
        );

    print('<FORM action="" method="POST">' . "\n");
    lovd_viewForm($aForm);
    print('</FORM>');

    if (POST)  {
        // User had to enter his/her password for authorization.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Get the location where uploaded curation files are stored.
            $sCurationFilesPath =  $_INI['paths']['uploaded_files'];
            $sAbsoluteFileName = realpath($sCurationFilesPath . '/' . $sCurationFileName);

            // Remove the file permanently.
            if (file_exists($sAbsoluteFileName) && unlink($sAbsoluteFileName)) {
                lovd_showInfoTable('File deleted successfully.<BR>', 'success', 600);
                if (strpos($nID, 'chr') === false) {
                    $sLogMessage = 'variant #' . $nID;
                } else {
                    $sLogMessage = 'summary annotation DBID #' . $nID;
                }
                lovd_writeLog('Event', LOG_EVENT, 'File ' . $sCurationFileName . ' deleted for ' . $sLogMessage);
            } else {
                lovd_errorAdd('delete', 'Failed to delete curation file ' . $sCurationFileName);
            }

            //  Set this popup to close after a few seconds and then refresh the variant VE to show the new file has bee uploaded.
            if (!lovd_error() && isset($_GET['in_window'])) {
                // We're in a new window, refresh opener and close window.
                print('<SCRIPT type="text/javascript">setTimeout(\'opener.location.reload();self.close();\', 1000);</SCRIPT>' . "\n\n");
            }
        } else {
            unset($_POST['password']);
        }
        print('<BR/>');
        lovd_errorPrint();
    }
    $_T->printFooter();

    exit;
}






if (PATH_COUNT == 2 && ACTION == 'curation_upload') {
    // URL: uploads?curation_upload&said=chrX_XXXXXX
    // Upload a file during variant curation.
    require ROOT_PATH . 'inc-lib-form.php';
    $_T->printHeader();

    $nID = sprintf('%010d', $_PE[1]);
    lovd_errorClean();

    $bAuthorised = false;

    $sSQL = 'SELECT s.analysis_statusid
             FROM ' . TABLE_SCREENINGS . ' AS s
             INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v 
             ON (s.id = s2v.screeningid AND s2v.variantid = ?)';
    $aResult = $_DB->query($sSQL, array($nID))->fetchAssoc();

    if ($aResult['analysis_statusid'] == ANALYSIS_STATUS_IN_PROGRESS) {
        if ($_AUTH['level'] >= LEVEL_OWNER) {
            $bAuthorised = true;
        }
    }

    if (!$bAuthorised) {
        lovd_errorAdd('delete', 'You are not authorised to upload curation file for this screening.');
        lovd_errorPrint();
        $_T->printFooter();

        if (isset($_GET['in_window'])) {
            // We're in a new window, refresh opener and close window.
            print('      <SCRIPT type="text/javascript">setTimeout(\'window.location = document.referrer;\', 2000);</SCRIPT>' . "\n\n");
        }

        exit;
    }

    if (empty($_INSTANCE_CONFIG['file_uploads'])) {
        lovd_errorAdd('mode', 'Failed to upload curation file');
        lovd_errorPrint();
        $_T->printFooter();
        exit;
    }
    $saID = $_GET['said'];
    if ($_POST['mode'] == '' ) {
        lovd_errorAdd('mode', 'The file type is not set!');
    }

    // Calculate maximum uploadable file size.
    $nMaxSizeLOVD = 100*1024*1024; // 100MB LOVD limit.
    $nMaxSize = min(
    $nMaxSizeLOVD,
    lovd_convertIniValueToBytes(ini_get('upload_max_filesize')),
    lovd_convertIniValueToBytes(ini_get('post_max_size')));
    define('LOG_EVENT', 'CurationFileUpload');
    $nWarnings = 0;
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
        // This array stores the file extensions and file types and whether the file is to be saved using nID or saID.
        $aFileTypes = $_INSTANCE_CONFIG['file_uploads'];
        if (!lovd_error()) {
            // DO NOT TRUST $_FILES['upfile']['mime'] VALUE !!
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $importFile = $_FILES['import']['tmp_name'];
            // Check the file type received from POST and see if the file MIME type is correct, depending on the values in the aFileTypes array.
            if ($aFileTypes[$_POST['mode']]['type'] == 'image') {
                if (false === $ext = array_search(
                    $finfo->file($importFile),
                    array(
                        'jpeg' => 'image/jpeg',
                        'jpg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                    ),
                    true
                )) {
                   lovd_errorAdd('import', 'Invalid file format. Expecting image type file.');
                }
            }
            elseif ($aFileTypes[$_POST['mode']]['type'] == 'excel') {
                if (false === $ext = array_search(
                    $finfo->file($importFile),
                    array(
                       'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                       'xls' => 'application/vnd.ms-excel',
                   //    'xlsx' => 'application/vnd.ms-excel',
                   //    'txt' => 'text/plain',
                    ),
                    true
                )) {
                   lovd_errorAdd('import', 'Invalid file format. Expecting excel type file.');
                }
            }
            else {
                lovd_errorAdd('import', 'Error: File type not recognised');
            }

            // Generate the new file name based on the file type and the ID to be used.
            // First, check if POST file type exists in the aFiletypes array.
            $sFileName = "";
            if ($aFileTypes[$_POST['mode']]) {
                //
                if ($aFileTypes[$_POST['mode']]['id'] == 'nid') {
                    $sFileName = $nID . '-' . $_POST['mode'];
                }
                elseif ($aFileTypes[$_POST['mode']]['id'] == 'said') {
                    if (empty($saID)) {
                        lovd_errorAdd('import', 'Summary annotation ID required for this file type.');
                    }
                    else {
                        $sFileName = $saID . '-' . $_POST['mode'];
                    }
                }
                else {
                    lovd_errorAdd('import', 'Error: could not generate file name - missing ID value.');
                }
            } else {
               lovd_errorAdd('import', 'Error: File type not recognised');
            }
        }

        if (!lovd_error()) {
            $sCurationFilesPath = $_INI['paths']['uploaded_files'];
            $sNewFileName = $sCurationFilesPath . '/' . $sFileName . '-' . time() . '.' . $ext;
            if (!move_uploaded_file($importFile, $sNewFileName)) {
                lovd_errorAdd('import', 'Failed to move uploaded file.');
                lovd_errorPrint();
                $_T->printFooter();
                exit;
            }

            // Write to log.
            lovd_writeLog('Event', LOG_EVENT, 'File ' . $sNewFileName . ' uploaded for variant #' . $nID . ' - DBID: ' . $saID );
            lovd_showInfoTable('File uploaded successfully.<BR>', 'success', 600);
            $_T->printFooter();

            //  Set this popup to close after a few seconds and then refresh the variant VE to show the new file has been uploaded.
            if (isset($_GET['in_window'])) {
                // We're in a new window, refresh opener and close window.
                print('      <SCRIPT type="text/javascript">setTimeout(\'window.location = document.referrer;\', 1000);</SCRIPT>' . "\n\n");
            }
            exit;
        }
        else {
            lovd_showInfoTable('Error occurred. File did not upload!<br>', 'stop', 600);
            lovd_errorPrint();
            $_T->printFooter();
            exit;
        }
    }

    $_T->printFooter();
}
?>
