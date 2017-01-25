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
    protected $nCurrentPopulationSize = null;

    protected $aCategories = array();
    protected $aColumns = array();

    public static $TYPE_GENEPANEL = 'genepanel';
    public static $TYPE_GENERAL = 'general';
    public static $EMPTY_DATA_DISPLAY = '-';
    protected static $DEFAULT_MIN_POP_SIZE = 100;

    function __construct ($nVariantId) {

        $this->nVariantId = $nVariantId;
        $this->loadExistingData();
    }

    public function getData () {
        return $this->aData;
    }

    public function getTimeGenerated () {
        return $this->nTimeGenerated;
    }

    public function getCurrentPopulationSize() {
        global $_DB;

        if ($this->nCurrentPopulationSize === null) {
            // Generate from database
            $sSQL = static::getQueryFor('population_size');
            $this->nCurrentPopulationSize = $_DB->query($sSQL)->rowCount();
        }

        return $this->nCurrentPopulationSize;
    }

    public function getDataPopulationSize() {
        if (isset($this->aData['population_size'])) {
            return $this->aData['population_size'];
        }

        return null;
    }

    protected function loadExistingData () {
        global $_DB;

        $sSQL = 'SELECT obscount_json, obscount_updated FROM ' . TABLE_VARIANTS . ' WHERE id = "' . $this->nVariantId . '"';
        $zResult = $_DB->query($sSQL)->fetchAssoc();

        if (!empty($zResult['obscount_json'])) {
            $this->aData = json_decode($zResult['obscount_json'], true);
        }

        if (!empty($zResult['obscount_updated'])) {
            $this->nTimeGenerated = strtotime($zResult['obscount_updated']);
        }

        return $this->aData;
    }

    public function buildData ($aSettings = array()) {
        global $_DB;

        // Check if current analysis status as well as user's permission allow data to be generated.
        if (!$this->canUpdateData()) {
            return array();
        }

        define('LOG_EVENT', 'UpdateObsCounts');

        $this->aIndividual = $this->initIndividualData();
        $aData = array();
        $aData['population_size'] = $this->getCurrentPopulationSize();

        foreach ($aSettings as $sType => $aTypeSettings) {
            $this->aCategories = $this->validateCategories($sType, $aTypeSettings);
            $this->aColumns = $this->validateColumns($sType, $aTypeSettings);

            switch ($sType) {
                case static::$TYPE_GENERAL:
                    $minPopSize = static::$DEFAULT_MIN_POP_SIZE;
                    if (isset($aSettings[static::$TYPE_GENERAL]['min_population_size'])) {
                        $minPopSize = $aSettings[static::$TYPE_GENERAL]['min_population_size'];
                    }

                    if ($aData['population_size'] < $minPopSize) {
                        $aData[static::$TYPE_GENERAL]['error'] = 'Data cannot be generated because population size is too small.';
                        break;
                    }

                    $aData[static::$TYPE_GENERAL] = array();
                    foreach ($this->aCategories[static::$TYPE_GENERAL] as $sCategory => $aRules) {
                        $aData[static::$TYPE_GENERAL][$sCategory] = $this->generateData($aRules);
                    }
                    break;
                case static::$TYPE_GENEPANEL:
                    $aData[static::$TYPE_GENEPANEL] = array();
                    foreach ($this->aCategories[static::$TYPE_GENEPANEL] as $sGenepanelId => $aGenepanelRules) {
                        foreach ($aGenepanelRules as $sCategory => $aRules) {
                            $aData[static::$TYPE_GENEPANEL][$sGenepanelId][$sCategory] = $this->generateData($aRules);
                        }
                    }
            }
        }

        // Save the built data into the database so that we can reuse next time as well as keeping history
        $sObscountJson = json_encode($aData);
        $sSQL = "UPDATE " . TABLE_VARIANTS . " SET obscount_json = '$sObscountJson', obscount_updated = NOW() WHERE id = ?";
        $_DB->query($sSQL, array($this->nVariantId));

        lovd_writeLog('Event', LOG_EVENT, 'Created Observation Counts for variant #' . $this->nVariantId . '. JSON DATA: ' . $sObscountJson);

        $this->aData = $this->loadExistingData();
        return $this->aData;

    }

    public function canUpdateData() {
        global $_DB, $_AUTH;

        $sSQL = 'SELECT s.analysis_statusid
                 FROM ' . TABLE_SCREENINGS . ' AS s
                 JOIN ' . TABLE_SCR2VAR . ' AS s2v 
                 ON (s.id = s2v.screeningid AND s2v.variantid = ' . $this->nVariantId . ')';

        $aResult = $_DB->query($sSQL)->fetchAssoc();

        if ($aResult['analysis_statusid'] == ANALYSIS_STATUS_READY) {
            return true;
        }

        if ($aResult['analysis_statusid'] == ANALYSIS_STATUS_IN_PROGRESS) {
            if ($_AUTH['level'] >= LEVEL_OWNER || !$this->loadExistingData()) {
                return true;
            }
        }

        // Every other analysis status (including CLOSED) cannot update observation count data.
        return false;
    }

    protected function generateData ($aRules) {
        global $_DB;

        $aData = array();
        $aData['label'] = $aRules['label'];

        $aData['values'] = static::$EMPTY_DATA_DISPLAY;
        $aData['total_individuals'] = static::$EMPTY_DATA_DISPLAY;
        $aData['num_affected'] = static::$EMPTY_DATA_DISPLAY;
        $aData['num_not_affected'] = static::$EMPTY_DATA_DISPLAY;
        $aData['num_ind_with_variant'] = static::$EMPTY_DATA_DISPLAY;
        $aData['percentage'] = static::$EMPTY_DATA_DISPLAY;
        $aData['threshold'] = static::$EMPTY_DATA_DISPLAY;

        // Only run query if this individual/screening has sufficient data
        if (empty($aRules['incomplete'])) {
            $aData['values'] = array();
            foreach ($aRules['fields'] as $sField) {
                $aData['values'][] = $this->aIndividual[$sField];
            }

            // TOTAL population in this database
            $sSQL = static::getQueryFor('total_individuals', $aRules['condition']);
            $aCount = $_DB->query($sSQL, array())->rowCount();
            $aData['total_individuals'] = $aCount;

            // TOTAL number of affected individuals in this database
            $sSQL = static::getQueryFor('num_affected', $aRules['condition']);
            $aCount = $_DB->query($sSQL, array())->rowCount();
            $aData['num_affected'] = $aCount;

            // TOTAL number of NOT affected individuals in this database
            $sSQL = static::getQueryFor('num_not_affected', $aRules['condition']);
            $aCount = $_DB->query($sSQL, array())->rowCount();
            $aData['num_not_affected'] = $aCount;

            // Number of individuals with this variant
            $sSQL = static::getQueryFor('num_ind_with_variant', $aRules['condition'], array('dbid' => $this->aIndividual['VariantOnGenome/DBID']));
            $aCountDBID = $_DB->query($sSQL)->rowCount();
            $aData['num_ind_with_variant'] = $aCountDBID;

            if (!empty($aData['total_individuals'])) {
                $aData['percentage'] = round((float) $aData['num_ind_with_variant'] / (float) $aData['total_individuals'] * 100, 0);
                if (!empty($aRules['threshold'])) {
                    $aData['threshold'] = ($aData['percentage'] > $aRules['threshold']? '> ': '<= ') . $aRules['threshold'] . ' %';
                }
            }
        }

        return $aData;
    }

    protected function validateCategories ($sType, $aSettings) {
        switch ($sType) {
            case static::$TYPE_GENERAL:
                // Build existing configuration options
                $aConfig = array(
                    'all' => array(
                        'label' => 'All',
                        'fields' => array(),
                        'condition' => 'i.id IS NOT NULL',
                        'threshold' => 2 // 2%
                    ),
                    'Individual/Gender' => array(
                        'label' => 'Gender',
                        'fields' => array('Individual/Gender'),
                        'condition' => '`Individual/Gender` = "' . $this->aIndividual['Individual/Gender'] . '"',
                        'incomplete' => ($this->aIndividual['Individual/Gender'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Individual/Origin/Ethnic' => array(
                        'label' => 'Ethnicity',
                        'fields' => array('Individual/Origin/Ethnic'),
                        'condition' => '`Individual/Origin/Ethnic` = "' . $this->aIndividual['Individual/Origin/Ethnic'] . '"',
                        'incomplete' => ($this->aIndividual['Individual/Origin/Ethnic'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Sample/Type' => array(
                        'label' => 'Sample Type',
                        'fields' => array('Screening/Sample/Type'),
                        'condition' => '`Screening/Sample/Type` = "' . $this->aIndividual['Screening/Sample/Type'] . '"',
                        'incomplete' => ($this->aIndividual['Screening/Sample/Type'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Library_preparation' => array(
                        'label' => 'Capture Method',
                        'fields' => array('Screening/Library_preparation'),
                        'condition' => '`Screening/Library_preparation` = "' . $this->aIndividual['Screening/Library_preparation'] . '"',
                        'incomplete' => ($this->aIndividual['Screening/Library_preparation'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Sequencing_software' => array(
                        'label' => 'Sequencing Technology',
                        'fields' => array('Screening/Sequencing_software'),
                        'condition' => '`Screening/Sequencing_Software` = "' . $this->aIndividual['Screening/Sequencing_software'] . '"',
                        'incomplete' => ($this->aIndividual['Screening/Sequencing_software'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Analysis_type' => array(
                        'label' => 'Analysis Pipeline',
                        'fields' => array('Screening/Analysis_type'),
                        'condition' => '`Screening/Analysis_type` = "' . $this->aIndividual['Screening/Analysis_type'] . '"',
                        'incomplete' => ($this->aIndividual['Screening/Analysis_type'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Library_preparation&Screening/Sequencing_software' => array(
                        'label' => 'Same Capture Method and Sequencing Technology',
                        'fields' => array('Screening/Library_preparation', 'Screening/Sequencing_software'),
                        'condition' => '`Screening/Library_preparation` = "' . $this->aIndividual['Screening/Library_preparation'] . '"'
                            . ' AND '
                            . '`Screening/Sequencing_Software` = "' . $this->aIndividual['Screening/Sequencing_software'] . '"',
                        'incomplete' => ($this->aIndividual['Screening/Library_preparation'] === ''
                                        || $this->aIndividual['Screening/Sequencing_software'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Library_preparation&Screening/Sequencing_software&Screening/Analysis_type' => array(
                        'label' => 'Same Capture Method, Sequencing Technology, and Analysis Pipeline',
                        'fields' => array('Screening/Library_preparation', 'Screening/Sequencing_software', 'Screening/Analysis_type'),
                        'condition' => '`Screening/Library_preparation` = "' . $this->aIndividual['Screening/Library_preparation'] . '"'
                            . ' AND '
                            . '`Screening/Sequencing_Software` = "' . $this->aIndividual['Screening/Sequencing_software'] . '"'
                            . ' AND '
                            . '`Screening/Analysis_type` = "' . $this->aIndividual['Screening/Analysis_type'] . '"',
                        'incomplete' => ($this->aIndividual['Screening/Library_preparation'] === ''
                                        || $this->aIndividual['Screening/Sequencing_software'] === ''
                                        || $this->aIndividual['Screening/Analysis_type'] === ''? true : false),
                        'threshold' => 2 // 2%
                    )
                );

                // Now build categories for this instance of LOVD based on what is specified on the settings array.

                if (empty($aSettings) || empty($aSettings['categories'])) {
                    // If categories is not specified in the settings for this type, then use ALL available categories.
                    $this->aCategories[static::$TYPE_GENERAL] = $aConfig;
                } else {
                    // Otherwise, only select the category specified in the instance settings.
                    foreach ($aSettings['categories'] as $sCategory) {
                        if (isset($aConfig[$sCategory])) {
                            $this->aCategories[static::$TYPE_GENERAL][$sCategory] = $aConfig[$sCategory];
                        }
                    }
                }

                break;

            case static::$TYPE_GENEPANEL:

                // Build existing configuration options
                $aGenepanelIds = array();
                $aGenepanelNames = array();
                if (!empty($this->aIndividual['genepanel_ids']) && !empty($this->aIndividual['genepanel_names'])) {
                    $aGenepanelIds = explode(',', $this->aIndividual['genepanel_ids']);
                    $aGenepanelNames = explode(',', $this->aIndividual['genepanel_names']);
                }

                foreach ($aGenepanelIds as $nIndex => $sGenepanelId) {
                    $this->aIndividual['genepanel_' . $sGenepanelId] = $aGenepanelNames[$nIndex];

                    $aConfig[$sGenepanelId] = array();
                    $aConfig[$sGenepanelId]['all'] = array(
                        'label' => 'Gene Panel',
                        'fields' => array('genepanel_' . $sGenepanelId),
                        'condition' => 'genepanelid = "' . $sGenepanelId . '"'
                    );

                    $aConfig[$sGenepanelId]['gender'] = array(
                        'label' => 'Gene Panel and Gender',
                        'fields' => array('Individual/Gender'),
                        'condition' => 'genepanelid = "' . $sGenepanelId . '"'
                            . ' AND '
                            . '`Individual/Gender` = "' . $this->aIndividual['Individual/Gender'] . '"',
                        'incomplete' => ($this->aIndividual['Individual/Gender'] === ''? true: false)
                    );

                    $aConfig[$sGenepanelId]['ethnic'] = array(
                        'label' => 'Gene Panel and Ethinicity',
                        'fields' => array('Individual/Origin/Ethnic'),
                        'condition' => 'genepanelid = "' . $sGenepanelId . '"'
                            . ' AND '
                            . '`Individual/Origin/Ethnic` = "' . $this->aIndividual['Individual/Origin/Ethnic'] . '"',
                        'incomplete' => ($this->aIndividual['Individual/Origin/Ethnic'] === ''? true: false)
                    );
                }

                if (empty($aSettings) || empty($aSettings['categories'])) {
                    // If categories is not specified in the settings for this type, then use ALL available categories.
                    $this->aCategories[static::$TYPE_GENEPANEL] = $aConfig;
                } else {
                    // Otherwise, only select the category specified in the instance settings.
                    foreach ($aSettings['categories'] as $sCategory) {
                        foreach ($aGenepanelIds as $nIndex => $sGenepanelId) {
                            if (isset($aConfig[$sGenepanelId][$sCategory])) {
                                $this->aCategories[static::$TYPE_GENEPANEL][$sGenepanelId][$sCategory] = $aConfig[$sGenepanelId][$sCategory];
                            }
                        }
                    }
                }


                break;
        }
        return $this->aCategories;
    }

    protected function validateColumns ($sType, $aSettings = array()) {
        $aAvailableColumns = array(
            'genepanel' => array(
                'label',
                'values',
                'total_individuals',
                'num_affected',
                'num_not_affected',
                'num_ind_with_variant',
                'percentage'
            ),
            'general' => array(
                'label',
                'values',
                'percentage',
                'threshold'
            )
        );

        $this->aColumns[$sType] = array();
        if (empty($aSettings) || empty($aSettings['columns'])) {
            // If columns is not specified in the settings for this type, then use ALL available columns.
            $this->aColumns[$sType] = $aAvailableColumns[$sType];
        } else {
            // Otherwise, only select the columns specified in the settings of this LOVD instance.
            foreach ($aSettings['columns'] as $sColumn) {
                if (isset($aAvailableColumns[$sType][$sColumn])) {
                    $this->aColumns[$sType][] = $aAvailableColumns[$sType][$sColumn];
                }
            }
        }

        return $this->aColumns;
    }

    protected function initIndividualData () {
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
                 WHERE gp.type != "blacklist"
                 GROUP BY i.id';

        $this->aIndividual = $_DB->query($sSQL)->fetchAssoc();

        return $this->aIndividual;
    }

    protected static function getQueryFor ($sColumn, $sCondition = '', $aParams = array()) {
        switch ($sColumn) {
            case 'total_individuals':
                return 'SELECT COUNT(s.individualid) AS total
                        FROM ' . TABLE_INDIVIDUALS . ' i 
                        JOIN ' . TABLE_SCREENINGS . ' s ON (s.individualid = i.id)
                        LEFT JOIN ' . TABLE_IND2GP . ' i2gp ON (i2gp.individualid = i.id) 
                        WHERE ' . $sCondition . ' 
                        GROUP BY s.individualid';

            case 'num_affected':
                return 'SELECT COUNT(s.individualid) AS total_affected
                        FROM ' . TABLE_INDIVIDUALS . ' i 
                        JOIN ' . TABLE_SCREENINGS . ' s ON (s.individualid = i.id AND i.`Individual/Affected` = "affected")
                        LEFT JOIN ' . TABLE_IND2GP . ' i2gp ON (i2gp.individualid = i.id) 
                        WHERE ' . $sCondition . ' 
                        GROUP BY s.individualid';

            case 'num_not_affected':
                return 'SELECT COUNT(s.individualid) AS total_not_affected
                        FROM ' . TABLE_INDIVIDUALS . ' i 
                        JOIN ' . TABLE_SCREENINGS . ' s ON (s.individualid = i.id AND i.`Individual/Affected` = "not affected")
                        LEFT JOIN ' . TABLE_IND2GP . ' i2gp ON (i2gp.individualid = i.id) 
                        WHERE ' . $sCondition . ' 
                        GROUP BY s.individualid';

            case 'num_ind_with_variant' :
                return 'SELECT COUNT(s.individualid) AS count_dbid 
                        FROM ' . TABLE_VARIANTS . ' AS vog 
                        JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid AND vog.`VariantOnGenome/DBID` = "' . $aParams['dbid'] . '") 
                        JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) 
                        JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) 
                        LEFT JOIN ' . TABLE_IND2GP . ' i2gp ON (i2gp.individualid = i.id) 
                        WHERE ' . $sCondition . ' 
                        GROUP BY s.individualid';

            case 'population_size':
                return 'SELECT COUNT(s.individualid) AS population_size
                        FROM ' . TABLE_SCREENINGS . ' AS s 
                        JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id)
                        GROUP BY s.individualid';


        }
    }
}