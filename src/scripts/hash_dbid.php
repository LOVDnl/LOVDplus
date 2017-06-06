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

// 1. Update Summary Annotation Records before we update VariantOnGenome/DBID in TABLE_VARIANTS.
$sSQL = 'SELECT DISTINCT id FROM ' . TABLE_SUMMARY_ANNOTATIONS;
$aSummaryAnnoDBIDs = $_DB->query($sSQL)->fetchAllColumn();

print(date('Y-m-d H:i:s') . ": Updating summary annotation records\n");
if (count($aSummaryAnnoDBIDs) > 0) {
    // Get variant data for each dbid in summary annotation records
    $sSQL = 'SELECT DISTINCT vog.`VariantOnGenome/DBID`, vog.`VariantOnGenome/DNA`, vog.chromosome, vog.position_g_start 
             FROM ' . TABLE_VARIANTS . ' AS vog 
             WHERE vog.`VariantOnGenome/DBID` IN (?' . str_repeat(',?', count($aSummaryAnnoDBIDs)-1) . ')';
    
    $aVariantsData = $_DB->query($sSQL, $aSummaryAnnoDBIDs)->fetchAllGroupAssoc();

    $aMapOldToNew = array();
    foreach ($aSummaryAnnoDBIDs as $sOldDBID) {
        $aMapOldToNew[$sOldDBID] = lovd_fetchDBID($aVariantsData[$sOldDBID]);
    }
}

foreach ($aMapOldToNew as $sOldDBID => $sNewDBID) {
    $sSQL = 'UPDATE ' . TABLE_SUMMARY_ANNOTATIONS . ' SET id = ? WHERE id = ?';
    $_DB->query($sSQL, array($sNewDBID, $sOldDBID));
}
print(date('Y-m-d H:i:s') . ": Finished updating summary annotation records\n");


// 2. Update references to VariantOnGenome/DBID in TABLE_LOGS
print(date('Y-m-d H:i:s') . ": Updating summary annotation log entries\n");
$sSQL = 'SELECT date, mtime, event, log FROM ' . TABLE_LOGS . ' WHERE event IN ("SARCreate", "SAREdit")';
$aLogs = $_DB->query($sSQL)->fetchALLAssoc();

foreach ($aLogs as $aOneLogEntry) {
    $sText = $aOneLogEntry['log'];
    preg_match('/- (.+)/', $sText, $aMatches);
    if (count($aMatches) > 1) {
        $sOldDBID = $aMatches[1];

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

        $sSQL = 'UPDATE ' . TABLE_LOGS . ' SET log = ? WHERE event = ? AND date = ? AND mtime = ? AND log = ?';
        $_DB->query($sSQL, array($sUpdatedText, $aOneLogEntry['event'], $aOneLogEntry['date'], $aOneLogEntry['mtime'], $aOneLogEntry['log']));
    }
}
print(date('Y-m-d H:i:s') . ": Finished updating summary annotation log entries\n");

// Final step: Update VariantOnGenome/DBID in TABLE_VARIANTS
$sSQL = 'SELECT COUNT(id) AS num FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/DBID` LIKE "chr_%"';
$nRow = $_DB->query($sSQL)->fetchColumn();

if (!$nRow) {
    print(date('Y-m-d H:i:s') . ": All DBIDs have been updated\n");
}

$nBatchSize = 1000;
$nBatches = ((int) $nRow/$nBatchSize) + 1;

print("Rows: " . $nRow . "\n");
print("Batches: " . $nBatches . "\n");

$nUpdated = 0;
for ($i=0; $i<$nBatches; $i++) {
    $sSQL = 'SELECT id FROM '  . TABLE_VARIANTS . ' WHERE `VariantOnGenome/DBID` LIKE "chr_%" ORDER BY `VariantOnGenome/DBID` LIMIT ' . $nBatchSize;
    $aVariantIds = $_DB->query($sSQL)->fetchAllColumn();

    if (count($aVariantIds) > 0) {
        // Update DBID
        $sSQL = 'UPDATE lovd_variants SET `VariantOnGenome/DBID`  = MD5(CONCAT("' . $_CONF['refseq_build'] . '", chromosome, ".g.", REPLACE(REPLACE(REPLACE(`VariantOnGenome/DNA`, "(", ""), ")", ""), "?", "") ))
             WHERE id IN (?' . str_repeat(',?', count($aVariantIds)-1) . ')';
        $_DB->query($sSQL, $aVariantIds);

        $nUpdated += count($aVariantIds);
        print(date('Y-m-d H:i:s') . ": " . $nUpdated . " DBIDs updated\n");
    }

}

