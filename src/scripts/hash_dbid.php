<?php
/*
 * Script to update old DBID format chr_XXXXXX to an md5 hash of columns that identify unique variants
 */

define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__) . '/../'));
define('FORMAT_ALLOW_TEXTPLAIN', true);
$_GET['format'] = 'text/plain';
// To prevent notices when running inc-init.php.
$_SERVER = array_merge($_SERVER, array(
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => 'scripts/hash_dbid.php',
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
require ROOT_PATH . 'inc-lib-form.php';
////////////////////////////////
///// Script starts here //////

// 1. Update Summary Annotation Records
// This step has to be performed BEFORE we update VariantOnGenome/DBID in TABLE_VARIANTS.
define('PATTERN_DBID', '_[0-9]{6}$');
$aMapOldToNew = array();

print(date('Y-m-d H:i:s') . ": Updating summary annotation records\n");
// Get variant data for each dbid in summary annotation records
$sSQL = 'SELECT DISTINCT vog.`VariantOnGenome/DBID`, vog.`VariantOnGenome/DNA`, vog.chromosome, vog.position_g_start 
         FROM ' . TABLE_VARIANTS . ' AS vog 
         JOIN ' . TABLE_SUMMARY_ANNOTATIONS . ' AS sa ON (vog.`VariantOnGenome/DBID` = sa.id)';

$aVariantsData = $_DB->query($sSQL)->fetchAllGroupAssoc();
foreach ($aVariantsData as $sOldDBID => $aOneVariant) {
    $aMapOldToNew[$sOldDBID] = lovd_fetchDBID($aOneVariant);
}

$zUpdateSAQuery = $_DB->prepare('UPDATE ' . TABLE_SUMMARY_ANNOTATIONS . ' SET id = ? WHERE id = ?');
foreach ($aMapOldToNew as $sOldDBID => $sNewDBID) {
    $zUpdateSAQuery->execute(array($sNewDBID, $sOldDBID));
}
print(date('Y-m-d H:i:s') . ": Finished updating summary annotation records\n");


// 2. Update references to VariantOnGenome/DBID in TABLE_LOGS
// This step has to be performed BEFORE we update VariantOnGenome/DBID in TABLE_VARIANTS
print(date('Y-m-d H:i:s') . ": Updating summary annotation log entries\n");
$sSQL = 'SELECT date, mtime, event, log FROM ' . TABLE_LOGS . ' WHERE event IN ("SARCreate", "SAREdit")';
$aLogs = $_DB->query($sSQL)->fetchALLAssoc();

$zUpdateQuery = $_DB->prepare('UPDATE ' . TABLE_LOGS . ' SET log = ? WHERE event = ? AND date = ? AND mtime = ? AND log = ?');
foreach ($aLogs as $aOneLogEntry) {
    $sText = $aOneLogEntry['log'];
    preg_match('/- (.+)/', $sText, $aMatches);
    if (count($aMatches) > 1) {
        $sOldDBID = $aMatches[1];

        // Don't do anything if pattern is not chrph_XXXXXX or symbol_XXXXXX
        if (!preg_match('/' . PATTERN_DBID . '/', $sOldDBID)) {
            break;
        }

        // Find new DBID
        if (!isset($aMapOldToNew[$sOldDBID])) {
            // search one more time
            $sSQL = 'SELECT DISTINCT vog.`VariantOnGenome/DBID`, vog.`VariantOnGenome/DNA`, vog.chromosome, vog.position_g_start
                     FROM ' . TABLE_VARIANTS . ' AS vog
                     WHERE vog.`VariantOnGenome/DBID` IN (?)';

            $aOneVariant = $_DB->query($sSQL, array($sOldDBID))->fetchAssoc();
            if ($aOneVariant) {
                $aMapOldToNew[$sOldDBID] = lovd_fetchDBID($aOneVariant);
            }
        }

        if (!empty($aMapOldToNew[$sOldDBID])) {
            $sUpdatedText = str_replace($sOldDBID, $aMapOldToNew[$sOldDBID], $sText);
        } else {
            print('Failed to update log entry: ' . $sText);
        }

        $zUpdateQuery->execute(array($sUpdatedText, $aOneLogEntry['event'], $aOneLogEntry['date'], $aOneLogEntry['mtime'], $aOneLogEntry['log']));
    }
}
print(date('Y-m-d H:i:s') . ": Finished updating summary annotation log entries\n");

// Final step: Update VariantOnGenome/DBID in TABLE_VARIANTS
$sSQL = 'SELECT COUNT(id) AS num FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/DBID` REGEXP "' . PATTERN_DBID . '"';
$nRow = $_DB->query($sSQL)->fetchColumn();

if (!$nRow) {
    print(date('Y-m-d H:i:s') . ": All DBIDs have been updated\n");
}

$nBatchSize = 1000;
$nBatches = (int) ($nRow/$nBatchSize) + 1;

print("Rows: " . $nRow . "\n");
print("Batches: " . $nBatches . "\n");

$sHashFunc = 'SHA1';
$sSQLUpdateDBID = 'UPDATE '  . TABLE_VARIANTS . ' SET `VariantOnGenome/DBID`  = ' . $sHashFunc . '(CONCAT("' . $_CONF['refseq_build'] . '", chromosome, ".g.", REPLACE(REPLACE(REPLACE(`VariantOnGenome/DNA`, "(", ""), ")", ""), "?", "") )) WHERE id IN ';
$zUpdateDBIDQuery = $_DB->prepare($sSQLUpdateDBID . ' (?' . str_repeat(',?', $nBatchSize-1) . ')');
$nUpdated = 0;
for ($i=0; $i<$nBatches; $i++) {
    $sSQL = 'SELECT id FROM '  . TABLE_VARIANTS . ' WHERE `VariantOnGenome/DBID` REGEXP "' . PATTERN_DBID . '" ORDER BY `VariantOnGenome/DBID` LIMIT ' . $nBatchSize;
    $aVariantIds = $_DB->query($sSQL)->fetchAllColumn();

    if (count($aVariantIds) > 0) {
        // Update DBID

        // The prepare statement needs to be modified if number of variants is smaller than batch size.
        $zFinalQuery = $zUpdateDBIDQuery;
        if (count($aVariantIds) !== $nBatchSize) {
            $zCustomisedQuery = $_DB->prepare($sSQLUpdateDBID . ' (?' . str_repeat(',?', count($aVariantIds)-1) . ')');
            $zFinalQuery = $zCustomisedQuery;
        }
        $zFinalQuery->execute($aVariantIds);

        $nUpdated += count($aVariantIds);
        print(date('Y-m-d H:i:s') . ": " . $nUpdated . " DBIDs updated\n");
    }
}
