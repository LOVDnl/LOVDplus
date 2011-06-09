<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2011-02-16
 * Modified    : 2011-05-31
 * For LOVD    : 3.0-alpha-01
 *
 * Copyright   : 2004-2011 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *
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





class LOVD_Phenotype extends LOVD_Custom {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Phenotype';
    var $bShared = true;





    function LOVD_Phenotype ($sObjectID = '')
    {
        // Default constructor.

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT p.*, ' .
                               'uo.name AS owner ' .
                               'FROM ' . TABLE_PHENOTYPES . ' AS p ' .
                               'LEFT JOIN ' . TABLE_USERS . ' AS uo ON (p.ownerid = uo.id) ' .
                               'WHERE p.id = ?';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'p.*, ' .
                                           'uo.id AS owner, ' . // FIXME; onnodig, je hebt p.ownerid toch al?
                                           'uo.name AS owner_, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_';
        $this->aSQLViewEntry['FROM']     = TABLE_PHENOTYPES . ' AS p ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (p.ownerid = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (p.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (p.edited_by = ue.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'p.id';

        // SQL code for viewing the list of genes
        $this->aSQLViewList['SELECT']   = 'p.*, ' .
                                          'p.id AS phenotypeid, ' .
                                          'uo.name AS owner';
        $this->aSQLViewList['FROM']     = TABLE_PHENOTYPES . ' AS p ' .
                                          'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (p.ownerid = uo.id)';

        $this->sObjectID = $sObjectID;

        // Run parent constructor to find out about the custom columns.
        parent::LOVD_Custom();
        
        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 $this->buildViewEntry(),
                 array(
                        'owner_' => 'Owner name',
                        // FIXME; statusid weer toevoegen.
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date_' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date last edited', LEVEL_COLLABORATOR),
                      ));

        // Because the gene information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList = array_merge(
                 array(
                        'phenotypeid' => array(
                                    'view' => array('Phenotype ID', 110),
                                    'db'   => array('phenotypeid', 'ASC', 'INT_UNSIGNED')),
                        'id' => array(
                                    'view' => array('Phenotype ID', 110),
                                    'db'   => array('p.id', 'ASC', true)),
                      ),
                 $this->buildViewList(),
                 array(
                        'owner' => array(
                                    'view' => array('Owner', 140),
                                    'db'   => array('owner', 'ASC', true)),
                        'individualid' => array(
                                    'view' => array('Individual ID', 70),
                                    'db'   => array('p.individualid', 'ASC', true)),
                        'diseaseid' => array(
                                    'view' => array('Disease ID', 70),
                                    'db'   => array('p.diseaseid', 'ASC', true)),
                      ));

        $this->sSortDefault = 'id';
    }





    function checkFields ($aData)
    {
        global $_AUTH;

        // Mandatory fields.
        // FIXME; if empty, just define as an empty array in the class header?
        // IVO: Already done, see objects.php.
        if (ACTION == 'edit') {
            $this->aCheckMandatory[] = 'password';
        }

        parent::checkFields($aData);

        // FIXME; move to object_custom.php. Zie ook opmerkingen over deze code in andere files.
        // FIXME; this set of if's can be made more efficient.
        // Dit moet ingewikkelder; wie wat kan aanpassen is ook afhankelijk van wie de owner is, denk ik.
        if (isset($_POST['ownerid'])) {
            if (!empty($_POST['ownerid']) && $_AUTH['level'] >= LEVEL_CURATOR) {
                $q = lovd_queryDB('SELECT * FROM ' . TABLE_USERS . ' WHERE id = ?', array($_POST['ownerid']));
                if (!$q) {
                    // FIXME; clearly they haven't used the selection list, so possibly a different error message needed?
                    lovd_errorAdd('ownerid' ,'Please select a proper owner from the \'Owner of this individual\' selection box.');
                }
            } elseif (empty($_POST['ownerid']) && $_AUTH['level'] >= LEVEL_CURATOR) {
                lovd_errorAdd('ownerid' ,'Please select a proper owner from the \'Owner of this individual\' selection box.');
            }
        } else {
            if (!empty($_POST['ownerid']) && $_AUTH['level'] < LEVEL_CURATOR) {
                // FIXME; this is a hack attempt. We should consider logging this. Or just plainly ignore the value.
                lovd_errorAdd('ownerid' ,'Not allowed to change \'Owner of this individual\'.');
            }
        }

        if (ACTION == 'edit' && (!isset($aData['password']) || md5($aData['password']) != $_AUTH['password'])) {
            lovd_errorAdd('password', 'Please enter your correct password for authorization.');
        }

        lovd_checkXSS();
    }





    function getForm ()
    {
        // Build the form.
        global $_AUTH, $_SETT;

        if (ACTION == 'edit') {
            global $zData;
            $_POST['diseaseid'] = $zData['diseaseid'];
        }

        list($sDisease) = mysql_fetch_row(lovd_queryDB('SELECT name FROM ' . TABLE_DISEASES . ' WHERE id=?', array($_POST['diseaseid'])));

        $aSelectOwner = array();

        if ($_AUTH['level'] >= LEVEL_CURATOR) {
            // FIXME; sorteren ergens op? Naam? Of land? Kijk naar hoe dit in LOVD 2.0 geregeld is.
            $q = lovd_queryDB('SELECT id, name FROM ' . TABLE_USERS);
            while ($z = mysql_fetch_assoc($q)) {
                $aSelectOwner[$z['id']] = $z['name'];
            }
            $aFormOwner = array('Owner of this individual', '', 'select', 'ownerid', 1, $aSelectOwner, false, false, false);
        } else {
            // FIXME; dit moet dan dus de owner zijn, mag die de status niet aanpassen (niet publiek -> wel publiek) of een publieke entry bewerken?
            // Overigens, in jouw code mogen alleen managers hier komen... Dit moet even goed worden uitgedacht.
            $aFormOwner = array('Owner of this individual', '', 'print', '<B>' . $_AUTH['name'] . '</B>');
        }

        // Array which will make up the form table.
        $this->aFormData = array_merge(
                 array(
                        array('POST', '', '', '', '40%', '14', '60%'),
                        array('', '', 'print', '<B>Phenotype information related to ' . $sDisease . '</B>'),
                        'hr',
                      ),
                 $this->buildViewForm(),
                 array(
                        'hr',
                        'skip',
                        array('', '', 'print', '<B>General information</B>'),
                        'hr',
                        $aFormOwner,
                        'hr',
'authorization_skip' => 'skip',
 'authorization_hr1' => 'hr',
     'authorization' => array('Enter your password for authorization', '', 'password', 'password', 20),
 'authorization_hr2' => 'hr',
                        'skip',
                      ));
                      
        if (ACTION != 'edit') {
            unset($this->aFormData['authorization_skip'], $this->aFormData['authorization_hr1'], $this->aFormData['authorization'], $this->aFormData['authorization_hr2']);
        }

        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        if ($sView == 'list') {
            // FIXME; welke van deze zijn nog nodig, nu objects.php zelf al waardes gaat voorspellen?
            $zData['row_id'] = $zData['id'];
            $zData['row_link'] = 'phenotypes/' . rawurlencode($zData['id']);
            // FIXME; het is beter id niet te overschrijven, misschien id_ of een ander veld gebruiken?
            $zData['id'] = '<A href="' . $zData['row_link'] . '" class="hide">' . $zData['id'] . '</A>';
        } else {
            $zData['owner_'] = '<A href="users/' . $zData['owner'] . '">' . $zData['owner_'] . '</A>';
        }

        return $zData;
    }





    function setDefaultValues ()
    {
        global $_AUTH;
        
        $_POST['ownerid'] = $_AUTH['id'];
        $this->initDefaultValues();
    }
}
?>
