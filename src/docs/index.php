<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-11-27
 * Modified    : 2021-08-16
 * For LOVD    : 3.0-27
 *
 * Copyright   : 2004-2021 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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

define('ROOT_PATH', '../');
define('TAB_SELECTED', 'docs');
require ROOT_PATH . 'inc-init.php';





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /docs
    // Provide link to PDF and HTML file.

    define('PAGE_TITLE', 'LOVD' . (LOVD_plus? '+' : ' 3.0') . ' documentation');
    $_T->printHeader();
    $_T->printTitle();

    if (LOVD_plus) {
        print('      The LOVD+ documentation is continuously being updated.<BR>Currently available is the LOVD+ user manual, in PDF format.<BR>' .
              '      <UL>' . "\n" .
              '        <LI>LOVD manual 3.0-29 (<A href="docs/LOVD+_manual.pdf" target="_blank"><B>PDF</B>, 22 pages, 1.2Mb</A>) - last updated October 16th, 2023</LI></UL>' . "\n\n");
    } else {
        print('      The LOVD 3.0 documentation is continuously being updated.<BR>Currently available is the LOVD 3.0 user manual, in PDF and HTML formats.<BR>' .
              '      <UL>' . "\n" .
              '        <LI>LOVD manual 3.0-27 (<A href="docs/LOVD_manual_3.0.pdf" target="_blank"><B>PDF</B>, 88 pages, 1.5Mb</A>) (<A href="docs/manual.html" target="_blank"><B>HTML</B>, single file, 4.9Mb</A>) - last updated June 15th 2021</LI></UL>' . "\n\n");
    }

    $_T->printFooter();
    exit;
}
?>
