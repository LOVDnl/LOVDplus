<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-01-12
 * Modified    : 2018-03-27
 * For LOVD    : 3.0-18
 *
 * Copyright   : 2004-2018 Leiden University Medical Center; http://www.LUMC.nl/
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

class LOVD_ObservationCounts
{
    // Wrap all the logic related to generating observation counts here.
    // It includes:
    // - How the SQL query is constructed to get the data for each cateogory.
    // - Whether observation counts data can be updated depending on status of the screening and role of the user.
    // - Generating and storing the data.
    // - Displaying the data.

    protected $aData = array(); // Store the observation counts data fetched from the database or calculated by buildData.
    protected $aIndividual = array(); // Store details of the individual where this variant is found.
    protected $nVariantID = null; // The ID of the variant we are looking into.
    protected $aColumns = array(); // The list of observation counts columns to be displayed for this instance.

    // Round calculated percentages to what amount of decimals? (0-3)
    protected static $nDefaultShowDecimals = 1;
    // How we want to show that a category does not have sufficient data to generate observation counts.
    protected static $sEmptyDataDisplay = '-';
    // The maximum number of variants where we allow url to be generated to view other variants in the same category.
    protected static $nMaxVariantsToEnableLink = 100;
    // The minimum amount of Individuals in the database before we calculate general observation counts.
    protected static $nDefaultMinimumPopulationSize = 100;





    function __construct ($nVariantID)
    {
        global $_INSTANCE_CONFIG;

        if (!isset($_INSTANCE_CONFIG['observation_counts'])) {
            $_INSTANCE_CONFIG['observation_counts'] = array();
        }

        $this->nVariantID = $nVariantID;
        $this->aIndividual = $this->initIndividualData();
        $this->aData = $this->loadExistingData();
    }





    public function buildData ()
    {
        // Generate observation counts data and store it in the database in json format.
        global $_DB, $_INSTANCE_CONFIG;

        // Check if current analysis status as well as user's permission allow data to be generated.
        if (!$this->canUpdateData()) {
            return array();
        }

        define('LOG_EVENT', 'UpdateObsCounts');

        $aData = array();
        $aData['population_size'] = $_DB->query(
            'SELECT COUNT(DISTINCT individualid) FROM ' . TABLE_SCREENINGS)->fetchColumn();
        foreach ($_INSTANCE_CONFIG['observation_counts'] as $sType => $aTypeSettings) {
            $this->aColumns = $this->validateColumns($sType, $aTypeSettings);

            // Now, generate observation counts data for each type selected in the settings.
            switch ($sType) {
                case 'general':
                    // Generic categories have the requirement that it can only be calculated if
                    // there is a minimum number of individuals (with screenings) in the database.
                    $minPopSize = self::$nDefaultMinimumPopulationSize;
                    if (isset($aTypeSettings['min_population_size'])) {
                        $minPopSize = $aTypeSettings['min_population_size'];
                    }

                    if ($aData['population_size'] < $minPopSize) {
                        $aData['general']['error'] = 'Data cannot be generated because population size is too small.';
                        break;
                    }

                    // Build the observation counts data for each category.
                    $aData['general'] = $this->generateData('general');
                    break;

                case 'genepanel':
                    $aData['genepanel'] = array();
                    foreach ($this->aIndividual['genepanels'] as $nGenePanelID => $sGenePanelName) {
                        // Build the observation counts data for each category.
                        $aData['genepanel'][$nGenePanelID] = $this->generateData('genepanel', $nGenePanelID);
                    }
                    break;
            }
        }


        // Save the built data into the database so that we can reuse next time as well as keeping history.
        $aData['updated'] = time();
        $sObscountJson = json_encode($aData);

        $sSQL = 'UPDATE ' . TABLE_VARIANTS . ' SET obscount_json = ? WHERE id = ?';
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

        if ($aResult['analysis_statusid'] == ANALYSIS_STATUS_IN_PROGRESS) {
            // If the status is in progress, you either need to be owner or up, or there should be no previous observation counts.
            if ($_AUTH['level'] >= LEVEL_OWNER || !$this->loadExistingData()) {
                return true;
            }
        }

        // Every other analysis status (including CLOSED) cannot update observation count data.
        return false;
    }





    protected function generateData ($sType, $nGenePanelID = 0)
    {
        // Given the configuration of a category, construct an array of data for that category.
        // No data is saved into the database here.
        // $aRules : The set of configurations to calculate data for this category
        // $sType : The observation counts type (check available static variables in this class with TYPE_ prefix).

        global $_DB, $_INSTANCE_CONFIG;

        // Previously from validateCategories():
        // These are the categories to choose from. We'll check which ones are needed, and generate the data for these.
        static $aCategories;
        if (!$aCategories) {
            // FIXME: value, condition_args and required are often the same. We
            //  could simplify the code, sacrificing flexibility, by merging those.
            // Syntax:
            //  'category_type' => array(
            //      'category' => array(
            //          'label' => 'The name you wish to see displayed, disabled by default for gene panel type.',
            //          'value' => 'The value visible for this observation count, either a string or an array of $aIndividual keys.',
            //          'condition' => 'The condition to run in the database in the WHERE clause, calculating this observation count.',
            //          'condition_args' => array('$aIndividual keys to be used to replace the question marks in the condition.'),
            //          'required' => array('$aIndividual keys that are required to be filled in.'),
            //          'threshold' => integer (0-100) that highlights the row if the percentage is above this threshold.
            //      ),
            //  ),
            $aCategories = array(
                'genepanel' => array(
                    'all' => array(
                        'label' => 'Gene Panel',
                        'value' => '', // Will be replaced later, depends on the gene panel.
                        'condition' => ''
                    ),
                    'gender' => array(
                        'label' => 'Gender',
                        'value' => array('Individual/Gender'),
                        'condition' => '`Individual/Gender` = ?',
                        'condition_args' => array('Individual/Gender'),
                        'required' => array('Individual/Gender'),
                    ),
                    'ethnic' => array(
                        'label' => 'Ethinicity',
                        'value' => array('Individual/Origin/Ethnic'),
                        'condition' => '`Individual/Origin/Ethnic` = ?',
                        'condition_args' => array('Individual/Origin/Ethnic'),
                        'required' => array('Individual/Origin/Ethnic'),
                    ),
                ),
                'general' => array(
                    'all' => array(
                        'label' => 'All',
                        'value' => '',
                        'condition' => 'i.id IS NOT NULL',
                        'threshold' => 2 // 2%
                    ),
                    'Individual/Gender' => array(
                        'label' => 'Gender',
                        'value' => array('Individual/Gender'),
                        'condition' => 'i.`Individual/Gender` = ?',
                        'condition_args' => array('Individual/Gender'),
                        'required' => array('Individual/Gender'),
                        'threshold' => 2 // 2%
                    ),
                    'Individual/Origin/Ethnic' => array(
                        'label' => 'Ethnicity',
                        'value' => array('Individual/Origin/Ethnic'),
                        'condition' => 'i.`Individual/Origin/Ethnic` = ?',
                        'condition_args' => array('Individual/Origin/Ethnic'),
                        'required' => array('Individual/Origin/Ethnic'),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Sample/Type' => array(
                        'label' => 'Sample Type',
                        'value' => array('Screening/Sample/Type'),
                        'condition' => 's.`Screening/Sample/Type` = ?',
                        'condition_args' => array('Screening/Sample/Type'),
                        'required' => array('Screening/Sample/Type'),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Library_preparation' => array(
                        'label' => 'Capture Method',
                        'value' => array('Screening/Library_preparation'),
                        'condition' => 's.`Screening/Library_preparation` = ?',
                        'condition_args' => array('Screening/Library_preparation'),
                        'required' => array('Screening/Library_preparation'),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Sequencing_software' => array(
                        'label' => 'Sequencing Technology',
                        'value' => array('Screening/Sequencing_software'),
                        'condition' => 's.`Screening/Sequencing_Software` = ?',
                        'condition_args' => array('Screening/Sequencing_software'),
                        'required' => array('Screening/Sequencing_software'),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Analysis_type' => array(
                        'label' => 'Analysis Pipeline',
                        'value' => array('Screening/Analysis_type'),
                        'condition' => 's.`Screening/Analysis_type` = ?',
                        'condition_args' => array('Screening/Analysis_type'),
                        'required' => array('Screening/Analysis_type'),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Library_preparation&Screening/Sequencing_software' => array(
                        'label' => 'Same Capture Method and Sequencing Technology',
                        'value' => array('Screening/Library_preparation', 'Screening/Sequencing_software'),
                        'condition' => 's.`Screening/Library_preparation` = ? AND s.`Screening/Sequencing_Software` = ?',
                        'condition_args' => array('Screening/Library_preparation', 'Screening/Sequencing_software'),
                        'required' => array('Screening/Library_preparation', 'Screening/Sequencing_software'),
                        'threshold' => 2 // 2%
                    ),
                    'Screening/Library_preparation&Screening/Sequencing_software&Screening/Analysis_type' => array(
                        'label' => 'Same Capture Method, Sequencing Technology, and Analysis Pipeline',
                        'value' => array('Screening/Library_preparation', 'Screening/Sequencing_software', 'Screening/Analysis_type'),
                        'condition' => 's.`Screening/Library_preparation` = ? AND s.`Screening/Sequencing_Software` = ? AND s.`Screening/Analysis_type` = ?',
                        'condition_args' => array('Screening/Library_preparation', 'Screening/Sequencing_software', 'Screening/Analysis_type'),
                        'required' => array('Screening/Library_preparation', 'Screening/Sequencing_software', 'Screening/Analysis_type'),
                        'threshold' => 2 // 2%
                    ),
                ),
            );
        }

        // Which categories will we load? An empty array in the instance's configuration means we'll show them all.
        // If we get here, we know the settings have defined this type at least.
        if (empty($_INSTANCE_CONFIG['observation_counts'][$sType]['categories'])) {
            // We have requested this type of observation counts, but we haven't selected which categories to see. Select them all.
            $_INSTANCE_CONFIG['observation_counts'][$sType]['categories'] = array_keys($aCategories[$sType]);
        } elseif (!is_array($_INSTANCE_CONFIG['observation_counts'][$sType]['categories'])) {
            // Otherwise, if the user has filled in a string or so, turn the categories value into an array, so that in_array() won't fail.
            $_INSTANCE_CONFIG['observation_counts'][$sType]['categories'] = array(
                $_INSTANCE_CONFIG['observation_counts'][$sType]['categories'],
            );
        }



        // Now, loop the requested categories and build up the returned array.
        $aData = array();
        foreach ($_INSTANCE_CONFIG['observation_counts'][$sType]['categories'] as $sCategory) {
            if (!isset($aCategories[$sType][$sCategory])) {
                // Unknown category requested.
                continue;
            }

            // To simplify the code...
            $aRules = $aCategories[$sType][$sCategory];

            // Build up this category's array.
            $aCategoryData = array(
                'label' => $aRules['label'],
                'value' => $aRules['value'],
                'total_individuals' => self::$sEmptyDataDisplay,
                'num_affected' => self::$sEmptyDataDisplay,
                'num_not_affected' => self::$sEmptyDataDisplay,
                'num_ind_with_variant' => self::$sEmptyDataDisplay,
                'percentage' => self::$sEmptyDataDisplay,
                'threshold' => self::$sEmptyDataDisplay,
            );

            // If this is the gene panel header, name it after the gene panel.
            if ($sType == 'genepanel' && $sCategory == 'all' && isset($this->aIndividual['genepanels'][$nGenePanelID])) {
                $aCategoryData['value'] = $this->aIndividual['genepanels'][$nGenePanelID];
            }

            // Set the values, in case they are keys of $aIndividual (which is mostly the case).
            if (is_array($aCategoryData['value'])) {
                $sValue = '';
                foreach ($aCategoryData['value'] as $sField) {
                    // If the data is not found in the individual data, it is assumed to be just a textual value.
                    $sValue .= (!$sValue? '' : ', ') . (!isset($this->aIndividual[$sField])? $sField : $this->aIndividual[$sField]);
                }
                $aCategoryData['value'] = $sValue;
            }

            // Some categories can only be run if some prerequisites have been
            //  satisfied; certain columns need to be active and filled in.
            // This is configured in the 'required' field.
            // Only run query if this individual/screening has sufficient data.
            $bComplete = true;
            if (isset($aRules['required'])) {
                foreach ($aRules['required'] as $sField) {
                    if (!isset($this->aIndividual[$sField]) || $this->aIndividual[$sField] === '') {
                        $bComplete = false;

                        // Indicates that this category has insufficient data.
                        $aCategoryData['value'] = self::$sEmptyDataDisplay;
                        break;
                    }
                }
            }

            if ($bComplete) {
                $aSQL = array(); // The arguments for the query.

                // If there is a condition, get the arguments to it, and add to the $aSQL array.
                if (!empty($aRules['condition']) && !empty($aRules['condition_args'])) {
                    foreach ($aRules['condition_args'] as $sField) {
                        // If the data is not found in the individual data, it is assumed to be just a textual value.
                        $aSQL[] = (!isset($this->aIndividual[$sField])? $sField : $this->aIndividual[$sField]);
                    }
                }

                // Gene panel counts always need to restrict the count on the gene panel.
                if ($sType == 'genepanel') {
                    $aRules['condition'] = 'genepanelid = ?' . (!$aRules['condition']? '' : ' AND ') . $aRules['condition'];
                    $aSQL = array_merge(array($nGenePanelID), $aSQL);
                }

                // Total number of individuals with screenings, matching the given conditions.
                $sSQL =  'SELECT COUNT(DISTINCT s.individualid)
                          FROM ' . TABLE_INDIVIDUALS . ' AS i 
                            INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.individualid = i.id)
                            LEFT OUTER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i2gp.individualid = i.id) 
                          WHERE ' . $aRules['condition'];
                $nCount = $_DB->query($sSQL, $aSQL)->fetchColumn();
                $aCategoryData['total_individuals'] = $nCount;

                // Number of individuals with screenings with this variant, matching the given conditions.
                $sSQL = 'SELECT COUNT(s.individualid) AS count_dbid, GROUP_CONCAT(DISTINCT TRIM(LEADING "0" FROM vog.id) SEPARATOR ";") as variant_ids
                         FROM ' . TABLE_VARIANTS . ' AS vog 
                           INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid AND vog.`VariantOnGenome/DBID` = ?) 
                           INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) 
                           INNER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) 
                           LEFT OUTER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i2gp.individualid = i.id) 
                         WHERE ' . $aRules['condition'] . ' 
                         GROUP BY s.individualid';
                $aCategoryData['variant_ids'] = array();
                $aCategoryData['num_ind_with_variant'] = 0;
                $zResult = $_DB->query($sSQL, array_merge(array($this->aIndividual['VariantOnGenome/DBID']), $aSQL));
                while ($aRow = $zResult->fetchAssoc()) {
                    $aCategoryData['num_ind_with_variant']++;
                    $aCategoryData['variant_ids'] = array_merge($aCategoryData['variant_ids'], explode(';', $aRow['variant_ids']));
                }

                if (!empty($aCategoryData['total_individuals'])) {
                    // Calculate percentage and round to 3 decimals. The user can then choose to display it using 0-3 decimals.
                    $aCategoryData['percentage'] = round((float)$aCategoryData['num_ind_with_variant'] / (float)$aCategoryData['total_individuals'] * 100, 3);
                    if (!empty($aRules['threshold'])) {
                        $aCategoryData['threshold'] = ($aCategoryData['percentage'] > $aRules['threshold'] ? '> ' : '<= ') . $aRules['threshold'] . ' %';
                    }
                }

                // These are the columns that don't always need to be calculated if this instance of LOVD does not need it.
                // TOTAL number of affected individuals in this database
                if (!empty($this->aColumns[$sType]['num_affected'])) {
                    $sSQL = 'SELECT COUNT(DISTINCT s.individualid)
                             FROM ' . TABLE_INDIVIDUALS . ' AS i 
                               INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.individualid = i.id AND i.`Individual/Affected` = "Affected")
                               LEFT OUTER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i2gp.individualid = i.id) 
                             WHERE ' . $aRules['condition'];
                    $nCount = $_DB->query($sSQL, $aSQL)->fetchColumn();
                    $aCategoryData['num_affected'] = $nCount;
                }

                // TOTAL number of NOT affected individuals in this database
                if (!empty($this->aColumns[$sType]['num_not_affected'])) {
                    $sSQL = 'SELECT COUNT(DISTINCT s.individualid)
                             FROM ' . TABLE_INDIVIDUALS . ' i 
                               INNER JOIN ' . TABLE_SCREENINGS . ' s ON (s.individualid = i.id AND i.`Individual/Affected` = "Not Affected")
                               LEFT OUTER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (i2gp.individualid = i.id) 
                             WHERE ' . $aRules['condition'];
                    $nCount = $_DB->query($sSQL, $aSQL)->fetchColumn();
                    $aCategoryData['num_not_affected'] = $nCount;
                }
            }

            // We want to ensure every category is still displayed on the table even if it has incomplete data.
            $aData[$sCategory] = $aCategoryData;
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
        $sSQL = 'SELECT i.*, s.*, vog.* 
                 FROM ' . TABLE_VARIANTS . ' AS vog 
                   INNER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid AND vog.id = ?) 
                   INNER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) 
                   INNER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) 
                 GROUP BY i.id';
        $aIndividual = $_DB->query($sSQL, array($this->nVariantID))->fetchAssoc();

        // Easier to just separate the genepanel query, so we can receive it properly.
        $aIndividual['genepanels'] = $_DB->query('SELECT gp.id, gp.name
                                                  FROM ' . TABLE_GENE_PANELS . ' AS gp
                                                    INNER JOIN ' . TABLE_IND2GP . ' AS i2gp ON (gp.id = i2gp.genepanelid)
                                                  WHERE i2gp.individualid = ? AND (gp.type IS NULL OR gp.type != "blacklist")',
            array($aIndividual['individualid']))->fetchAllCombine();

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
        $bIndAffectedColActive = $_DB->query(
            'SELECT COUNT(*) 
             FROM ' . TABLE_ACTIVE_COLS . '
             WHERE colid = "Individual/Affected"')->fetchColumn();

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





    public function display ()
    {
        // Returns a string of html to display observation counts data.
        global $_AUTH, $_INSTANCE_CONFIG;

        $sMetadata = '';
        $aData = $this->aData;
        $aSettings = $_INSTANCE_CONFIG['observation_counts'];
        $bHasPermissionToViewVariants = ($_AUTH['level'] >= LEVEL_ANALYZER);
        $generateDataLink = ' <SPAN id="obscount-refresh"> | <A href="#" onClick="lovd_generate_obscount(\'' . $this->nVariantID . '\');return false;">Refresh Data</A></SPAN>';
        if (!$this->canUpdateData()) {
            $generateDataLink = '<TR><TD>Current analysis status or your user permission does not allow Observation Counts data to be updated.</TD></TR>';
        }
        $sDataTables = '';

        if(empty($aData) && !lovd_isAuthorized('variant', $this->nVariantID)) {
            $sMetadata = '<TR><TD>You do not have permission to generate Observation Counts for this variant.</TD></TR>';
        } elseif (empty($aData)) {
            $sMetadata = '<TR><TD>There is no existing Observation Counts data <SPAN id="obscount-refresh"> | <A href="#" onClick="lovd_generate_obscount(\'' . $this->nVariantID . '\');return false;">Generate Data</A></SPAN></TD></TR>';
        } else {
            // If there is data, loop through categories and display results.
            foreach (array_keys($_INSTANCE_CONFIG['observation_counts']) as $sType) {
                // Prepare settings.
                $nDecimals = self::$nDefaultShowDecimals;
                if (isset($_INSTANCE_CONFIG['observation_counts'][$sType]['show_decimals'])) {
                    $nDecimals = $_INSTANCE_CONFIG['observation_counts'][$sType]['show_decimals'];
                }

                // Column definitions.
                $sColumns = '';
                foreach ($aSettings[$sType]['columns'] as $sLabel) {
                    $sColumns .= '<TH>' . $sLabel . '</TH>';
                }

                switch ($sType) {
                    // Type-specific formatting.
                    case 'genepanel':
                        $sCategories = '';
                        if (!empty($aData['genepanel']['error'])) {
                            $sColspan = ' colspan="' . count($aSettings['genepanel']['columns']) . '"';
                            $sCategories .= '<TR><TD' . $sColspan . '>' . $aData['genepanel']['error'] . '</TD></TR>';
                        } else {
                            foreach ($aData['genepanel'] as $sGpId => $aGpData) {
                                foreach ($aGpData as $sCategory => $aCategoryData) {
                                    $sCategories .= '<TR>';
                                    foreach ($aSettings['genepanel']['columns'] as $sKey => $sLabel) {
                                        $sFormattedValue = $aCategoryData[$sKey];

                                        if ($sKey == 'percentage') {
                                            // Format the percentage, as per requested.
                                            $sFormattedValue = number_format($sFormattedValue, $nDecimals);
                                            if ($bHasPermissionToViewVariants) {
                                                if ($aCategoryData[$sKey] > 0 && $aCategoryData[$sKey] <= self::$nMaxVariantsToEnableLink) {
                                                    // If the total number of variants is not too big for us to generate an url.
                                                    $sFormattedValue = '<A href="/variants/DBID/' . $this->getVogDBID() . '?search_variantid=' . implode('|', $aCategoryData['variant_ids']) . '" target="_blank">' . $sFormattedValue . '</A>';
                                                }
                                            }
                                        }

                                        $sCategories .= ($sCategory == 'all'? '<TH>' : '<TD>') .
                                            $sFormattedValue .
                                            ($sCategory == 'all'? '</TH>' : '</TD>');
                                    }
                                    $sCategories .= '</TR>';
                                }
                            }
                        }
                        break;

                    case 'general':
                        $sCategories = '';
                        if (!empty($aData['general']['error'])) {
                            $sColspan = ' colspan="' . count($aSettings['general']['columns']) . '"';
                            $sCategories .= '<TR><TD' . $sColspan . '>' . $aData['general']['error'] . '</TD></TR>';
                        } else {
                            foreach ($aData['general'] as $sCategory => $aCategoryData) {
                                // If threshold data has the greater than sign, mark the row.
                                $sClass = '';
                                if (strpos($aData['general'][$sCategory]['threshold'], '>') !== false) {
                                    $sClass = ' class="above-threshold"';
                                }

                                $sCategories .= '<TR' . $sClass . '>';
                                foreach ($aSettings['general']['columns'] as $sKey => $sLabel) {
                                    if ($sKey == 'percentage') {
                                        // Format the percentage, as per requested.
                                        $sFormattedValue = number_format($aCategoryData[$sKey], $nDecimals);
                                    } else {
                                        $sFormattedValue = $aCategoryData[$sKey];
                                    }
                                    $sCategories .= '<TD>' . $sFormattedValue . '</TD>';
                                }
                                $sCategories .= '</TR>';
                            }
                        }
                        break;
                }

                $sTable = '';
                if (!empty($aData[$sType])) {
                    $sTable = '
            <TABLE id="obscount-table-' . $sType . '" width="600" class="data">
              <TR id="obscount-header-' . $sType . '"></TR>
              <TBODY id="obscount-data-' . $sType . '">
                <TR>' . $sColumns . '</TR>' .
                        $sCategories . '
              </TBODY>
            </TABLE>';
                }

                $sDataTables .= $sTable;
            }

            $sMetadata .= '
              <TR id="obscount-info">
                <TH>Data updated ' . date('d M Y h:ia', $aData['updated']) . ' | Population size was: ' . $aData['population_size'] . $generateDataLink . '</TH>
              </TR>';
        }

        // HTML to be displayed.
        $sTable = '
            <TABLE width="600" class="data">
              <TR><TH style="font-size : 13px;">Observation Counts</TH></TR>' .
              $sMetadata . '
              <TR id="obscount-feedback" style="display: none;">
                <TH>Loading data...</TH>
              </TR>
            </TABLE>' . $sDataTables;

        return $sTable;
    }
}
