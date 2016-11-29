<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-07
 * Modified    : 2016-11-29
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Anthony Marty <anthony.marty@unimelb.edu.au>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
    // This class extends the basic Object class and it handles the GenePanelGene object.
    var $sObject = 'Gene_Panel_Gene';





    function __construct ($nID = '')
    {
        // Default constructor.
        $this->sTable  = 'TABLE_GP2GENE';

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT gp2g.*, gp.pmid_mandatory
            FROM ' . TABLE_GP2GENE . ' AS gp2g INNER JOIN ' . TABLE_GENE_PANELS . ' AS gp ON (gp2g.genepanelid = gp.id)
            WHERE gp2g.genepanelid = ? AND gp2g.geneid = ?';

        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'gp2g.*, gp2g.geneid AS id, uc.name AS created_by_, ue.name AS edited_by_, t.id_ncbi AS transcript_ncbi, g.id_omim';
        $this->aSQLViewEntry['FROM']     = TABLE_GP2GENE . ' AS gp2g
         LEFT OUTER JOIN ' . TABLE_GENES . ' AS g ON (gp2g.geneid = g.id) ' .
        'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (gp2g.created_by = uc.id) ' .
        'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (gp2g.edited_by = ue.id) ' .
        'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (gp2g.transcriptid = t.id)';

        // SQL code for viewing the list of gene panel genes
        $this->aSQLViewList['SELECT']   = 'gp2g.*, gp2g.geneid AS id, uc.name AS created_by_, t.id_ncbi AS transcript_ncbi, g.id_omim';
        $this->aSQLViewList['FROM']     = TABLE_GP2GENE . ' AS gp2g ' .
        'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (gp2g.created_by = uc.id) ' .
        'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (gp2g.transcriptid = t.id) ' .
        'LEFT OUTER JOIN ' . TABLE_GENES . ' AS g ON (gp2g.geneid = g.id)';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry =
            array(
                'genepanelid' => 'Gene Panel ID',
                'geneid' => 'Gene Symbol',
                'transcript_ncbi_' => 'Transcript ID',
                'inheritance' => 'Inheritance',
                'pmid_' => 'PubMed',
                'id_omim_' => 'OMIM',
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
                'transcript_ncbi_' => array(
                    'view' => array('Transcript', 100),
                    'db'   => array('t.id_ncbi', 'ASC', true),
                    'legend' => array('The preferred transcript.')),
                'inheritance' => array(
                    'view' => array('Inheritance', 80),
                    'db'   => array('gp2g.inheritance', 'ASC', true),
                    'legend' => array('The mode of inheritance.')),
                'pmid_' => array(
                    'view' => array('PubMed', 60),
                    'db'   => array('gp2g.pmid', 'ASC', false),
                    'legend' => array('PubMed ID.')),
                'id_omim_' => array(
                    'view' => array('OMIM', 60),
                    'db'   => array('g.id_omim', 'ASC', false),
                    'legend' => array('OMIM ID.')),
                'created_by_' => array(
                    'view' => array('Added By', 110),
                    'db'   => array('uc.name', 'DESC', true),
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





    function checkFields ($aData, $zData = false)
    {
        // Checks fields before submission of data.

        if ($zData['pmid_mandatory']) {
            // This gene panel has the option set that the PMID field may not be empty.
            $this->aCheckMandatory[] = 'pmid';
        }

        parent::checkFields($aData);

        // If the PMID ID has been filled in, but it's just a zero, complain as well.
        // We won't check if it actually exists, but it has to be a bit meaningful.
        if (isset($aData['pmid']) && !preg_match('/^[1-9]\d{6,}$/', $aData['pmid'])) {
            // The PMIDs of the last 25 years all are 8 digits, but just
            // in case we're referring to something really, really old...
            lovd_errorAdd('pmid', 'The PubMed ID has to be at least seven digits long and cannot start with a \'0\'.');
        }
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
        $aTranscripts = $_DB->query('SELECT id, id_ncbi FROM ' . TABLE_TRANSCRIPTS . ' WHERE geneid = ?', array($zData['geneid']))->fetchAllCombine();

        // If we have found some transcripts for this gene then show them here otherwise show no transcripts available.
        if (count($aTranscripts)) {
            $aTranscriptsForm =  array('' => '-- select --') + $aTranscripts;
        } else {
            $aTranscriptsForm = array('' => '-- no transcripts available --');
        }

        // If updating this, also update the code in gene_panels.php.
        $aInheritance = array(
            '' => '-- select --',
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
                // Unfortunately, without the ID of the gene panel, we can not see whether or not the PMID field is mandatory or not.
                // I guess it's better to have it suggest that it's mandatory, but it's not, then to actually say it's optional but it's not.
                array('PubMed ID', '', 'text', 'pmid', 20),
                array('Remarks (optional)', '', 'textarea', 'remarks', 70, 3),
                'hr',
                'skip'
            );

        return parent::getForm();
    }





    function prepareData ($zData = '', $sView = 'list') {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        // Change the formatting based on the type of view
        if ($sView != 'list') {
            $zData['genepanelid'] = '<A href="gene_panels/' . $zData['genepanelid'] . '">' . $zData['genepanelid'] . '</A>';
        }
        // Only format this viewlist if we are not downloading it
        $zData['pmid_'] = $zData['pmid'];
        if (isset($zData['id_omim'])) {
            // The REV view doesn't have this data, since it's taken from a different table.
            $zData['id_omim_'] = $zData['id_omim'];
        }
        $zData['transcript_ncbi_'] = $zData['transcript_ncbi'];
        if (FORMAT == 'text/html') {
            // Format the pubmed URL.
            if ($zData['pmid']) {
                $zData['pmid_'] = '<SPAN' . ($sView != 'list' ? '' : ' onclick="cancelParentEvent(event);"') . '><A href="' . lovd_getExternalSource('pubmed_article', $zData['pmid'], true) . '" target="_blank">' . $zData['pmid'] . '</A></SPAN>';
            }
            // Format the OMIM URL.
            if (!empty($zData['id_omim'])) {
                $zData['id_omim_'] = '<SPAN' . ($sView != 'list' ? '' : ' onclick="cancelParentEvent(event);"') . '><A href="' . lovd_getExternalSource('omim', $zData['id_omim'], true) . '" target="_blank">' . $zData['id_omim'] . '</A></SPAN>';
            }
            // Create a link to a transcript.
            if ($zData['transcriptid']) {
                $zData['transcript_ncbi_'] = '<SPAN' . ($sView != 'list' ? '' : ' onclick="cancelParentEvent(event);"') . '><A href="transcripts/' . $zData['transcriptid'] . '">' . $zData['transcript_ncbi'] . '</A></SPAN>';
            }
        }
        return $zData;
    }
}
?>
