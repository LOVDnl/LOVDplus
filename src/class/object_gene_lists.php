<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2016-03-01
 * Modified    : 2016-03-01
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





class LOVD_GeneList extends LOVD_Object {
    // This class extends the basic Object class and it handles the Link object.
    var $sObject = 'Gene_List';





    function __construct ()
    {
        // Default constructor.
        global $_AUTH;

        // SQL code for loading an entry for an edit form.
        $this->sSQLLoadEntry = 'SELECT gl.*, ' .
                               'GROUP_CONCAT(DISTINCT gl2d.diseaseid ORDER BY gl2d.diseaseid SEPARATOR ";") AS _active_diseases ' .
                               'FROM ' . TABLE_GENE_LISTS . ' AS gl ' .
                               'LEFT OUTER JOIN ' . TABLE_GL2DIS . ' AS gl2d ON (gl.id = gl2d.genelistid) ' .
                               'WHERE gl.id = ? ' .
                               'GROUP BY gl.id';

        // SQL code for viewing the list of gene lists
        $this->aSQLViewList['SELECT']   = 'gl.*, ' .
                                          'GROUP_CONCAT(DISTINCT IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", d.name, d.symbol) ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR ", ") AS diseases_, ' .
                                          'COUNT(DISTINCT gl2g.geneid) AS genes';
        $this->aSQLViewList['FROM']     = TABLE_GENE_LISTS . ' AS gl ' .
                                          'LEFT OUTER JOIN ' . TABLE_GL2DIS . ' AS gl2d ON (gl.id = gl2d.genelistid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_GL2GENE . ' AS gl2g ON (gl.id = gl2g.genelistid) ' .
                                          'LEFT OUTER JOIN ' . TABLE_DISEASES . ' AS d ON (gl2d.diseaseid = d.id)';
        $this->aSQLViewList['GROUP_BY'] = 'gl.id';

        // List of columns and (default?) order for viewing a list of entries.
        $this->aColumnsViewList =
            array(
                'id_' => array(
                    'view' => array('ID', 60),
                    'db'   => array('gl.id', 'ASC', true)),
                'name' => array(
                    'view' => array('Name', 150),
                    'db'   => array('gl.name', 'ASC', true),
                    'legend' => array('The name of the gene list.','')),
                'description' => array(
                    'view' => array('Description', 50),
                    'db'   => array('gl.description', 'ASC', true),
                    'legend' => array('The gene list description.')),
                'type' => array(
                    'view' => array('Type', 60),
                    'db'   => array('gl.type', 'ASC', true),
                    'legend' => array('The gene list type of Gene List, Blacklist or Mendeliome','The gene list type:<ul><li>Gene List - A list of genes that will include variants during filtering</li><li>Blacklist - A list of genes that will exclude variants during filtering</li><li>Mendeliome - A list of genes with known disease causing variants</li></ul>')),
                'cohort' => array(
                    'view' => array('Cohort', 50),
                    'db'   => array('gl.cohort', 'ASC', true),
                    'legend' => array('The cohort the gene list belongs to.')),
                'genes' => array(
                    'view' => array('Genes', 60),
                    'db'   => array('genes', 'DESC', 'INT_UNSIGNED'),
                    'legend' => array('The number of genes in this gene list.')),
                'diseases_' => array(
                    'view' => array('Associated with diseases', 150),
                    'db'   => array('diseases_', false, 'TEXT'),
                    'legend' => array('The diseases associated with this gene list.')),
                'created_date' => array(
                    'view' => array('Created Date', 110),
                    'db'   => array('gl.created_date', 'DESC', true),
                    'legend' => array('The date the gene list was created.')),
            );
        $this->sSortDefault = 'id_';

        parent::__construct();
    }


    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.
        global $_AUTH, $_CONF, $_DB, $_SETT;

        if (!in_array($sView, array('list', 'entry'))) {
            $sView = 'list';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        $zData['type'] = ucwords(str_replace("_", " ", $zData['type']));
        $zData['created_date'] = substr($zData['created_date'], 0, 10);

        return $zData;

    }
}
?>
