<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-10-28
 * Modified    : 2013-11-29
 * For LOVD    : 3.0-09
 *
 * Copyright   : 2004-2013 Leiden University Medical Center; http://www.LUMC.nl/
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

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'i.*, "" AS owned_by, ' .
                                           'GROUP_CONCAT(DISTINCT d.id SEPARATOR ";") AS _diseaseids, ' .
                                           'GROUP_CONCAT(DISTINCT d.id, ";", IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol), ";", d.name ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ";;") AS __diseases, ' .
                                           'GROUP_CONCAT(DISTINCT s.id SEPARATOR ";") AS _screeningids, ' .
                                           'COUNT(DISTINCT s2v.variantid) AS variants, ' .
                                           'ua.name AS analysis_by_, ' .
                                           'uaa.name AS analysis_approved_by_, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.screeningid = s.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ua ON (i.analysis_by = ua.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uaa ON (i.analysis_approved_by = uaa.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (i.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (i.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'i.id';

        // SQL code for viewing the list of individuals
        $this->aSQLViewList['SELECT']   = 'i.*, ' .
                                          'i.id AS individualid, ' .
                                        // FIXME; Can we get this order correct, such that diseases without abbreviation nicely mix with those with? Right now, the diseases without symbols are in the back.
                                          'GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, ' .
//                                          'COUNT(DISTINCT ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? 's2v.variantid' : 'vog.id') . ') AS variants_, ' . // Counting s2v.variantid will not include the limit opposed to vog in the join's ON() clause.
                                          'ua.name AS analysis_by_, ' .
                                          'uaa.name AS analysis_approved_by_, ' .
                                          'CONCAT_WS(";", ua.id, ua.name, ua.email, ua.institute, ua.department, IFNULL(ua.countryid, "")) AS _analyzer, ' .
                                          'CASE ds.id WHEN ' . ANALYSIS_STATUS_WAIT . ' THEN "marked" WHEN ' . ANALYSIS_STATUS_APPROVED .' THEN "del" WHEN ' . ANALYSIS_STATUS_ARCHIVED .' THEN "del" END AS class_name,' .
                                          'ds.name AS analysis_status';
        $this->aSQLViewList['FROM']     = TABLE_INDIVIDUALS . ' AS i ' .
                                          'LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS i2d ON (i.id = i2d.individualid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (i2d.diseaseid = d.id) ' .
//                                          'LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (i.id = s.individualid) ' .
//                                          'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s2v.screeningid = s.id) ' .
//                                          ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' :
//                                              'LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id AND (vog.statusid >= ' . STATUS_MARKED . (!$_AUTH? '' : ' OR vog.created_by = "' . $_AUTH['id'] . '" OR vog.owned_by = "' . $_AUTH['id'] . '"') . ')) ') .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ua ON (i.analysis_by = ua.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uaa ON (i.analysis_approved_by = uaa.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_ANALYSIS_STATUS . ' AS ds ON (i.analysis_statusid = ds.id)';
        $this->aSQLViewList['GROUP_BY'] = 'i.id';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
            array(
                'id_zis' => 'ZIS ID',
                'id_miracle' => 'Miracle ID',
            ),
                 $this->buildViewEntry(),
                 array(
                        'diseases_' => 'Diseases',
                        'parents_' => 'Parent(s)',
                        'variants' => 'Total variants imported',
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date_' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date last edited', LEVEL_COLLABORATOR),
                        'analysis_status' => 'Analysis status',
                        'analysis_by_' => 'Analysis by',
                        'analysis_date' => 'Analysis started',
                        'analysis_approved_by' => 'Analysis approved by',
                        'analysis_approved_date' => 'Analysis approved',
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
                         'db'   => array('i.analysis_date', 'DESC', true)),
                     'analysis_approved_by_' => array(
                         'view' => array('Analysis approved by', 160),
                         'db'   => array('uaa.name', 'ASC', true)),
                     'analysis_approved_date_' => array(
                         'view' => array('Analysis approved', 110),
                         'db'   => array('i.analysis_approved_date', 'DESC', true)),

                      ));
        $this->sSortDefault = 'id';

        // Because the information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();
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
        } else {
            $zData['analysis_status'] = $_SETT['analysis_status'][$zData['analysis_statusid']];
        }

        return $zData;
    }
}
?>
