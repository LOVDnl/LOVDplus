<?php
define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__) . '/../'));
define('FORMAT_ALLOW_TEXTPLAIN', true);
$_GET['format'] = 'text/plain';
// To prevent notices when running inc-init.php.
$_SERVER = array_merge($_SERVER, array(
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => 'scripts/migrate_gp_filters_config.php',
    'QUERY_STRING' => '',
    'REQUEST_METHOD' => 'GET',
));
// Try and improve HTTP_HOST, since settings may depend on it.
$aPath = explode('/', trim(dirname($_SERVER['SCRIPT_NAME']), '/'));
foreach ($aPath as $sDirName) {
    // Stupid but effective check.
    if (preg_match('/^((([0-9a-z][-0-9a-z]*[0-9a-z]|[0-9a-z])\.)+[a-z]{2,6})$/', $sDirName)) {
        // Valid host name.
        $_SERVER['HTTP_HOST'] = $sDirName;
        break;
    }
}
require ROOT_PATH . 'inc-init.php';
////////////////////////////////
///// Script starts here //////
if ($argc < 2) {
    print "Please provide a valid time zone. For example: 'Australia/Melbourne'\n";
    exit;
} else {
    $sTimezone = $argv[1];
    if (in_array($sTimezone, timezone_identifiers_list())) {
        date_default_timezone_set($sTimezone);
    } else {
        print "Please provide a valid time zone. For example: 'Australia/Melbourne'\n";
        exit;
    }
}

// We need to initialise an array of genes for each gene panel.
// We create two arrays here
// 1. A simple array of the current list of genes of each gene panel in case we cannot get its revision history
// 2. A two dimensional array keyed by gene panel ID, then keyed by the timestamp of the created date of that version of gene panel

print "Building gene panel data\n";
// 1. Get an array of genes keyed by gene panel ID

// We don't want to use group_concat here, because group_concat has max limit.
// Using group_concat may cause us losing some genes.
$sSQL = 'SELECT genepanelid, geneid FROM ' . TABLE_GP2GENE;
$zResultsGenes = $_DB->query($sSQL)->fetchAllAssoc();
$aGenePanels = array();
foreach ($zResultsGenes as $aRow) {
    if (!isset($aGenePanels[$aRow['genepanelid']])) {
        $aGenePanels[$aRow['genepanelid']] = array();
    }

    $aGenePanels[$aRow['genepanelid']][] = $aRow['geneid'];
}

// 2. Get an array of genes keyed by gene panel ID and unix timestamp
// Look into gene panel revisions
//
// $aGenePanelsRev = array(
//    '00001' => array(
//        '123456789' => array(
//            'BRCA1',
//            'BRCA2'
//        ),
//        '123456790' => array(
//            'BRCA1',
//            'BRCA2',
//            'PCSK5'
//        )
//    )
// );
$sSQL = 'SELECT genepanelid, geneid, valid_from, valid_to, deleted 
         FROM ' . TABLE_GP2GENE_REV . '
         ORDER BY genepanelid, valid_from ASC';
$zResultsGenesRev = $_DB->query($sSQL)->fetchAllAssoc();
$aGenePanelsRev = array();
$aDeleted = array();

// 2.a Initialise a two dimensional array keyed with timestamps of created time of each version of the gene panel
// At this step, newly added genes are also added to the sub-arrays
foreach ($zResultsGenesRev as $aRow) {
    $nGpID = $aRow['genepanelid'];
    if (!isset($aGenePanelsRev[$nGpID])) {
        $aGenePanelsRev[$nGpID] = array();
        $aDeleted[$nGpID] = array();
    }

    $nTimeFrom = strtotime($aRow['valid_from']);
    $nTimeTo = strtotime($aRow['valid_to']);

    // Initialise an array to store the genes of this version of the gene panel
    // created at time $nTimeFrom
    if (!isset($aGenePanelsRev[$nGpID][$nTimeFrom])) {
        $aGenePanelsRev[$nGpID][$nTimeFrom] = array();
    }
    $aGenePanelsRev[$nGpID][$nTimeFrom][] = $aRow['geneid'];

    // If this gene will be deleted in future, store it in a separate array keyed by its deleted time
    if ($aRow['deleted']) {
        if (!isset($aDeleted[$nTimeTo])) {
            $aDeleted[$nGpID][$nTimeTo] = array();
        }
        $aDeleted[$nGpID][$nTimeTo][] = $aRow['geneid'];

        // If a gene will be deleted, that means there is a new version of this gene panel created at this timestamp
        // Now just create a placeholder here, we will fill in the values outside this loop
        if (!isset($aGenePanelsRev[$nGpID][$nTimeTo])) {
            $aGenePanelsRev[$nGpID][$nTimeTo] = array();
        }
    }
}

// 2.b Fill in the empty sub array we initialise above
// A new version of a gene panel consists of:
// Genes from previous version - genes deleted in this version + genes added in this version
foreach ($aGenePanelsRev as $sGpID => &$aGenesByTime) {
    // Important to sort by timestamp before we run the second loop
    // so that we get the correct list genes from immediate older version of this gene panel
    ksort($aGenesByTime); // sort by timestamp
    ksort($aDeleted[$sGpID]); // sort by valid_to time or deleted time

    $aPrevGenes = array();
    foreach ($aGenesByTime as $nTime => $aAddedGenes) {
        // First version of this gene panel.
        if (empty($aPrevGenes)) {
            $aPrevGenes = $aAddedGenes;
            continue;
        }

        // If we reach this line of code, that means we are in later versions of this gene panel
        // We need to copy genes from prev iteration (prev version of this gene panel) and remove the deleted genes
        // then, added some genes added at this version
        // $aNewVersion = $aPrevGenes - $aDeleted[$sGpID][$nTime] + $aGenesByTime[$nTime]
        if (!empty($aPrevGenes)) {
            $aNewVersion = $aPrevGenes;

            // If some genes have been deleted in this version.
            if (!empty($aDeleted[$sGpID][$nTime])) {
                $aNewVersion = array_diff($aNewVersion, $aDeleted[$sGpID][$nTime]);
            }

            // If some genes have been added in this version.
            if (!empty($aAddedGenes)) {
                $aNewVersion = array_merge($aNewVersion, $aAddedGenes);
            }

            $aGenePanelsRev[$sGpID][$nTime] = $aNewVersion;
        }

        $aPrevGenes = $aNewVersion;
    }
}

// Now, fetch all the analysis runs that uses 'apply_selected_gene_panels'
$sSQL = '
SELECT arf.runid, 
  GROUP_CONCAT(IF(gp.type = "gene_panel" OR gp.type = "mendeliome", LPAD(ar2gp.genepanelid, 5, 0), NULL)) AS gene_panel, 
  GROUP_CONCAT(IF(gp.type = "blacklist", LPAD(ar2gp.genepanelid, 5, 0), NULL)) AS blacklist, 
  ar.custom_panel,
  ar.created_date AS run_date
FROM ' . TABLE_ANALYSES_RUN_FILTERS . ' arf
JOIN ' . TABLE_AR2GP . ' ar2gp ON arf.runid = ar2gp.runid
JOIN ' . TABLE_ANALYSES_RUN . ' ar ON ar.id = arf.runid
JOIN ' . TABLE_GENE_PANELS . ' gp ON ar2gp.genepanelid = gp.id
WHERE arf.filterid = "apply_selected_gene_panels" AND arf.config_json IS NULL
GROUP BY arf.runid';

$zResultsAnalysisRuns = $_DB->query($sSQL)->fetchAllAssoc();

$sSQLUpdate = 'UPDATE ' . TABLE_ANALYSES_RUN_FILTERS . ' SET config_json = ? WHERE filterid = "apply_selected_gene_panels" AND runid = ?';
$zUpdateQuery = $_DB->prepare($sSQLUpdate);

// Loop through each analysis run and create configuration array to be inserted into
// config_json column in TABLE_ANALYSES_RUN_FILTERS
print "Updating analysis\n";
foreach ($zResultsAnalysisRuns as $aAnalysisRun) {
    $aConfig = array(
        'gene_panels' => array(),
        'metadata' => array()
    );

    $aAllGenepanelIDs = array();
    // Populate $aConfig['gene_panels'] array
    if (!empty($aAnalysisRun['gene_panel'])) {
        $aGenepanelIDs = explode(',', $aAnalysisRun['gene_panel']);
        $aConfig['gene_panels']['gene_panel'] = $aGenepanelIDs;
        $aAllGenepanelIDs = array_merge($aAllGenepanelIDs, $aGenepanelIDs);
    }

    if (!empty($aAnalysisRun['blacklist'])) {
        $aBlacklistIDs = explode(',', $aAnalysisRun['blacklist']);
        $aConfig['gene_panels']['blacklist'] = $aBlacklistIDs;
        $aAllGenepanelIDs = array_merge($aAllGenepanelIDs, $aBlacklistIDs);
    }

    if (!empty($aAnalysisRun['custom_panel'])) {
        $aConfig['gene_panels']['custom_panel'] = array('custom_panel');

        // For custom panel, populate its metadata field now
        // since we already know what they are
        $aConfig['metadata']['custom_panel'] = array(
            'genes' => explode(', ', $aAnalysisRun['custom_panel']),
            'last_modified' => $aAnalysisRun['run_date']
        );
    }

    // Populate $aConfig['metadata'] array
    foreach ($aAllGenepanelIDs as $sGpID) {
        $nRunTime = strtotime($aAnalysisRun['run_date']);

        $aValidVersion = array();
        $nValidTime = 0;
        if (isset($aGenePanelsRev[$sGpID])) {
            foreach ($aGenePanelsRev[$sGpID] as $nTime => $aGenes) {
                if ($nRunTime < $nTime) {
                    break;
                }

                $aValidVersion = $aGenes;
                $nValidTime = $nTime;
            }
        }

        // If we can't find version history, the best we can do is get the current version.
        if (empty($aValidVersion)) {
            $aValidVersion = $aGenePanels[$sGpID];
            $sLastModified = $aAnalysisRun['run_date'];
        } else {
            // Format from unix timestamp to string date
            $sLastModified = date('Y-m-d H:i:s');
        }

        $aConfig['metadata'][$sGpID] = array('genes' => $aValidVersion, 'last_modified' => $aAnalysisRun['run_date']);
    }

    // Update database 'config_json' column.
    $sConfigJson = json_encode($aConfig);
    $zUpdateQuery->execute(array($sConfigJson, $aAnalysisRun['runid']));
}

print count($zResultsAnalysisRuns) . " analysis updated\n";