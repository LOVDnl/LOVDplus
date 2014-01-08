<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-01-03
 * Modified    : 2014-01-08
 * For LOVD    : 3.0-09
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}
// Require parent class definition.
require_once ROOT_PATH . 'class/object_screenings.php';





class LOVD_ScreeningMOD extends LOVD_Screening {
    // This class extends the basic Object class and it handles the Screening object.





    function __construct ()
    {
        // Default constructor.
        global $_AUTH;

        // Run parent constructor to find out about the custom columns.
        parent::__construct();

        // And now we're going to overwrite the whole damn thing.
        $this->sObject = 'ScreeningMOD';
        $this->sTable  = 'TABLE_SCREENINGS';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 's.*, ' .
                                           'i.statusid AS individual_statusid, ' .
                                           'GROUP_CONCAT(DISTINCT "=\"", s2g.geneid, "\"" SEPARATOR "|") AS search_geneid, ' .
                                           'IF(s.variants_found = 1 AND COUNT(s2v.variantid) = 0, -1, COUNT(DISTINCT ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? 's2v.variantid' : 'vog.id') . ')) AS variants_found_, ' .
                                           'uo.name AS owned_by_, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_SCREENINGS . ' AS s ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) ' .
                                           ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' :
                                               'LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id AND (vog.statusid >= ' . STATUS_MARKED . (!$_AUTH? '' : ' OR vog.created_by = "' . $_AUTH['id'] . '" OR vog.owned_by = "' . $_AUTH['id'] . '"') . ')) ') .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.owned_by = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (s.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (s.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 's.id';

        // SQL code for viewing the list of screenings
        $this->aSQLViewList['SELECT']   = 's.*, ' .
                                          's.id AS screeningid, ' .
                                          'IF(s.variants_found = 1 AND COUNT(s2v.variantid) = 0, -1, COUNT(DISTINCT ' . ($_AUTH['level'] >= LEVEL_COLLABORATOR? 's2v.variantid' : 'vog.id') . ')) AS variants_found_, ' .
                                          'GROUP_CONCAT(DISTINCT s2g.geneid SEPARATOR ", ") AS genes, ' .
                                          ($_AUTH['level'] < LEVEL_COLLABORATOR? '' :
                                              'CASE i.statusid WHEN ' . STATUS_MARKED . ' THEN "marked" WHEN ' . STATUS_HIDDEN .' THEN "del" END AS class_name, ') .
                                          'uo.name AS owned_by_, ' .
                                          'CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner';
        $this->aSQLViewList['FROM']     = TABLE_SCREENINGS . ' AS s ' .
                                          'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (s.id = s2v.screeningid) ' .
                                          ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' :
                                              'LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS vog ON (s2v.variantid = vog.id AND (vog.statusid >= ' . STATUS_MARKED . (!$_AUTH? '' : ' OR vog.created_by = "' . $_AUTH['id'] . '" OR vog.owned_by = "' . $_AUTH['id'] . '"') . ')) ') .
                                          'LEFT OUTER JOIN ' . TABLE_SCR2GENE . ' AS s2g ON (s.id = s2g.screeningid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.owned_by = uo.id)';
        $this->aSQLViewList['GROUP_BY'] = 's.id';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 $this->buildViewEntry(),
                 array(
                        'variants_found_' => 'Variants found?',
                      ));

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'id' => array(
                                    'view' => array('Screening ID', 110),
                                    'db'   => array('s.id', 'ASC')),
                        'individualid' => array(
                                    'view' => false,
                                    'db'   => array('s.individualid', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'variants_found_' => array(
                                    'view' => array('Variants found', 100),
//                                    'db'   => array('variants_found_', 'ASC', 'INT_UNSIGNED')),
                                    'db'   => array('variants_found_', 'ASC')), // Do not allow search, the search boxes are all narrow because the table is not displayed when the JS is run.
                        'created_date' => array(
                                    'view' => array('Date created', 130),
                                    'db'   => array('s.created_date', 'ASC')),
                      ));
        $this->sSortDefault = 'id';

        // Hide some custom columns from view.
        unset($this->aColumnsViewList['Screening/SNP_overlap']);
        unset($this->aColumnsViewList['Screening/Derived_gender']);
        unset($this->aColumnsViewList['Screening/Covered_exome/Fraction']);
        unset($this->aColumnsViewList['Screening/Father/Covered_exome/Fraction']);
        unset($this->aColumnsViewList['Screening/Mother/Covered_exome/Fraction']);
        unset($this->aColumnsViewList['Screening/Reads_on_target/Fraction']);
        unset($this->aColumnsViewList['Screening/Father/Reads_on_target/Fraction']);
        unset($this->aColumnsViewList['Screening/Mother/Reads_on_target/Fraction']);
        unset($this->aColumnsViewList['Screening/Analysis_restricted']);

        // Also make sure the custom cols are not searchable.
        foreach ($this->aColumnsViewList as $sCol => $aCol) {
            if (isset($aCol['db'][2])) {
                $aCol['db'][2] = false;
            }
            $this->aColumnsViewList[$sCol] = $aCol;
        }

        // Because the information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        global $_AUTH, $_PE, $_SETT;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'entry') {
            $zData['individualid_'] = '<A href="individuals/' . $zData['individualid'] . '">' . $zData['individualid'] . '</A>';
            if ($_AUTH['level'] >= LEVEL_COLLABORATOR) {
                $zData['individualid_'] .= ' <SPAN style="color : #' . $this->getStatusColor($zData['individual_statusid']) . '">(' . $_SETT['data_status'][$zData['individual_statusid']] . ')</SPAN>';
            }
            if ($_PE[0] == 'individuals') {
                // Screenings VE op Individuals VE.
                $zData['variants_found_'] .= ' (<A href="screenings/' . $zData['id'] . '">See all</A>)';
            }
        }
        $zData['variants_found_'] = ($zData['variants_found_'] == -1? 'Not yet submitted' : $zData['variants_found_']);

        return $zData;
    }
}
?>
