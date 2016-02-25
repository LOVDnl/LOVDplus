<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-02-25
 * Modified    : 2016-02-25
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2015 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
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
require_once ROOT_PATH . 'class/objects.php';





class LOVD_GeneStatistic extends LOVD_Object {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Gene_Statistic';

// TODO MGHA-AM Change the onclick function to go to the gene information rather than post back to itself
// TODO MGHA-AM The legend is broken due to the extra BR added to the column titles. Is there a better way to do this?


    function __construct ()
    {
        // Default constructor.
        global $_AUTH;

        // SQL code for viewing the list of genes
        $this->aSQLViewList['SELECT']   = 'gs.*, gs.id AS geneid, (CASE gs.vep_annotation WHEN 1 THEN "Yes" ELSE "No" END) AS vepyesno';
        $this->aSQLViewList['FROM']     = TABLE_GENE_STATISTICS . ' AS gs';

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
            array(
                'geneid' => array(
                    'view' => false, // Copy of the gene's ID for the search terms in the screening's viewEntry.
                    'db'   => array('gs.id', 'ASC', true)),
                'id_' => array(
                    'view' => array('Symbol<BR><BR>', 80),
                    'db'   => array('gs.id', 'ASC', true)),
                'vepyesno' => array(
                    'view' => array('VEP<BR>Annotation<BR>', 20),
                    'db'   => array('vepyesno', 'ASC', true),
                    'legend' => array('Will this gene be annotated within VEP?')),
                'nextera_cds_bases' => array(
                    'view' => array('Nextera<BR>CDS<BR>Bases', 65),
                    'db'   => array('gs.nextera_cds_bases', 'DESC', 'INT_UNSIGNED'),
                    'legend' => array('How many bases in the capture overlap coding regions of this gene.')),
                'nextera_exon_bases' => array(
                    'view' => array('Nextera<BR>Exon<BR>Bases', 65),
                    'db'   => array('gs.nextera_exon_bases', 'DESC', 'INT_UNSIGNED'),
                    'legend' => array('How many bases in the capture overlap exons of this gene.')),
                'refseq_cds_bases' => array(
                    'view' => array('RefSeq<BR>CDS<BR>Bases', 60),
                    'db'   => array('gs.refseq_cds_bases', 'DESC', 'INT_UNSIGNED'),
                    'legend' => array('How many coding bases are in RefSeq for this gene.')),
                'refseq_exon_bases' => array(
                    'view' => array('Refseq<BR>Exon<BR>Bases', 60),
                    'db'   => array('gs.refseq_exon_bases', 'DESC', 'INT_UNSIGNED'),
                    'legend' => array('How many exon bases are in RefSeq for this gene.')),
                'cds_coverage' => array(
                    'view' => array('CDS<BR>Coverage<BR>', 50),
                    'db'   => array('gs.cds_coverage', 'DESC', 'DECIMAL'),
                    'legend' => array('Percentage of coding bases covered by the capture.')),
                'exon_coverage' => array(
                    'view' => array('Exon<BR>Coverage<BR>', 50),
                    'db'   => array('gs.exon_coverage', 'DESC', 'DECIMAL'),
                    'legend' => array('Percentage of exon bases covered.')),
                'alternative_names' => array(
                    'view' => array('Alternative Names <BR><BR>', 130),
                    'db'   => array('gs.alternative_names', 'DESC', true),
                    'legend' => array('Other known synonyms for this gene.')),
                'exon_mean_of_mean_coverage' => array(
                    'view' => array('Exon Mean<BR>of Mean<BR>Coverage', 80),
                    'db'   => array('gs.exon_mean_of_mean_coverage', 'DESC', 'DECIMAL'),
                    'legend' => array('The average coverage across the exons of this gene for one sample, averaged over a number of samples.')),
                'exon_mean_coverage_sd' => array(
                    'view' => array('Exon Mean<BR>Coverage<BR>SD', 80),
                    'db'   => array('gs.exon_mean_coverage_sd', 'DESC', 'DECIMAL'),
                    'legend' => array('The standard deviation of the mean coverage across samples.')),
                'exon_mean_of_median_coverage' => array(
                    'view' => array('Exon Mean<BR>of Median<BR>Coverage', 83),
                    'db'   => array('gs.exon_mean_of_median_coverage', 'DESC', 'DECIMAL'),
                    'legend' => array('The median coverage across the exons of this gene for one sample, averaged over a number of samples.')),
                'exon_mean_of_percent_20x' => array(
                    'view' => array('Exon Mean<BR>of<BR>Percent>20x', 50),
                    'db'   => array('gs.exon_mean_of_percent_20x', 'DESC', 'DECIMAL'),
                    'legend' => array('The percentage of this gene\'s exons with coverage greater than 20, averaged over a number of samples.')),
                'exon_mean_percent_sd' => array(
                    'view' => array('Exon Mean<BR>Percent<BR>SD', 83),
                    'db'   => array('gs.exon_mean_percent_sd', 'DESC', 'DECIMAL'),
                    'legend' => array('The standard deviation of the mean percentage coverage across samples.')),
                'cds_mean_of_mean_coverage' => array(
                    'view' => array('CDS Mean<BR>of Mean<BR>Coverage', 50),
                    'db'   => array('gs.cds_mean_of_mean_coverage', 'DESC', 'DECIMAL'),
                    'legend' => array('The average coverage across the coding regions of this gene for one sample, averaged over a number of samples.')),
                'cds_mean_coverage_sd' => array(
                    'view' => array('CDS Mean<BR>Coverage<BR>SD', 77),
                    'db'   => array('gs.cds_mean_coverage_sd', 'DESC', 'DECIMAL'),
                    'legend' => array('The standard deviation of the mean coverage across samples.')),
                'cds_mean_of_median_coverage' => array(
                    'view' => array('CDS Mean<BR>of Median<BR>Coverage', 50),
                    'db'   => array('gs.cds_mean_of_median_coverage', 'DESC', 'DECIMAL'),
                    'legend' => array('The median coverage across the coding regions of this gene for one sample, averaged over a number of samples.')),
                'cds_mean_of_percent_20x' => array(
                    'view' => array('CDS Mean<BR>of<BR>Percent>20x', 50),
                    'db'   => array('gs.cds_mean_of_percent_20x', 'DESC', 'DECIMAL'),
                    'legend' => array('The percentage of this gene\'s coding regions with coverage greater than 20, averaged over a number of samples.')),
                'cds_mean_percent_sd' => array(
                    'view' => array('CDS Mean<BR>Percent<BR>SD', 80),
                    'db'   => array('gs.cds_mean_percent_sd', 'DESC', 'DECIMAL'),
                    'legend' => array('The standard deviation of the mean percentage coverage across samples.')),
            );
        $this->sSortDefault = 'id_';

        // Because the gene information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();

        parent::__construct();
    }
}
?>
