<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-22
 * Modified    : 2016-04-05
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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





class LOVD_GenePanelGeneREV extends LOVD_GenePanelGene {
    // This class extends the basic GenePanelGene class and it handles the GenePanelGeneREV object.
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
                    'db'   => array('gp2g.valid_from', 'ASC', true),
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
        $this->sSortDefault = 'geneid';
        // And, since SortDefault can handle only one column:
        $this->aSQLViewList['ORDER_BY'] = 'valid_from ASC, valid_to ASC';
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





    // FIXME: This function does not belong here. It does not interact with the
    // Object at all, does not use the Object's methods, it's basically a static
    // method that should be included in a library. It's also just used once, so
    // I'll move the function elsewhere later.
    function displayGenePanelHistory ($nID, $sFromDate, $sToDate)
    {
        global $_DB;

        // Fill in the time if we don't have it already.
        // Format has already been checked, now we can just check for the length.
        // Todo: if this function is called from somewhere else in future, should it not also check the format itself?
        if (strlen($sFromDate) == 10) {
            $sFromDate .= ' 00:00:00'; // Set the 'from' date as the first second of the selected day.
        }
        if (strlen($sToDate) == 10) {
            $sToDate .= ' 23:59:59'; // Set the 'to' date as the last second of the selected day.
        }

        // Query to get the gene panel revisions.
        $aGenePanelRevs = $_DB->query('SELECT * FROM ' . TABLE_GENE_PANELS_REV . ' WHERE id = ? AND valid_from >= ? ORDER BY valid_from ASC', array($nID, $sFromDate))->fetchAll();
        $nCount = 0; // The number of Gene Panel revisions (modifications) in date range, not counting the "Created record" revision.

         // FIXME: Could we replace this with a Gene Panel Rev VL? There is quite some more info needed, though, so might not work...
        foreach ($aGenePanelRevs as $aGenePanelRev) {
            if ($aGenePanelRev['valid_from'] <= $sToDate) {
                // The revision's valid_from date is used to determine if an event (record created, record modified) happens in the selected date range.
                // The revision's valid_to date doesn't matter for these events, for the purpose of showing the history.
                $aChanges[$nCount][0] = $aGenePanelRev['reason'];
                $aChanges[$nCount][1] = $aGenePanelRev['valid_from'];
                $nCount ++;
            }
        }

        if ($aGenePanelRevs[0]['created_date'] >= $sFromDate && $aGenePanelRevs[0]['created_date'] <= $sToDate) {
            // This gene panel was created within the given date range. Don't include that entry as a "difference".
            $nCount --;
        }

        // If the To Date is earlier than when the gene panel was created, then notify user.
        if ($sToDate < $aGenePanelRevs[0]['created_date']) {
            lovd_showInfoTable('This gene panel did not exist yet in the given date range. It was created ' . $aGenePanelRevs[0]['created_date'] . '.', 'information');
        } elseif ($nCount == 0) { // "modification" count is zero.
            lovd_showInfoTable('Information about this gene panel has not changed between the given dates.', 'information');
        }
        else {
            // Display the gene panel revisions.
            print('
        <TABLE border="0" cellpadding="0" cellspacing="1" width="750" class="data" style="font-size : 13px;">   
          <TR>
            <TH>Changes to Gene Panel information</TH>
            <TH width="150">Date</TH>
          </TR>' . "\n");

            // FIXME: Could we replace this with a Gene Panel Rev VL? There is quite some more info needed, though, so might not work...
            foreach ($aChanges as $aChange) {
                // The revision's valid_from date is used to determine if an event (record created, record modified) happens in the selected date range.
                // The revision's valid_to date doesn't matter for these events, for the purpose of showing the history.
                print('          <TR>
            <TD>' . nl2br($aChange[0]) . '</TD>
            <TD>' . $aChange[1] . '</TD>
          </TR>' . "\n");
            }

            print('        </TABLE><BR>' . "\n\n");
        }

        // This more complex query can handle the case of a gene that is added, removed, then added again.
        // Also, exclude genes where valid_from and valid_to do not overlap with the selected data range because they are not relevant and would produce wrong results.
        $aGenePanelGeneRevs = $_DB->query('SELECT geneid, MIN(valid_from) AS valid_from, MAX(valid_to) AS valid_to FROM ' . TABLE_GP2GENE_REV . ' WHERE genepanelid = ? AND (valid_to >= ? and valid_from <= ?) GROUP BY geneid', array($nID, $sFromDate, $sToDate))->fetchAll();
        $nAddedCount = 0; // Number of genes that have been added between selected date range.
        $nRemovedCount = 0; // Number of genes that have been removed between selected date range.

        $aAddedGenes = array();
        $aRemovedGenes = array();
        // Display the gene panel gene revisions for the genepanel between two dates.
        foreach ($aGenePanelGeneRevs as $aGenePanelGeneRev) {
            if ($aGenePanelGeneRev['valid_from'] >= $sFromDate && $aGenePanelGeneRev['valid_to'] >= $sToDate) {
                // Added Genes: These are genes which were created between the from date and to date and are still valid after to date.
                $aAddedGenes[$nAddedCount] = $aGenePanelGeneRev['geneid'];
                $nAddedCount ++;
            } elseif ($aGenePanelGeneRev['valid_from'] <= $sFromDate && $aGenePanelGeneRev['valid_to'] <= $sToDate) {  // Removed Genes: these are genes which existed at the fromDate but not after the toDate.
                $aRemovedGenes[$nRemovedCount] = $aGenePanelGeneRev['geneid'];
                $nRemovedCount ++;
            }
        }

        if ($nAddedCount == 0 && $nRemovedCount == 0) {
            lovd_showInfoTable('Genes in this gene panel have not changed between the given dates.', 'information');
        }
        else {
            // Display the gene panel gene revisions for the genepanel between two dates.
            print('    <TABLE border="0" cellpadding="0" cellspacing="0" width="750">
      <TR valign="top">' . "\n");
            foreach (array(1, 0) as $i) {
                print('        <TD>
          <TABLE border="0" cellpadding="0" cellspacing="1" width="365" class="data" style="font-size : 13px;' . ($i ? '' : ' margin-left : 20px;') . '">
            <TR>
              <TH>' . ($i ? 'Added Genes' : 'Removed Genes') . '</TH>
            </TR>' . "\n");
                foreach ($aAddedGenes as $sAddedGene) {
                    print((!$i ? '' : '            <TR>
              <TD>' . $sAddedGene . '</TD>
            </TR>' . "\n"));
                }
                foreach ($aRemovedGenes as $aRemovedGene) {
                    print(($i ? '' : '            <TR>
              <TD>' . $aRemovedGene . ' </TD>
            </TR>' . "\n"));
                    //  }
                }
                print('          </TABLE>
        </TD>' . "\n");
            }
            print('      </TR>
    </TABLE>' . "\n");
        }
    }
}
?>
