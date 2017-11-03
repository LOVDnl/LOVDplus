<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-06-05
 * Modified    : 2017-11-03
 * For LOVD+   : 3.0-18
 *
 * Copyright   : 2004-2017 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *************/

// Script to update old DBID format chr_XXXXXX to a sha1
//  hash of columns that identify unique variants.

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
require ROOT_PATH . 'inc-init.php';
require ROOT_PATH . 'inc-lib-form.php';

////////////////////////////////
///// Script starts here //////

// 1. Update Summary Annotation Records
// This step has to be performed BEFORE we update VariantOnGenome/DBID in TABLE_VARIANTS.
define('PATTERN_DBID', '_[0-9]{6}$');
$aMapOldToNew = array();

print(date('Y-m-d H:i:s') . ": Updating summary annotation records...\n");
// Get variant data for each DBID in the summary annotation record table.
$sSQL = 'SELECT DISTINCT vog.`VariantOnGenome/DBID`, vog.`VariantOnGenome/DNA`, vog.chromosome
         FROM ' . TABLE_VARIANTS . ' AS vog 
         INNER JOIN ' . TABLE_SUMMARY_ANNOTATIONS . ' AS sa ON (vog.`VariantOnGenome/DBID` = sa.id)';

$zVariantsData = $_DB->query($sSQL)->fetchAllGroupAssoc();
foreach ($zVariantsData as $sOldDBID => $zOneVariant) {
    $aMapOldToNew[$sOldDBID] = lovd_fetchDBID($zOneVariant);
}

$qUpdateSAQuery = $_DB->prepare('UPDATE ' . TABLE_SUMMARY_ANNOTATIONS . ' SET id = ? WHERE id = ?');
foreach ($aMapOldToNew as $sOldDBID => $sNewDBID) {
    $qUpdateSAQuery->execute(array($sNewDBID, $sOldDBID));
}
print(date('Y-m-d H:i:s') . ": Finished updating summary annotation records!\n");





// 2. Update references to VariantOnGenome/DBID in TABLE_LOGS
// This step has to be performed BEFORE we update VariantOnGenome/DBID in TABLE_VARIANTS
print(date('Y-m-d H:i:s') . ": Updating summary annotation log entries...\n");
$sSQL = 'SELECT date, mtime, event, log FROM ' . TABLE_LOGS . ' WHERE event IN ("SARCreate", "SAREdit")';
$zLogs = $_DB->query($sSQL)->fetchAllAssoc();

$qUpdateQuery = $_DB->prepare('UPDATE ' . TABLE_LOGS . ' SET log = ? WHERE event = ? AND date = ? AND mtime = ? AND log = ?');
foreach ($zLogs as $zOneLogEntry) {
    $sText = $zOneLogEntry['log'];
    if (preg_match('/- (.+' . PATTERN_DBID . ')/', $sText, $aMatches)) {
        $sOldDBID = $aMatches[1];

        // Find new DBID.
        if (!isset($aMapOldToNew[$sOldDBID])) {
            // Perhaps the SAR wasn't there anymore. Search in the variant table itself.
            $sSQL = 'SELECT DISTINCT vog.`VariantOnGenome/DBID`, vog.`VariantOnGenome/DNA`, vog.chromosome
                     FROM ' . TABLE_VARIANTS . ' AS vog
                     WHERE vog.`VariantOnGenome/DBID` = ?';

            $zOneVariant = $_DB->query($sSQL, array($sOldDBID))->fetchAssoc();
            if ($zOneVariant) {
                $aMapOldToNew[$sOldDBID] = lovd_fetchDBID($zOneVariant);
            }
        }

        if (!empty($aMapOldToNew[$sOldDBID])) {
            $sUpdatedText = str_replace($sOldDBID, $aMapOldToNew[$sOldDBID], $sText);
        } else {
            print('Failed to update log entry: ' . $sText . "\n");
        }

        $qUpdateQuery->execute(array($sUpdatedText, $zOneLogEntry['event'], $zOneLogEntry['date'], $zOneLogEntry['mtime'], $zOneLogEntry['log']));
    }
}
print(date('Y-m-d H:i:s') . ": Finished updating summary annotation log entries!\n");





// Final step: Update VariantOnGenome/DBID in TABLE_VARIANTS.
// The LIKE "ch%" makes sure we don't match any SHA1 results, as that cannot include an 'h'.
$sSQL = 'SELECT COUNT(id) AS num FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/DBID` LIKE "ch%"';
$nRow = $_DB->query($sSQL, array())->fetchColumn();

if (!$nRow) {
    print(date('Y-m-d H:i:s') . ": No DBIDs to update!\n");
}

$nBatchSize = 10000;
$nBatches = ceil($nRow/$nBatchSize);

print('Rows: ' . $nRow . "\n");
print('Batches: ' . $nBatches . "\n");

// TODO: WARNING! UPDATE THIS QUERY WHENEVER lovd_fetchDBID() IS UPDATED!
$sSQLUpdateDBID = 'UPDATE '  . TABLE_VARIANTS . ' SET `VariantOnGenome/DBID` = SHA1(CONCAT("' . $_CONF['refseq_build'] . '.chr", chromosome, ":", REPLACE(REPLACE(REPLACE(`VariantOnGenome/DNA`, "(", ""), ")", ""), "?", ""))) 
                   WHERE `VariantOnGenome/DBID` LIKE "ch%"
                   LIMIT ' . $nBatchSize;
$zUpdateDBIDQuery = $_DB->prepare($sSQLUpdateDBID);


for ($nBatch = 1; $nBatch <= $nBatches; $nBatch++) {
    $zUpdateDBIDQuery->execute();
    print(date('Y-m-d H:i:s') . ': ' . $nBatch . ' batches of ' . $nBatchSize . " updated!\n");
    flush(); // May not actually result in output, depends on some other factors, too.
}
?>
