<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-10-28
 * Modified    : 2019-09-30
 * For LOVD    : 3.0-22
 *
 * Copyright   : 2004-2019 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Anthony Marty <anthony.marty@unimelb.edu.au>
 *               Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
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





class LOVD_IndividualMOD extends LOVD_Individual
{
    // This class extends the Individual class and it handles the Individuals in LOVD+.





    function __construct ()
    {
        // Default constructor.
        global $_SETT;

        // Run parent constructor to find out about the custom columns.
        parent::__construct();

        // And now we're going to overwrite the whole damn thing.
        $this->sObject = 'IndividualMOD';
        $this->sTable  = 'TABLE_INDIVIDUALS';

        // SQL code for loading the gene panel data.
        $this->sSQLLoadEntry = 'SELECT i.*, ' .
                               'GROUP_CONCAT(DISTINCT i2gp.genepanelid ORDER BY i2gp.genepanelid SEPARATOR ";") AS _gene_panels, ' .
                               'GROUP_CONCAT(DISTINCT i2d.diseaseid ORDER BY i2d.diseaseid SEPARATOR ";") AS _active_diseases ' .
                               'FROM ' . TABLE_INDIVIDUALS . ' AS i ' .
                               'LEFT OUTER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i.id = i2gp.individualid) ' .
                               'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                               'WHERE i.id = ? ' .
                               'GROUP BY i.id';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'i.*, "" AS owned_by, ' .
                                           'GROUP_CONCAT(DISTINCT d.id SEPARATOR ";") AS _diseaseids, ' .
                                           'GROUP_CONCAT(DISTINCT d.id, ";", IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol), ";", d.name ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ";;") AS __diseases, ' .
                                           'GROUP_CONCAT(DISTINCT gp.id, ";", gp.name, ";", gp.type ORDER BY gp.type DESC, gp.name ASC SEPARATOR ";;") AS __gene_panels, ' .
                                           '(SELECT COUNT(*) FROM ' . TABLE_SCR2VAR . ' AS s2v INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s2v.screeningid = s.id) WHERE s.individualid = i.id) AS variants, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
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
                                          's.analysis_by, ' .
                                          's.analysis_date, ' .
                                          's.analysis_approved_date, ' .
                                          (!lovd_verifyInstance('mgha')? '' : 's.`Screening/Pipeline/Run_ID`, CASE WHEN s.`Screening/Mother/Sample_ID` IS NOT NULL AND CHAR_LENGTH(s.`Screening/Mother/Sample_ID`) > 0 AND s.`Screening/Father/Sample_ID` IS NOT NULL AND CHAR_LENGTH(s.`Screening/Father/Sample_ID`) > 0 THEN "Trio" ELSE "Individual" END AS family_type, ') . // MGHA specific individual identifiers.
                                          (!lovd_verifyInstance('mgha_seq') ? '' : 's.`Screening/Pipeline/Run_ID`, s.`Screening/Pipeline/Path`, ') .
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
            (!lovd_verifyInstance('leiden')? array() : array(
                'id_miracle' => 'Miracle ID',
                'id_zis' => 'ZIS ID',
            )),
                 $this->buildViewEntry(),
                 array(
                        'custom_panel_' => 'Custom gene panel',
                        'gene_panels_' => 'Gene panels',
                        'diseases_' => 'Diseases',
                        'parents_' => 'Parent(s)',
                        'variants' => 'Total variants imported',
                        'created_by_' => array('Created by', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'created_date_' => array('Date created', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'edited_by_' => array('Last edited by', $_SETT['user_level_settings']['see_nonpublic_data']),
                        'edited_date_' => array('Date last edited', $_SETT['user_level_settings']['see_nonpublic_data']),
                      ));

        // Set some instance specific individual identifiers.
        if (lovd_verifyInstance('mgha', false)) {
            $aIndIdentifier = array(
                'Screening/Pipeline/Run_ID' => array(
                    'view' => array('Pipeline Run ID', 100),
                    'db'   => array('s.`Screening/Pipeline/Run_ID`', 'ASC', true))
            );

            if (lovd_verifyInstance('mgha')) {
                $aIndIdentifier = array_merge($aIndIdentifier, array(
                    'family_type' => array(
                        'view' => array('Family Type', 100),
                        'db'   => array('family_type', 'ASC', 'TEXT'))
                ));
            }

        } elseif (lovd_verifyInstance('leiden')) {
            $aIndIdentifier = array(
                'id_zis' => array(
                    'view' => array('ZIS ID', 100),
                    'db'   => array('i.id_zis', 'ASC', true)),
            );
        } else {
            $aIndIdentifier = array();
        }

        // Some instance specific screening identifiers.
        if (lovd_verifyInstance('mgha_seq')) {
            $aScreeningIdentifier = array(
                'Screening/Pipeline/Path' => array(
                    'view' => array('Pipeline Path', 100),
                    'db'   => array('s.`Screening/Pipeline/Path`', 'ASC', true))
            );
        } else {
            $aScreeningIdentifier = array();
        }

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'individualid' => array(
                                    'view' => false,
                                    'db'   => array('i.id', 'ASC', true)),
                        'id' => array(
                                    'view' => array('Individual ID', 100),
                                    'db'   => array('i.id', 'ASC', true)),
                      ),
                 $aIndIdentifier,
                 $aScreeningIdentifier,
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





    function buildForm ($sPrefix = '')
    {
        $aForm = parent::buildForm($sPrefix);
        // Remove all except the remarks.
        $aFormFiltered = array();

        foreach($aForm as $sCol => $val) {
            if (strpos($sCol, 'Individual/Curation/') !== false) {
                $aFormFiltered[$sCol] = $aForm[$sCol];
            }
        }

        if (isset($aForm['Individual/Remarks'])) {
            $aFormFiltered['Individual/Remarks'] = $aForm['Individual/Remarks'];
        }
        return $aFormFiltered;
    }





    function getForm ()
    {
        // Build the form.

        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            return parent::getForm();
        }

        // Array which will make up the form table.
        $this->aFormData = array_merge(
            array(
                array('POST', '', '', '', '35%', '14', '65%'),
            ),
            $this->buildForm());

        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        $zData['parents_'] = ''; // To prevent notices.
        $zData = parent::prepareData($zData, $sView);
        if ($sView == 'list') {
            $zData['analysis_date_'] = substr($zData['analysis_date'], 0, 10);
            $zData['analysis_approved_date_'] = substr($zData['analysis_approved_date'], 0, 10);
        } else {
            // Make the custom panel link to the genes.
            $zData['custom_panel_'] = '';
            foreach(explode(', ', $zData['custom_panel']) as $sGene) {
                $zData['custom_panel_'] .= (!$zData['custom_panel_']? '' : ', ') . '<A href="genes/' . $sGene . '">' . $sGene . '</A>';
            }
            // Gene panels assigned.
            $zData['gene_panels_'] = '';
            foreach($zData['gene_panels'] as $aGenePanels) {
                list($nID, $sName, $sType) = $aGenePanels;
                $zData['gene_panels_'] .= (!$zData['gene_panels_']? '' : ', ') . '<A href="gene_panels/' . $nID . '">' . $sName . '</A>';
            }
            $zData['gene_panels_'] = $zData['gene_panels_'] . ' <SPAN style="float:right; margin-left : 25px;"><A href="individuals/' . $zData['id'] . '?edit_panels">Edit gene panels</A></SPAN>';
        }

        return $zData;
    }
}
?>
