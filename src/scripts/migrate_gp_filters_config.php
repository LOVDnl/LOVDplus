<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2017-08-15
 * Modified    : 2022-11-30
 * For LOVD+   : 3.0-29
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Juny Kesumadewi <juny.kesumadewi@unimelb.edu.au>
 *               Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *************/

// Script to fill the config_json field for the gene panel filter.

define('FORMAT_ALLOW_TEXTPLAIN', true);
$_GET['format'] = 'text/plain';

define('ROOT_PATH', '../');
require ROOT_PATH . 'inc-init.php';





// Increase DB limits to allow concatenation of large number of gene IDs.
$_DB->q('SET group_concat_max_len = 500000');

// Fetch all the analysis runs that uses 'apply_selected_gene_panels'.
print('Fetching all analysis filters to be updated...
');
ob_flush();
// Quite a complicated way to find out when a gene panel has last been edited, because we never used the gene panel's
//  edited_date when its genes were modified. That would have saved us a great deal of work in this query.
$zAnalysisRuns = $_DB->q(
    'SELECT
       arf.runid,
       GROUP_CONCAT(
         CONCAT(gp.id, ";", REPLACE(gp.name, ";", ","), ";", REPLACE(gp.type, "mendeliome", "gene_panel"), ";",
           GREATEST(
             IFNULL(
               (SELECT MAX(gp2g.valid_from)
                FROM ' . TABLE_GP2GENE_REV . ' AS gp2g
                WHERE gp2g.genepanelid = gp.id AND gp2g.valid_from <= ar.created_date AND gp2g.valid_to > ar.created_date), gp.created_date),
             IFNULL(
               (SELECT MAX(gp2g.valid_to)
                FROM ' . TABLE_GP2GENE_REV . ' AS gp2g
                WHERE gp2g.genepanelid = gp.id AND gp2g.valid_to < ar.created_date AND gp2g.deleted = 1), gp.created_date)), ";",
           (SELECT GROUP_CONCAT(DISTINCT gp2g.geneid SEPARATOR ",")
            FROM ' . TABLE_GP2GENE_REV . ' AS gp2g
            WHERE gp2g.genepanelid = gp.id AND gp2g.valid_from <= ar.created_date AND gp2g.valid_to > ar.created_date
            GROUP BY gp2g.genepanelid)) SEPARATOR ";;") AS __gene_panels,
       REPLACE(ar.custom_panel, " ", "") AS _custom_panel,
       ar.created_date
     FROM ' . TABLE_ANALYSES_RUN_FILTERS . ' AS arf
       INNER JOIN ' . TABLE_ANALYSES_RUN . ' AS ar ON (arf.runid = ar.id)
       INNER JOIN ' . TABLE_AR2GP . ' AS ar2gp ON (arf.runid = ar2gp.runid)
       INNER JOIN ' . TABLE_GENE_PANELS_REV . ' AS gp ON (ar2gp.genepanelid = gp.id AND ar.created_date >= gp.valid_from AND ar.created_date < gp.valid_to)
     WHERE arf.filterid = ? AND arf.config_json IS NULL
     GROUP BY arf.runid', array('apply_selected_gene_panels'))->fetchAllAssoc();



// Properly explode the gene panel data.
$zAnalysisRuns = array_map(function ($aAnalysisRun)
{
    $aAnalysisRun['gene_panels'] = array();
    if ($aAnalysisRun['__gene_panels']) {
        $aAnalysisRun['gene_panels'] = array_map(function ($sGenePanelData)
        {
            list($nID, $sName, $sType, $sLastModified, $sGenes) = explode(';', $sGenePanelData);
            return array(
                'id' => $nID,
                'name' => $sName,
                'type' => $sType,
                'last_modified' => $sLastModified,
                'genes' => explode(',', $sGenes),
            );
        }, explode(';;', $aAnalysisRun['__gene_panels']));
    }
    $aAnalysisRun['custom_panel'] = array();
    if ($aAnalysisRun['_custom_panel']) {
        $aAnalysisRun['custom_panel'] = explode(',', $aAnalysisRun['_custom_panel']);
    }
    unset($aAnalysisRun['__gene_panels'], $aAnalysisRun['_custom_panel']);
    return $aAnalysisRun;
}, $zAnalysisRuns);





// Prepare updating the analysis filters.
$sSQLUpdate = 'UPDATE ' . TABLE_ANALYSES_RUN_FILTERS . ' SET config_json = ? WHERE filterid = "apply_selected_gene_panels" AND runid = ?';
$qUpdate = $_DB->prepare($sSQLUpdate);

// Loop through each analysis run and create the configuration array to be
//  inserted into the config_json column in TABLE_ANALYSES_RUN_FILTERS.
print('Updating analyses...
');
foreach ($zAnalysisRuns as $aAnalysisRun) {
    $aConfig = array(
        'gene_panels' => array(),
        'metadata' => array()
    );

    // Populate $aConfig array with gene panels and blacklists.
    foreach ($aAnalysisRun['gene_panels'] as $aGenePanel) {
        if (!isset($aConfig['gene_panels'][$aGenePanel['type']])) {
            // Note, that gene_panel and mendeliome
            //  have already been merged into gene_panel.
            $aConfig['gene_panels'][$aGenePanel['type']] = array();
        }
        $aConfig['gene_panels'][$aGenePanel['type']][] = $aGenePanel['id'];

        // Populate $aConfig['metadata'] array.
        $aConfig['metadata'][$aGenePanel['id']] = array(
            'name' => $aGenePanel['name'],
            'genes' => $aGenePanel['genes'],
            'last_modified' => $aGenePanel['last_modified'],
        );
    }

    // Add the custom panel, if present.
    if (!empty($aAnalysisRun['custom_panel'])) {
        $aConfig['gene_panels']['custom_panel'] = array('custom_panel');

        // Populate $aConfig['metadata'] array.
        $aConfig['metadata']['custom_panel'] = array(
            'name' => '', // To keep things standard.
            'genes' => $aAnalysisRun['custom_panel'],
            // Because we don't know the date the custom panel has been edited,
            //  we take the date of the analysis run.
            'last_modified' => $aAnalysisRun['created_date']
        );
    }

    // Update database 'config_json' column.
    $sConfigJson = json_encode($aConfig);
    $qUpdate->execute(array($sConfigJson, $aAnalysisRun['runid']));

    print('.');
    flush();
}

print('
Updated ' . count($zAnalysisRuns) . ' analysis run(s).
');
