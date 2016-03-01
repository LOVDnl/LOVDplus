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
    // URL: /genes_lists/1
    // View specific entry.

    $sID = rawurldecode($_PE[1]);
    define('PAGE_TITLE', 'View gene list #' . $sID);
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_gene_lists.php';
    $_DATA = new LOVD_GeneList();
    $zData = $_DATA->viewEntry($sID);

    $aNavigation = array();
    if ($_AUTH && $_AUTH['level'] >= LEVEL_CURATOR) {
        // Authorized user is logged in. Provide tools.
        $aNavigation[CURRENT_PATH . '?edit']             = array('menu_edit.png', 'Edit gene list information', 1);
        $aNavigation['transcripts/' . $sID . '?create']  = array('menu_plus.png', 'Add gene(s) to gene list', 1);
        if ($_AUTH['level'] >= LEVEL_MANAGER) {
            $aNavigation[CURRENT_PATH . '?delete']       = array('cross.png', 'Delete gene entry', 1);
        }
    }
    lovd_showJGNavigation($aNavigation, 'Genes');

    $_T->printFooter();
    exit;
}

print('No condition met using the provided URL.');

?>
