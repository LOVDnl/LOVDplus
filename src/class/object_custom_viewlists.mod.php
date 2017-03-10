<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2013-11-07
 * Modified    : 2017-03-03
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Anthony Marty <anthony.marty@unimelb.edu.au>
 *               Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
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
        global $_DB, $_AUTH, $_INI, $_INSTANCE_CONFIG, $_SETT;

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


        // Collect custom column information, all public and active columns.
        // In LOVD_plus, the public_view field is used to set if a custom column will be displayed in a VL or not.
        // So, in LOVD_plus we need to check for ALL USERS if a custom column has public_view flag turned on or not.
        $sSQL = 'SELECT c.id, c.width, c.head_column, c.description_legend_short, c.description_legend_full, c.mysql_type, c.form_type, c.select_options, c.col_order, c.public_view FROM ' . TABLE_ACTIVE_COLS . ' AS ac INNER JOIN ' . TABLE_COLS . ' AS c ON (c.id = ac.colid) ' .
                'WHERE c.public_view = 1 AND (c.id LIKE ?' . str_repeat(' OR c.id LIKE ?', count($aObjects)-1) . ') ' .
                'GROUP BY c.id ' .
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
        // Loop requested data types, and keep columns in order indicated by request.
        foreach ($aObjects as $nKey => $sObject) {
            switch ($sObject) {
                case 'AnalysisRunResults':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'arr.*';
                    $nKeyVOG = array_search('VariantOnGenome', $aObjects);
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_ANALYSES_RUN_RESULTS . ' AS arr';
                        // We can't have objects::viewList() optimizing on us and not giving us the normal view with VL headers
                        // because we have no results yet... Always set to 1. Doesn't need to be correct, just not zero.
                        $this->nCount = 1;
                        if (array_search('VariantOnGenome', $aObjects)) {
                            $aSQL['GROUP_BY'] = 'vog.id'; // Necessary for GROUP_CONCAT().
                        }
                    } elseif ($nKeyVOG !== false && $nKeyVOG < $nKey) { // Adding the analysis run results later.
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_ANALYSES_RUN_RESULTS . ' AS arr ON (arr.variantid = vog.id)';
                    }
                    break;

                case 'VariantOnGenome':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'vog.*, a.name AS allele_, eg.name AS vog_effect, CONCAT(cs.id, cs.name) AS curation_status_';
                    if (lovd_verifyInstance('mgha')) {
                        $aSQL['SELECT'] .= ', IF(vog.`VariantOnGenome/Sequencing/Allele/Frequency` < 1, "Het", "Hom") as zygosity_, ROUND(vog.`VariantOnGenome/Sequencing/Depth/Alt/Fraction`, 2) as var_frac_';
                    }
                    // Observation count columns.
                    // Find the diseases that this individual has assigned using the analysis run ID in $_GET.
                    if (!empty($_GET['search_runid'])) {
                        // We have selected an analyses and have to use the runid to find out the diseases this individual has.
                        $sDiseaseIDs = implode(',', $_DB->query('SELECT i2d.diseaseid FROM ' . TABLE_IND2DIS . ' AS i2d INNER JOIN ' . TABLE_SCREENINGS . ' AS scr ON (i2d.individualid = scr.individualid) INNER JOIN ' . TABLE_ANALYSES_RUN . ' AS ar ON (scr.id = ar.screeningid) WHERE ar.id = ?', array($_GET['search_runid']))->fetchAllColumn());
                    } elseif (!empty($_GET['search_variantid'])) {
                        // We are viewing the default VL that does not contain the runid but it does have some variants to find out the diseases this individual has.
                        preg_match('/^\d+/', $_GET['search_variantid'], $aRegs); // Find the first variant ID in the list of variants.
                        $sDiseaseIDs = implode(',', $_DB->query('SELECT i2d.diseaseid FROM ' . TABLE_IND2DIS . ' AS i2d INNER JOIN ' . TABLE_SCREENINGS . ' AS scr ON (i2d.individualid = scr.individualid) INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (scr.id = s2v.screeningid) WHERE s2v.variantid = ?', array($aRegs[0]))->fetchAllColumn());
                    } else {
                        // There is no data we can use to find this individuals diseases.
                        $sDiseaseIDs = '';
                    }
                    // Check if we have found any diseases and set the boolean flag accordingly.
                    $bDiseases = (bool) $sDiseaseIDs;

                    $aSQL['SELECT'] .= ', COUNT(DISTINCT os.individualid) AS obs_variant';
                    $aSQL['SELECT'] .= ', COUNT(DISTINCT os.individualid) / ' . $_DB->query('SELECT COUNT(*) FROM ' . TABLE_INDIVIDUALS)->fetchColumn() . ' AS obs_var_ind_ratio';

                    if ($bDiseases) {
                        // If this individual has diseases then setup the disease specific observation count columns.
                        $aSQL['SELECT'] .= ', COUNT(DISTINCT odi2d.individualid) AS obs_disease';
                        $aSQL['SELECT'] .= ', COUNT(DISTINCT odi2d.individualid) / ' . $_DB->query('SELECT COUNT(DISTINCT i2d.individualid) FROM ' . TABLE_IND2DIS . ' AS i2d WHERE i2d.diseaseid IN (' . $sDiseaseIDs . ')')->fetchColumn() . ' AS obs_var_dis_ind_ratio';
                    } else {
                        // Otherwise do not do anything for the disease specific observation count columns.
                        $aSQL['SELECT'] .= ', NULL AS obs_disease, NULL AS obs_var_dis_ind_ratio';
                    }

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
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_CURATION_STATUS . ' AS cs ON (vog.curation_statusid = cs.id)';

                    // Outer joins for the observation counts.
                    // Join the variants table using the DBID to get all of the variants that are the same as this one.
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_VARIANTS . ' AS ovog USING (`VariantOnGenome/DBID`)';
                    // Join the screening2variants table to get the screening IDs for all these variants.
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS os2v ON (ovog.id = os2v.variantid)';
                    // Join the screening table to to get the individual IDs for these variants as we count the DISTINCT individualids.
                    $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS os ON (os2v.screeningid = os.id)';

                    // Outer join for the disease specific observation counts.
                    if ($bDiseases) {
                        // Join the individuals2diseases table to get the individuals with this variant and this individuals diseases.
                        $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_IND2DIS . ' AS odi2d ON (os.individualid = odi2d.individualid AND odi2d.diseaseid in(' . $sDiseaseIDs . '))';
                    }
                    break;

                case 'VariantOnTranscript':
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
                                $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'GROUP_CONCAT(DISTINCT ' . ($sCol != 'VariantOnTranscript/DNA'? '`' . $sCol . '`' : 'CONCAT(t.geneid, ":", `' . $sCol . '`)') . ' ORDER BY ' . $sGCOrderBy . ' SEPARATOR ", ") AS `' . $sCol . '`';
                            }
                        }
                        // Security checks in this file's prepareData() need geneid to see if the column in question is set to non-public for one of the genes.
                        $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'GROUP_CONCAT(DISTINCT t.geneid SEPARATOR ";") AS _geneid, GROUP_CONCAT(DISTINCT IF(IFNULL(g.id_omim, 0) = 0, "", CONCAT(g.id, ";", g.id_omim)) SEPARATOR ";;") AS __gene_OMIM';
                        // Disease are to be displayed separated by semocolon.
                        // Each disease is displayed as [SYMBOL: INHERITANCE] or if symbol does not exist [NAME: INHERITANCE]
                        $aSQL['SELECT'] .= ', (SELECT GROUP_CONCAT(
                                                        DISTINCT 
                                                            IF(CASE d.symbol WHEN "-" THEN "" ELSE d.symbol END = "", 
                                                                IF(d.inheritance IS NULL, d.name, CONCAT(d.name, IF(d.inheritance = "", "", ": " ), d.inheritance)), 
                                                                IF(d.inheritance IS NULL, d.symbol, CONCAT(d.symbol, IF(d.inheritance = "", "", ": " ), d.inheritance))
                                                            ) 
                                                        ORDER BY (d.symbol != "" AND d.symbol != "-") DESC, d.symbol, d.name SEPARATOR "; ") 
                                               FROM ' . TABLE_GEN2DIS . ' g2d INNER JOIN ' . TABLE_DISEASES . ' d ON (g2d.diseaseid = d.id) 
                                               WHERE g2d.geneid = g.id) 
                                               AS gene_disease_names';
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_VARIANTS_ON_TRANSCRIPTS . ' AS vot ON (';
                        // Earlier, VOG was used, join to that.
                        $aSQL['FROM'] .= 'vog.id = vot.id)';
                        // Join to transcripts for the NM number, but also to genes to show the gene's OMIM ID.
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_TRANSCRIPTS . ' AS t ON (vot.transcriptid = t.id) LEFT JOIN ' . TABLE_GENES . ' AS g ON (t.geneid = g.id)';
                        // We have no fallback, so we'll easily detect an error if we messed up somewhere.

                    }
                    break;

                case 'Individual':
                    $nKeyScreening = array_search('Screening', $aObjects);
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'i.*';
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_INDIVIDUALS . ' AS i';
                    } elseif ($nKeyScreening !== false && $nKeyScreening < $nKey) {
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id)';
                    }
                    break;

                case 'Screening':
                    $nKeyVOG = array_search('VariantOnGenome', $aObjects);
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 's.*';
                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_SCR2VAR . ' AS s2v
                                        LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (vog.id = s2v.screeningid)';
                    } elseif ($nKeyVOG !== false && $nKeyVOG < $nKey) {
                        $aSQL['FROM'] .= ' LEFT JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid)
                                           LEFT JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid)';
                    }
                    break;

                case 'GenePanels':
                    $aSQL['SELECT'] .= (!$aSQL['SELECT']? '' : ', ') . 'GROUP_CONCAT(DISTINCT gp.name SEPARATOR ", ") AS gene_panels,
                        IFNULL(GROUP_CONCAT(DISTINCT t_preferred.id_ncbi SEPARATOR ";"),  GROUP_CONCAT(DISTINCT t.id_ncbi SEPARATOR ";") ) AS preferred_transcripts,
                        MIN(t_preferred.id) AS preferred_transcriptid';
                    $nKeyVOT = array_search('VariantOnTranscript', $aObjects);

                    if (!$aSQL['FROM']) {
                        // First data table in query.
                        $aSQL['FROM'] = TABLE_GP2GENE . ' AS gp2g  
                                        LEFT OUTER JOIN ' . TABLE_GENE_PANELS . ' AS gp ON (gp2g.genepanelid = gp.id)';
                    } elseif ($nKeyVOT !== false && $nKeyVOT < $nKey) {
                        $aSQL['FROM'] .= ' LEFT OUTER JOIN ' . TABLE_GP2GENE . ' AS gp2g ON (t.geneid = gp2g.geneid) 
                                           LEFT OUTER JOIN ' . TABLE_GENE_PANELS . ' AS gp ON (gp2g.genepanelid = gp.id)
                                           LEFT OUTER JOIN ' . TABLE_TRANSCRIPTS . ' AS t_preferred ON (gp2g.transcriptid = t_preferred.id)';
                    }
                    if (array_search('VariantOnGenome', $aObjects)) {
                        $aSQL['GROUP_BY'] = 'vog.id'; // Necessary for GROUP_CONCAT().
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

                    $sEffectLegend = 'The variant\'s affect on a protein\'s function, in the format <STRONG>[Reported]/[Curator concluded]</STRONG> indicating:
                    <TABLE border="0" cellpadding="0" cellspacing="0">
                      <TR>
                        <TD width="30">+</TD>
                        <TD>The variant affects function</TD>
                      </TR>
                      <TR>
                        <TD>+?</TD>
                        <TD>The variant probably affects function</TD>
                      </TR>
                      <TR>
                        <TD>-</TD>
                        <TD>The variant does not affect function</TD>
                      </TR>
                      <TR>
                        <TD>-?</TD>
                        <TD>The variant probably does not affect function</TD>
                      </TR>
                      <TR>
                        <TD>?</TD>
                        <TD>Effect unknown</TD>
                      </TR>
                      <TR>
                        <TD>.</TD>
                        <TD>Effect not classified</TD>
                      </TR>
                    </TABLE>';

                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                // NOTE: there are more columns defined a little further below.
                                'curation_status_' => array(
                                        'view' => array('Curation status', 70),
                                        'db'   => array('curation_status_', 'ASC', 'TEXT'),
                                        'legend' => array('The variant\'s curation status.',
                                        'The variant\'s curation status.')),
                                'curation_statusid' => array(
                                        'view' => false,
                                        'db'   => array('vog.curation_statusid', 'ASC', true)),
                                'variantid' => array(
                                        'view' => false,
                                        'db'   => array('vog.id', 'ASC', true)),
                                'vog_effect' => array(
                                        'view' => array('Effect', 70),
                                        'db'   => array('eg.name', 'ASC', true),
                                        'legend' => array(
                                            strip_tags(str_replace(array('<TR>', '</TD> <TD>'), array("\n", '      '), preg_replace('/\s+/', ' ', strip_tags($sEffectLegend, '<tr><td>')))),
                                            $sEffectLegend)),
                                'chromosome' => array(
                                        'view' => array('Chr', 40),
                                        'db'   => array('vog.chromosome', 'ASC', true)),
                                'allele_' => array(
                                     'view' => array('Allele', 'ASC', true),
                                     'db'   => array('allele_', 'ASC', true)),
                              ));

                    if (!$this->sSortDefault) {
                        // First data table in view.
                        $this->sSortDefault = 'VariantOnGenome/DNA';
                    }
                    $this->sRowLink = 'variants/{{zData_row_id}}#{{zData_transcriptid}}';
                    break;

                case 'VariantOnTranscript':
                    $sInheritance_legend = '<BR/>Inheritance<BR/><TABLE>';
                    foreach ($_SETT['diseases_inheritance'] as $sKey => $sDescription) {
                        $sInheritance_legend .= '<TR><TD>' . $sKey . '</TD><TD>: ' . $sDescription . '</TD></TR>';
                    }
                    $sInheritance_legend .= '</TABLE>';

                    $sPrefix = 'vot.';
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                         array(
                                'transcriptid' => array(
                                        'view' => false,
                                        'db'   => array('vot.transcriptid', 'ASC', true)),
                                'gene_disease_names' => array(
                                     'view' => array('Associated diseases', 200),
                                     'db'   => array('gene_disease_names', 'ASC', 'TEXT'),
                                     'legend' => array('The diseases associated with this variant\'s gene(s).' . strip_tags(str_replace(array('<TR>', '</TD> <TD>'), array("\n", '      '), preg_replace('/\s+/', ' ', strip_tags($sInheritance_legend, '<tr><td>')))),
                                         'The diseases associated with this variant\'s gene(s).' . $sInheritance_legend)),
                              ));

                    if (lovd_verifyInstance('mgha', false)) {
                        $this->aColumnsViewList = array_merge($this->aColumnsViewList, array(
                            'symbol' => array(
                                'view' => array('Gene', 10),
                                'db'   => array('symbol', 'ASC', true)),
                            'transcript' => array(
                                'view' => array('Transcript', 20),
                                'db'   => array('transcript', 'ASC', true)),
                            'preferred_transcripts' => array(
                                'view' => array('Transcript', 20),
                                'db'   => array('preferred_transcripts', 'ASC', true)),
                        ));
                    }

                    if (!$this->sSortDefault) {
                        // First data table in view.
                        $this->sSortDefault = 'VariantOnTranscript/DNA';
                    }
                    break;
                case 'Screening':
                    $sPrefix = 's.';
                    break;
                case 'Individual':
                    $sPrefix = 'i.';
                    break;
            }



            // The custom columns.
            foreach ($this->aColumns as $sColID => $aCol) {
                if (strpos($sColID, $sObject . '/') === 0) {
                    $bAlignRight = preg_match('/^(DEC|FLOAT|(TINY|SMALL|MEDIUM|BIG)?INT)/', $aCol['mysql_type']);

                    $this->aColumnsViewList[$sColID] =
                         array(
                                'view' => array($aCol['head_column'], $aCol['width'], ($bAlignRight? ' align="right"' : '')),
                                'db'   => array($sPrefix . '`' . $aCol['id'] . '`', 'ASC', lovd_getColumnType('', $aCol['mysql_type'])),
                                'legend' => array($aCol['description_legend_short'], $aCol['description_legend_full']),
                              );
                }
            }
            // Alamut link should not be searchable, because we have nothing in those columns.
            if (isset($this->aColumnsViewList['VariantOnGenome/Alamut']['db'][2])) {
                $this->aColumnsViewList['VariantOnGenome/Alamut']['db'][2] = false;
            }
            // The VariantOnTranscript/DNA column here has the gene name there, too, which should be usable for filtering.
            if (isset($this->aColumnsViewList['VariantOnTranscript/DNA']['db'])) {
                $this->aColumnsViewList['VariantOnTranscript/DNA']['db'][0] = 'CONCAT(t.geneid, ":", `VariantOnTranscript/DNA`)';
            }

            // Hide the DBID column, just for the MGHA.
            if (lovd_verifyInstance('mgha', false)) {
                $this->aColumnsViewList['VariantOnGenome/DBID']['view'] = false;
            }

            // Variant Priority is to be sorted in DESC order.
            if (isset($this->aColumnsViewList['VariantOnGenome/Variant_priority']['db'])) {
                $this->aColumnsViewList['VariantOnGenome/Variant_priority']['db'][1] = 'DESC';
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

                    if (lovd_verifyInstance('mgha', false)) {
                        $this->aColumnsViewList = array_merge($this->aColumnsViewList, array(
                            'clinvar_' => array(
                                'view' => array('ClinVar Description', 100)
                            ),
                        ));
                    }
                    break;
                case 'VariantOnGenome':
                    // The fixed columns.
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                        array(
                            'obs_variant' => array(
                                'view' => array('#Ind. w/ var.', 70),
                                'db'   => array('obs_variant', 'ASC', 'INT'),
                                'legend' => array('The number of individuals with this variant within this database.',
                                    'The number of individuals with this variant within this database.')),
                            'obs_var_ind_ratio' => array(
                                'view' => array('Var. ind. ratio', 70),
                                'db'   => array('obs_var_ind_ratio', 'ASC', 'DECIMAL'),
                                'legend' => array('The ratio of the number of individuals with this variant divided by the total number of individuals within this database.',
                                    'The ratio of the number of individuals with this variant divided by the total number of individuals within this database.')),
                            'obs_disease' => array(
                                'view' => array('#Ind. w/ var & dis.', 70),
                                'db'   => array('obs_disease', 'ASC', 'INT'),
                                'legend' => array('The number of individuals with this variant within this database that have at least one of the diseases in common as this individual.',
                                    'The number of individuals with this variant within this database that have at least one of the diseases in common as this individual.')),
                            'obs_var_dis_ind_ratio' => array(
                                'view' => array('Var. dis. ind. ratio', 70),
                                'db'   => array('obs_var_dis_ind_ratio', 'ASC', 'DECIMAL'),
                                'legend' => array('The ratio of the number of individuals with this variant and this disease divided by the total number of individuals with this disease within this database.',
                                    'The ratio of the number of individuals with this variant and this disease divided by the total number of individuals with this disease within this database.')),
                        ));

                        if ($_INI['instance']['name'] == 'mgha') {
                            $this->aColumnsViewList = array_merge($this->aColumnsViewList, array(
                                'zygosity_' => array(
                                    'view' => array('Zygosity', 70),
                                    'db' => array('zygosity_', 'ASC', 'TEXT'),
                                ),
                                'var_frac_' => array(
                                    'view' => array('Var Frac', 70),
                                    'db' => array('var_frac_', 'ASC', 'DECIMAL'),
                                ),
                            ));
                        }
                    break;

                case 'GenePanels':
                    $this->aColumnsViewList = array_merge($this->aColumnsViewList,
                        array(
                            'gene_panels' => array(
                                'view' => array('Gene panels', 150),
                                'db'   => array('gene_panels', 'DESC', 'TEXT')),
                        ));
                    break;
            }
        }



        // Modifications specifically for the Analysis Results VL.
        // The table is regarded too wide, and columns need to be narrowed.
        // We could shorten headers etc in the database, but this will shorten
        // them too for the VE, which reduces the clarity of the data.
        // FIXME+: Put this into the adapter as well.
        $aVLModifications = array(
            'VariantOnGenome/DNA' => array('view' => array('DNA change (genomic)', 100)),
            'VariantOnGenome/Alamut' => array('view' => array('Alamut', 60)),
            'VariantOnGenome/Conservation_score/PhyloP' => array('view' => array('PhyloP', 60)),
            'VariantOnGenome/HGMD/Association' => array('view' => array('HGMD', 50)),
            'VariantOnGenome/Sequencing/Depth/Alt/Fraction' => array('view' => array('RD Alt (%)', 90)),
            'VariantOnGenome/Sequencing/Quality' => array('view' => array('Seq. Q.', 60)),
            'VariantOnTranscript/DNA' => array('view' => array('DNA change (cDNA)', 100)),
            'VariantOnTranscript/Protein' => array('view' => array('Protein', 100)),
        );
        foreach ($aVLModifications as $sCol => $aModifications) {
            if (isset($this->aColumnsViewList[$sCol])) {
                $this->aColumnsViewList[$sCol] = array_merge($this->aColumnsViewList[$sCol], $aModifications);
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





    function prepareData ($zData = '', $sView = 'list', $sViewListID = '')
    {
        global $_SETT;

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
        // Variants requiring confirmation are transparent a bit.
        if (!empty($zData['curation_statusid']) && $zData['curation_statusid'] == CUR_STATUS_REQUIRES_CONFIRMATION) {
            $zData['class_name'] = (empty($zData['class_name'])? '' : $zData['class_name'] . ' ') . 'transparent50';
        }

        if (!empty($zData['VariantOnGenome/DNA'])) {
            $zData['VariantOnGenome/DNA'] = preg_replace('/ins([ACTG]{3})([ACTG]{3,})/', 'ins${1}...', $zData['VariantOnGenome/DNA']);
        }
        if (isset($zData['VariantOnTranscript/DNA'])) {
            $zData['VariantOnTranscript/DNA'] = preg_replace('/ins([ACTG]{3})([ACTG]{3,})/', 'ins${1}...', $zData['VariantOnTranscript/DNA']);
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
        if (!empty($zData['curation_status_'])) {
            $zData['curation_status_'] = substr($zData['curation_status_'], 2);
        }

        if (isset($zData['VariantOnTranscript/dbNSFP/ClinVar/Clinical_Significance'])) {
            $zData['clinvar_'] = implode(', ', lovd_mapCodeToDescription(explode(',', $zData['VariantOnTranscript/dbNSFP/ClinVar/Clinical_Significance'], $_SETT['clinvar_var_effect'])));
        }

        return $zData;
    }





    function viewList ($sViewListID = false, $aColsToSkip = array(), $bNoHistory = false, $bHideNav = false, $bOptions = false, $bOnlyRows = false)
    {
        global $_INSTANCE_CONFIG;

        // Re-order columns order in viewlist before we call the parent's constructor
        $sViewListID = (empty($sViewListID)? 0 : $sViewListID);
        if (isset($_INSTANCE_CONFIG['custom_object']['viewList']['colsToShow'][$sViewListID])) {
            $aColsToShow = $_INSTANCE_CONFIG['custom_object']['viewList'] ['colsToShow'][$sViewListID];

            $aReorderedViewList = array();
            foreach ($aColsToShow as $sColName) {
                if (isset($this->aColumnsViewList[$sColName])) {
                    $aReorderedViewList[$sColName] = $this->aColumnsViewList[$sColName];
                }
            }

            $this->aColumnsViewList = $aReorderedViewList;
        }

        return parent::viewList($sViewListID, $aColsToSkip, $bNoHistory, $bHideNav, $bOptions, $bOnlyRows);
    }
}
?>
