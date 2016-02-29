<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-02-25
 * Modified    : 2016-02-25
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
define('TAB_SELECTED', 'genes');
$sViewListID = 'GeneStatistic';
$bBadGenes = false;

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

    $sGeneSymbols = '';
    if (isset($_POST['geneSymbols'])) {
        $sGeneSymbols = $_POST['geneSymbols'];
        // Explode the gene symbol string into an array, trim the whitespace, remove duplicates and remove empty array elements
        // TODO Can we also handle new line separated lists somehow? Replace newlines with commas? The cleaning up of the array should remove any issues this creates.
        $aGeneSymbols = array_filter(array_unique(array_map('trim',explode(",",$sGeneSymbols))));
        $aCorrectGeneSymbols = array();
        $aBadGeneSymbols = array();
        $sBadGenesHTML = '';


        // Check if there are any genes left after cleaning up the gene symbol string
        if (count($aGeneSymbols) > 0) {
            // Loop through all the gene symbols in the array and check them for any errors
            foreach ($aGeneSymbols as $key => $sGeneSymbol) {
                // Check to see if this gene symbol has been found within the database
                //$sSQL = 'SELECT id FROM ' . TABLE_GENE_STATISTICS . ' WHERE id = ?';
                $sSQL = 'SELECT id FROM ' . TABLE_GENE_STATISTICS . ' WHERE id = ?';
                $sCorrectGeneSymbol = $_DB->query($sSQL, array($sGeneSymbol))->fetchColumn();

                if ($sCorrectGeneSymbol) {
                    // A correct gene symbol was found so lets use that to remove any case issues
                    $aGeneSymbols[$key] = $sCorrectGeneSymbol;
                    $sGeneSymbol = $sCorrectGeneSymbol;
                    $aCorrectGeneSymbols[] = $sCorrectGeneSymbol;
                } else {
                    // This gene symbol was not found in the database
                    $aBadGeneSymbols[] = $sGeneSymbol;
                    $bBadGenes = true;
                }
            }
            // Create a table of any bad gene symbols and try to work out if there is a correct gene symbol available
            if ($bBadGenes) {
                $sBadGenesHTML .= '<h3>Genes not found!</h3>These genes were not found, please review them and correct your gene list before proceeding.<table  border="0" cellpadding="0" cellspacing="1" class="data">';
                $sBadGenesHTML .= '<thead><tr><th>Gene Symbol</th><th>Found in Database</th><th>Found in HGNC</th></tr></thead><tbody>';
                // Loop through the bad genes and check them
                foreach ($aBadGeneSymbols as $sBadGeneSymbol) {
                    $sBadGenesHTML .= '<tr class="data"><td>' . $sBadGeneSymbol . '</td>';

                    // Search within the database to see if this gene symbol is in the Alternative Names column
                    $sSQL = 'SELECT id FROM ' . TABLE_GENE_STATISTICS . ' WHERE alternative_names REGEXP \'[[:<:]]' . $sBadGeneSymbol . '[[:>:]]\'';
                    $sFoundInDB = $_DB->query($sSQL)->fetchColumn();
                    if ($sFoundInDB) {
                        $sBadGenesHTML .= '<td>' . $sFoundInDB . '</td>';
                    } else {
                        $sBadGenesHTML .= '<td> - </td>';
                    }

                    // TODO Search the HGNC database to see if the correct gene name can be found
                    $sBadGenesHTML .= '<td>To be completed...</td>';

                    $sBadGenesHTML .= '</tr>';
                }

                $sBadGenesHTML .= '</tbody></table><br>';


            }





        }
//        var_dump($aGeneSymbols); // TODO REMOVE IN PRODUCTION
//        var_dump($aBadGeneSymbols);


        // Write back the cleaned up gene symbol list to the form to be displayed to the user
        $sGeneSymbols = implode(', ', $aGeneSymbols);

        // Mark the correct gene symbols as checked for this viewlist
        $_SESSION['viewlists'][$sViewListID]['checked'] = $aCorrectGeneSymbols;

    }

    // TODO Setup an alert to show when you are only viewing checked genes otherwise it may cause some confusion about why genes are not being shown. Turn off the checked filter using this alert?
    // TODO Provide some screen notification to say you have submitted and selected the genes in the comma separated gene list and explain how to only show those genes.

    // TODO BUG 1. Select genes 2. Only show selected genes 3. Sorting is disabled even though there are only a few genes selected 4. Only show selected genes again 5. Sorting is now enabled 6. Show all genes 7. Sorting is still enabled even though too many results are returned
    // TODO BUG Continued Objects.php, line 1052, I suspect since the setting and removing of the check filter does not change the search criteria it does not bother re counting the rows and as such uses the last record counts to determine if the sort should be enabled. Not sure then why it works if you activate the check filter twice...
    ?>
    <script type="text/javascript">
        // This function toggles the checked filter
        function lovd_AJAX_viewListCheckedFilter(filterOption)    {
            // If the hidden element does not yet exist then create it
            if($('#filterChecked').length == 0) {
                $('#viewlistForm_<?php print $sViewListID;?>').prepend('<input type="hidden" name="filterChecked" id="filterChecked" value="' + filterOption + '" />');
            }
            // Otherwise set the checked filter preference
            else {
                $('#filterChecked').val(filterOption);
            }
            // If the page number has been set then set it back to page 1
            if (document.forms['viewlistForm_<?php print $sViewListID;?>'].page) {
                document.forms['viewlistForm_<?php print $sViewListID;?>'].page.value=1;
            }
            // Refresh the viewlist so as it can apply the checked filter
            setTimeout('lovd_AJAX_viewListSubmit(\'<?php print $sViewListID;?>\')', 0);
        }

        $(document).ready(function() {
            // When loading this page check to see when to show or hide the gene entry form based on the contents of the form
        <?php if (!empty($sGeneSymbols)) { ?>
            $('#genesForm').show();
            $('#geneFormShowHide').val('show');
            $('#searchBoxTitle').html('<b>Search for genes:</b>');
        <?php } else { ?>
            $('#genesForm').hide();
            $('#geneFormShowHide').val('hide');
            $('#searchBoxTitle').html('Show gene search box');
        <?php } ?>
            // Function to control how to show or hide the gene entry form
            $("#searchBoxTitle").click(function(){
                $("#genesForm").toggle('fast');
                if ($('#geneFormShowHide').val() == 'show') {
                    $('#geneFormShowHide').val('hide');
                    $('#searchBoxTitle').html('Show gene search box');
                } else {
                    $('#geneFormShowHide').val('show');
                    $('#searchBoxTitle').html('Search for genes:');
                }
            });
        });
    </script>
<?php

print('<div id="searchBoxTitle" style="cursor: pointer;text-decoration: underline;font-size : 11px;font-weight: bold"></div>');
print('<form id="genesForm" method="post" style="display: none;">Enter in you list of gene symbols separated by commas and press search to automatically select them. Select \'Show only checked genes\' from the menu to hide unselected genes.<BR><input type="hidden" id="geneFormShowHide" value="hide"><textarea rows="5" cols="200" name="geneSymbols" id="geneSymbols">' . htmlentities($sGeneSymbols) . '</textarea><BR><input type="submit" name="submitGenes" id="submitGenes" value=" Search "></form>');

// If genes were not found then display the error
if ($bBadGenes) {
    lovd_showInfoTable($sBadGenesHTML,'stop', 760);
}

require ROOT_PATH . 'class/object_gene_statistics.php';
$_DATA = new LOVD_GeneStatistic();
// Redirect the link when clicking on genes to the genes info page
//$_DATA->setRowLink($sViewListID, ROOT_PATH . 'genes/' . $_DATA->sRowID);
// Bold the row when clicked. Not sure if this is better or going to the gene info is better. It might get annoying going away from this page as you lose the work you have done.
$_DATA->setRowLink($sViewListID, 'javascript:$(\'#{{id}}\').toggleClass(\'marked\');');
// Allow users to download this gene statistics selected gene list
print('      <UL id="viewlistMenu_' . $sViewListID . '" class="jeegoocontext jeegooviewlist">' . "\n");
print('        <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\', function(){lovd_AJAX_viewListCheckedFilter(true);});"><SPAN class="icon" style="background-image: url(gfx/check.png);"></SPAN>Show only checked genes</A></LI>' . "\n");
print('        <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\', function(){lovd_AJAX_viewListCheckedFilter(false);});"><SPAN class="icon" style="background-image: url(gfx/cross_disabled.png);"></SPAN>Show all genes</A></LI>' . "\n");
print('        <LI class="icon"><A click="lovd_AJAX_viewListSubmit(\'' . $sViewListID . '\', function(){lovd_AJAX_viewListDownload(\'' . $sViewListID . '\', false);});"><SPAN class="icon" style="background-image: url(gfx/menu_save.png);"></SPAN>Download selected genes</A></LI>' . "\n");
print('      </UL>' . "\n\n");
$_DATA->viewList($sViewListID, array(), false, false, (bool) ($_AUTH['level'] >= LEVEL_SUBMITTER));

$_T->printFooter();
exit;
}





// Display a message if the gene statistics page has an invalid URL
define('PAGE_TITLE', 'View gene statistics');
$_T->printHeader();
$_T->printTitle();
print ('Incorrect use of the gene statistics page, please <a href="' . $_PE[0] . '">click here</a> to view all the gene statistics.<br><br><br><br>');
$_T->printFooter();
exit;

?>
