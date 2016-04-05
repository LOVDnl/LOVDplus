<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-04-03
 * Modified    : 2016-04-05
 * For LOVD    : 3.0-15
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
define('FORMAT_ALLOW_TEXTPLAIN', true);
$_GET['format'] = 'text/plain';
require ROOT_PATH . 'inc-init.php';

// But we don't care about your session (in fact, it locks the whole LOVD if we keep this page running).
session_write_close();





// First, get list of individuals that need a ZIS nr.
$aIndividuals = $_DB->query('SELECT id_miracle, id FROM ' . TABLE_INDIVIDUALS . ' WHERE id_zis IS NULL')->fetchAllCombine();

// Nothing to do? Bye...
if (!$aIndividuals) {
    exit;
}

// Loop through the files in the dir and try and find IDs... It's stupid, but we have to open them all...
$h = @opendir($_INI['paths']['alternative_ids']);
if (!$h) {
    die('Can\'t open directory.' . "\n");
}
while (($sFile = readdir($h)) !== false) {
    if ($sFile{0} == '.') {
        // Current dir, parent dir, and hidden files.
        continue;
    }
    // Try and open the file, check the first line if it conforms to the standard, and import.
    $aFile = @file($_INI['paths']['alternative_ids'] . '/' . $sFile, FILE_IGNORE_NEW_LINES);
    if ($sFile === false) {
        die('Error opening file: ' . $sFile . ".\n");
    }
    if (!isset($aFile[0]) || !preg_match('/^\d+\t\d+$/', $aFile[0])) {
        // Not a fatal error...
        print('Ignoring file, does not conform to format: ' . $sFile . ".\n");
        continue;
    }
    // Now that we passed this point, proves nothing. We're running into useless lines in correct files either way.
    // So we need to keep checking what we're getting.
    foreach ($aFile as $sLine) {
        if (!preg_match('/^\d+\t\d+$/', $sLine)) {
            // Wrong line in correct file. Silently ignore, preventing notices below.
            continue;
        }
        list($nMiracleID, $nZISID) = explode("\t", $sLine);
        if (isset($aIndividuals[$nMiracleID]) && ctype_digit($nZISID)) {
            // We know this one!
            if ($_DB->query('UPDATE ' . TABLE_INDIVIDUALS . ' SET id_zis = ? WHERE id = ? AND id_miracle = ? AND id_zis IS NULL', array($nZISID, $aIndividuals[$nMiracleID], $nMiracleID))->rowCount()) {
                print('Added ZIS ID for Miracle ID ' . $nMiracleID . ".\n");
                unset($aIndividuals[$nMiracleID]);
            }
        }
    }
}

// Report what we have left.
if (count($aIndividuals)) {
    print('The following individual(s) have no ZIS ID yet, and I can\'t locate one:' . "\n" .
        implode("\n", array_keys($aIndividuals)) . "\n");
}
?>
