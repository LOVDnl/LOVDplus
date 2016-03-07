<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-07
 * Modified    : 2016-03-07
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Anthony Marty <anthony.marty@unimelb.edu.au>
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
require_once ROOT_PATH . 'class/objects.php';





class LOVD_GenePanelGene extends LOVD_Object {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Gene_Panel_Gene';





    function __construct ($nID = '')
    {
        // Default constructor.
        global $_AUTH;
        $this->sTable  = 'TABLE_GP2GENE';

        // Check to see if the gene panel ID is stored in a form coming from AJAX otherwise use the value passed to the constructor
        if (isset($_GET['id'])) {
            $nID = $_GET['id'];
        }

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'gp2g.*, gp2g.geneid AS id, uc.name AS created_by_, ue.name AS edited_by_ ';
        $this->aSQLViewEntry['FROM']     = TABLE_GP2GENE . ' AS gp2g ' .
        'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (gp2g.created_by = uc.id) ' .
        'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (gp2g.created_by = ue.id) ';

        // SQL code for viewing the list of gene panel genes
        $this->aSQLViewList['SELECT']   = 'gp2g.*, gp2g.geneid AS id, uc.name AS created_by_';
        $this->aSQLViewList['FROM']     = TABLE_GP2GENE . ' AS gp2g ' .
        'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (gp2g.created_by = uc.id) ';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
            array(
                'geneid' => 'Gene Symbol',
                'transcriptid' => 'Transcript ID',
                'inheritance' => 'Inheritance',
                'id_omim' => 'OMIM ID',
                'pmid' => 'PubMed ID',
                'remarks' => 'Remarks',
                'created_by_' => 'Created by',
                'created_date' => 'Created Date',
                'edited_by_' => 'Edited by',
                'edited_date' => 'Edited Date',
            );

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
            array(
                // We need to define this here, to make LOVD_Object::getCount() cooperate with us.
                // Since we don't have an 'id' column in this table, getCount() will look for its alternative name here.
                'id' => array(
                    'view' => false,
                    'db'   => array('gp2g.geneid', 'ASC', true)),
                'genepanelid' => array(
                    'view' => false,
                    'db'   => array('gp2g.genepanelid', 'ASC', true)),
                'geneid' => array(
                    'view' => array('Symbol', 100),
                    'db'   => array('gp2g.geneid', 'ASC', true),
                    'legend' => array('The gene symbol.')),
                'transcriptid' => array(
                    'view' => array('Transcript', 100),
                    'db'   => array('gp2g.transcriptid', 'ASC', true),
                    'legend' => array('The preferred transcript.')),
                'inheritance' => array(
                    'view' => array('Inheritance', 80),
                    'db'   => array('gp2g.inheritance', 'ASC', true),
                    'legend' => array('The mode of inheritance.')),
                'id_omim' => array(
                    'view' => array('OMIM ID', 60),
                    'db'   => array('gp2g.id_omim', 'ASC', true),
                    'legend' => array('OMIM ID.')),
                'pmid' => array(
                    'view' => array('PubMed ID', 60),
                    'db'   => array('gp2g.pmid', 'ASC', true),
                    'legend' => array('PubMed ID.')),
                'created_by_' => array(
                    'view' => array('Added By', 110),
                    'db'   => array('created_by_', 'DESC', true),
                    'legend' => array('The user added this gene to this gene panel.')),
                'created_date' => array(
                    'view' => array('Added Date', 110),
                    'db'   => array('gp2g.created_date', 'DESC', true),
                    'legend' => array('The date the gene was added to this gene panel.')),
            );
        $this->sSortDefault = 'geneid';
        $this->nID = $nID;

        parent::__construct();
    }





    function viewEntry ($nID = false) {
        global $_DB;

        list($nGenePanelID, $sGeneID) = explode(',', $nID);
        $this->aSQLViewEntry['WHERE'] .= (empty($this->aSQLViewEntry['WHERE'])? '' : ' AND ') . 'gp2g.genepanelid = \'' . $nGenePanelID . '\'';

        // Before passing this on to parent::viewEntry(), perform a standard getCount() check on the genepanel ID,
        // to make sure that we won't get a query error when the combination of GeneID/GenePanelID does not yield
        // any results. Easiest is then to fake a wrong $nID such that parent::viewEntry() will complain.
        if (!$_DB->query('SELECT COUNT(*) FROM ' . TABLE_GP2GENE . ' WHERE geneid = ? AND genepanelid = ?', array($sGeneID, $nGenePanelID))->fetchColumn()) {
            $sGeneID = -1;
        }
        parent::viewEntry($sGeneID);
    }
}
?>
