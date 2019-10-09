<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-22
 * Modified    : 2018-03-21
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2018 Leiden University Medical Center; http://www.LUMC.nl/
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
require_once ROOT_PATH . 'class/object_gene_panel_genes.php';





class LOVD_GenePanelGeneREV extends LOVD_GenePanelGene
{
    // This class extends the GenePanelGene class and it handles the Gene Panels' Genes' Revisions.
    var $sObject = 'Gene_Panel_Gene_REV';





    function __construct ($nID = '')
    {
        // Default constructor.

        // For all the defaults.
        parent::__construct();

        $this->sTable  = 'TABLE_GP2GENE_REV';

        // SQL code for viewing the list of gene panel genes
        $this->aSQLViewList['SELECT']   = 'gp2g.*, gp2g.geneid AS id, uc.name AS created_by_, ue.name AS edited_by_, ud.name AS deleted_by_, t.id_ncbi AS transcript_ncbi ';
        $this->aSQLViewList['FROM']     = TABLE_GP2GENE_REV . ' AS gp2g ' .
        'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (gp2g.created_by = uc.id) 
         LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (gp2g.edited_by = ue.id)
         LEFT OUTER JOIN ' . TABLE_USERS . ' AS ud ON (gp2g.deleted_by = ud.id) ' .
        'LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (gp2g.transcriptid = t.id)';

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
                'pmid' => array(
                    'view' => array('PubMed', 60),
                    'db'   => array('gp2g.pmid', 'ASC', true),
                    'legend' => array('PubMed ID.')),
                'created_by_' => array(
                    'view' => array('Added By', 110),
                    'db'   => array('uc.name', 'DESC', true),
                    'legend' => array('The user added this gene to this gene panel.')),
                'created_date' => array(
                    'view' => array('Added Date', 110),
                    'db'   => array('gp2g.created_date', 'DESC', true),
                    'legend' => array('The date the gene was added to this gene panel.')),
                'edited_by_' => array(
                    'view' => array('Edited By', 110),
                    'db'   => array('ue.name', 'DESC', true),
                    'legend' => array('The user last edited this gene in this gene panel.')),
                'edited_date' => array(
                    'view' => array('Date edited', 110),
                    'db'   => array('gp2g.edited_date', 'DESC', true),
                    'legend' => array('The date the gene was last edited in this gene panel.')),
                'valid_from' => array(
                    'view' => array('Valid From', 110),
                    'db'   => array('gp2g.valid_from', 'DESC', true),
                    'legend' => array('The date this version became valid.')),
                'valid_to' => array(
                    'view' => array('Valid to', 110),
                    'db'   => array('gp2g.valid_to', 'DESC', true),
                    'legend' => array('The date this version was invalidated by an update.')),
                'reason' => array(
                    'view' => array('Reason', 110),
                    'db'   => array('gp2g.reason', 'ASC', true),
                    'legend' => array('The reason for editing or deleting this entry.')),
                'deleted_' => array(
                    'view' => array('Deleted?', 50, 'style="text-align: center;"'),
                    'db'   => array('gp2g.deleted', 'ASC', true),
                    'legend' => array('Whether this entry has been deleted or not.')),
                'deleted_by_' => array(
                    'view' => array('Deleted by', 110),
                    'db'   => array('ud.name', 'ASC', true),
                    'legend' => array('The user that deleted this gene from this gene panel.')),
            );
        $this->sSortDefault = 'valid_from';
        // And, since SortDefault can handle only one column:
        $this->aSQLViewList['ORDER_BY'] = 'GREATEST(gp2g.valid_from, IF(gp2g.valid_to = "9999-12-31", gp2g.valid_from, gp2g.valid_to)) DESC, gp2g.valid_from DESC, gp2g.valid_to DESC, gp2g.geneid';
        $this->sRowLink = '';
    }





    function prepareData ($zData = '', $sView = 'list') {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        $zData['reason'] = str_replace("\r\n", '<BR>', $zData['reason']);
        $zData['deleted_'] = '';

        // Changes dependent on version.
        if ($zData['valid_to'] == '9999-12-31 00:00:00') {
            // Most current entry.
            $zData['valid_to'] = '(current)';
            $zData['class_name'] = 'colGreen';
        } elseif ($zData['deleted']) {
            // Entry has been deleted.
            $zData['deleted_'] = '<IMG src="gfx/mark_0.png">';
            $zData['class_name'] = 'colRed';
        } elseif ($zData['created_date'] != $zData['valid_from']) {
            // Updated entry.
            $zData['class_name'] = 'colOrange';
        } else {
            // Created entry (not the most current one).
            $zData['class_name'] = 'del';
        }

        return $zData;
    }
}
?>
