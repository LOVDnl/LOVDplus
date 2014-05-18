<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-01-31
 * Modified    : 2014-03-07
 * For LOVD    : 3.0-10
 *
 * Copyright   : 2004-2014 Leiden University Medical Center; http://www.LUMC.nl/
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

define('ROOT_PATH', './');
require ROOT_PATH . 'inc-init.php';

if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





if (PATH_COUNT == 1 && !ACTION) {
    // URL: /analyses
    // View all entries.
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /analyses/001
    // View specific entry.
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create') {
    // URL: /analyses?create
    // Create a new entry.
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /analyses/001?edit
    // Edit an entry.
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'modify') {
    // URL: /analyses/001?modify
    // Mentioned in the code, but should be replaced by a link to the runID, or perhaps just pick the filters defined in the analysis?
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /analyses/001?delete
    // Drop specific entry.
    exit;
}





if (PATH_COUNT == 2 && $_PE[1] == 'run' && !ACTION) {
    // URL: /analyses/run
    // View all entries.
    exit;
}





if (PATH_COUNT == 3 && $_PE[1] == 'run' && ctype_digit($_PE[2]) && !ACTION) {
    // URL: /analyses/run/00001
    // View specific entry.
    exit;
}





if (PATH_COUNT == 3 && $_PE[1] == 'run' && ctype_digit($_PE[2]) && ACTION == 'modify') {
    // URL: /analyses/run/00001?modify
    // Modify a run analysis.

    $nID = sprintf('%05d', $_PE[2]);
    define('PAGE_TITLE', 'Modify analysis run #' . $nID);
    define('LOG_EVENT', 'AnalysisRunModify');

    // Require form functions.
    require ROOT_PATH . 'inc-lib-form.php';

    // Load appropriate user level for this analysis run.
    lovd_isAuthorized('analysisrun', $nID);
    lovd_requireAUTH(LEVEL_CURATOR);

    $zData = $_DB->query('SELECT ar.*, GROUP_CONCAT(filterid SEPARATOR ";") AS _filters FROM ' . TABLE_ANALYSES_RUN . ' AS ar INNER JOIN ' . TABLE_ANALYSES_RUN_FILTERS . ' AS arf ON (ar.id = arf.runid) WHERE id = ?', array($nID))->fetchAssoc();
    if (!$zData) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such ID!', 'stop');
        $_T->printFooter();
        exit;
    }
    $zData['filters'] = explode(';', $zData['_filters']);
    $nFilters = count($zData['filters']);

    if (count($zData['filters']) <= 1) {
        // Column has already been removed from everything it can be removed from.
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('This analysis run already has no filters left to remove.', 'stop');
        $_T->printFooter();
        exit;
    }

    if (POST) {
        // Should have selected at least one filter.
        if (empty($_POST['remove_filters'])) {
            lovd_errorAdd('target', 'Please select at least one filter to remove from this analysis run.');
        } elseif (count($_POST['remove_filters']) >= $nFilters) {
            // But can't select them all.
            lovd_errorAdd('target', 'You cannot remove all filters from this analysis run.');
        }

        if (!lovd_error()) {
            $_T->printHeader();
            $_T->printTitle();

            $_DB->beginTransaction();
            // First, copy the analysis run.
            $_DB->query('INSERT INTO ' . TABLE_ANALYSES_RUN . ' (analysisid, screeningid, modified, created_by, created_date) VALUES (?, ?, 1, ?, NOW())', array($zData['analysisid'], $zData['screeningid'], $_AUTH['id']));
            $nNewRunID = $_DB->lastInsertId();
            // Now insert filters.
            foreach ($zData['filters'] as $nOrder => $sFilter) {
                if (in_array($sFilter, $_POST['remove_filters'])) {
                    continue; // Column selected to be removed.
                }
                $_DB->query('INSERT INTO ' . TABLE_ANALYSES_RUN_FILTERS . ' (runid, filterid, filter_order) VALUES (?, ?, ?)', array($nNewRunID, $sFilter, $nOrder));
            }

            // If we get here, it all succeeded.
            $_DB->commit();

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created run ' . str_pad($nNewRunID, 5, '0', STR_PAD_LEFT) . ' based on ' . $nID . ' with filter(s) \'' . implode('\', \'', $_POST['remove_filters']) . '\' removed');

            // Thank the user...
            lovd_showInfoTable('Successfully created a new analysis run!', 'success');

            if (isset($_GET['in_window'])) {
                // We're in a new window, refresh opener en close window.
                print('      <SCRIPT type="text/javascript">setTimeout(\'opener.location.reload();self.close();\', 1000);</SCRIPT>' . "\n\n");
            } else {
                print('      <SCRIPT type="text/javascript">setTimeout(\'window.location.href=\\\'' . lovd_getInstallURL() . 'screenings/' . $zData['screeningid'] . '\\\';\', 1000);</SCRIPT>' . "\n\n");
            }

            $_T->printFooter();
            exit;
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . (isset($_GET['in_window'])? '&amp;in_window' : '') . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
        array('POST', '', '', '', '40%', 14, '60%')
    );

    print('      Please select the filters that you would like to <B>remove</B> from the analysis.<BR><BR>' . "\n");

    $nOptions = ($nFilters > 15? 15 : $nFilters);
    $aForm[] = array('Remove the following filters', '', 'select', 'remove_filters', $nOptions, array_combine(array_values($zData['filters']), $zData['filters']), false, true, false);
    $aForm[] = 'skip';

    $aForm = array_merge($aForm,
             array(
                    array('', '', 'submit', 'Remove filters'),
                  )
                       );
    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 3 && $_PE[1] == 'run' && ctype_digit($_PE[2]) && ACTION == 'delete') {
    // URL: /analyses/run/00001?delete
    // Drop specific entry.

    $nID = sprintf('%05d', $_PE[2]);
    define('PAGE_TITLE', 'Delete analysis run ' . $nID);
    define('LOG_EVENT', 'AnalysisRunDelete');

    // Load appropriate user level for this analysis run.
    lovd_isAuthorized('analysisrun', $nID);
    lovd_requireAUTH(LEVEL_CURATOR);

    // ADMIN can always delete an analysis run, even when the individual's analysis hasn't been started by him.
    // FIXME: Shouldn't the owner of the individual's analysis be able to delete the run, when it's created by somebody else?
    $sSQL = 'SELECT ar.* FROM ' . TABLE_ANALYSES_RUN . ' AS ar WHERE ar.id = ?' . ($_AUTH['level'] == LEVEL_ADMIN? '' : ' AND ar.created_by = ?');
    $aSQL = array($nID);
    if ($_AUTH['level'] < LEVEL_ADMIN) {
        $aSQL[] = $_AUTH['id'];
    }
    $zData = $_DB->query($sSQL, $aSQL)->fetchAssoc();
    if (!$zData) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('No such ID or not allowed!', 'stop');
        $_T->printFooter();
        exit;
    }

    // This also deletes the entries in TABLE_ANALYSES_RUN_FILTERS && TABLE_ANALYSES_RUN_RESULTS.
    $_DB->query('DELETE FROM ' . TABLE_ANALYSES_RUN . ' WHERE id = ?', array($nID));

    // Write to log...
    lovd_writeLog('Event', LOG_EVENT, 'Deleted analysis run ' . $nID);

    $_T->printHeader();
    $_T->printTitle();

    // Thank the user...
    lovd_showInfoTable('Successfully deleted the analysis run!', 'success');

    if (isset($_GET['in_window'])) {
        // We're in a new window, refresh opener en close window.
        print('      <SCRIPT type="text/javascript">setTimeout(\'opener.location.reload();self.close();\', 1000);</SCRIPT>' . "\n\n");
    } else {
        print('      <SCRIPT type="text/javascript">setTimeout(\'window.location.href=\\\'' . lovd_getInstallURL() . 'individuals\\\';\', 1000);</SCRIPT>' . "\n\n");
    }

    $_T->printFooter();
    exit;
}
?>
