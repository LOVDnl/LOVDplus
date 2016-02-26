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

    // TODO BUG 1. Select genes 2. Only show selected genes 3. Sorting is disabled even though there are only a few genes selected 4. Only show selected genes again 5. Sorting is now enabled 6. Show all genes 7. Sorting is still enabled even though too many results are returned
    // Objects.php, line 1052, I suspect since the setting and removing of the check filter does not change the search criteria it does not bother re counting the rows and as such uses the last record counts to determine if the sort should be enabled. Not sure then why it works if you activate the check filter twice...
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
    </script>
<?php
    require ROOT_PATH . 'class/object_gene_statistics.php';
    $_DATA = new LOVD_GeneStatistic();
    // Redirect the link when clicking on genes to the genes info page
    $_DATA->setRowLink($sViewListID, ROOT_PATH . 'genes/' . $_DATA->sRowID);
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





if (PATH_COUNT == 1 && ACTION == 'search') {
// URL: /gene_statistics?search
// Search on a long list of genes that can be pasted into the page.
    
    // TODO Create a popup lovd_openWindow() that will allow the users to paste in the long list of genes
    // TODO Validate the genes and alert if any of the genes are not within LOVD. Allow to proceed with only the found genes or allow the users to try and locate the correct genes
    // TODO If we continue then write the genes to the session variable $_SESSION['viewlists'][$sViewListID]['checked'] and refresh the viewlist so as those genes are now checked

    lovd_requireAUTH(LEVEL_SUBMITTER);
    define('PAGE_TITLE', 'Gene statistics - Batch gene upload');
    $_T->printHeader();
    $_T->printTitle();

    // This is an example of how you can set the genes to be already checked in the gene statistics viewlist. This will overwrite the existing checked genes so handle with care.
    // $_SESSION['viewlists'][$sViewListID]['checked'] = array('A1BG','A1CF','A2M-AS1','A2MP1','A4GNT');

    print('Paste in comma separated values to be used for selecting a large number of genes');

    $_T->printFooter();
    exit;
}





// Display a message if the gene statistics page has an invalid URL
define('PAGE_TITLE', 'View gene statistics');
$_T->printHeader();
$_T->printTitle();
print ('Incorrect use of the gene statistics page, please <a href="' . ROOT_PATH . $_PE[0] . '">click here</a> to view all the gene statistics.<br><br><br><br>');
$_T->printFooter();
exit;

?>
