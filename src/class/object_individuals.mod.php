<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-10-28
 * Modified    : 2016-03-07
 * For LOVD    : 3.0-12
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}
// Require parent class definition.
require_once ROOT_PATH . 'class/object_individuals.php';





class LOVD_IndividualMOD extends LOVD_Individual {
    // This class extends the basic Object class and it handles the Individuals object.





    function __construct ()
    {
        // Default constructor.
        global $_AUTH;

        // Run parent constructor to find out about the custom columns.
        parent::__construct();

        // And now we're going to overwrite the whole damn thing.
        $this->sObject = 'IndividualMOD';
        $this->sTable  = 'TABLE_INDIVIDUALS';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'i.*, "" AS owned_by, ' .
                                           'GROUP_CONCAT(DISTINCT d.id SEPARATOR ";") AS _diseaseids, ' .
                                           'GROUP_CONCAT(DISTINCT d.id, ";", IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol), ";", d.name ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ";;") AS __diseases, ' .
                                           'GROUP_CONCAT(DISTINCT gp.id, ";", gp.name ORDER BY gp.name ASC SEPARATOR ";;") AS __gene_panels, ' .
                                           'GROUP_CONCAT(DISTINCT s.id SEPARATOR ";") AS _screeningids, ' .
                                           'COUNT(DISTINCT s2v.variantid) AS variants, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.screeningid = s.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i.id = i2gp.individualid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_GENE_PANELS . ' AS gp ON (i2gp.genepanelid = gp.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (i.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (i.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'i.id';

        // SQL code for viewing the list of individuals.
        $this->aSQLViewList['SELECT']   = 'i.*, ' .
                                          'i.id AS individualid, ' .
                                          's.id AS screeningid, ' .
                                          's.analysis_date, ' .
                                          's.analysis_approved_date, ' .
                                        // FIXME; Can we get this order correct, such that diseases without abbreviation nicely mix with those with? Right now, the diseases without symbols are in the back.
                                          'GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, ' .
//                                          'COUNT(DISTINCT ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? 's2v.variantid' : 'vog.id') . ') AS variants_, ' . // Counting s2v.variantid will not include the limit opposed to vog in the join's ON() clause.
                                          'GROUP_CONCAT(DISTINCT gp.name ORDER BY gp.name ASC SEPARATOR ", ") AS gene_panels_, ' .
                                          'ua.name AS analysis_by_, ' .
                                          'uaa.name AS analysis_approved_by_, ' .
                                          'CONCAT_WS(";", ua.id, ua.name, ua.email, ua.institute, ua.department, IFNULL(ua.countryid, "")) AS _analyzer, ' .
                                          'CASE ds.id WHEN ' . ANALYSIS_STATUS_WAIT . ' THEN "marked" WHEN ' . ANALYSIS_STATUS_CONFIRMED . ' THEN "del" WHEN ' . ANALYSIS_STATUS_ARCHIVED . ' THEN "del" END AS class_name,' .
                                          'ds.name AS analysis_status';
        $this->aSQLViewList['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
                                          'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i.id = i2gp.individualid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_GENE_PANELS . ' AS gp ON (i2gp.genepanelid = gp.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) ' .
//                                          'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.screeningid = s.id) ' .
//                                          ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' :
//                                              'LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id AND (vog.statusid >= ' . STATUS_MARKED . (!$_AUTH? '' : ' OR vog.created_by = "' . $_AUTH['id'] . '" OR vog.owned_by = "' . $_AUTH['id'] . '"') . ')) ') .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ua ON (s.analysis_by = ua.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uaa ON (s.analysis_approved_by = uaa.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_ANALYSIS_STATUS . ' AS ds ON (s.analysis_statusid = ds.id)';
        $this->aSQLViewList['GROUP_BY'] = 's.id';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
            array(
                'id_miracle' => 'Miracle ID',
                'id_zis' => 'ZIS ID',
            ),
                 $this->buildViewEntry(),
                 array(
                        'custom_panel' => 'Custom gene panel', // TODO AM Do we need to create URLS to the genes for the view entry?
                        'gene_panels_' => 'Gene panels',
                        'diseases_' => 'Diseases',
                        'parents_' => 'Parent(s)',
                        'variants' => 'Total variants imported',
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date_' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date last edited', LEVEL_COLLABORATOR),
                      ));

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'individualid' => array(
                                    'view' => false,
                                    'db'   => array('i.id', 'ASC', true)),
                        'id' => array(
                                    'view' => array('Individual ID', 100),
                                    'db'   => array('i.id', 'ASC', true)),
                        'id_zis' => array(
                                    'view' => array('ZIS ID', 100),
                                    'db'   => array('i.id_zis', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                     'diseases_' => array(
                         'view' => array('Disease', 175),
                         'db'   => array('diseases_', 'ASC', true)),
                     'gene_panels_' => array(
                         'view' => array('Gene panels', 200),
                         'db'   => array('gene_panels_', 'ASC', true)),
                     'custom_panel' => array(
                         'view' => array('Custom panel', 100),
                         'db'   => array('i.custom_panel', 'ASC', true)),
//                     'variants_' => array(
//                         'view' => array('Variants', 75),
//                         'db'   => array('variants_', 'ASC', 'INT_UNSIGNED')),
                     'analysis_status' => array(
                         'view' => array('Analysis status', 120),
                         'db'   => array('ds.name', false, true)),
                     'analysis_by_' => array(
                         'view' => array('Analysis by', 160),
                         'db'   => array('ua.name', 'ASC', true)),
                     'analysis_date_' => array(
                         'view' => array('Analysis started', 110),
                         'db'   => array('s.analysis_date', 'DESC', true)),
                     'analysis_approved_by_' => array(
                         'view' => array('Analysis approved by', 160),
                         'db'   => array('uaa.name', 'ASC', true)),
                     'analysis_approved_date_' => array(
                         'view' => array('Analysis approved', 110),
                         'db'   => array('s.analysis_approved_date', 'DESC', true)),

                      ));
        $this->sSortDefault = 'id';

        // Because the information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();
    }





    function checkFields ($aData, $zData = false)
    {
        if (ACTION == 'edit_panels') {
            // If we are assigning gene panels to an individual then only check the relevant fields.
            global $_DB;
            $this->getForm();

            // Checks to make sure a valid gene panel ID is used
            $aGenePanels = array_keys($this->aFormData['aGenePanels'][5]);
            if (!empty($aData['gene_panels'])) {
                foreach ($aData['gene_panels'] as $nGenePanel) {
                    if ($nGenePanel && !in_array($nGenePanel, $aGenePanels)) {
                        lovd_errorAdd('gene_panels', htmlspecialchars($nGenePanel) . ' is not a valid gene panel.');
                    }
                }
            }

            // Checks the genes added to the custom panel to ensure they exist within the database
            if (!empty($aData['custom_panel'])) {
                // Explode the custom panel genes into an array
                $aGeneSymbols = array_filter(array_unique(array_map('trim', preg_split('/(\s|[,;])+/', strtoupper($aData['custom_panel'])))));

                // Check if there are any genes left after cleaning up the gene symbol string.
                if (count($aGeneSymbols) > 0) {
                    // Load the genes and alternative names into an array.
                    $aGenesInLOVD = $_DB->query('SELECT UPPER(id), id FROM ' . TABLE_GENES)->fetchAllCombine();
                    // Loop through all the gene symbols in the array and check them for any errors.
                    foreach ($aGeneSymbols as $key => $sGeneSymbol) {
                        $sGeneSymbol = $sGeneSymbol;
                        // Check to see if this gene symbol has been found within the database.
                        if (isset($aGenesInLOVD[$sGeneSymbol])) {
                            // A correct gene symbol was found, so lets use that to remove any case issues.
                            $aGeneSymbols[$key] = $aGenesInLOVD[$sGeneSymbol];
                        } else {
                            // This gene symbol was not found in the database.
                            // It got uppercased by us, but we assume that will be OK.
                            lovd_errorAdd('custom_panel', 'The gene symbol ' . htmlspecialchars($sGeneSymbol) . ' can not be found within the database.');
                        }
                    }
                    // Write the cleaned up custom gene panel back to POST so as to ensure the genes in the custom panel are stored in a standard way.
                    $_POST['custom_panel'] = implode(", ", $aGeneSymbols); // TODO AM Ivo you are probably not going to like this as we are directly overwriting form data here, let me know how best to achieve this.
                }
            }
            lovd_checkXSS();
        } else {
            // Otherwise use the parents checkFields function.
            parent::checkFields($aData);
        }
    }





    function getForm ()
    {
        // Build the form.
        if (ACTION == 'edit_panels') {
            // Show the form for editing gene panels if we are assigning gene panels to an individual.

            // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
            if (!empty($this->aFormData)) {
                return LOVD_Custom::getForm(); // Bypass the LOVD_Individual object so as it doesn't add in the extra columns into the form.
            }

            global $_DB;

            // Get list of gene panels.
            $aGenePanelsForm = $_DB->query('(SELECT "optgroup2" AS id, "Mendeliome" AS name, "mendeliome_header" AS type)
                                        UNION
                                        (SELECT "optgroup1", "Gene Panels", "gene_panel_header")
                                        UNION
                                        (SELECT "optgroup3", "Blacklist", "blacklist_header")
                                        UNION
                                        (SELECT CAST(id AS CHAR), name, type FROM lovd_gene_panels)
                                        ORDER BY type DESC, name')->fetchAllCombine(); // TODO AM I have had to cast the id as a char to get the id with zero padding eg "00033" as the inc-lib-form.php creates the option values in this way. Without doing this the "selected" option is not set correctly as it does not match without the zero padding. Is there are better way to do this?
            $nGenePanels = count($aGenePanelsForm);
            foreach ($aGenePanelsForm as $nID => $sGenePanel) {
                $aGenePanelsForm[$nID] = lovd_shortenString($sGenePanel, 75);
            }
            $nGPFieldSize = ($nGenePanels < 20 ? $nGenePanels : 20);
            if (!$nGenePanels) {
                $aGenePanelsForm = array('' => 'No gene panel entries available');
                $nGPFieldSize = 1;
            }

            $this->aFormData = array_merge(
                array(
                    array('POST', '', '', '', '50%', '14', '50%'),
                    'custom_panel' => array('Custom gene panel', '', 'textarea', 'custom_panel', 50, 2),
                    'aGenePanels' => array('Assigned gene panels', '', 'select', 'gene_panels', $nGPFieldSize, $aGenePanelsForm, false, true, false),
                ));

            return LOVD_Custom::getForm();
        } else {
            // Otherwise use the parents functions.
            // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
            if (!empty($this->aFormData)) {
                return parent::getForm();
            }
            return parent::getForm();
        }

    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        global $_SETT;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        $zData = parent::prepareData($zData, $sView);
        if ($sView == 'list') {
            $zData['analysis_date_'] = substr($zData['analysis_date'], 0, 10);
            $zData['analysis_approved_date_'] = substr($zData['analysis_approved_date'], 0, 10);
        }

        return $zData;
    }
}
?>
