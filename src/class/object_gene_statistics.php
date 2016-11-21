<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-02-25
 * Modified    : 2016-03-23
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





class LOVD_GeneStatistic extends LOVD_Object {
    // This class extends the basic Object class and it handles the GeneStatistic object.
    var $sObject = 'Gene_Statistic';





    function __construct ()
    {
        // Default constructor.
        global $_DB;

        // SQL code for viewing the list of genes
        $this->aSQLViewList['SELECT']   = 'g.name, gs.*, g.id, (CASE gs.vep_annotation WHEN 1 THEN "Yes" ELSE "No" END) AS vepyesno, ' .
                                            'GROUP_CONCAT(DISTINCT gp.name ORDER BY gp.name DESC SEPARATOR ", ") AS gene_panels_, MAX(CASE WHEN gp.type = "blacklist" THEN 1 ELSE 0 END) AS blacklist_flag_ ';
        $this->aSQLViewList['FROM']     = TABLE_GENES . ' AS g LEFT OUTER JOIN ' . TABLE_GENE_STATISTICS . ' AS gs ON (g.id = gs.id) ' .
                                            'LEFT OUTER JOIN ' . TABLE_GP2GENE . ' AS gp2g ON (g.id = gp2g.geneid) ' .
                                            'LEFT OUTER JOIN ' . TABLE_GENE_PANELS . ' AS gp ON (gp2g.genepanelid = gp.id)';
        $this->aSQLViewList['GROUP_BY'] = 'g.id';
        // If we detect that the user wants to only show the checked genes and there are genes stored in the session variable then lets add them to the where clause here.
        if (isset($_GET['viewlistid']) && isset($_GET['filterChecked']) && $_GET['filterChecked'] == 'true' && $_SESSION['viewlists'][$_GET['viewlistid']]['checked']) {
            // Run the PDO:quote function over all the gene IDs to sanitize them
            $this->aSQLViewList['WHERE']     = 'g.id IN (' . implode(',', array_map(array($_DB, 'quote'),$_SESSION['viewlists'][$_GET['viewlistid']]['checked'])) . ')';
        }



        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
            array(
                'id_' => array(
                    'view' => array('Symbol<BR><BR><BR>', 80),
                    'db'   => array('g.id', 'ASC', true)),
                'hgnc' => array(
                    'view' => array('Latest<BR>HGNC<BR>Symbol<BR>', 65),
                    'db'   => array('gs.hgnc', 'DESC', true),
                    'legend' => array('The latest symbol according to the HGNC.')),
                'blacklist_flag_' => array(
                    'view' => false,
                    'db'   => array('blacklist_flag_', 'ASC', 'INT')),
                'name' => array(
                    'view' => array('Gene <BR><BR><BR>', 100),
                    'db'   => array('g.name', 'ASC', true)),
                'gene_panels_' => array(
                    'view' => array('Gene Panels<BR><BR><BR>', 100),
                    'db'   => array('gene_panels_', false, 'TEXT'),
                    'legend' => array('The gene panels that this gene is found in.')),
                'vepyesno' => array(
                    'view' => array('VEP <BR>Annotation<BR><BR>', 20),
                    'db'   => array('vepyesno', 'ASC', true),
                    'legend' => array('Will this gene be annotated within VEP?')),
                'chromosome' => array(
                    'view' => array('Chr <BR><BR><BR>', 50),
                    'db'   => array('gs.chromosome', 'DESC', true),
                    'legend' => array('Chromosome this gene is located within.')),
                'start_pos' => array(
                    'view' => array('Start <BR>Position<BR><BR>', 65),
                    'db'   => array('gs.start_pos', 'DESC', true),
                    'legend' => array('Genomic start position for this gene.')),
                'end_pos' => array(
                    'view' => array('End <BR>Position<BR><BR>', 65),
                    'db'   => array('gs.end_pos', 'DESC', true),
                    'legend' => array('Genomic end position for this gene.')),
                'refseq_cds_bases' => array(
                    'view' => array('RefSeq <BR>CDS <BR>Bases<BR>', 60),
                    'db'   => array('gs.refseq_cds_bases', 'DESC', true),
                    'legend' => array('How many coding bases are in RefSeq for this gene.')),
                'refseq_exon_bases' => array(
                    'view' => array('Refseq <BR>Exon <BR>Bases<BR>', 60),
                    'db'   => array('gs.refseq_exon_bases', 'DESC', true),
                    'legend' => array('How many exon bases are in RefSeq for this gene.')),
                'nextera_cds_bases' => array(
                    'view' => array('Nextera <BR>CDS <BR>Bases<BR>', 65),
                    'db'   => array('gs.nextera_cds_bases', 'DESC', true),
                    'legend' => array('How many bases in the capture overlap coding regions of this gene.')),
                'nextera_exon_bases' => array(
                    'view' => array('Nextera <BR>Exon <BR>Bases<BR>', 65),
                    'db'   => array('gs.nextera_exon_bases', 'DESC', true),
                    'legend' => array('How many bases in the capture overlap exons of this gene.')),
                'nextera_cds_coverage' => array(
                    'view' => array('Nextera <BR>CDS <BR>Coverage <BR>', 50),
                    'db'   => array('gs.nextera_cds_coverage', 'DESC', true),
                    'legend' => array('Percentage of coding bases covered by the capture.')),
                'nextera_exon_coverage' => array(
                    'view' => array('Nextera <BR>Exon <BR>Coverage <BR>', 50),
                    'db'   => array('gs.nextera_exon_coverage', 'DESC', true),
                    'legend' => array('Percentage of exon bases covered.')),
                'nextera_exon_mean_of_mean_coverage' => array(
                    'view' => array('Nextera <BR>Exon Mean <BR>of Mean <BR>Coverage', 80),
                    'db'   => array('gs.nextera_exon_mean_of_mean_coverage', 'DESC', true),
                    'legend' => array('The average coverage across the exons of this gene for one sample, averaged over a number of samples.')),
                'nextera_exon_mean_coverage_sd' => array(
                    'view' => array('Nextera <BR>Exon Mean <BR>Coverage <BR>SD', 80),
                    'db'   => array('gs.nextera_exon_mean_coverage_sd', 'DESC', true),
                    'legend' => array('The standard deviation of the mean coverage across samples.')),
                'nextera_exon_mean_of_median_coverage' => array(
                    'view' => array('Nextera <BR>Exon Mean <BR>of Median <BR>Coverage', 83),
                    'db'   => array('gs.nextera_exon_mean_of_median_coverage', 'DESC', true),
                    'legend' => array('The median coverage across the exons of this gene for one sample, averaged over a number of samples.')),
                'nextera_exon_mean_of_percent_20x' => array(
                    'view' => array('Nextera <BR>Exon Mean <BR>of <BR>Percent>20x', 50),
                    'db'   => array('gs.nextera_exon_mean_of_percent_20x', 'DESC', true),
                    'legend' => array('The percentage of this gene\'s exons with coverage greater than 20, averaged over a number of samples.')),
                'nextera_exon_mean_percent_sd' => array(
                    'view' => array('Nextera <BR>Exon Mean <BR>Percent <BR>SD', 83),
                    'db'   => array('gs.nextera_exon_mean_percent_sd', 'DESC', true),
                    'legend' => array('The standard deviation of the mean percentage coverage across samples.')),
                'nextera_cds_mean_of_mean_coverage' => array(
                    'view' => array('Nextera <BR>CDS Mean <BR>of Mean <BR>Coverage', 50),
                    'db'   => array('gs.nextera_cds_mean_of_mean_coverage', 'DESC', true),
                    'legend' => array('The average coverage across the coding regions of this gene for one sample, averaged over a number of samples.')),
                'nextera_cds_mean_coverage_sd' => array(
                    'view' => array('Nextera <BR>CDS Mean <BR>Coverage <BR>SD', 77),
                    'db'   => array('gs.nextera_cds_mean_coverage_sd', 'DESC', true),
                    'legend' => array('The standard deviation of the mean coverage across samples.')),
                'nextera_cds_mean_of_median_coverage' => array(
                    'view' => array('Nextera <BR>CDS Mean <BR>of Median <BR>Coverage', 50),
                    'db'   => array('gs.nextera_cds_mean_of_median_coverage', 'DESC', true),
                    'legend' => array('The median coverage across the coding regions of this gene for one sample, averaged over a number of samples.')),
                'nextera_cds_mean_of_percent_20x' => array(
                    'view' => array('Nextera <BR>CDS Mean <BR>of <BR>Percent>20x', 50),
                    'db'   => array('gs.nextera_cds_mean_of_percent_20x', 'DESC', true),
                    'legend' => array('The percentage of this gene\'s coding regions with coverage greater than 20, averaged over a number of samples.')),
                'nextera_cds_mean_percent_sd' => array(
                    'view' => array('Nextera <BR>CDS Mean <BR>Percent <BR>SD', 80),
                    'db'   => array('gs.nextera_cds_mean_percent_sd', 'DESC', true),
                    'legend' => array('The standard deviation of the mean percentage coverage across samples.')),
                'cre_cds_bases' => array(
                    'view' => array('CRE <BR>CDS <BR>Bases<BR>', 65),
                    'db'   => array('gs.cre_cds_bases', 'DESC', true),
                    'legend' => array('How many bases in the capture overlap coding regions of this gene.')),
                'cre_exon_bases' => array(
                    'view' => array('CRE <BR>Exon <BR>Bases<BR>', 65),
                    'db'   => array('gs.cre_exon_bases', 'DESC', true),
                    'legend' => array('How many bases in the capture overlap exons of this gene.')),
                'cre_cds_coverage' => array(
                    'view' => array('CRE <BR>CDS <BR>Coverage <BR>', 50),
                    'db'   => array('gs.cre_cds_coverage', 'DESC', true),
                    'legend' => array('Percentage of coding bases covered by the capture.')),
                'cre_exon_coverage' => array(
                    'view' => array('CRE <BR>Exon <BR>Coverage <BR>', 50),
                    'db'   => array('gs.cre_exon_coverage', 'DESC', true),
                    'legend' => array('Percentage of exon bases covered.')),
                'cre_exon_mean_of_mean_coverage' => array(
                    'view' => array('CRE <BR>Exon Mean <BR>of Mean <BR>Coverage', 80),
                    'db'   => array('gs.cre_exon_mean_of_mean_coverage', 'DESC', true),
                    'legend' => array('The average coverage across the exons of this gene for one sample, averaged over a number of samples.')),
                'cre_exon_mean_coverage_sd' => array(
                    'view' => array('CRE <BR>Exon Mean <BR>Coverage <BR>SD', 80),
                    'db'   => array('gs.cre_exon_mean_coverage_sd', 'DESC', true),
                    'legend' => array('The standard deviation of the mean coverage across samples.')),
                'cre_exon_mean_of_median_coverage' => array(
                    'view' => array('CRE <BR>Exon Mean <BR>of Median <BR>Coverage', 83),
                    'db'   => array('gs.cre_exon_mean_of_median_coverage', 'DESC', true),
                    'legend' => array('The median coverage across the exons of this gene for one sample, averaged over a number of samples.')),
                'cre_exon_mean_of_percent_20x' => array(
                    'view' => array('CRE <BR>Exon Mean <BR>of <BR>Percent>20x', 50),
                    'db'   => array('gs.cre_exon_mean_of_percent_20x', 'DESC', true),
                    'legend' => array('The percentage of this gene\'s exons with coverage greater than 20, averaged over a number of samples.')),
                'cre_exon_mean_percent_sd' => array(
                    'view' => array('CRE <BR>Exon Mean <BR>Percent <BR>SD', 83),
                    'db'   => array('gs.cre_exon_mean_percent_sd', 'DESC', true),
                    'legend' => array('The standard deviation of the mean percentage coverage across samples.')),
                'cre_cds_mean_of_mean_coverage' => array(
                    'view' => array('CRE <BR>CDS Mean <BR>of Mean <BR>Coverage', 50),
                    'db'   => array('gs.cre_cds_mean_of_mean_coverage', 'DESC', true),
                    'legend' => array('The average coverage across the coding regions of this gene for one sample, averaged over a number of samples.')),
                'cre_cds_mean_coverage_sd' => array(
                    'view' => array('CRE <BR>CDS Mean <BR>Coverage <BR>SD', 77),
                    'db'   => array('gs.cre_cds_mean_coverage_sd', 'DESC', true),
                    'legend' => array('The standard deviation of the mean coverage across samples.')),
                'cre_cds_mean_of_median_coverage' => array(
                    'view' => array('CRE <BR>CDS Mean <BR>of Median <BR>Coverage', 50),
                    'db'   => array('gs.cre_cds_mean_of_median_coverage', 'DESC', true),
                    'legend' => array('The median coverage across the coding regions of this gene for one sample, averaged over a number of samples.')),
                'cre_cds_mean_of_percent_20x' => array(
                    'view' => array('CRE <BR>CDS Mean <BR>of <BR>Percent>20x', 50),
                    'db'   => array('gs.cre_cds_mean_of_percent_20x', 'DESC', true),
                    'legend' => array('The percentage of this gene\'s coding regions with coverage greater than 20, averaged over a number of samples.')),
                'cre_cds_mean_percent_sd' => array(
                    'view' => array('CRE <BR>CDS Mean <BR>Percent <BR>SD', 80),
                    'db'   => array('gs.cre_cds_mean_percent_sd', 'DESC', true),
                    'legend' => array('The standard deviation of the mean percentage coverage across samples.')),
                'alternative_names' => array(
                    'view' => array('Alternative Names <BR><BR><BR>', 130),
                    'db'   => array('gs.alternative_names', 'DESC', true),
                    'legend' => array('Other known synonyms for this gene.')),
            );
        $this->sSortDefault = 'id_';

        // Because the gene information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();

        parent::__construct();
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
            if ($zData['blacklist_flag_']) {
                // Mark the genes that occur within a blacklist as red.
                $zData['class_name'] = (empty($zData['class_name'])? '' : $zData['class_name'] . ' ') . 'marked';
            }
        }

        return $zData;
    }
}
?>
