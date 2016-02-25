<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-02-25
 * Modified    : 2016-02-25
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2014 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Msc. Daan Asscheman <D.Asscheman@LUMC.nl>
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

if (PATH_COUNT == 1 && !ACTION) {
    // URL: /gene_statistics
    // View all entries.

    // Managers are allowed to download this list...
    if ($_AUTH['level'] >= LEVEL_MANAGER) {
        define('FORMAT_ALLOW_TEXTPLAIN', true);
    }

    define('PAGE_TITLE', 'View gene statistics');
    $_T->printHeader();
    $_T->printTitle();

    require ROOT_PATH . 'class/object_gene_statistics.php';
    $_DATA = new LOVD_GeneStatistic();
    $_DATA->viewList('GeneStatistic', array(), false, false, (bool) ($_AUTH['level'] >= LEVEL_SUBMITTER));

    $_T->printFooter();
    exit;
} else {
    define('PAGE_TITLE', 'View gene statistics');
    $_T->printHeader();
    $_T->printTitle();
    print ('Incorrect use of the gene statistics page, please <a href="' . ROOT_PATH . 'gene_statistics">click here</a> to view all the gene statistics.<br><br><br><br>');
    $_T->printFooter();
    exit;
}

?>
