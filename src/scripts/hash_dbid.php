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
    'REQUEST_URI' => 'scripts/update_dbid.php',
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

$sSQL = 'SELECT COUNT(id) AS num FROM ' . TABLE_VARIANTS . ' WHERE `VariantOnGenome/DBID` LIKE "chr_%"';
$nRow = $_DB->query($sSQL)->fetchColumn();

if (!$nRow) {
    print(date('Y-m-d H:i:s') . ": All DBIDs have been updated\n");
}

$nBatchSize = 1000;
$nBatches = ($nRow < $nBatchSize? 1 :$nRow/$nBatchSize);

$nUpdated = 0;
for ($i=0; $i<$nBatches; $i++) {
    $sSQL = 'SELECT id FROM '  . TABLE_VARIANTS . ' WHERE `VariantOnGenome/DBID` LIKE "chr_%" ORDER BY `VariantOnGenome/DBID` LIMIT ' . $nBatchSize . ' OFFSET ' . $i;
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
