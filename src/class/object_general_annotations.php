<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-12
 * Modified    : 2016-06-03
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Anthony Marty <anthony.marty@unimelb.edu.au>
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

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}
// Require parent class definition.
require_once ROOT_PATH . 'class/object_custom.php';





class LOVD_GeneralAnnotation extends LOVD_Custom {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'General_Annotation';
    var $sCategory = 'GeneralAnnotation';
    var $sTable = 'TABLE_GENERAL_ANNOTATIONS';
    var $bShared = false;





    function __construct ()
    {
        // Default constructor.
        global $_AUTH;

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'ga.*, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_GENERAL_ANNOTATIONS . ' AS ga ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (ga.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (ga.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'ga.id';

        // Run parent constructor to find out about the custom columns.
        parent::__construct();

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 array(
                        'TableHeader_General' => 'General annotations',
                        'effectid' => 'Affects function',
                      ),
                 $this->buildViewEntry(),
                 array(
                        'created_by_' => 'Created by',
                        'created_date' => 'Date created',  // Todo: why does it not have underscore at end like edited_date?
                        'edited_by_' => 'Last edited by',
                        'edited_date_' => 'Date last edited',
                      ));

//        $this->unsetColsByAuthLevel(); // TODO AM DO we need this? Check with Ivo.
    }


    function checkFields ($aData, $zData = false)
    {
        // Checks fields before submission of data.
        global $_DB;

        // Mandatory fields.
       // $this->aCheckMandatory =
       //     array(
       //         'name',
       //         'description',
       //     );

        parent::checkFields($aData);

        lovd_checkXSS();  // todo: is this required here? it's not in object_gene_panels checkFields
    }




    function getForm ()
    {
        // Build the form.
        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            return parent::getForm();
        }
        global $_SETT;

        $this->aFormData = array_merge(
            array(
                array('POST', '', '', '', '50%', '14', '50%'),
            //    array('', '', 'print', '<B>General information</B>'),
            //    'hr',
            //    'skip'
            ),
            $this->buildForm(),

            array(array('Affects function', '', 'select', 'effectid', 6, $_SETT['var_effect'], false, false, false))
        );

        return parent::getForm();
    }




    function prepareData ($zData = '', $sView = 'list')
    {
        global $_SETT;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'entry') {
            if ($zData['effectid'] != '') {
                // Replace the effectid with the effect text if it has been set.
                $zData['effectid'] = $_SETT['var_effect'][$zData['effectid']];
            }
        }

        return $zData;
    }
}
?>
