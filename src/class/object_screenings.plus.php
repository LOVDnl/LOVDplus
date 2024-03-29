<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-01-03
 * Modified    : 2023-11-16
 * For LOVD+   : 3.0-29
 *
 * Copyright   : 2004-2023 Leiden University Medical Center; http://www.LUMC.nl/
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}
// Require parent class definition.
require_once ROOT_PATH . 'class/object_screenings.php';





class LOVD_ScreeningPLUS extends LOVD_Screening
{
    // This class extends the Screening class and it handles the Screenings within LOVD+.
    var $sObject = 'ScreeningPLUS';
    var $sCategory = 'Screening';
    var $sTable  = 'TABLE_SCREENINGS';





    function __construct ()
    {
        // Default constructor.

        // Run parent constructor to find out about the custom columns.
        parent::__construct();

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 's.*, ' .
                                           'i.statusid AS individual_statusid, ' .
                                           'IF(s.variants_found = 1 AND EXISTS(SELECT 1 FROM ' . TABLE_SCR2VAR . ' AS s2v WHERE s2v.screeningid = s.id) = 0, -1, (SELECT COUNT(s2v.variantid) FROM ' . TABLE_SCR2VAR . ' AS s2v WHERE s2v.screeningid = s.id)) AS variants_found_, ' .
                                           'uo.name AS owned_by_, ' .
                                           'ua.name AS analysis_by_, ' .
                                           'uaa.name AS analysis_approved_by_, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_SCREENINGS . ' AS s ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.owned_by = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ua ON (s.analysis_by = ua.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uaa ON (s.analysis_approved_by = uaa.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (s.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (s.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 's.id';

        // SQL code for viewing the list of screenings.
        $this->aSQLViewList['SELECT']   = 's.*, ' .
                                          's.id AS screeningid, ' .
                                          'IF(s.variants_found = 1 AND EXISTS(SELECT 1 FROM ' . TABLE_SCR2VAR . ' AS s2v WHERE s2v.screeningid = s.id) = 0, -1, (SELECT COUNT(s2v.variantid) FROM ' . TABLE_SCR2VAR . ' AS s2v WHERE s2v.screeningid = s.id)) AS variants_found_, ' .
                                          'uo.name AS owned_by_, ' .
                                          'ua.name AS analysis_by_, ' .
                                          'uaa.name AS analysis_approved_by_, ' .
                                          'ds.name AS analysis_status, ' .
                                          'CONCAT_WS(";", uo.id, uo.name, uo.email, uo.institute, uo.department, IFNULL(uo.countryid, "")) AS _owner';
        $this->aSQLViewList['FROM']     = TABLE_SCREENINGS . ' AS s ' .
                                          'LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ua ON (s.analysis_by = ua.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uaa ON (s.analysis_approved_by = uaa.id) ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (s.owned_by = uo.id)
                                           LEFT OUTER JOIN ' . TABLE_ANALYSIS_STATUS . ' AS ds ON (s.analysis_statusid = ds.id)';
        $this->aSQLViewList['GROUP_BY'] = 's.id';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 $this->buildViewEntry(),
                 array(
                     'analysis_status' => 'Analysis status',
                     'analysis_by_' => 'Analysis by',
                     'analysis_date' => 'Analysis started',
                     'analysis_approved_by_' => 'Analysis approved by',
                     'analysis_approved_date' => 'Analysis approved',
                        'variants_found_link' => 'Variants found?',
                        'variants_to_be_confirmed_' => 'Variants to be confirmed',
                        'curation_progress_' => 'Curation progress',
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
                     'curation_progress_' => array(
                         'view' => array('Curation progress', 100),
                         'db'   => array('curation_progress_', false)),
                     'variants_found_' => array(
                         'view' => array('Variants found', 100),
                         'db'   => array('variants_found_', 'ASC', 'INT_UNSIGNED')),
                     'created_date_' => array(
                         'view' => array('Imported', 80),
                         'db'   => array('s.created_date', 'DESC', true)),
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

        // Make sure the custom cols are not searchable, if they're visible.
        // (we need the invisible individualid column to be searchable)
        foreach ($this->aColumnsViewList as $sCol => $aCol) {
            if (isset($aCol['db'][2]) && $aCol['view']) {
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
        global $_AUTH, $_DB, $_PE, $_SETT, $_INI;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'list') {
            $zData['created_date_']  = substr($zData['created_date'], 0, 10);
            $zData['analysis_date_'] = substr($zData['analysis_date'], 0, 10);
            $zData['analysis_approved_date_'] = substr($zData['analysis_approved_date'], 0, 10);
        } else {
            $zData['individualid_'] = '<A href="individuals/' . $zData['individualid'] . '">' . $zData['individualid'] . '</A>';
            if ($_AUTH && $_AUTH['level'] >= $_SETT['user_level_settings']['see_nonpublic_data']) {
                $zData['individualid_'] .= ' <SPAN style="color : #' . $this->getStatusColor($zData['individual_statusid']) . '">(' . $_SETT['data_status'][$zData['individual_statusid']] . ')</SPAN>';
            }
            $zData['variants_found_link'] = $zData['variants_found_'];
            if (in_array($_PE[0], array('ajax', 'individuals')) && $zData['variants_found_'] > 0) {
                // Screenings VE op Individuals VE.
                $zData['variants_found_link'] .= ' (<A href="screenings/' . $zData['id'] . '">See all</A>)';
            }
            $zData['analysis_status'] = $_SETT['analysis_status'][$zData['analysis_statusid']];
            // Add link to action, depending on level and current status.
            $sOpen = $sClose = '';
            if ($_AUTH) {
                if ($zData['analysis_statusid'] == ANALYSIS_STATUS_IN_PROGRESS) {
                    if ($_AUTH['level'] >= LEVEL_OWNER) {
                        $sClose = 'Close';
                    }
                } elseif ($zData['analysis_statusid'] == ANALYSIS_STATUS_CLOSED) {
                    if ($_AUTH['level'] >= LEVEL_OWNER && $zData['analysis_approved_by'] == $_AUTH['id']) {
                        $sOpen = 'Re-open for analysis';
                    }
                    if ($_AUTH['level'] >= LEVEL_MANAGER) {
                        $sClose = 'Close as waiting for confirmation';
                    }
                } elseif ($zData['analysis_statusid'] == ANALYSIS_STATUS_WAIT_CONFIRMATION) {
                    if ($_AUTH['level'] >= LEVEL_MANAGER) {
                        $sOpen = 'Re-open';
                        if ($_AUTH['level'] >= LEVEL_ADMIN) {
                            $sClose = 'Confirm';
                        }
                    }
                } elseif ($zData['analysis_statusid'] == ANALYSIS_STATUS_CONFIRMED) {
                    if ($_AUTH['level'] >= LEVEL_ADMIN) {
                        $sOpen = 'Re-open';
                    }
                }
            }
            // In the following two links I cannot use CURRENT_PATH, because this file can also have been loaded through ajax/viewentry.php!
            if ($sOpen) {
                $zData['analysis_status'] .= ' (<A href="individuals/' . $zData['individualid'] . '/analyze/' . $zData['id'] . '?open">' . $sOpen . '</A>)';
            }
            if ($sClose) {
                $zData['analysis_status'] .= ' (<A href="individuals/' . $zData['individualid'] . '/analyze/' . $zData['id'] . '?close">' . $sClose . '</A>)';
            }
        }
        if (lovd_verifyInstance('mgha', false)) {
            // Just do a separate query for the variants to be confirmed (instead of modifying the VE query).
            $zData['variants_to_be_confirmed_'] = $_DB->q('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) WHERE vog.curation_statusid = ? AND s2v.screeningid = ?', array(CUR_STATUS_REQUIRES_CONFIRMATION, $zData['id']))->fetchColumn();
        } else {
            // Just do a separate query for the variants to be confirmed (instead of modifying the VE query).
            $zData['variants_to_be_confirmed_'] = $_DB->q('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) WHERE vog.curation_statusid IN (?, ?) AND s2v.screeningid = ?', array(CUR_STATUS_VARIANT_OF_INTEREST, CUR_STATUS_REQUIRES_CONFIRMATION, $zData['id']))->fetchColumn();
            if ($zData['variants_to_be_confirmed_']) {
                $zData['variants_to_be_confirmed_'] .= ' (<A href="screenings/' . $zData['id'] . '?downloadToBeConfirmed">download</A>)';
            }
        }
        if ($_INI['instance']['name'] == 'leiden') {
            if ($zData['variants_to_be_confirmed_']) {
                if (($_AUTH['level'] >= LEVEL_OWNER && $zData['analysis_statusid'] < ANALYSIS_STATUS_CLOSED) ||
                    ($_AUTH['level'] >= LEVEL_MANAGER && $zData['analysis_statusid'] < ANALYSIS_STATUS_WAIT_CONFIRMATION)
                ) {
                    // Managers are allowed to export the variants as well.
                    // There are now two ways of doing this. The "normal" txt export to Prinia that will then forward
                    //  the data to Miracle, or a direct XML export to Miracle.
                    $zData['variants_to_be_confirmed_'] .= ' (<A id="export_variantsTXT" href="#" onclick="$.get(\'screenings/' . $zData['id'] . '?exportToBeConfirmedTXT\',function(sResponse){if(sResponse.substring(0,1)==\'1\'){alert(\'Successfully exported \'+sResponse.substring(2)+\' lines of variant data.\');$(\'#export_variantsTXT\').replaceWith($(\'#export_variantsTXT\').html());}else{alert(\'Error while exporting file:\n\'+sResponse);}}).fail(function(){alert(\'Error while exporting file.\');});return false;">export to Prinia</A>)';
                    $zData['variants_to_be_confirmed_'] .= ' (<A id="export_variantsXML" href="#" onclick="$.get(\'screenings/' . $zData['id'] . '?exportToBeConfirmedXML\',function(sResponse){if(sResponse.substring(0,1)==\'1\'){alert(\'Successfully exported \'+sResponse.substring(2)+\' variants.\');$(\'#export_variantsXML\').replaceWith($(\'#export_variantsXML\').html());}else{alert(\'Error while exporting file:\n\'+sResponse);}}).fail(function(){alert(\'Error while exporting file.\');});return false;">export to Miracle</A>)';
                }
            }
        }

        $zData['curation_progress_'] = $_DB->q('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) WHERE vog.curation_statusid >= ? AND vog.curation_statusid != ? AND s2v.screeningid = ?', array(CUR_STATUS_CURATED_REPORTABLE, CUR_STATUS_NOT_FOR_CURATION, $zData['id']))->fetchColumn() . ' of ' . $_DB->q('SELECT COUNT(*) FROM ' . TABLE_VARIANTS . ' AS vog INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) WHERE vog.curation_statusid IS NOT NULL AND vog.curation_statusid != ? AND s2v.screeningid = ?', array(CUR_STATUS_NOT_FOR_CURATION, $zData['id']))->fetchColumn() . ' variants curated';

        return $zData;
    }
}
?>
