<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-02-25
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

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /gene_statistics
    // View all entries.

    // Submitters are allowed to download this list...
    if ($_AUTH['level'] >= LEVEL_SUBMITTER) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }
    lovd_requireAUTH(LEVEL_SUBMITTER);

    define('PAGE_TITLE', 'View gene statistics');
    $_T->printHeader();
    $_T->printTitle();
    $sViewListID = 'GeneStatistic';

    $aGeneSymbols = array();        // The array of gene symbols that a user enters into the form to search for within the database.
    $aCorrectGeneSymbols = array(); // The array of gene symbols that were found within the database after the search.
    $aBadGeneSymbols = array();     // The array of gene symbols that were not found within the database.
    $sBadGenesHTML = '';            // The HTML that is generated to display the gene symbols that were not found within the database.
    if (isset($_POST['geneSymbols'])) {
        // Handle lists separated by new lines, spaces, commas and semicolons.
        // Trim the whitespace, remove duplicates and remove empty array elements.
        $aGeneSymbols = array_filter(array_unique(array_map('trim', preg_split('/(\s|[,;])+/', $_POST['geneSymbols']))));

        // Check if there are any genes left after cleaning up the gene symbol string.
        if (count($aGeneSymbols) > 0) {
            // Load the genes and alternative names into an array.
            $aGenesInLOVD = $_DB->query('SELECT UPPER(id), id FROM ' . TABLE_GENES)->fetchAllCombine();
            // Loop through all the gene symbols in the array and check them for any errors.
            foreach ($aGeneSymbols as $key => $sGeneSymbol) {
                $sGeneSymbol = strtoupper($sGeneSymbol);
                // Check to see if this gene symbol has been found within the database.
                if (isset($aGenesInLOVD[$sGeneSymbol])) {
                    // A correct gene symbol was found, so lets use that to remove any case issues.
                    $aGeneSymbols[$key] = $aGenesInLOVD[$sGeneSymbol];
                    $aCorrectGeneSymbols[] = $aGenesInLOVD[$sGeneSymbol];
                } else {
                    // This gene symbol was not found in the database
                    $aBadGeneSymbols[] = $sGeneSymbol;
                }
            }
            // Create a table of any bad gene symbols and try to work out if there is a correct gene symbol available.
            if ($aBadGeneSymbols) {
                $sBadGenesHTML .= '    <H3>Genes not found!</H3>' . "\n" .
                                  '    These genes were not found, please review them and correct your gene list before proceeding' . "\n" .
                                  '    <TABLE  border="0" cellpadding="0" cellspacing="1" class="data">' . "\n" .
                                  '      <TR>' . "\n" .
                                  '        <TH>Gene Symbol</TH>' . "\n" .
                                  '        <TH>Found in Database</TH>' . "\n" .
                                  '        <TH>Found in HGNC</TH>' . "\n" .
                                  '      </TR>' . "\n";
                // Loop through the bad genes and check them.
                foreach ($aBadGeneSymbols as $sBadGeneSymbol) {
                    $sBadGenesHTML .= '      <TR class="data">' . "\n" .
                                      '        <TD>' . $sBadGeneSymbol . '</TD>' . "\n";

                    // Search within the database to see if this gene symbol is in the Alternative Names column.
                    $sFoundInDB = $_DB->query('SELECT id FROM ' . TABLE_GENE_STATISTICS . ' WHERE CONCAT(" ", alternative_names, ",") LIKE ?', array('% ' . $sBadGeneSymbol . ',%'))->fetchColumn();
                    if ($sFoundInDB) {
                        $sBadGenesHTML .= '        <TD>' . $sFoundInDB . '</TD>' . "\n";
                    } else {
                        $sBadGenesHTML .= '        <TD> - </TD>' . "\n";
                    }

                    // TODO Search the HGNC database to see if the correct gene name can be found.
                    // If you'd like to implement this, look into lovd_getGeneInfoFromHGNC().
                    $sBadGenesHTML .= '        <TD>To be completed...</TD>' . "\n";

                    $sBadGenesHTML .= '      </TR>' . "\n";
                }

                $sBadGenesHTML .= '    </TABLE><BR>' . "\n";
            }
        }

        // Mark the correct gene symbols as checked for this viewlist
        $_SESSION['viewlists'][$sViewListID]['checked'] = $aCorrectGeneSymbols;
    }

    ?>
    <SCRIPT type="text/javascript">
        // This function toggles the checked filter.
        function lovd_AJAX_viewListCheckedFilter(ViewListID, filterOption)
        {
            // If the hidden element does not yet exist, then create it.
            if ($('#filterChecked').length == 0) {
                $('#viewlistForm_' + ViewListID).prepend('<INPUT type="hidden" name="filterChecked" id="filterChecked" value="' + filterOption + '" />');
            }
            // Otherwise, set the checked filter preference.
            else {
                $('#filterChecked').val(filterOption);
            }

            if (filterOption) {
                $('#searchChecked').show();
                $('#searchInfo').hide();
            } else {
                $('#searchChecked').hide();
                $('#searchInfo').show();
            }
            // Set the page number to 1.
            document.forms['viewlistForm_' + ViewListID].page.value=1;
            // Refresh the viewlist so as it can apply the checked filter.
            lovd_AJAX_viewListSubmit(ViewListID);
        }

        $(document).ready(function() {
            // When loading this page check to see when to show or hide the gene entry form based on the contents of the form
            if ($('#geneSymbols').val()) {
                $('#genesForm').show();
                $('#geneFormShowHide').val('show');
            } else {
                $('#genesForm').hide();
                $('#geneFormShowHide').val('hide');
            }
            // Function to control how to show or hide the gene entry form
            $("#searchBoxTitle").click(function() {
                $("#genesForm").toggle('fast');
                if ($('#geneFormShowHide').val() == 'show') {
                    $('#geneFormShowHide').val('hide');
                } else {
                    $('#geneFormShowHide').val('show');
                }
            });
        });
    </SCRIPT>
<?php

    print('    <DIV id="searchBoxTitle" style="font-weight : bold; border : 1px solid #224488; cursor : pointer; text-align : center; padding : 2px 5px; font-size : 11px; width: 160px;">' . "\n" .
          '     Search for genes' . "\n" .
          '   </DIV>' . "\n" .
          '   <FORM id="genesForm" method="post" style="display: none;">' .
          '     Enter in you list of gene symbols separated by commas and press search to automatically select them. This will overwrite any previously selected genes. ' . "\n" .
          '     Select \'Show only selected genes\' from the menu to only show the genes entered below.<BR>' . "\n" .
          '     <INPUT type="hidden" id="geneFormShowHide" value="hide">' . "\n" .
          '     <TEXTAREA rows="5" cols="200" name="geneSymbols" id="geneSymbols">' . htmlentities(implode(', ', $aGeneSymbols)) . '</TEXTAREA><BR>' . "\n" .
          '     <INPUT type="submit" name="submitGenes" id="submitGenes" value=" Search ">' . "\n" .
          '   </FORM>' . "\n");
    // Show an info box if the gene lists are limited by the search.
    if (isset($aGeneSymbols) && count($aGeneSymbols) > 0) {
        print('    <DIV id="searchInfo">');
        lovd_showInfoTable('Genes from the search above have been selected in the list below. <A href="javascript:lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\', function(){lovd_AJAX_viewListCheckedFilter(\'' . $sViewListID . '\', true);});">Click here</A> to limit the list to only those genes.');
        print('    </DIV>' . "\n");
    }
    print('    <DIV id="searchChecked" style="display: none;">');
    lovd_showInfoTable('Currently only showing selected genes below. <A href="javascript:lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\', function(){lovd_AJAX_viewListCheckedFilter(\'' . $sViewListID . '\', false);});">Show all genes</A>.');
    print('    </DIV>' . "\n");

    // If genes were not found then display the error
    if ($aBadGeneSymbols) {
        lovd_showInfoTable($sBadGenesHTML,'stop', 760);
    }

    require ROOT_PATH . 'class/object_gene_statistics.php';
    $_DATA = new LOVD_GeneStatistic();
    // Redirect the link when clicking on genes to the genes info page.
    //$_DATA->setRowLink($sViewListID, ROOT_PATH . 'genes/' . $_DATA->sRowID);
    // Bold the row when clicked. Not sure if this is better or going to the gene info is better. It might get annoying going away from this page as you lose the work you have done.
    $_DATA->setRowLink($sViewListID, 'javascript:$(\'#{{id}}\').toggleClass(\'marked\');');
    // Allow users to download this gene statistics selected gene list.
    print('      <UL id="viewlistMenu_' . $sViewListID . '" class="jeegoocontext jeegooviewlist">' . "\n");
    print('        <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\', function(){lovd_AJAX_viewListCheckedFilter(\'' . $sViewListID . '\', true);});"><SPAN class="icon" style="background-image: url(gfx/check.png);"></SPAN>Show only selected genes</A></LI>' . "\n");
    print('        <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\', function(){lovd_AJAX_viewListCheckedFilter(\'' . $sViewListID . '\', false);});"><SPAN class="icon" style="background-image: url(gfx/cross_disabled.png);"></SPAN>Show all genes</A></LI>' . "\n");
    print('        <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\', function(){lovd_AJAX_viewListDownload(\'' . $sViewListID . '\', false);});"><SPAN class="icon" style="background-image: url(gfx/menu_save.png);"></SPAN>Download selected genes</A></LI>' . "\n");
    print('        <LI class="icon"><A href="' . CURRENT_PATH . '?import"><SPAN class="icon" style="background-image: url(gfx/menu_import.png);"></SPAN>Import gene statistics</A></LI>' . "\n");
    print('      </UL>' . "\n\n");
    $_DATA->viewList($sViewListID, array(), false, false, (bool) ($_AUTH['level'] >= LEVEL_SUBMITTER));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'import') {
    // URL: /gene_statistics?import
    // Import new gene statistics

    // FIXME: For later: This code is mostly duplicated from import.php.
    //  If we're somehow standardizing this feature, then integrate this into import.php.
    lovd_requireAUTH(LEVEL_MANAGER);

    require ROOT_PATH . 'inc-lib-form.php';

    // Calculate maximum uploadable file size.
    $nMaxSizeLOVD = 100*1024*1024; // 100MB LOVD limit.
    $nMaxSize = min(
        $nMaxSizeLOVD,
        lovd_convertIniValueToBytes(ini_get('upload_max_filesize')),
        lovd_convertIniValueToBytes(ini_get('post_max_size')));

    define('PAGE_TITLE', 'Import gene statistics');
    $_T->printHeader();
    $_T->printTitle();

    // Check if the file has been uploaded successfully
    if (POST || $_FILES) {
        // Form sent, first check the file itself.
        lovd_errorClean();

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
            // Find out the MIME-type of the uploaded file. Sometimes mime_content_type() seems to return False. Don't stop processing if that happens.
            // However, when it does report something different, mention what type was found so we can debug it.
            $sType = '';
            if (function_exists('mime_content_type')) {
                $sType = mime_content_type($_FILES['import']['tmp_name']);
            }
            if ($sType && substr($sType, 0, 5) != 'text/') { // Not all systems report the regular files as "text/plain"; also reported was "text/x-pascal; charset=us-ascii".
                lovd_errorAdd('import', 'The upload file is not a tab-delimited text file and cannot be imported. It seems to be of type "' . htmlspecialchars($sType) . '".');
            } else {
                // Read in the header of the file and validate this is the correct file format
                $aData = lovd_php_file($_FILES['import']['tmp_name']);

                if (!$aData) {
                    lovd_errorAdd('import', 'Cannot open file after it was received by the server.');
                } else {
                    $iGeneFileCount = count($aData);

                    // Check each of the headers to make sure that the columns appear within the database, create error msg of missing columns
                    $aFileColumnNames = explode("\t", $aData[0]);
                    $aTableColumnNames = lovd_getColumnList(TABLE_GENE_STATISTICS);
                    $aSQLColumns = array();
                    $aMissingColumns = array();
                    $aMissingColumnIDs = array();

                    if ($aFileColumnNames[0] != 'id') {
                        lovd_errorAdd('import', 'This does not look like a correct gene statistics file as the gene id column is not in the first position. Please check the file and try again.');
                    }

                    // Look through each of the column names in the file and check if the column exists within LOVD.
                    foreach ($aFileColumnNames as $i => $sFileColumnName) {
                        if (!in_array($sFileColumnName, $aTableColumnNames)) {
                            unset($aFileColumnNames[$i]);
                            $aMissingColumns[] = $sFileColumnName;
                            $aMissingColumnIDs[] = $i;
                        }
                    }
                    $aFileColumnNames[] = 'created_date';
                    $sSQLColumnNames = implode(', ', $aFileColumnNames);
                }
            }
        }
        // If no errors then truncate the table before inserting the new statistics data
        if (!lovd_error()) {
            require ROOT_PATH . 'class/progress_bar.php';
            // This already puts the progress bar on the screen.
            $_BAR = new ProgressBar('', 'Importing Gene Statistics Records');
            flush();

            $_DB->query('TRUNCATE TABLE ' . TABLE_GENE_STATISTICS);
            $pdoInsert = $_DB->prepare('INSERT IGNORE INTO ' . TABLE_GENE_STATISTICS . ' (' . $sSQLColumnNames . ') VALUES (?' . str_repeat(', ?', count($aFileColumnNames) - 1) . ')');

            $aMissingGenes = array();
            // Get all the current gene symbols in LOVD.
            $aGenesInLOVD = $_DB->query('SELECT UPPER(id), id FROM ' . TABLE_GENES)->fetchAllCombine();
            $sDateNow = date('Y-m-d H:i:s');
            // Loop through each of the gene symbols and check to see if they exist within LOVD, create an error log. Remove genes that are not within LOVD?
            foreach ($aData as $i => $sLine) {
                // Skip the first line with the headers in it.
                if ($i == 0) {
                    continue;
                }
                $sLine = trim($sLine);
                $aColumns = explode("\t", $sLine);

                $sFileGeneSymbol = $aColumns[0];
                // Check if the gene symbol exists within LOVD.
                if (!isset($aGenesInLOVD[strtoupper($sFileGeneSymbol)])) {
                    $aMissingGenes[] = $sFileGeneSymbol;
                } else {
                    foreach ($aMissingColumnIDs as $iMissingColumnID) {
                        unset($aColumns[$iMissingColumnID]);
                    }
                    $aColumns = array_values($aColumns);
                    $aColumns[] = $sDateNow;
                    $pdoInsert->execute($aColumns);
                }
                // Update the progress bar every 1000 records.
                $_BAR->setProgress(($i / $iGeneFileCount) * 100);
                if ($i % 1000 == 0) {
                    $_BAR->setMessage('Processing record ' . $i . ' of ' . $iGeneFileCount);
                }
            }

            $_BAR->setProgress(100);
            $_BAR->setMessage('Done!');
            $_BAR->setMessageVisibility('done', true);

            // Print the results of the import.
            if (count($aMissingColumns)) {
                lovd_showInfoTable('The following columns were ignored as they were not found in LOVD: ' . implode(', ', $aMissingColumns) . '.', 'warning');
            }
            if (count($aMissingGenes)) {
                lovd_showInfoTable('<b>' . count($aMissingGenes) . ' genes in the gene statistics file were not found within LOVD so they were not imported:</b><BR>' .
                    implode(', ', $aMissingGenes) . '.');
            }

            print('<BR><A href=' . CURRENT_PATH . '>View gene statistics.</A><BR><BR>');

            $_T->printFooter();
            exit;
        }
    }

    lovd_errorPrint();

    // Create the form to prompt for the gene statistics file.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post" enctype="multipart/form-data">' . "\n" .
        '        <INPUT type="hidden" name="MAX_FILE_SIZE" value="' . $nMaxSize . '">' . "\n");

    $aForm =
        array(
            array('POST', '', '', '', '40%', '14', '60%'),
            array('', '', 'print', '<B>File selection</B> (Gene statistics tab-delimited format only!)'),
            'hr',
            array('Select the file to import', '', 'file', 'import', 40),
            array('', 'Current file size limits:<BR>LOVD: ' . ($nMaxSizeLOVD/(1024*1024)) . 'M<BR>PHP (upload_max_filesize): ' . ini_get('upload_max_filesize') . '<BR>PHP (post_max_size): ' . ini_get('post_max_size'), 'note', 'The maximum file size accepted is ' . round($nMaxSize/pow(1024, 2), 1) . ' MB' . ($nMaxSize == $nMaxSizeLOVD? '' : ', due to restrictions on this server. If you wish to have it increased, contact the server\'s system administrator') . '.'),
            'hr',
            'skip',
            array('', '', 'submit', 'Import file'));

    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");
    $_T->printFooter();
    exit;
}





// Display a message if the gene statistics page has an invalid URL.
define('PAGE_TITLE', 'View gene statistics');
$_T->printHeader();
$_T->printTitle();
print ('Incorrect use of the gene statistics page, please <A href="' . $_PE[0] . '">click here</A> to view all the gene statistics.<BR><BR><BR><BR>');
$_T->printFooter();
exit;
?>
