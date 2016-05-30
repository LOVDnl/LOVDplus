<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-07
 * Modified    : 2016-05-17
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
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
require_once ROOT_PATH . 'class/object_custom_viewlists.php';





class LOVD_CustomViewListMOD extends LOVD_CustomViewList {
    // This class extends the basic Object class and it handles pre-configured custom viewLists.
    var $sObject = 'Custom_ViewListMOD';





    function __construct ($aObjects = array(), $sOtherID = '')
    {
        // Default constructor.
        global $_DB, $_AUTH;

        if (!is_array($aObjects)) {
            $aObjects = explode(',', $aObjects);
        }
        $this->sObjectID = implode(',', $aObjects);
        // Receive OtherID or Gene.
        if (ctype_digit($sOtherID)) {
            $sGene = '';
            $this->nOtherID = $sOtherID;
        } else {
            $sGene = $sOtherID;
        }


        // FIXME: Disable this part when not using any of the custom column data types...
        // Collect custom column information, all active columns (possibly restricted per gene).
        // FIXME; This join is not always needed (it's done for VOT columns, but sometimes they are excluded, or the join is not necessary because of the user level), exclude when not needed to speed up the query?
        //   Also, the select of public_view makes no sense of VOTs are restricted by gene.
        $sSQL = 'SELECT c.id, c.width, c.head_column, c.description_legend_short, c.description_legend_full, c.mysql_type, c.form_type, c.select_options, c.col_order, GROUP_CONCAT(sc.geneid, ":", sc.public_view SEPARATOR ";") AS public_view FROM ' . TABLE_ACTIVE_COLS . ' AS ac INNER JOIN ' . TABLE_COLS . ' AS c ON (c.id = ac.colid) LEFT OUTER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (sc.colid = ac.colid) ' .
                    'WHERE ' . ($_AUTH['level'] >= ($sGene? LEVEL_COLLABORATOR : LEVEL_MANAGER)? '' : '((c.id NOT LIKE "VariantOnTranscript/%" AND c.public_view = 1) OR sc.public_view = 1) AND ') . '(c.id LIKE ?' . str_repeat(' OR c.id LIKE ?', count($aObjects)-1) . ') ' .
                    (!$sGene? 'GROUP BY c.id ' :
                      // If gene is given, only shown VOT columns active in the given gene! We'll use an UNION for that, so that we'll get the correct width and order also.
                      'AND c.id NOT LIKE "VariantOnTranscript/%" GROUP BY c.id ' . // Exclude the VOT columns from the normal set, we'll load them below.
                      'UNION ' .
                      'SELECT c.id, sc.width, c.head_column, c.description_legend_short, c.description_legend_full, c.mysql_type, c.form_type, c.select_options, sc.col_order, CONCAT(sc.geneid, ":", sc.public_view) AS public_view FROM ' . TABLE_COLS . ' AS c INNER JOIN ' . TABLE_SHARED_COLS . ' AS sc ON (c.id = sc.colid) WHERE sc.geneid = ? ' .
                      ($_AUTH['level'] >= LEVEL_COLLABORATOR? '' : 'AND sc.public_view = 1 ')) .
                    'ORDER BY col_order';
        $aSQL = array();
        foreach ($aObjects as $sObject) {
            $aSQL[] = $sObject . '/%';
        }
        if ($sGene) {
            $aSQL[] = $sGene;
        }
        if ($sOtherID) {
            $this->nID = $sOtherID; // We need the AJAX script to have the same restrictions!!!
        }

        // Increase the max group_concat() length, so that lists of many many genes still have all genes mentioned here (22.000 genes take 193.940 bytes here).
        $_DB->query('SET group_concat_max_len = 200000');
        $q = $_DB->query($sSQL, $aSQL);
        while ($z = $q->fetchAssoc()) {
            $z['custom_links'] = array();
            $z['form_type'] = explode('|', $z['form_type']);
            $z['select_options'] = explode("\r\n", $z['select_options']); // What do we use this for?
            if (substr($z['id'], 0,19) == 'VariantOnTranscript') {
                $z['public_view'] = explode(';', rtrim(preg_replace('/([A-Za-z0-9-]+:0;|:1)/', '', $z['public_view'] . ';'), ';'));
            }
            if (is_null($z['public_view'])) {
                $z['public_view'] = array();
            }
            $this->aColumns[$z['id']] = $z;
        }
        if ($_AUTH) {
            $_AUTH['allowed_to_view'] = array_merge($_AUTH['curates'], $_AUTH['collaborates']);
        }



        $aSQL = $this->aSQLViewList;
        $aColumnsToShow = array(); // Custom: Instead of telling LOVD what NOT to show, for the diagnostic software we tell LOVD what he SHOULD show (VOG and VOT VLs).
        // Loop requested data types, and keep columns in order indicated by request.
        foreach ($aObjects as $nKey => $sObject) {
            switch ($sObject) {
                case 'AnalysisRunResults':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'arr.*';
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_ANALYSES_RUN_RESULTS . ' AS arr';
                        // We can't have objects::viewList() optimizing on us and not giving us the normal view with VL headers
                        // because we have no results yet... Always set to 1. Doesn't need to be correct, just not zero.
                        $this->nCount = 1;
                        if (array_search('VariantOnGenome', $aObjects)) {
                            $aSQL['GROUP_BY'] = 'vog.id'; // Necessary for GROUP_CONCAT().
                        }
                    }
                    break;

                case 'VariantOnGenome':
                    $aColumnsToShow['VariantOnGenome'] =
                        array(
                            'VariantOnGenome/DNA',
                            'VariantOnGenome/Alamut',
                            'VariantOnGenome/Conservation_score/PhyloP',
                            'VariantOnGenome/HGMD/Association',
                            'VariantOnGenome/Sequencing/Depth/Alt/Fraction',
                            'VariantOnGenome/Sequencing/Quality',
                            'VariantOnGenome/Sequencing/GATKcaller',
                        );
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'vog.*, a.name AS allele_, eg.name AS vog_effect';
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['SELECT'] .= ', vog.id AS row_id'; // To ensure other table's id columns don't interfere.
                        $aSQL['FROM'] = TABLE_VARIANTS . ' AS vog';
                        $this->nCount = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS)->fetchColumn();
                        $aSQL['GROUP_BY'] = 'vog.id'; // Necessary for GROUP_CONCAT(), such as in Screening.
                        $aSQL['ORDER_BY'] = 'vog.chromosome ASC, vog.position_g_start';
                    } else {
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_VARIANTS . ' AS vog ON (';
                        $nKeyARR = array_search('AnalysisRunResults', $aObjects);
                        $nKeyVOT = array_search('VariantOnTranscript', $aObjects);
                        if ($nKeyARR !== false && $nKeyARR < $nKey) {
                            // Earlier, ARR was used, join to that.
                            $aSQL['FROM'] .= 'arr.variantid = vog.id)';
                        } elseif ($nKeyVOT !== false && $nKeyVOT < $nKey) {
                            // Earlier, VOT was used, join to that.
                            $aSQL['FROM'] .= 'vot.id = vog.id)';
                        }
                        // We have no fallback, so we'll easily detect an error if we messed up somewhere.
                    }
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (vog.allele = a.id)';
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_EFFECT . ' AS eg ON (vog.effectid = eg.id)';
                    break;

                case 'VariantOnTranscript':
                    $aColumnsToShow['VariantOnTranscript'] =
                        array(
                            'VariantOnTranscript/DNA',
                            'VariantOnTranscript/Protein',
                            'VariantOnTranscript/GVS/Function',
                        );
                    $nKeyVOG = array_search('VariantOnGenome', $aObjects);
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'vot.*, vot.id AS row_id'; // To ensure other table's id columns don't interfere.
                        $aSQL['FROM'] = TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot';
                        $this->nCount = $_DB->query('SELECT COUNT(*) FROM ' . TABLE_VARIANTS_ON_TRANSCRIPTS)->fetchColumn();
                        $aSQL['GROUP_BY'] = 'vot.id'; // Necessary for GROUP_CONCAT(), such as in Screening.
                    } elseif ($nKeyVOG !== false && $nKeyVOG < $nKey) {
                        // Previously, VOG was used. We will join VOT with VOG, using GROUP_CONCAT.
                        // SELECT will be different: we will GROUP_CONCAT the whole lot, per column.
                        // Sort GROUP_CONCAT() based on transcript name. We'll have to join Transcripts for that.
                        //   That will break if somebody wants to join transcripts themselves, but why would somebody want that?
                        $sGCOrderBy = 't.geneid';
                        foreach ($this->aColumns as $sCol => $aCol) {
                            if (substr($sCol, 0, 19) == 'VariantOnTranscript') {
                                // DNA should not contain a /, simply because then the search algorithm will always use WHERE instead of HAVING and as such will not allow searching on the gene name in the field.
                                $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'GROUP_CONCAT(DISTINCT ' . ($sCol != 'VariantOnTranscript/DNA'? '`' . $sCol . '`' : 'CONCAT(t.geneid, ":", `' . $sCol . '`)') . ' ORDER BY ' . $sGCOrderBy . ' SEPARATOR ", ") AS `' . ($sCol != 'VariantOnTranscript/DNA'? $sCol : 'VariantOnTranscript_DNA') . '`';
                            }
                        }
                        // Security checks in this file's prepareData() need geneid to see if the column in question is set to non-public for one of the genes.
                        $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") AS _geneid, GROUP_CONCAT(DISTINCT IF(IFNULL(g.id_omim, 0) = 0, "", CONCAT(g.id, ";", g.id_omim)) SEPARATOR ";;") AS __gene_OMIM';
                        $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . '(SELECT GROUP_CONCAT(d.name SEPARATOR \'; \') FROM ' . TABLE_GEN2DIS . ' g2d INNER JOIN ' . TABLE_DISEASES . ' d ON (g2d.diseaseid = d.id) WHERE g2d.geneid = g.id) AS gene_disease_name';
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (';
                        // Earlier, VOG was used, join to that.
                        $aSQL['FROM'] .= 'vog.id = vot.id)';
                        // Join to transcripts for the NM number, but also to genes to show the gene's OMIM ID.
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) LEFT JOIN ' . TABLE_GENES . ' AS g ON (t.geneid = g.id)';
                        // We have no fallback, so we'll easily detect an error if we messed up somewhere.
                    }
                    break;
            }
        }

        if (!$aSQL['SELECT'] || !$aSQL['FROM']) {
            // Apparently, not implemented or no objects given.
            lovd_displayError('ObjectError', 'CustomViewLists::__construct() requested with non-existing or missing object(s) \'' . htmlspecialchars(implode(',', $aObjects)) . '\'.');
        }
        $this->aSQLViewList = $aSQL;



        if ($this->sObjectID == 'Transcript,VariantOnTranscript,VariantOnGenome') {
            // The joining of the tables needed for this view are in this order, but I want a different order on display.
            $aObjects = array('Transcript', 'VariantOnGenome', 'VariantOnTranscript');
        }



        // Now build $this->aColumnsViewList, from the order given by $aObjects and TABLE_COLS.col_order.
        foreach ($aObjects as $nKey => $sObject) {
            switch ($sObject) {
                case 'AnalysisRunResults':
                    $sPrefix = 'arr.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                             'runid' => array(
                                 'view' => false,
                                 'db'   => array('arr.runid', 'ASC', true)),
                              ));
                    break;

                case 'VariantOnGenome':
                    $sPrefix = 'vog.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                // NOTE: there are more columns defined a little further below.
                                'chromosome' => array(
                                        'view' => array('Chr', 50),
                                        'db'   => array('vog.chromosome', 'ASC', true)),
/*
                                'allele_' => array(
                                        'view' => array('Allele', 120),
                                        'db'   => array('a.name', 'ASC', true),
                                        'legend' => array('On which allele is the variant located? Does not necessarily imply inheritance!',
                                                          'On which allele is the variant located? Does not necessarily imply inheritance! \'Paternal\' (confirmed or inferred), \'Maternal\' (confirmed or inferred), \'Parent #1\' or #2 for compound heterozygosity without having screened the parents, \'Unknown\' for heterozygosity without having screened the parents, \'Both\' for homozygozity.')),
*/
                                'vog_effect' => array(
                                        'view' => array('Effect', 70),
                                        'db'   => array('eg.name', 'ASC', true),
                                        'legend' => array('The variant\'s effect on a protein\'s function, in the format Reported/Curator concluded; ranging from \'+\' (variant affects function) to \'-\' (does not affect function).',
                                                          'The variant\'s affect on a protein\'s function, in the format Reported/Curator concluded; \'+\' indicating the variant affects function, \'+?\' probably affects function, \'-\' does not affect function, \'-?\' probably does not affect function, \'?\' effect unknown.')),
                              ));

                    if (!$this->sSortDefault) {
                        // First data table in view.
                        $this->sSortDefault = 'VariantOnGenome/DNA';
                    }
                    $this->sRowLink = 'variants/{{zData_row_id}}#{{zData_transcriptid}}';
                    break;

                case 'VariantOnTranscript':
                    $sPrefix = 'vot.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                'transcriptid' => array(
                                        'view' => false,
                                        'db'   => array('vot.transcriptid', 'ASC', true)),
                                 'gene_disease_name' => array(
                                     'view' => array('Diseases', 200),
                                     'db'   => array('gene_disease_name', 'ASC', 'TEXT'),
                                     'legend' => array('The diseases associated with this gene.',
                                         'The diseases associated with this gene.')),
                              ));
                    if (!$this->sSortDefault) {
                        // First data table in view.
                        $this->sSortDefault = 'VariantOnTranscript/DNA';
                    }
                    break;
            }



            // The custom columns.
            foreach ($this->aColumns as $sColID => $aCol) {
                if (strpos($sColID, $sObject . '/') === 0) {
                    if (!isset($aColumnsToShow[$sObject]) || in_array($sColID, $aColumnsToShow[$sObject])) {
                        $bAlignRight = preg_match('/^(DEC|FLOAT|(TINY|SMALL|MEDIUM|BIG)?INT)/', $aCol['mysql_type']);

                        $this->aColumnsViewList[$sColID] =
                             array(
                                    'view' => array($aCol['head_column'], $aCol['width'], ($bAlignRight? ' align="right"' : '')),
                                    'db'   => array($sPrefix . '`' . $aCol['id'] . '`', 'ASC', lovd_getColumnType('', $aCol['mysql_type'])),
                                    'legend' => array($aCol['description_legend_short'], $aCol['description_legend_full']),
                                  );
                    }
                }
            }
            // Alamut link should not be searchable, because we have nothing in those columns.
            if (isset($this->aColumnsViewList['VariantOnGenome/Alamut']['db'][2])) {
                $this->aColumnsViewList['VariantOnGenome/Alamut']['db'][2] = false;
            }
            // The VariantOnTranscript/DNA column here has the gene name there, too, which should be usable for filtering.
            // Tell the filtering to use HAVING instead of WHERE for this column, using the alias.
            if (isset($this->aColumnsViewList['VariantOnTranscript/DNA']['db'])) {
                $this->aColumnsViewList['VariantOnTranscript/DNA']['db'][0] = 'VariantOnTranscript_DNA';
            }



            // Some fixed columns are supposed to be shown AFTER this objects's custom columns, so we'll need to go through the objects again.
            switch ($sObject) {
                case 'VariantOnTranscript':
                    // More fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                        array(
                            'gene_OMIM_' => array(
                                'view' => array('OMIM links', 100),
                                'db'   => array('__gene_OMIM', 'ASC', 'TEXT')),
                        ));
                    break;
            }
        }



        // Gather the custom link information. It's just easier to load all custom links, instead of writing code that checks for the appropiate objects.
        $aLinks = $_DB->query('SELECT l.*, GROUP_CONCAT(c2l.colid SEPARATOR ";") AS colids FROM ' . TABLE_LINKS . ' AS l INNER JOIN ' . TABLE_COLS2LINKS . ' AS c2l ON (l.id = c2l.linkid) GROUP BY l.id')->fetchAllAssoc();
        foreach ($aLinks as $aLink) {
            $aLink['regexp_pattern'] = '/' . str_replace(array('{', '}'), array('\{', '\}'), preg_replace('/\[\d\]/', '(.*)', $aLink['pattern_text'])) . '/';
            $aLink['replace_text'] = preg_replace('/\[(\d)\]/', '\$$1', $aLink['replace_text']);
            $aCols = explode(';', $aLink['colids']);
            foreach ($aCols as $sColID) {
                if (isset($this->aColumns[$sColID])) {
                    $this->aColumns[$sColID]['custom_links'][] = $aLink['id'];
                }
            }
            $this->aCustomLinks[$aLink['id']] = $aLink;
        }

        // Not including parent constructor, because these table settings will make it freak out.
        //parent::__construct();
        // Therefore, row links need to be created by us (which is done above).
    }





    function prepareData ($zData = '', $sView = 'list')
    {
        // Prepares the data by "enriching" the variable received with links, pictures, etc.

        // Needs to be done before the custom links are rendered.
        if (isset($this->aColumnsViewList['VariantOnGenome/Alamut'])) {
            $zData['VariantOnGenome/Alamut'] = '{Alamut:' . $zData['chromosome'] . ':' . str_replace('g.', '', $zData['VariantOnGenome/DNA']) . '}';
        }

        // Makes sure it's an array and htmlspecialchars() all the values.
        $zData = parent::prepareData($zData, $sView);

        // Coloring...
        if (!empty($zData['VariantOnTranscript/GVS/Function'])) {
            switch ($zData['VariantOnTranscript/GVS/Function']) {
                case 'coding-synonymous':
                case 'coding-synonymous-near-splice':
                case 'intron':
                    $zData['class_name'] = 'colGreen';
                    break;
                case 'coding':
                case 'coding-near-splice':
                case 'coding-notMod3':
                case 'codingComplex':
                case 'missense':
                case 'missense-near-splice':
                    $zData['class_name'] = 'colOrange';
                    break;
                case 'frameshift':
                case 'frameshift-near-splice':
                case 'splice':
                case 'splice-3':
                case 'splice-5':
                case 'stop-gained':
                    $zData['class_name'] = 'colRed';
                    break;
            }
        }
        // Variants marked as "to be confirmed" are transparent a bit.
        if ($zData['to_be_confirmed'] && empty($zData['confirmed'])) {
            $zData['class_name'] = (empty($zData['class_name'])? '' : $zData['class_name'] . ' ') . 'transparent50';
        }

        if (!empty($zData['VariantOnGenome/DNA'])) {
            $zData['VariantOnGenome/DNA'] = preg_replace('/ins([ACTG]{3})([ACTG]{3,})/', 'ins${1}...', $zData['VariantOnGenome/DNA']);
        }
        if (isset($zData['VariantOnTranscript_DNA'])) {
            $zData['VariantOnTranscript/DNA'] = preg_replace('/ins([ACTG]{3})([ACTG]{3,})/', 'ins${1}...', $zData['VariantOnTranscript_DNA']);
        }
        if (isset($zData['gene_OMIM'])) {
            $zData['gene_OMIM_'] = '';
            foreach ($zData['gene_OMIM'] as $aGeneOMIM) {
                if ($aGeneOMIM && count($aGeneOMIM) > 1) {
                    list($sGene, $nOMIMID) = $aGeneOMIM;
                    $zData['gene_OMIM_'] .= (!$zData['gene_OMIM_']? '' : ', ') . '<SPAN class="anchor" onclick="lovd_openWindow(\'' . lovd_getExternalSource('omim', $nOMIMID) . '\', \'GeneOMIMPage\', 1100, 650); cancelParentEvent(event);">' . $sGene . '</SPAN>';
                }
            }
        }

        return $zData;
    }
}
?>
