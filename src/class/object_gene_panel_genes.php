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

// TODO Reorder the code blocks so as they are in the correct order (see existing files for example)

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
        $this->aSQLViewEntry['SELECT']   = 'gp2g.*, gp2g.geneid AS id, uc.name AS created_by_, ue.name AS edited_by_, t.id_ncbi AS transcript_ncbi ';
        $this->aSQLViewEntry['FROM']     = TABLE_GP2GENE . ' AS gp2g ' .
        'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (gp2g.created_by = uc.id) ' .
        'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (gp2g.created_by = ue.id) ' .
        'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (gp2g.transcriptid = t.id)';

        // SQL code for viewing the list of gene panel genes
        $this->aSQLViewList['SELECT']   = 'gp2g.*, gp2g.geneid AS id, uc.name AS created_by_, t.id_ncbi AS transcript_ncbi ';
        $this->aSQLViewList['FROM']     = TABLE_GP2GENE . ' AS gp2g ' .
        'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (gp2g.created_by = uc.id) ' .
        'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (gp2g.transcriptid = t.id)';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
            array(
                'genepanelid' => 'Gene Panel ID',
                'geneid' => 'Gene Symbol',
                'transcript_ncbi' => 'Transcript ID',
                'inheritance' => 'Inheritance',
                'id_omim' => 'OMIM',
                'pmid' => 'PubMed',
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
                'transcript_ncbi' => array(
                    'view' => array('Transcript', 100),
                    'db'   => array('transcript_ncbi', 'ASC', true),
                    'legend' => array('The preferred transcript.')),
                'inheritance' => array(
                    'view' => array('Inheritance', 80),
                    'db'   => array('gp2g.inheritance', 'ASC', true),
                    'legend' => array('The mode of inheritance.')),
                'id_omim' => array(
                    'view' => array('OMIM', 60),
                    'db'   => array('gp2g.id_omim', 'ASC', false),
                    'legend' => array('OMIM ID.')),
                'pmid' => array(
                    'view' => array('PubMed', 60),
                    'db'   => array('gp2g.pmid', 'ASC', false),
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

        // If we are downloading a plain text viewlist then lets include the extra columns
        if (FORMAT == 'text/plain') {
            $this->aColumnsViewList['remarks'] = array(
                'view' => array('Remarks', 80),
                'db' => array('gp2g.remarks', 'ASC', true)
            );
        }

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





    function prepareData ($zData = '', $sView = 'list') {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        // Change the formatting based on the type of view
        if ($sView == 'list') {

        } else {
            $zData['genepanelid'] = '<A href="gene_panels/' . $zData['genepanelid'] . '">' . $zData['genepanelid'] . '</A>';
        }
        // Only format this viewlist if we are not downloading it
        if (FORMAT != 'text/plain') {

            // Format the pubmed URL
            if ($zData['pmid']) {
                $zData['pmid'] = '<SPAN' . ($sView != 'list' ? '' : ' onclick="cancelParentEvent(event);"') . '><A href="http://www.ncbi.nlm.nih.gov/pubmed/' . $zData['pmid'] . '" target="_blank">PubMed</A></SPAN>';
            }
            // Format the OMIM URL
            if ($zData['id_omim']) {
                $zData['id_omim'] = '<SPAN' . ($sView != 'list' ? '' : ' onclick="cancelParentEvent(event);"') . '><A href="' . lovd_getExternalSource('omim', $zData['id_omim'], true) . '" target="_blank">OMIM</A></SPAN>';
            }
            // Create a link to a transcript
            if ($zData['transcriptid']) {
                $zData['transcript_ncbi'] = '<SPAN' . ($sView != 'list' ? '' : ' onclick="cancelParentEvent(event);"') . '><A href="transcripts/' . $zData['transcriptid'] . '">' . $zData['transcript_ncbi'] . '</A></SPAN>';
            }
        }
        return $zData;
    }





    function getForm ()
    {
        // Build the form.
        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            return parent::getForm();
        }
        global $_DB, $zData;

        // Get the available transcripts for this gene.
        $aTranscripts = $_DB->query('SELECT id, id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ?',array($zData['geneid']))->fetchAllCombine();

        // If we have found some transcripts for this gene then show them here otherwise show no transcripts available.
        if (count($aTranscripts)) {
            $aTranscriptsForm =  array('' => 'Please select...') + $aTranscripts;
        } else {
            $aTranscriptsForm = array('' => 'No transcripts available');
        }

        // If updating this, also update the code in gene_panels.php.
        $aInheritance = array(
            '' => 'Please Select...',
            'Autosomal Recessive' => 'Autosomal Recessive',
            'Dominant' => 'Dominant',
            'X-Linked' => 'X-Linked'
        );

        $this->aFormData =
            array(
                array('POST', '', '', '', '50%', '14', '50%'),
                array('Symbol', '', 'print', $zData['geneid'], 30),
                array('Transcript (optional)', '', 'select', 'transcriptid', 1, $aTranscriptsForm, '', false, false),
                array('Inheritance (optional)', '', 'select', 'inheritance', 1, $aInheritance, '', false, false),
                array('OMIM ID (optional)', '', 'text', 'id_omim', 20),
                array('PubMed ID (optional)', '', 'text', 'pmid', 20),
                array('Remarks (optional)', '', 'textarea', 'remarks', 70, 3),
                'hr','skip'
            );

        return parent::getForm();
    }





    function loadEntry ($nGenePanelID = false, $sGeneID = false)
    {
        // Loads and returns an entry from the database.
        global $_DB, $_T;

        if (empty($nGenePanelID) || empty($sGeneID)) {
            // We were called, but the class wasn't initiated with an ID. Fail.
            lovd_displayError('LOVD-Lib', 'Objects::(' . $this->sObject . ')::loadEntry() - Method didn\'t receive IDs');
        }

        // Build query.
        if ($this->sSQLLoadEntry) {
            $sSQL = $this->sSQLLoadEntry;
        } else {
            $sSQL = 'SELECT * FROM ' . TABLE_GP2GENE . ' WHERE genepanelid = ? and geneid = ?';
        }
        $q = $_DB->query($sSQL, array($nGenePanelID, $sGeneID), false);
        if ($q) {
            $zData = $q->fetchAssoc();
        }
        if (!$q || !$zData) {
            $sError = $_DB->formatError(); // Save the PDO error before it disappears.

            $_T->printHeader();
            if (defined('PAGE_TITLE')) {
                $_T->printTitle();
            }

            if ($sError) {
                lovd_queryError($this->sObject . '::loadEntry()', $sSQL, $sError);
            }

            lovd_showInfoTable('No such ID!', 'stop');

            $_T->printFooter();
            exit;

        }
        // Not sure if I need this below as we are using two IDs to identify a gene panel gene record
        else {
            $this->nID = 1;
        }

        $zData = $this->autoExplode($zData);

        return $zData;
    }





    function checkFields ($aData, $zData = false)
    {
        // Checks fields before submission of data.

        if (!empty($aData['id_omim']) && !preg_match('/^[1-9]\d{5}$/', $aData['id_omim'])) {
            lovd_errorAdd('id_omim', 'The OMIM ID has to be six digits long and cannot start with a \'0\'.');
        }

        // TODO Can we validate the pmid field?

        parent::checkFields($aData);

    }
}
?>
