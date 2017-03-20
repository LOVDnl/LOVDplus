<?php

/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-01-12
 * Modified    : 2017-03-17
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
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

if (!defined('ROOT_PATH')) {
    exit;
}

error_reporting(~E_NOTICE);
class LOVD_ObservationCounts
{
    // Wrap all the logic related to generating observation counts here.
    // It includes:
    // - How the SQL query is constructed to get the data for each cateogory.
    // - Whether observation counts data can be updated depending on status of the screening and role of the user.

    protected $aData = array(); // Store the observation counts data fetched from the database or calculated by buildData.
    protected $aIndividual = array(); // Store details of the individual where this variant is found.
    protected $nVariantID = null; // The Id of the variant we are looking into.

    // The configurations of observation counts categories for this instance.
    protected $aCategories = array(
        'genepanel' => array(),
        'general' => array(),
    );
    protected $aColumns = array(); // The list of observation counts columns to be displayed for this instance.

    // We currently divide the Observation Counts data calculations into these types.
    // They are essentially different because they have different criteria of
    // how the data should be calculated
    // Q: Why used and named like a constant, but not defined like one? (usage also not everywhere)
    // A: Just use a variable.
    public static $EMPTY_DATA_DISPLAY = '-'; // How we want to show that a category does not have sufficient data to generate observation counts.
    // Q: Why this separately from the rest of the config?
    // A: Just pull it into the other defaults.
    protected static $DEFAULT_MIN_POP_SIZE = 100;

    function __construct ($nVariantID)
    {
        $this->nVariantID = $nVariantID;
        $this->aIndividual = $this->initIndividualData();
        $this->aData = $this->loadExistingData();
    }





    public function buildData ($aSettings = array())
    {
        // Generate observation counts data and store it in the database in json format.
        //
        // $aSettings is expected in the following format
        // array(
        //    TYPE_ABC => array(
        //      'columns' => array(
        //          'col_key_1' => 'Column Label 1',
        //          'col_key_2' => 'Column Label 2'
        //       ),
        //      'categories' => array(
        //          'category_key_1',
        //          'category_key_2'
        //      )
        //    ),
        //
        //    TYPE_XYZ => array(...)
        // );
        //
        // Example:
        // array(
        // Q: Let's choose one spot in where to define these defaults?
        // A: Just set them in the constructor.
        //    // If we want to display gene panel observation counts using default config,
        //    // then simply add 'genepanel' => array()
        //
        //    'genepanel' => array(
        //
        //        // If columns is empty, use default columns list
        //        'columns' => array(
        //            'value' => 'Gene Panel',
        //            'total_individuals' => 'Total # Individuals',
        //            'num_affected' => '# of Affected Individuals',
        //            'num_not_affected' => '# of Unaffected Individuals',
        //            'percentage' => 'Percentage (%)'
        //        ),
        //
        //        // if categories is empty, use default categories list
        //        'categories' => array()
        //     ),
        //
        //    // If we want to display general categories observation counts using default config,
        //    // then simply add 'general' => array()
        //
        //    'general' => array(
        //
        //        // if columns is empty, use default columns list
        //        'columns' => array(
        //            'label' => 'Category',
        //            'value' => 'Value',
        //            'threshold' => 'Percentage'
        //        ),
        //
        //        // if categories is empty, use default categories list
        //        'categories' => array(),
        //        'min_population_size' => 100
        //     )
        // );
        global $_DB;

        // Check if current analysis status as well as user's permission allow data to be generated.
        if (!$this->canUpdateData()) {
            return array();
        }

        define('LOG_EVENT', 'UpdateObsCounts');

        $aData = array();
        $aData['population_size'] = $_DB->query(
            'SELECT COUNT(DISTINCT individualid) FROM ' . TABLE_SCREENINGS)->fetchColumn();
        foreach ($aSettings as $sType => $aTypeSettings) {
            // Initialize and validate if the categories selected by this instance is valid.
            $this->aCategories[$sType] = $this->validateCategories($sType, $aTypeSettings);
            $this->aColumns = $this->validateColumns($sType, $aTypeSettings);

            // Now, generate observation counts data for each type selected in the settings.
            switch ($sType) {
                case 'general':
                    // Generic categories have the requirement that it can only be calculated if
                    // there is a minimum number of individuals (with screenings) in the database.
                    $minPopSize = static::$DEFAULT_MIN_POP_SIZE;
                    if (isset($aSettings['general']['min_population_size'])) {
                        $minPopSize = $aSettings['general']['min_population_size'];
                    }

                    if ($aData['population_size'] < $minPopSize) {
                        $aData['general']['error'] = 'Data cannot be generated because population size is too small.';
                        break;
                    }

                    $aData['general'] = array();
                    foreach ($this->aCategories[$sType] as $sCategory => $aRules) {
                        // Build the observation counts data for each category.
                        $aData['general'][$sCategory] = $this->generateData($aRules, 'general');
                    }
                    break;

                case 'genepanel':
                    $aData['genepanel'] = array();
                    foreach ($this->aCategories[$sType] as $sGenepanelId => $aGenepanelRules) {
                        foreach ($aGenepanelRules as $sCategory => $aRules) {
                            // Build the observation counts data for each category.
                            $aData['genepanel'][$sGenepanelId][$sCategory] = $this->generateData($aRules, 'genepanel');
                        }
                    }
                    break;
            }
        }


        // Save the built data into the database so that we can reuse next time as well as keeping history.
        $aData['updated'] = time();
        $sObscountJson = json_encode($aData);

        $sSQL = "UPDATE " . TABLE_VARIANTS . " SET obscount_json = ? WHERE id = ?";
        $_DB->query($sSQL, array($sObscountJson, $this->nVariantID));

        lovd_writeLog('Event', LOG_EVENT, 'Created Observation Counts for variant #' . $this->nVariantID . '. JSON DATA: ' . $sObscountJson);

        // Now that we have newly generated observation counts data, we want to make sure all the class variables has the most updated values.
        $this->aData = $aData;

        return $this->aData;
    }






    public function canUpdateData ()
    {
        // Check if this user can update/generate new observation counts data depending on:
        // - Their roles.
        // - The current status of the analysis.
        global $_DB, $_AUTH;

        $sSQL = 'SELECT s.analysis_statusid
                 FROM ' . TABLE_SCREENINGS . ' AS s
                 INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v 
                 ON (s.id = s2v.screeningid AND s2v.variantid = ?)';
        $aResult = $_DB->query($sSQL, array($this->nVariantID))->fetchAssoc();

        // Q: This means anyone can update as long as the screening is open. Is that intentional? There's a read-only user.
        // A: Make it require at least LEVEL_ANALYZER. ANALYZER can also load data if THERE IS NO DATA YET, for status IN PROGRESS. Currently done in code below.
        if ($aResult['analysis_statusid'] == ANALYSIS_STATUS_READY) {
            return true;
        }

        // Q: This is different than the "normal" authorization used in LOVD+. Maybe standardize that?
        // A: STANDARDIZE THIS, BUT don't allow ADMIN for STATUS WAIT CONFIRMATION.
        if ($aResult['analysis_statusid'] == ANALYSIS_STATUS_IN_PROGRESS) {
            // If the status is in progress, you either need to be owner or up, or there should be no previous observation counts.
            if ($_AUTH['level'] >= LEVEL_OWNER || !$this->loadExistingData()) {
                return true;
            }
        }

        // Every other analysis status (including CLOSED) cannot update observation count data.
        return false;
    }





    protected function generateData ($aRules, $sType)
    {
        // Given the configuration of a category, construct an array of data for that category.
        // No data is saved into the database here.
        // $aRules : The set of configurations to calculate data for this category
        // $sType : The observation counts type (check available static variables in this class with TYPE_ prefix).

        global $_DB;

        $aData = array();
        $aData['label'] = $aRules['label'];
        $aData['value'] = $aRules['value'];

        $aData['total_individuals'] = static::$EMPTY_DATA_DISPLAY;
        $aData['num_affected'] = static::$EMPTY_DATA_DISPLAY;
        $aData['num_not_affected'] = static::$EMPTY_DATA_DISPLAY;
        $aData['num_ind_with_variant'] = static::$EMPTY_DATA_DISPLAY;
        $aData['percentage'] = static::$EMPTY_DATA_DISPLAY;
        $aData['threshold'] = static::$EMPTY_DATA_DISPLAY;

        // Q: This function needs some more comments.
        // Only run query if this individual/screening has sufficient data.
        if (empty($aRules['incomplete'])) {
            // Total number of individuals with screenings, matching the given conditions.
            $sSQL =  'SELECT COUNT(DISTINCT s.individualid)
                      FROM ' . TABLE_INDIVIDUALS . ' AS i 
                        INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.individualid = i.id)
                        LEFT OUTER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i2gp.individualid = i.id) 
                      WHERE ' . $aRules['condition'];
            $nCount = $_DB->query($sSQL, array())->fetchColumn();
            $aData['total_individuals'] = $nCount;

            // Number of individuals with screenings with this variant, matching the given conditions.
            $sSQL = 'SELECT COUNT(s.individualid) AS count_dbid, GROUP_CONCAT(DISTINCT TRIM(LEADING "0" FROM vog.id) SEPARATOR ";") as variant_ids
                     FROM ' . TABLE_VARIANTS . ' AS vog 
                       INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid AND vog.`VariantOnGenome/DBID` = ?) 
                       INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) 
                       INNER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) 
                       LEFT OUTER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i2gp.individualid = i.id) 
                     WHERE ' . $aRules['condition'] . ' 
                     GROUP BY s.individualid';
            $aData['variant_ids'] = array();
            $aData['num_ind_with_variant'] = 0;
            $zResult = $_DB->query($sSQL, array($this->aIndividual['VariantOnGenome/DBID']));
            while ($aRow = $zResult->fetchAssoc()) {
                $aData['num_ind_with_variant']++;
                $aData['variant_ids'] = array_merge($aData['variant_ids'], explode(';', $aRow['variant_ids']));
            }

            if (!empty($aData['total_individuals'])) {
                $aData['percentage'] = round((float)$aData['num_ind_with_variant'] / (float)$aData['total_individuals'] * 100, 0);
                if (!empty($aRules['threshold'])) {
                    $aData['threshold'] = ($aData['percentage'] > $aRules['threshold'] ? '> ' : '<= ') . $aRules['threshold'] . ' %';
                }
            }

            // These are the columns that don't always need to be calculated if this instance of LOVD does not need it.
            // TOTAL number of affected individuals in this database
            if (!empty($this->aColumns[$sType]['num_affected'])) {
                $sSQL = 'SELECT COUNT(DISTINCT s.individualid)
                         FROM ' . TABLE_INDIVIDUALS . ' AS i 
                           INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.individualid = i.id AND i.`Individual/Affected` = "Affected")
                           LEFT OUTER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i2gp.individualid = i.id) 
                         WHERE ' . $aRules['condition'] . ' 
                         GROUP BY s.individualid';
                $nCount = $_DB->query($sSQL, array())->fetchColumn();
                $aData['num_affected'] = $nCount;
            }

            // TOTAL number of NOT affected individuals in this database
            if (!empty($this->aColumns[$sType]['num_not_affected'])) {
                $sSQL = 'SELECT COUNT(DISTINCT s.individualid)
                         FROM ' . TABLE_INDIVIDUALS . ' i 
                           INNER JOIN ' . TABLE_SCREENINGS . ' s ON (s.individualid = i.id AND i.`Individual/Affected` = "Not Affected")
                           LEFT OUTER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i2gp.individualid = i.id) 
                         WHERE ' . $aRules['condition'];
                $nCount = $_DB->query($sSQL, array())->fetchColumn();
                $aData['num_not_affected'] = $nCount;
            }
        }

        return $aData;
    }





    public function getData ()
    {
        return $this->aData;
    }





    public function getDataPopulationSize ()
    {
        // Retrieve the total number of individuals at the time the loaded data was generated.

        if (isset($this->aData['population_size'])) {
            return $this->aData['population_size'];
        }

        return null;
    }






    public function getTimeGenerated ()
    {
        return $this->aData['updated'];
    }





    public function getVogDBID ()
    {
        // Returns the DBID of the variant we are generating the observation counts for.

        return (empty($this->aIndividual['VariantOnGenome/DBID'])? '' : $this->aIndividual['VariantOnGenome/DBID']);
    }






    protected function initIndividualData ()
    {
        // Retrieve information about this individual who has this variant ID $this->nVariantID.
        global $_DB;

        // Query data related to this individual.
        // Q: How to handle an individual with multiple screenings?
        $sSQL = 'SELECT i.*, s.*, vog.*, 
                   GROUP_CONCAT(DISTINCT i2gp.genepanelid ORDER BY i2gp.genepanelid SEPARATOR ",") AS genepanel_ids, 
                   GROUP_CONCAT(DISTINCT gp.name ORDER BY i2gp.genepanelid SEPARATOR ",") AS genepanel_names 
                 FROM ' . TABLE_VARIANTS . ' AS vog 
                   INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid AND vog.id = ?) 
                   INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) 
                   INNER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) 
                   LEFT OUTER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i.id = i2gp.individualid) 
                   LEFT OUTER JOIN ' . TABLE_GENE_PANELS . ' AS gp ON (i2gp.genepanelid = gp.id)
                 WHERE gp.type IS NULL OR gp.type != "blacklist"
                 GROUP BY i.id';
        $aIndividual = $_DB->query($sSQL, array($this->nVariantID))->fetchAssoc();

        return $aIndividual;
    }





    protected function loadExistingData ()
    {
        // Retrieve data that was previously stored in the database as json.
        global $_DB;

        $sSQL = 'SELECT obscount_json FROM ' . TABLE_VARIANTS . ' WHERE id = ?';
        $zResult = $_DB->query($sSQL, array($this->nVariantID))->fetchAssoc();

        return json_decode($zResult['obscount_json'], true);
    }





    protected function validateCategories ($sType, $aSettings)
    {
        // Check if the categories specified in $aSettings is a valid category.
        // We then load the configuration for all the valid categories into $aCategories.
        // $sType : The observation counts type (check available static variables in this class with TYPE_ prefix).
        // $aSettings: subarray passed to buildData
        // array(
        //  'categories' => array(
        //      'category_key_1',
        //      'category_key_2'
        //  ),
        //  'columns' => array(
        //      'col_key_1' => 'Column Label 1',
        //      'col_key_2' => 'Column Label 2'
        //  )
        // )

        // Data structure can be different for different types.
        // We will build them separately.
        $aCategories = array();
        switch ($sType) {
            case 'general':
                // Build available configuration options
                // Q: This is very unsafe. We should not feel that we can trust the data that's passed directly into the SQL. This needs to be implemented differently.
                // A: Restructure this function. Anyway needs to be done because it's generating lots of notices if you don't have one of these functions, making the whole feature unavailable for everyone else.
                // Q: The values here need comments to explain what they mean and what they are for.
                // Q: If I understand correctly, "incomplete" needs to be false to get this stuff to run? Perhaps rename to "active" or so?
                // A: Change it if I want to.
                // Q: Should this array, which is basically the default settings, be set in __construct()?
                // A: Change it, if I want to. Make sure the way it's set, doesn't generate these notices anymore.
                $aConfig = array(
                    'all' => array(
                        'label' => 'All',
                        'value' => '',
                        'condition' => 'i.id IS NOT NULL',
                        'threshold' => 2 // 2%
                    ),
                    'Individual/Gender' => array(
                        'label' => 'Gender',
                        'value' => $this->aIndividual['Individual/Gender'],
                        'condition' => 'i.`Individual/Gender` = "' . $this->aIndividual['Individual/Gender'] . '"',
                        'incomplete' => ($this->aIndividual['Individual/Gender'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Individual/Origin/Ethnic' => array(
                        'label' => 'Ethnicity',
                        'value' => $this->aIndividual['Individual/Origin/Ethnic'],
                        'condition' => 'i.`Individual/Origin/Ethnic` = "' . $this->aIndividual['Individual/Origin/Ethnic'] . '"',
                        'incomplete' => ($this->aIndividual['Individual/Origin/Ethnic'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Sample/Type' => array(
                        'label' => 'Sample Type',
                        'value' => $this->aIndividual['Screening/Sample/Type'],
                        'condition' => 's.`Screening/Sample/Type` = "' . $this->aIndividual['Screening/Sample/Type'] . '"',
                        'incomplete' => ($this->aIndividual['Screening/Sample/Type'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Library_preparation' => array(
                        'label' => 'Capture Method',
                        'value' => $this->aIndividual['Screening/Library_preparation'],
                        'condition' => 's.`Screening/Library_preparation` = "' . $this->aIndividual['Screening/Library_preparation'] . '"',
                        'incomplete' => ($this->aIndividual['Screening/Library_preparation'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Sequencing_software' => array(
                        'label' => 'Sequencing Technology',
                        'value' => $this->aIndividual['Screening/Sequencing_software'],
                        'condition' => 's.`Screening/Sequencing_Software` = "' . $this->aIndividual['Screening/Sequencing_software'] . '"',
                        'incomplete' => ($this->aIndividual['Screening/Sequencing_software'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Analysis_type' => array(
                        'label' => 'Analysis Pipeline',
                        'value' => $this->aIndividual['Screening/Analysis_type'],
                        'condition' => 's.`Screening/Analysis_type` = "' . $this->aIndividual['Screening/Analysis_type'] . '"',
                        'incomplete' => ($this->aIndividual['Screening/Analysis_type'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Library_preparation&Screening/Sequencing_software' => array(
                        'label' => 'Same Capture Method and Sequencing Technology',
                        'value' => $this->aIndividual['Screening/Library_preparation'] . ', ' . $this->aIndividual['Screening/Sequencing_software'],
                        'condition' => 's.`Screening/Library_preparation` = "' . $this->aIndividual['Screening/Library_preparation'] . '"'
                            . ' AND '
                            . 's.`Screening/Sequencing_Software` = "' . $this->aIndividual['Screening/Sequencing_software'] . '"',
                        'incomplete' => ($this->aIndividual['Screening/Library_preparation'] === ''
                                        || $this->aIndividual['Screening/Sequencing_software'] === ''? true : false),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Library_preparation&Screening/Sequencing_software&Screening/Analysis_type' => array(
                        'label' => 'Same Capture Method, Sequencing Technology, and Analysis Pipeline',
                        'value' => $this->aIndividual['Screening/Library_preparation'] . ', ' . $this->aIndividual['Screening/Sequencing_software'] . ', ' . $this->aIndividual['Screening/Analysis_type'],
                        'condition' => 's.`Screening/Library_preparation` = "' . $this->aIndividual['Screening/Library_preparation'] . '"'
                            . ' AND '
                            . 's.`Screening/Sequencing_Software` = "' . $this->aIndividual['Screening/Sequencing_software'] . '"'
                            . ' AND '
                            . 's.`Screening/Analysis_type` = "' . $this->aIndividual['Screening/Analysis_type'] . '"',
                        'incomplete' => ($this->aIndividual['Screening/Library_preparation'] === ''
                                        || $this->aIndividual['Screening/Sequencing_software'] === ''
                                        || $this->aIndividual['Screening/Analysis_type'] === ''? true : false),
                        'threshold' => 2 // 2%
                    )
                );

                // Now build categories for this instance of LOVD based on what is specified on the settings array.
                if (empty($aSettings) || empty($aSettings['categories'])) {
                    // If categories is not specified in the settings for this type, then use ALL available categories.
                    $aCategories = $aConfig;
                } else {
                    // Otherwise, only select the category specified in the instance settings.
                    foreach ($aSettings['categories'] as $sCategory) {
                        if (isset($aConfig[$sCategory])) {
                            $aCategories[$sCategory] = $aConfig[$sCategory];
                        }
                    }
                }

                break;

            case 'genepanel':
                // Build existing configuration options.
                $aGenepanelIds = array();
                $aGenepanelNames = array();
                if (!empty($this->aIndividual['genepanel_ids']) && !empty($this->aIndividual['genepanel_names'])) {
                    $aGenepanelIds = explode(',', $this->aIndividual['genepanel_ids']);
                    $aGenepanelNames = explode(',', $this->aIndividual['genepanel_names']);
                }

                $aConfig = array();
                foreach ($aGenepanelIds as $nIndex => $sGenepanelId) {
                    $aConfig[$sGenepanelId] = array(
                        'all' => array(
                            'label' => 'Gene Panel',
                            'value' => $aGenepanelNames[$nIndex],
                            'condition' => 'genepanelid = "' . $sGenepanelId . '"'
                        ),
                        'gender' => array(
                            'label' => 'Gender',
                            'value' => $this->aIndividual['Individual/Gender'],
                            'condition' => 'genepanelid = "' . $sGenepanelId . '"'
                                . ' AND '
                                . '`Individual/Gender` = "' . $this->aIndividual['Individual/Gender'] . '"',
                            'incomplete' => ($this->aIndividual['Individual/Gender'] === ''? true: false)
                        ),
                        'ethnic' => array(
                            'label' => 'Ethinicity',
                            'value' =>$this->aIndividual['Individual/Origin/Ethnic'],
                            'condition' => 'genepanelid = "' . $sGenepanelId . '"'
                                . ' AND '
                                . '`Individual/Origin/Ethnic` = "' . $this->aIndividual['Individual/Origin/Ethnic'] . '"',
                            'incomplete' => ($this->aIndividual['Individual/Origin/Ethnic'] === ''? true: false)
                        ),
                    );
                }

                // Now build columns for this instance of LOVD based on what is specified on the settings array.
                if (empty($aSettings) || empty($aSettings['categories'])) {
                    // If categories is not specified in the settings for this type, then use ALL available categories.
                    $aCategories = $aConfig;
                } else {
                    // Otherwise, only select the categories specified in the instance settings.
                    foreach ($aSettings['categories'] as $sCategory) {
                        foreach ($aGenepanelIds as $nIndex => $sGenepanelId) {
                            if (isset($aConfig[$sGenepanelId][$sCategory])) {
                                $aCategories[$sGenepanelId][$sCategory] = $aConfig[$sGenepanelId][$sCategory];
                            }
                        }
                    }
                }

                break;
        }
        return $aCategories;
    }





    protected function validateColumns ($sType, $aSettings = array())
    {
        // Validate if the columns in $aSettings are valid.
        // We then populate the valid columns into $this->aColumns.
        // $sType : The observation counts type (check available static variables in this class with TYPE_ prefix).
        // $aSettings: subarray passed to buildData
        // array(
        //  'categories' => array(
        //      'category_key_1',
        //      'category_key_2'
        //  ),
        //  'columns' => array(
        //      'col_key_1' => 'Column Label 1',
        //      'col_key_2' => 'Column Label 2'
        //  )
        // )

        global $_DB;

        // Q: The values here need comments to explain what they mean and what they are for.
        // Q: Should this array, which is basically the default settings, be set in __construct()?
        // A: Change it if I want to.
        $aAvailableColumns = array(
            'genepanel' => array(
                'label' => 'Category',
                'value' => 'Gene Panel',
                'total_individuals' => 'Total # Individuals',
                'num_affected' => '# of Affected Individuals',
                'num_not_affected' => '# of Unaffected Individuals',
                'num_ind_with_variant' => '# of Unaffected Individuals',
                'percentage' => 'Percentage (%)'
            ),
            'general' => array(
                'label' => 'Category',
                'value' => 'Value',
                'percentage' => 'Percentage',
                'threshold' => 'Percentage'
            )
        );

        // Some columns require custom columns to be active.
        $sSQL = 'SELECT colid FROM ' . TABLE_ACTIVE_COLS . ' WHERE colid = "Individual/Affected"';
        $zResult = $_DB->query($sSQL)->fetchAssoc();
        $bIndAffectedColActive = ($zResult && $zResult['colid']? true: false);

        if (!$bIndAffectedColActive) {
            unset($aAvailableColumns['genepanel']['num_affected']);
            unset($aAvailableColumns['genepanel']['num_not_affected']);
        }

        // Now build the list of valid columns for this LOVD instance.
        $this->aColumns[$sType] = array();
        if (empty($aSettings) || empty($aSettings['columns'])) {
            // If columns is not specified in the settings for this type, then use ALL available columns.
            $this->aColumns[$sType] = $aAvailableColumns[$sType];
        } else {
            // Otherwise, only select the columns specified in the settings of this LOVD instance.
            foreach ($aSettings['columns'] as $sColumn => $sLabel) {
                if (isset($aAvailableColumns[$sType][$sColumn])) {
                    $this->aColumns[$sType][$sColumn] = $sColumn;
                }
            }
        }

        return $this->aColumns;
    }
}