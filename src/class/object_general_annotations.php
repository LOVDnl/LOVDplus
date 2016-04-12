<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-04-12
 * Modified    : 2016-04-12
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
                      ),
                 $this->buildViewEntry(),
                 array(
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date last edited', LEVEL_COLLABORATOR),
                      ));

        $this->unsetColsByAuthLevel(); // TODO AM DO we need this? Check with Ivo.
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        return $zData;
    }
}
?>
