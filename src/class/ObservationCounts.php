<?php

/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-01-12
 * Modified    : 2017-01-12
 * For LOVD    : 3.0-13
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
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

if (!defined('ROOT_PATH')) {
    exit;
}

class LOVD_ObservationCounts
{
    protected $aData = array();
    protected $aIndividual = array();
    protected $nVariantId = null;
    protected $nTimeGenerated = null;

    protected $aConfig = array();
    protected $aCategories = array();
    protected $aColumns = array();

    function __construct ($nVariantId) {

        $this->nVariantId = $nVariantId;
        $this->loadExistingData();
    }

    public function getData() {
        return $this->aData;
    }

    public function getTimeGenerated() {
        return $this->nTimeGenerated;
    }

    protected function loadExistingData() {
        global $_DB;

        $sSQL = 'SELECT obscount_json, obscount_generated FROM ' . TABLE_VARIANTS . ' WHERE id = "' . $this->nVariantId . '"';
        $zResult = $_DB->query($sSQL)->fetchAssoc();

        if (!empty($zResult['obscount_json'])) {
            $this->aData = json_decode($zResult['obscount_json'], true);
        }

        if (!empty($zResult['obscount_generated'])) {
            $this->nTimeGenerated = strtotime($zResult['obscount_generated']);
        }

        return $this->aData;
    }

    protected function buildCategoryConfig($aCategories = array()) {

        $aConfig = array();

        $aGenepanelIds = array();
        $aGenepanelNames = array();
        if (!empty($this->aIndividual['genepanel_ids']) && !empty($this->aIndividual['genepanel_names'])) {
            $aGenepanelIds = explode(',', $this->aIndividual['genepanel_ids']);
            $aGenepanelNames = explode(',', $this->aIndividual['genepanel_names']);
        }

        foreach ($aGenepanelIds as $nIndex => $sGenepanelId) {
            $this->aIndividual['genepanel_' . $sGenepanelId] = $aGenepanelNames[$nIndex];

            $aConfig['genepanel_all_' . $sGenepanelId] = array(
                'label' => 'Gene Panel',
                'table' => TABLE_IND2GP,
                'fields' => array('genepanel_' . $sGenepanelId),
                'condition' => 'genepanelid = "' . $sGenepanelId . '"'
            );

            $aConfig['genepanel_gender_' . $sGenepanelId] = array(
                'label' => 'Gene Panel and Gender',
                'table' => TABLE_IND2GP,
                'fields' => array('genepanel_' . $sGenepanelId, 'Individual/Gender'),
                'condition' => 'genepanelid = "' . $sGenepanelId . '"'
                    . ' AND '
                    . '`Individual/Gender` = "' . $this->aIndividual['Individual/Gender'] . '"'
            );

            $aConfig['genepanel_ethnic_' . $sGenepanelId] = array(
                'label' => 'Gene Panel and Ethinicity',
                'table' => TABLE_IND2GP,
                'fields' => array('genepanel_' . $sGenepanelId, 'Individual/Origin/Ethnic'),
                'condition' => 'genepanelid = "' . $sGenepanelId . '"'
                    . ' AND '
                    . '`Individual/Origin/Ethnic` = "' . $this->aIndividual['Individual/Origin/Ethnic'] . '"'
            );
        }

        $aConfig = array_merge($aConfig, array(
            'Individual/Gender' => array(
                'label' => 'Gender',
                'fields' => array('Individual/Gender'),
                'condition' => '`Individual/Gender` = "' . $this->aIndividual['Individual/Gender'] . '"'
            ),
            'Individual/Origin/Ethnic' => array(
                'label' => 'Ethnicity',
                'fields' => array('Individual/Origin/Ethnic'),
                'condition' => '`Individual/Origin/Ethnic` = "' . $this->aIndividual['Individual/Origin/Ethnic'] . '"'
            ),
            'Screening/Sample/Type' => array(
                'label' => 'Sample Type',
                'fields' => array('Screening/Sample/Type'),
                'condition' => '`Screening/Sample/Type` = "' . $this->aIndividual['Screening/Sample/Type'] . '"'
            ),
            'Screening/Library_preparation' => array(
                'label' => 'Capture Method',
                'fields' => array('Screening/Library_preparation'),
                'condition' => '`Screening/Library_preparation` = "' . $this->aIndividual['Screening/Library_preparation'] . '"'
            ),
            'Screening/Sequencing_software' => array(
                'label' => 'Sequencing Technology',
                'fields' => array('Screening/Sequencing_software'),
                'condition' => '`Screening/Sequencing_Software` = "' . $this->aIndividual['Screening/Sequencing_software'] . '"'
            ),
            'Screening/Analysis_type' => array(
                'label' => 'Analysis Pipeline',
                'fields' => array('Screening/Analysis_type'),
                'condition' => '`Screening/Analysis_type` = "' . $this->aIndividual['Screening/Analysis_type'] . '"'
            ),
            'Screening/Library_preparation&Screening/Sequencing_software' => array(
                'label' => 'Same Capture Method and Sequencing Technology',
                'fields' => array('Screening/Library_preparation', 'Screening/Sequencing_software'),
                'condition' => '`Screening/Library_preparation` = "' . $this->aIndividual['Screening/Library_preparation'] . '"'
                    . ' AND '
                    . '`Screening/Sequencing_Software` = "' . $this->aIndividual['Screening/Sequencing_software'] . '"'
            ),
            'Screening/Library_preparation&Screening/Sequencing_software&Screening/Analysis_type' => array(
                'label' => 'Same Capture Method, Sequencing Technology, and Analysis Pipeline',
                'fields' => array('Screening/Library_preparation', 'Screening/Sequencing_software', 'Screening/Analysis_type'),
                'condition' => '`Screening/Library_preparation` = "' . $this->aIndividual['Screening/Library_preparation'] . '"'
                    . ' AND '
                    . '`Screening/Sequencing_Software` = "' . $this->aIndividual['Screening/Sequencing_software'] . '"'
                    . ' AND '
                    . '`Screening/Analysis_type` = "' . $this->aIndividual['Screening/Analysis_type'] . '"'
            )
        ));

        if (empty($aCategories)) {
            $this->aCategories = $aConfig;
        } else {
            // Allow the category order to follow the order passed in $aCategories
            foreach ($aCategories as $sCat) {
                if (isset($aConfig[$sCat])) {
                    $this->aCategories[$sCat] = $aConfig[$sCat];
                } elseif (strpos($sCat, '*') !== false) {
                    // genepanel category is one example that required pattern matching
                    foreach ($aConfig as $sKey => $aOneConfig) {
                        if (preg_match('/'.$sCat.'/', $sKey)) {
                            $this->aCategories[$sKey] = $aConfig[$sKey];
                        }
                    }
                }
            }
        }

        return $this->aCategories;
    }

    protected function validateColumns($aColumns = array()) {

        $aAvailableColumns = array(
            'label',
            'values',
            'total_individuals',
            'num_affected',
            'num_not_affected',
            'num_ind_with_variant',
            'percentage'
        );

        if (empty($aColumns)) {
            $this->aColumns = $aAvailableColumns;
        } else {
            // Allow the column order to follow the column order passed in $aColumns
            $this->aColumns = array_intersect(array_keys($aColumns), $aAvailableColumns);
        }

        return $this->aColumns;
    }

    protected function initIndividualData() {
        global $_DB;

        // Query data related to this individual
        $sSQL = 'SELECT i.*, s.*, vog.*, 
                 GROUP_CONCAT(DISTINCT i2gp.genepanelid ORDER BY i2gp.genepanelid SEPARATOR ",") AS genepanel_ids, 
                 GROUP_CONCAT(DISTINCT gp.name ORDER BY i2gp.genepanelid SEPARATOR ",") AS genepanel_names 
                 FROM ' . TABLE_VARIANTS . ' AS vog 
                 JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid AND vog.id = "' . $this->nVariantId . '") 
                 JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) 
                 JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) 
                 LEFT JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i.id = i2gp.individualid) 
                 LEFT JOIN ' . TABLE_GENE_PANELS . ' AS gp ON (i2gp.genepanelid = gp.id) 
                 GROUP BY i.id';

        $this->aIndividual = $_DB->query($sSQL)->fetchAssoc();

        return $this->aIndividual;
    }

    public function buildData($aColumns, $aCategories) {
        global $_DB;

        $this->aIndividual = $this->initIndividualData();
        $this->aCategories = $this->buildCategoryConfig($aCategories);
        $this->aColumns = $this->validateColumns($aColumns);

        $aData = array();
        foreach ($this->aCategories as $sCategory => $aRules) {
            $aData[$sCategory] = array();
            $aData[$sCategory]['label'] = $aRules['label'];
            $aData[$sCategory]['values'] = array();
            foreach ($aRules['fields'] as $sField) {
                $aData[$sCategory]['values'][] = $this->aIndividual[$sField];
            }

            // TOTAL population in this database
            $sSQL = 'SELECT COUNT(s.individualid) AS total
                     FROM ' . TABLE_INDIVIDUALS . ' i 
                     JOIN ' . TABLE_SCREENINGS . ' s ON (s.individualid = i.id)
                     LEFT JOIN ' . TABLE_IND2GP . ' i2gp ON (i2gp.individualid = i.id) 
                     WHERE ' . $aRules['condition'] . ' 
                     GROUP BY s.individualid';

            $aCount = $_DB->query($sSQL, array())->rowCount();
            $aData[$sCategory]['total_individuals'] = $aCount;

            // TOTAL number of affected individuals in this database
            $sSQL = 'SELECT COUNT(s.individualid) AS total_affected
                     FROM ' . TABLE_INDIVIDUALS . ' i 
                     JOIN ' . TABLE_SCREENINGS . ' s ON (s.individualid = i.id AND i.`Individual/Affected` = "affected")
                     LEFT JOIN ' . TABLE_IND2GP . ' i2gp ON (i2gp.individualid = i.id) 
                     WHERE ' . $aRules['condition'] . ' 
                     GROUP BY s.individualid';

            $aCount = $_DB->query($sSQL, array())->rowCount();
            $aData[$sCategory]['num_affected'] = $aCount;

            // TOTAL number of affected individuals in this database
            $sSQL = 'SELECT COUNT(s.individualid) AS total_not_affected
                     FROM ' . TABLE_INDIVIDUALS . ' i 
                     JOIN ' . TABLE_SCREENINGS . ' s ON (s.individualid = i.id AND i.`Individual/Affected` = "not affected")
                     LEFT JOIN ' . TABLE_IND2GP . ' i2gp ON (i2gp.individualid = i.id) 
                     WHERE ' . $aRules['condition'] . ' 
                     GROUP BY s.individualid';

            $aCount = $_DB->query($sSQL, array())->rowCount();
            $aData[$sCategory]['num_not_affected'] = $aCount;

            // Number of individuals with this variant
            $sSQL = 'SELECT COUNT(s.individualid) AS count_dbid 
                     FROM ' . TABLE_VARIANTS . ' AS vog 
                     JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid AND vog.`VariantOnGenome/DBID` = "' . $this->aIndividual['VariantOnGenome/DBID'] . '") 
                     JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) 
                     JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) 
                     LEFT JOIN ' . TABLE_IND2GP . ' i2gp ON (i2gp.individualid = i.id) 
                     WHERE ' . $aRules['condition'] . ' 
                     GROUP BY s.individualid';

            $aCountDBID = $_DB->query($sSQL)->rowCount();
            $aData[$sCategory]['num_ind_with_variant'] = $aCountDBID;

            if (!empty($aData[$sCategory]['total_individuals'])) {
                $aData[$sCategory]['percentage'] = round((float) $aData[$sCategory]['num_ind_with_variant'] / (float) $aData[$sCategory]['total_individuals'] * 100, 0);
            }
        }

        // Save the built data into the database so that we can reuse next time as well as keeping history
        $sObscountJson = json_encode($aData);
        $sSQL = "UPDATE " . TABLE_VARIANTS . " SET obscount_json = '$sObscountJson', obscount_generated = NOW() WHERE id = ?";
        $_DB->query($sSQL, array($this->nVariantId));

        $this->aData = $this->loadExistingData();
        return $this->aData;

    }
}