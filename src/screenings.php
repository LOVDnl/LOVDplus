<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-03-18
 * Modified    : 2014-03-03
 * For LOVD    : 3.0-10
 *
 * Copyright   : 2004-2014 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && in_array(ACTION, array('downloadToBeConfirmed', 'exportToBeConfirmed'))) {
    // URL: /screenings/0000000001?downloadToBeConfirmed
    // URL: /screenings/0000000001?exportToBeConfirmed
    // Download, or export, the variants to be confirmed in the format for the pipeline.

    $nID = sprintf('%010d', $_PE[1]);
    define('PAGE_TITLE', 'Download variants to be confirmed for screening #' . $nID);

    // Load appropiate user level for this screening entry.
    $bAuthorized = lovd_isAuthorized('screening_analysis', $nID);

    // Load status as well, since that's also important.
    $zData = $_DB->query('SELECT * FROM ' . TABLE_SCREENINGS . ' WHERE id = ?', array($nID))->fetchAssoc();

    // Export depends on user level (Owner or Manager) and status.
    if (ACTION == 'exportToBeConfirmed') {
        if (!$bAuthorized) {
            // Either returned false or 0. Both are bad in this case.
            die('9|No authorization on this screening.');
        } elseif (!($_AUTH['level'] >= LEVEL_OWNER && $zData['analysis_statusid'] < ANALYSIS_STATUS_CLOSED) &&
                  !($_AUTH['level'] >= LEVEL_MANAGER && $zData['analysis_statusid'] < ANALYSIS_STATUS_WAIT_CONFIRMATION)) {
            die('9|Unable to export variants, the analysis status requires a higher user level.');
        }
    }
    lovd_requireAUTH(LEVEL_OWNER);

    // First, let's see if there is something that we need to confirm.
    $aVariants = $_DB->query('SELECT "' . $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_name'] . '" AS refseq_build, vog.chromosome, "genomic_id_ncbi", vog.position_g_start, vog.position_g_end, vog.`VariantOnGenome/DNA`, vog.`VariantOnGenome/Sequencing/Father/VarPresent` AS is_present_father, vog.`VariantOnGenome/Sequencing/Mother/VarPresent` AS is_present_mother, g.id AS gene_id, g.name AS gene_name, t.id_ncbi AS transcript_id_ncbi, vot.`VariantOnTranscript/DNA`, vot.`VariantOnTranscript/RNA`, vot.`VariantOnTranscript/Protein`, vog.allele, "VariantOnGenome/Genetic_origin", MAX(IFNULL((i2d.diseaseid = g2d.diseaseid), 0)) AS in_gene_panel
                              FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d USING (individualid) LEFT OUTER JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (vog.id = vot.id) LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' t ON (vot.transcriptid = t.id) LEFT OUTER JOIN ' . TABLE_GENES . ' AS g ON (t.geneid = g.id) LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (g.id = g2d.geneid)
                              WHERE vog.to_be_confirmed = 1 AND s2v.screeningid = ?
                              GROUP BY vog.chromosome, vog.`VariantOnGenome/DNA`, g.id', array($nID))->fetchAllAssoc();
    if (!$aVariants) {
        if (ACTION == 'downloadToBeConfirmed') {
            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('No variants were marked to be confirmed for this screening.', 'stop');
            $_T->printFooter();
        } else {
            die('0|No variants to export.');
        }
        exit;
    }

    // Fetch Miracle ID, we need that for matching the variants with the individual.
    $nMiracleID = $_DB->query('SELECT id_miracle FROM ' . TABLE_INDIVIDUALS . ' AS i INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) WHERE s.id = ?', array($nID))->fetchColumn();

    // Load the gene panel (disease(s)), for the header.
    // NOTE: We could fetch this earlier, and at the same time change the variant query to not join to the IND2DIS table, but oh, well.
    $aDiseases = $_DB->query('SELECT d.id, d.symbol, d.edited_date, COUNT(g2d.geneid) AS genes FROM ' . TABLE_DISEASES . ' AS d INNER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (d.id = i2d.diseaseid) INNER JOIN ' . TABLE_SCREENINGS . ' AS s USING (individualid) LEFT OUTER JOIN ' . TABLE_GEN2DIS . ' AS g2d ON (d.id = g2d.diseaseid) WHERE s.id = ? GROUP BY d.id HAVING genes > 0', array($nID))->fetchAllAssoc();

    $sPath = rtrim($_INI['paths']['confirm_variants'], '/') . '/';
    $sFile = 'LOVD_VariantsToBeConfirmed_' . $nMiracleID . '_' . date('Y-m-d_H.i.s') . '.txt';
    header('Content-type: text/plain; charset=UTF-8');
    if (ACTION == 'downloadToBeConfirmed') {
        header('Content-Disposition: attachment; filename="' . $sFile . '"');
        header('Pragma: public');
    } else {
        // Collect the file's contents, so we can write it to disk.
        ob_start();
    }
    print('# id_miracle = ' . $nMiracleID . "\r\n");
    foreach ($aDiseases as $aDisease) {
        print('# active_gene_panel = (' . $aDisease['id'] . ', ' . $aDisease['symbol'] . ', ' . $aDisease['edited_date'] . ', ' . $aDisease['genes'] . ' genes)' . "\r\n");
    }
    print('"{{' . implode('}}"' . "\t" . '"{{', array_keys($aVariants[0])) . '}}"' . "\r\n");

    foreach ($aVariants as $aVariant) {
        $aVariant['genomic_id_ncbi'] = $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']];
        switch($aVariant['allele']) {
            case '3':
                $aVariant['allele'] = 'Homozygous';
                break;
            case '10':
            case '11':
                $aVariant['allele'] = 'Paternal';
                break;
            case '20':
            case '21':
                $aVariant['allele'] = 'Maternal';
                break;
            default:
                $aVariant['allele'] = 'Heterozygous';
        }
        if (in_array($aVariant['is_present_father'], array(1, 2)) && in_array($aVariant['is_present_mother'], array(1, 2))) {
            $aVariant['VariantOnGenome/Genetic_origin'] = 'De novo';
        } elseif ($aVariant['is_present_father'] >= 5 || $aVariant['is_present_mother'] >= 5) {
            $aVariant['VariantOnGenome/Genetic_origin'] = 'Germline (inherited)';
        } else {
            $aVariant['VariantOnGenome/Genetic_origin'] = 'Unknown';
        }
        print('"' . implode('"' . "\t" . '"', $aVariant) . "\"\r\n");
    }

    if (ACTION == 'exportToBeConfirmed') {
        $sFileContents = ob_get_contents();
        ob_end_clean();

        if ($sFileContents) {
            $f = @fopen($sPath . $sFile, 'w');
            if ($f) {
                fputs($f, $sFileContents);
                fclose($f);
                die('1|' . count($aVariants));
            }

            die('0|Could not create file ' . $sPath . $sFile);
        } else {
            die('0|No output generated.');
        }
    }

    exit;
}










if ($_AUTH) {
    // If authorized, check for updates.
    require ROOT_PATH . 'inc-upgrade.php';
}





if ((PATH_COUNT == 1 || (!empty($_PE[1]) && !ctype_digit($_PE[1]))) && !ACTION) {
    // URL: /screenings
    // URL: /screenings/DMD
    // View all entries.

    if (!empty($_PE[1])) {
        $sGene = $_DB->query('SELECT id FROM ' . TABLE_GENES . ' WHERE id = ?', array(rawurldecode($_PE[1])))->fetchColumn();
        if ($sGene) {
            // We need the authorization call if we would show the screenings with VARIANTS in gene X, not before!
//            lovd_isAuthorized('gene', $sGene); // To show non public entries.

            // FIXME; This doesn't work; searching for gene X also finds XYZ.
            $_GET['search_genes'] = $sGene;
        } else {
            // Command or gene not understood.
            // FIXME; perhaps a HTTP/1.0 501 Not Implemented? If so, provide proper output (gene not found) and
            //   test if browsers show that output or their own error page. Also, then, use the same method at
            //   the bottom of all files, as a last resort if command/URL is not understood. Do all of this LATER.
            exit;
        }
    }

    define('PAGE_TITLE', 'View screenings' . (isset($sGene)? ' that screened gene ' . $sGene : ''));
    $_T->printHeader();
    $_T->printTitle();

    $aColsToHide = array();
    if (isset($sGene)) {
        $aColsToHide[] = 'genes';
    }

    require ROOT_PATH . 'class/object_screenings.php';
    $_DATA = new LOVD_Screening();
    $_DATA->viewList('Screenings', $aColsToHide, false, false, (bool) ($_AUTH['level'] >= LEVEL_MANAGER));

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && !ACTION) {
    // URL: /screenings/0000000001
    // View specific entry.

    $nID = sprintf('%010d', $_PE[1]);
    define('PAGE_TITLE', 'View screening #' . $nID);
    $_T->printHeader();
    $_T->printTitle();

    // Load appropiate user level for this screening entry.
    lovd_isAuthorized('screening', $nID);

    require ROOT_PATH . 'class/object_screenings.php';
    $_DATA = new LOVD_Screening($nID);
    $zData = $_DATA->viewEntry($nID);

    $aNavigation = array();
    if ($_AUTH && $_AUTH['level'] >= LEVEL_OWNER) {
        $aNavigation[CURRENT_PATH . '?edit']                   = array('menu_edit.png', 'Edit screening information', 1);
        if ($zData['variants_found']) {
            $aNavigation['variants?create&amp;target=' . $nID] = array('menu_plus.png', 'Add variant to screening', 1);
            $aNavigation[CURRENT_PATH . '?removeVariants']     = array('cross.png', 'Remove variants from screening', ($zData['variants_found_'] > 0? 1 : 0));
        }
        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            $aNavigation[CURRENT_PATH . '?delete']             = array('cross.png', 'Delete screening entry', 1);
        }
    }
    lovd_showJGNavigation($aNavigation, 'Screenings');

    if (!empty($zData['search_geneid'])) {
        $_GET['search_geneid'] = html_entity_decode(rawurldecode($zData['search_geneid']));
        print('<BR><BR>' . "\n\n");
        $_T->printTitle('Genes screened', 'H4');
        require ROOT_PATH . 'class/object_genes.php';
        $_DATA = new LOVD_Gene();
        $_DATA->setSortDefault('id');
        $_DATA->viewList('Genes_for_S_VE', array(), true, true);
        unset($_GET['search_geneid']);
    }

    if ($zData['variants_found'] || !empty($zData['variants'])) {
        $_GET['search_screeningid'] = $nID;
        print('<BR><BR>' . "\n\n");
        $_T->printTitle('Variants found', 'H4');
        require ROOT_PATH . 'class/object_custom_viewlists.php';
        // VOG needs to be first, so it groups by the VOG ID.
        $_DATA = new LOVD_CustomViewList(array('VariantOnGenome', 'Scr2Var', 'VariantOnTranscript'));
        $_DATA->viewList('CustomVL_VOT_for_S_VE', array('transcriptid'), false, false, (bool) ($_AUTH['level'] >= LEVEL_MANAGER));
    }

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 1 && ACTION == 'create' && isset($_GET['target']) && ctype_digit($_GET['target'])) {
    // URL: /screenings?create
    // Create a new entry.

    define('LOG_EVENT', 'ScreeningCreate');

    lovd_requireAUTH($_SETT['user_level_settings']['submit_new_data']);

    $_GET['target'] = sprintf('%08d', $_GET['target']);
    $z = $_DB->query('SELECT id FROM ' . TABLE_INDIVIDUALS . ' WHERE id = ?', array($_GET['target']))->fetchAssoc();
    if (!$z) {
        define('PAGE_TITLE', 'Create a new screening entry');
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable('The individual ID given is not valid, please go to the desired individual entry and click on the "Add screening" button.', 'stop');
        $_T->printFooter();
        exit;
    } elseif (!lovd_isAuthorized('individual', $_GET['target'])) {
        lovd_requireAUTH(LEVEL_OWNER);
    }
    $_POST['individualid'] = $_GET['target'];
    define('PAGE_TITLE', 'Create a new screening information entry for individual #' . $_GET['target']);

    lovd_isAuthorized('gene', $_AUTH['curates']);

    require ROOT_PATH . 'class/object_screenings.php';
    $_DATA = new LOVD_Screening();
    require ROOT_PATH . 'inc-lib-form.php';

    $bSubmit = (isset($_AUTH['saved_work']['submissions']['individual'][$_POST['individualid']]));

    if (POST) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('individualid', 'variants_found', 'owned_by', 'created_by', 'created_date'),
                            $_DATA->buildFields());

            // Prepare values.
            $_POST['owned_by'] = ($_AUTH['level'] >= LEVEL_CURATOR? $_POST['owned_by'] : $_AUTH['id']);
            $_POST['created_by'] = $_AUTH['id'];
            $_POST['created_date'] = date('Y-m-d H:i:s');

            $nID = $_DATA->insertEntry($_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Created screening information entry ' . $nID);

            $aSuccessGenes = array();
            if (!empty($_POST['genes']) && is_array($_POST['genes'])) {
                foreach ($_POST['genes'] as $sGene) {
                    // Add disease to gene.
                    if (in_array($sGene, lovd_getGeneList())) {
                        $q = $_DB->query('INSERT INTO ' . TABLE_SCR2GENE . ' VALUES (?, ?)', array($nID, $sGene), false);
                        // FIXME; I think this is not possible without a query error, that by default halts the system. Maybe you want to set $_DB->query()'s third argument to false?
                        if (!$q->rowCount()) {
                            // Silent error.
                            // FIXME; maybe better to group the error messages, just like when editing?
                            lovd_writeLog('Error', LOG_EVENT, 'Gene entry ' . $sGene . ' - could not be added to screening ' . $nID);
                        } else {
                            $aSuccessGenes[] = $sGene;
                        }
                    }
                }
            }

            if (count($aSuccessGenes)) {
                lovd_writeLog('Event', LOG_EVENT, 'Gene entries successfully added to screening ' . $nID);
            }

            if ($bSubmit) {
                if (!isset($_AUTH['saved_work']['submissions']['individual'][$_POST['individualid']]['screenings'])) {
                    $_AUTH['saved_work']['submissions']['individual'][$_POST['individualid']]['screenings'] = array();
                }
                $_AUTH['saved_work']['submissions']['individual'][$_POST['individualid']]['screenings'][] = $nID;

            } else {
                if (!isset($_AUTH['saved_work']['submissions']['screening'])) {
                    $_AUTH['saved_work']['submissions']['screening'] = array();
                }
                $_AUTH['saved_work']['submissions']['screening'][$nID] = array();
            }

            lovd_saveWork();

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'submit/screening/' . $nID);

            $_T->printHeader();
            $_T->printTitle();

            lovd_showInfoTable('Successfully created the screening entry!', 'success');

            $_T->printFooter();
            exit;
        }

    } else {
        // Default values.
        $_DATA->setDefaultValues();
    }



    $_T->printHeader();
    $_T->printTitle();

    if (GET) {
        print('      To create a new screening information entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '&amp;target=' . $_GET['target'] . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'print', '<INPUT type="submit" value="Create screening information entry">' . ($bSubmit? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . 'submit/individual/' . $_POST['individualid'] . '\'; return false;" style="border : 1px solid #FF4422;">' : '')),
                      ));
    lovd_viewForm($aForm);

    print("\n" .
          '      </FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'edit') {
    // URL: /screenings/0000000001?edit
    // Edit an entry.

    $nID = sprintf('%010d', $_PE[1]);
    define('PAGE_TITLE', 'Edit an screening information entry');
    define('LOG_EVENT', 'ScreeningEdit');

    // Load appropiate user level for this screening entry.
    lovd_isAuthorized('screening', $nID);
    lovd_requireAUTH(LEVEL_OWNER);

    require ROOT_PATH . 'class/object_screenings.php';
    $_DATA = new LOVD_Screening();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    $bSubmit = (isset($_AUTH['saved_work']['submissions']['screening'][$nID]) || (isset($_AUTH['saved_work']['submissions']['individual'][$zData['individualid']]['screenings']) && in_array($nID, $_AUTH['saved_work']['submissions']['individual'][$zData['individualid']]['screenings'])));

    if (POST) {
        lovd_errorClean();

        $_DATA->checkFields($_POST);

        if (!lovd_error()) {
            // Fields to be used.
            $aFields = array_merge(
                            array('variants_found'),
                            (!$bSubmit || !empty($zData['edited_by'])? array('edited_by', 'edited_date') : array()),
                            $_DATA->buildFields());

            // Prepare values.
            $_POST['variants_found'] = (!isset($_POST['variants_found'])? '1' : $_POST['variants_found']);
            if ($_AUTH['level'] >= LEVEL_CURATOR) {
                $aFields[] = 'owned_by';
            }
            // Only actually committed to the database if we're not in a submission, or when they are already filled in.
            $_POST['edited_by'] = $_AUTH['id'];
            $_POST['edited_date'] = date('Y-m-d H:i:s');

            if (!$bSubmit) {
                // Put $zData with the old values in $_SESSION for mailing.
                // FIXME; change owner to owned_by_ in the load entry query of object_screenings.php.
                $zData['owned_by_'] = $zData['owner'];
                if ($zData['variants_found']) {
                    $zData['variants_found_'] = $_DB->query('SELECT COUNT(variantid) FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ?', array($nID))->fetchColumn();
                    if (!$zData['variants_found_']) {
                        $zData['variants_found_'] = 0;
                    }
                } else {
                    $zData['variants_found_'] = 'None';
                }
                $_SESSION['work']['edits']['screening'][$nID] = $zData;
            }

            // FIXME: implement versioning in updateEntry!
            $_DATA->updateEntry($nID, $_POST, $aFields);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Edited screening information entry ' . $nID);

            // Change linked genes?
            // Genes the screening is currently linked to.

            // Remove genes.
            $aToRemove = array();
            foreach ($zData['genes'] as $sGene) {
                if (!in_array($sGene, $_POST['genes'])) {
                    // User has requested removal...
                    $aToRemove[] = $sGene;
                }
            }

            if ($aToRemove) {
                $q = $_DB->query('DELETE FROM ' . TABLE_SCR2GENE . ' WHERE screeningid = ? AND geneid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($zData['id']), $aToRemove), false);
                if (!$q) {
                    // Silent error.
                    lovd_writeLog('Error', LOG_EVENT, 'Gene information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' could not be removed from screening ' . $nID);
                } else {
                    lovd_writeLog('Event', LOG_EVENT, 'Gene information entr' . (count($aToRemove) == 1? 'y' : 'ies') . ' ' . implode(', ', $aToRemove) . ' successfully removed from screening ' . $nID);
                }
            }

            // Add genes.
            $aSuccess = array();
            $aFailed = array();
            foreach ($_POST['genes'] as $sGene) {
                if (!in_array($sGene, $zData['genes']) && in_array($sGene, lovd_getGeneList())) {
                    // Add gene to screening.
                    $q = $_DB->query('INSERT IGNORE INTO ' . TABLE_SCR2GENE . ' VALUES (?, ?)', array($nID, $sGene), false);
                    if (!$q) {
                        $aFailed[] = $sGene;
                    } else {
                        $aSuccess[] = $sGene;
                    }
                }
            }
            if ($aFailed) {
                // Silent error.
                lovd_writeLog('Error', LOG_EVENT, 'Gene information entr' . (count($aFailed) == 1? 'y' : 'ies') . ' ' . implode(', ', $aFailed) . ' could not be added to screening ' . $nID);
            } elseif ($aSuccess) {
                lovd_writeLog('Event', LOG_EVENT, 'Gene information entr' . (count($aSuccess) == 1? 'y' : 'ies') . ' ' . implode(', ', $aSuccess) . ' successfully added to screening ' . $nID);
            }

            // Thank the user...
            if ($bSubmit) {
                header('Refresh: 3; url=' . lovd_getInstallURL() . 'submit/screening/' . $nID);

                $_T->printHeader();
                $_T->printTitle();
                lovd_showInfoTable('Successfully edited the screening information entry!', 'success');

                $_T->printFooter();
            } else {
                header('Location: ' . lovd_getInstallURL() . 'submit/finish/screening/' . $nID . '?edit');
            }

            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    } else {
        // Default values.
        foreach ($zData as $key => $val) {
            $_POST[$key] = $val;
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    if (GET) {
        print('      To edit an screening information entry, please fill out the form below.<BR>' . "\n" .
              '      <BR>' . "\n\n");
    }

    lovd_errorPrint();

    // Tooltip JS code.
    lovd_includeJS('inc-js-tooltip.php');
    lovd_includeJS('inc-js-custom_links.php');

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array_merge(
                 $_DATA->getForm(),
                 array(
                        array('', '', 'print', '<INPUT type="submit" value="Edit screening information entry">' . ($bSubmit? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . 'submit/screening/' . $nID . '\'; return false;" style="border : 1px solid #FF4422;">' : '')),
                      ));
    lovd_viewForm($aForm);

    print("\n" .
          '      </FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'confirmVariants') {
    // URL: /screenings/0000000001?confirmVariants
    // Confirm existing variant entries within the same individual.

    $nID = sprintf('%010d', $_PE[1]);
    define('PAGE_TITLE', 'Confirm variant entries with screening #' . $nID);
    define('LOG_EVENT', 'VariantConfirm');

    $z = $_DB->query('SELECT id, individualid, variants_found FROM ' . TABLE_SCREENINGS . ' WHERE id = ?', array($nID))->fetchAssoc();
    $aVariantsIndividual = $_DB->query('SELECT DISTINCT s2v.variantid FROM ' . TABLE_SCR2VAR . ' AS s2v INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) WHERE s.individualid = ?', array($z['individualid']))->fetchAllColumn();
    $aVariantsScreening = $_DB->query('SELECT variantid FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ?', array($nID))->fetchAllColumn();

    $sMessage = '';
    if (!$z) {
        $sMessage = 'The screening ID given is not valid, please go to the desired screening entry and click on the "Add variant" button.';
    } elseif (!lovd_isAuthorized('screening', $nID)) {
        lovd_requireAUTH(LEVEL_OWNER);
    } elseif (!$z['variants_found']) {
        $sMessage = 'Cannot confirm variants with the given screening, because the value \'Have variants been found?\' is unchecked.';
    } elseif (!count($aVariantsIndividual)) {
        $sMessage = 'You cannot confirm variants with this screening, because there aren\'t any variants connected to this individual yet!';
    } elseif (count($aVariantsScreening) == count($aVariantsIndividual)) {
        $sMessage = 'You cannot confirm any more variants with this screening, because all this individual\'s variants have already been found/confirmed by this screening!';
    }
    if ($sMessage) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable($sMessage, 'stop');
        $_T->printFooter();
        exit;
    } else {
        $nIndividual = $z['individualid'];
        $_GET['search_screeningids'] = $_DB->query('SELECT GROUP_CONCAT(id SEPARATOR "|") FROM ' . TABLE_SCREENINGS . ' WHERE individualid = ? AND id != ? GROUP BY individualid', array($nIndividual, $nID))->fetchColumn();
    }

    $bSubmit = false;
    if (isset($_AUTH['saved_work']['submissions']['screening'][$nID])) {
        $bSubmit = true;
        $aSubmit = &$_AUTH['saved_work']['submissions']['screening'][$nID];
    } elseif (isset($_AUTH['saved_work']['submissions']['individual'][$nIndividual])) {
        $aSubmit = &$_AUTH['saved_work']['submissions']['individual'][$nIndividual];
        if (isset($aSubmit['screenings']) && in_array($nID, $aSubmit['screenings'])) {
            $bSubmit = true;
        }
    }

    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        // Preventing notices...
        // $_SESSION['viewlists']['Screenings_' . $nID . '_confirmVariants']['checked'] stores the IDs of the variants that are supposed to be present in TABLE_SCR2VAR.
        if (isset($_SESSION['viewlists']['Screenings_' . $nID . '_confirmVariants']['checked'])) {
            // Check if all checked variants are actually from this individual.
            $aDiff = array_diff($_SESSION['viewlists']['Screenings_' . $nID . '_confirmVariants']['checked'], $aVariantsIndividual);
            if (!empty($aDiff)) {
                // The user tried to fake a $_POST by inserting an ID that did not come from our code.
                lovd_errorAdd('', 'Invalid variant, please select the variants from the top viewlist!');
            }
        }

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        } elseif (!lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            // User had to enter his/her password for authorization.
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            $_DB->beginTransaction();

            $nVariantsChecked = 0; // Amount of variants checked. Determines which options to show after submit.
            $aNewVariants = array();

            // Insert newly confirmed variants.
            $q = $_DB->prepare('INSERT INTO ' . TABLE_SCR2VAR . '(screeningid, variantid) VALUES (?, ?)');
            foreach ($_SESSION['viewlists']['Screenings_' . $nID . '_confirmVariants']['checked'] as $nVariant) {
                $nVariantsChecked ++;
                if (!in_array($nVariant, $aVariantsScreening)) {
                    // If the variant is not already connected to this screening, we will add it now.
                    $aNewVariants[] = $nVariant;
                    $q->execute(array($nID, $nVariant));
                }
            }

            // If we get here, it all succeeded.
            $_DB->commit();
            unset($_SESSION['viewlists']['Screenings_' . $nID . '_confirmVariants']);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Updated the list of variants confirmed with screening #' . $nID);

            if ($bSubmit) {
                if (!isset($aSubmit['confirmedVariants'][$nID])) {
                    $aSubmit['confirmedVariants'][$nID] = array();
                }
                $aSubmit['confirmedVariants'][$nID] = array_merge($aNewVariants, $aSubmit['confirmedVariants'][$nID]);

                lovd_saveWork();

                // Thank the user...
                header('Refresh: 3; url=' . lovd_getInstallURL() . 'submit/screening/' . $nID);

                $_T->printHeader();
                $_T->printTitle();

                lovd_showInfoTable('Successfully confirmed the variant entr' . (count($aNewVariants) > 1? 'ies' : 'y') . '!', 'success');

                $_T->printFooter();

            } else {
                if (!isset($_SESSION['work']['submits']['confirmedVariants'][$nID])) {
                    $_SESSION['work']['submits']['confirmedVariants'][$nID] = array();
                }
                $_SESSION['work']['submits']['confirmedVariants'][$nID] = array_merge($aNewVariants, $_SESSION['work']['submits']['confirmedVariants'][$nID]);
                header('Location: ' . lovd_getInstallURL() . 'submit/finish/confirmedVariants/' . $nID);
            }
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    } else {
        // Default session values.
        $_SESSION['viewlists']['Screenings_' . $nID . '_confirmVariants']['checked'] = array();
    }

    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();
    lovd_showInfoTable('The variant entries below are all variants found in this individual, not yet confirmed by/added to this screening.', 'information');

    $_GET['page_size'] = 10;
    $_GET['search_screeningids'] .= ' !' . $nID;
    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
    $_DATA->viewList('Screenings_' . $nID . '_confirmVariants', array('id_', 'chromosome'), true, false, true);

    print('      <BR><BR>' . "\n\n");

    // Table.
    print('      <FORM id="confirmVariants" action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '', '0%', '0', '100%'),
                    array('', '', 'print', 'Enter your password for authorization'),
                    array('', '', 'password', 'password', 20),
                    array('', '', 'print', '<INPUT type="submit" value="Save variant list" onclick="lovd_AJAX_viewListSubmit(\'Screenings_' . $nID . '_confirmVariants\', function () { $(\'#confirmVariants\').submit(); }); return false;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . 'variants?create&amp;target=' . $nID . '\'; return false;" style="border : 1px solid #FF4422;">'),
                  );
    lovd_viewForm($aForm);

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'removeVariants') {
    // URL: /screenings/0000000001?removeVariants
    // Remove variants from a screening entry.

    $nID = sprintf('%010d', $_PE[1]);
    define('PAGE_TITLE', 'Remove variant entries from screening #' . $nID);
    define('LOG_EVENT', 'VariantRemove');

    $z = $_DB->query('SELECT id, individualid, variants_found FROM ' . TABLE_SCREENINGS . ' WHERE id = ?', array($nID))->fetchAssoc();
    $aVariants = $_DB->query('SELECT variantid FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ?', array($nID))->fetchAllColumn();
    if ($aVariants) {
        $aValidVariants = $_DB->query('SELECT variantid, COUNT(screeningid) AS nCount FROM ' . TABLE_SCR2VAR . ' WHERE variantid IN (?' . str_repeat(', ?', count($aVariants) - 1) . ') GROUP BY variantid HAVING nCount > 1', $aVariants)->fetchAllColumn();
        $aInvalidVariants = array_diff($aVariants, $aValidVariants);
    }

    $sMessage = '';
    if (!$z) {
        $sMessage = 'The screening ID given is not valid, please go to the desired screening entry and click on the "Remove variants" button.';
    } elseif (!lovd_isAuthorized('screening', $nID)) {
        lovd_requireAUTH(LEVEL_OWNER);
    } elseif (!$z['variants_found']) {
        $sMessage = 'Cannot remove variants with the given screening, because the value \'Have variants been found?\' is unchecked.';
    } elseif (!count($aVariants)) {
        $sMessage = 'You cannot remove variants with this screening, because there aren\'t any variants connected to this screening yet!';
    } elseif (!count($aValidVariants)) {
        $sMessage = 'You cannot remove any more variants with this screening, because this is the only screening these variants are connected to!';
    }
    if ($sMessage) {
        $_T->printHeader();
        $_T->printTitle();
        lovd_showInfoTable($sMessage, 'stop');
        $_T->printFooter();
        exit;
    }

    $bSubmit = false;
    if (isset($_AUTH['saved_work']['submissions']['screening'][$nID])) {
        $bSubmit = true;
        $aSubmit = &$_AUTH['saved_work']['submissions']['screening'][$nID];
    } elseif (isset($_AUTH['saved_work']['submissions']['individual'][$nIndividual])) {
        $aSubmit = &$_AUTH['saved_work']['submissions']['individual'][$nIndividual];
        if (isset($aSubmit['screenings']) && in_array($nID, $aSubmit['screenings'])) {
            $bSubmit = true;
        }
    }

    require ROOT_PATH . 'inc-lib-form.php';

    if (POST) {
        lovd_errorClean();

        // Preventing notices...
        // $_SESSION['viewlists']['Screenings_' . $nID . '_removeVariants']['checked'] stores the IDs of the variants that are supposed to be present in TABLE_SCR2VAR.
        if (isset($_SESSION['viewlists']['Screenings_' . $nID . '_removeVariants']['checked'])) {
            // Check if all checked variants are actually from this screening.
            $aDiff = array_diff($_SESSION['viewlists']['Screenings_' . $nID . '_removeVariants']['checked'], $aValidVariants);
            if (!empty($aDiff)) {
                // The user tried to fake a $_POST by inserting an ID that did not come from our code.
                lovd_errorAdd('', 'Invalid variant, please select the variants from the top viewlist!');
            }
        }

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        } elseif (!lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            // User had to enter his/her password for authorization.
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            $_DB->beginTransaction();

            $aToRemove = $_SESSION['viewlists']['Screenings_' . $nID . '_removeVariants']['checked'];
            if (!empty($aToRemove)) {
                // Remove variants from screening...
                $_DB->query('DELETE FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ? AND variantid IN (?' . str_repeat(', ?', count($aToRemove) - 1) . ')', array_merge(array($nID), $aToRemove));
            }

            // If we get here, it all succeeded.
            $_DB->commit();
            unset($_SESSION['viewlists']['Screenings_' . $nID . '_removeVariants']);

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Updated the list of variants confirmed with screening #' . $nID);

            if ($bSubmit && !empty($aSubmit['confirmedVariants'][$nID]) && !empty($aToRemove)) {
                $aSubmit['confirmedVariants'][$nID] = array_diff($aSubmit['confirmedVariants'][$nID], $aToRemove);
                lovd_saveWork();
            }

            header('Refresh: 3; url=' . lovd_getInstallURL() . ($bSubmit? 'submit/screening/' . $nID : CURRENT_PATH));

            $_T->printHeader();
            $_T->printTitle();

            // Thank the user...
            lovd_showInfoTable('Successfully removed the variant entr' . (count($aToRemove) > 1? 'ies' : 'y') . '!', 'success');

            $_T->printFooter();

            exit;
        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    } else {
        // Default session values.
        $_SESSION['viewlists']['Screenings_' . $nID . '_removeVariants']['checked'] = array();
    }

    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();
    lovd_showInfoTable('The variant entries below are all variants that can be removed from this screening. Variants that are not also added to another screening can not be removed.', 'information');

    $_GET['page_size'] = 10;
    $_GET['search_screeningids'] = $nID;
    $_GET['search_id_'] = (count($aInvalidVariants)? '!' . implode(' !', $aInvalidVariants) : '');
    require ROOT_PATH . 'class/object_genome_variants.php';
    $_DATA = new LOVD_GenomeVariant();
    $_DATA->viewList('Screenings_' . $nID . '_removeVariants', array('id_', 'screeningids', 'chromosome'), true, false, true);

    print('      <BR><BR>' . "\n\n");

    // Table.
    print('      <FORM id="removeVariants" action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    // Array which will make up the form table.
    $aForm = array(
                    array('POST', '', '', '', '0%', '0', '100%'),
                    array('', '', 'print', 'Enter your password for authorization'),
                    array('', '', 'password', 'password', 20),
                    array('', '', 'print', '<INPUT type="submit" value="Save variant list" onclick="lovd_AJAX_viewListSubmit(\'Screenings_' . $nID . '_removeVariants\', function () { $(\'#removeVariants\').submit(); }); return false;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<INPUT type="submit" value="Cancel" onclick="window.location.href=\'' . lovd_getInstallURL() . ($bSubmit? 'submit/screening/' : 'screenings/') . $nID . '\'; return false;" style="border : 1px solid #FF4422;">'),
                  );
    lovd_viewForm($aForm);

    $_T->printFooter();
    exit;
}





if (PATH_COUNT == 2 && ctype_digit($_PE[1]) && ACTION == 'delete') {
    // URL: /screenings/0000000001?delete
    // Drop specific entry.

    $nID = sprintf('%010d', $_PE[1]);
    define('PAGE_TITLE', 'Delete screening information entry ' . $nID);
    define('LOG_EVENT', 'ScreeningDelete');

    lovd_isAuthorized('screening', $nID);
    lovd_requireAUTH(LEVEL_CURATOR);

    require ROOT_PATH . 'class/object_screenings.php';
    $_DATA = new LOVD_Screening();
    $zData = $_DATA->loadEntry($nID);
    require ROOT_PATH . 'inc-lib-form.php';

    $a = $_DB->query('SELECT variantid, screeningid FROM ' . TABLE_SCR2VAR . ' GROUP BY variantid HAVING COUNT(screeningid) = 1 AND screeningid = ?', array($nID))->fetchAllColumn();
    $aVariantsRemovable = array();
    if (!empty($a)) {
        $aVariantsRemovable = $_DB->query('SELECT variantid FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ? AND variantid IN (?' . str_repeat(', ?', count($a) - 1) . ')', array_merge(array($nID), $a))->fetchAllColumn();
    }
    $nVariantsRemovable = count($aVariantsRemovable);

    if (!empty($_POST)) {
        lovd_errorClean();

        // Mandatory fields.
        if (empty($_POST['password'])) {
            lovd_errorAdd('password', 'Please fill in the \'Enter your password for authorization\' field.');
        }

        // User had to enter his/her password for authorization.
        if ($_POST['password'] && !lovd_verifyPassword($_POST['password'], $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        if (!lovd_error()) {
            // Query text.
            // This also deletes the entries in TABLE_SCR2GENES && TABLE_SCR2VAR.
            $_DB->beginTransaction();
            if (isset($_POST['remove_variants']) && $_POST['remove_variants'] == 'remove') {
                $_DB->query('DELETE FROM ' . TABLE_VARIANTS . ' WHERE id IN (?' . str_repeat(', ?', count($aVariantsRemovable) - 1) . ')', $aVariantsRemovable);
            }

            $_DATA->deleteEntry($nID);

            $_DB->commit();

            // Write to log...
            lovd_writeLog('Event', LOG_EVENT, 'Deleted screening information entry ' . $nID);

            // Thank the user...
            header('Refresh: 3; url=' . lovd_getInstallURL() . 'individuals/' . $zData['individualid']);

            $_T->printHeader();
            $_T->printTitle();
            lovd_showInfoTable('Successfully deleted the screening information entry!', 'success');

            $_T->printFooter();
            exit;

        } else {
            // Because we're sending the data back to the form, I need to unset the password fields!
            unset($_POST['password']);
        }
    }



    $_T->printHeader();
    $_T->printTitle();

    lovd_errorPrint();

    // Table.
    print('      <FORM action="' . CURRENT_PATH . '?' . ACTION . '" method="post">' . "\n");

    $nVariants = $_DB->query('SELECT COUNT(variantid) FROM ' . TABLE_SCR2VAR . ' WHERE screeningid = ?', array($nID))->fetchColumn();
    $aOptions = array('remove' => 'Yes, Remove ' . ($nVariantsRemovable == 1? 'this variant' : 'these variants') . ' from this screening', 'keep' => 'No, Keep ' . ($nVariantsRemovable == 1? 'this variant' : 'these variants') . ' as separate entries');

    // Array which will make up the form table.
    $aForm = array_merge(
                 array(
                        array('POST', '', '', '', '40%', '14', '60%'),
                        array('Deleting screening information entry', '', 'print', '<B>' . $nID . ' (Owner: ' . $zData['owner'] . ')</B>'),
                        'skip',
                        array('', '', 'print', 'This screening entry has ' . ($nVariants? $nVariants : 0) . ' variant' . ($nVariants == 1? '' : 's') . ' attached.'),
'variants_removable' => array('', '', 'print', (!$nVariantsRemovable? 'No variants will be removed.' : '<B>' . $nVariantsRemovable . ' variant' . ($nVariantsRemovable == 1? '' : 's') . ' will be removed, because ' . ($nVariantsRemovable == 1? 'it is' : 'these are'). ' not attached to other screenings!!!</B>')),
          'variants' => array('Should LOVD remove ' . ($nVariantsRemovable == 1? 'this variant' : 'these ' . $nVariantsRemovable . ' variants') . '?', '', 'select', 'remove_variants', 1, $aOptions, false, false, false),
     'variants_skip' => 'skip',
                        array('Enter your password for authorization', '', 'password', 'password', 20),
                        array('', '', 'submit', 'Delete screening information entry'),
                      ));
    if (!$nVariantsRemovable) {
        if (!$nVariants) {
            unset($aForm['variants_removable']);
        }
        unset($aForm['variants'], $aForm['variants_skip']);
    }

    lovd_viewForm($aForm);

    print('</FORM>' . "\n\n");

    $_T->printFooter();
    exit;
}

?>
