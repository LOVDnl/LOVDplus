<?php

$_INSTANCE_CONFIG = array();

$_INSTANCE_CONFIG['screenings'] = array(
    'viewList' => array(
        'colsToShow' => array(
            // We can have view list id as key here if needed.
            // 0 here means the viewList columns seen by the constructor (at the point where we don't know VL id yet.
            0 => array(
                // Invisible.
                'individualid',

                // Visible.
                'id',
                'Screening/Tumor/Sample_ID',
                'Screening/Normal/Sample_ID',
                'Screening/Pipeline/Path',
                'variants_found_',
                'analysis_status'
            )
        )
    )
);

function lovd_prepareMappings() {

    $aColumnMappings = array(
        'CHROM' => 'chromosome',
        'POS' => 'position',
        'ID' => 'VariantOnGenome/dbSNP',
        'REF' => 'ref',
        'ALT' => 'alt',
        'QUAL' => 'VariantOnGenome/Sequencing/Quality',
        'SYMBOL' => 'symbol',

        'Feature' => 'transcriptid',
        'HGVSc' => 'VariantOnTranscript/DNA',
        'HGVSp' => 'VariantOnTranscript/Protein',

        // made up columns
        'allele' => 'allele',
    );

    return $aColumnMappings;
}

function lovd_prepareVariantData($aLine) {
    $aLine['CHROM'] = 'chr' . $aLine['CHROM'];

    return $aLine;
}

function lovd_prepareGeneAliases() {
    // Prepare the $aGeneAliases array with a site specific gene alias list.
    // The convert and merge script will provide suggested gene alias key value pairs to add to this array.
    $aGeneAliases = array();
    return $aGeneAliases;
}

function lovd_prepareGenesToIgnore() {
    // Prepare the $aGenesToIgnore array with a site specific gene list.
    $aGenesToIgnore = array();
    return $aGenesToIgnore;
}

function lovd_prepareScreeningID($aMetaData) {
    return 1;
}

function lovd_getInputFilePrefixPattern() {
    return '(.+)';
}

function lovd_getRequiredHeaderColumns() {
    return array(
        'CHROM',
        'POS',
        'ID',
        'REF',
        'ALT',
        'QUAL',
        'FILTER'
    );
}

function lovd_prepareHeaders($aHeaders, $options = array()) {
    extract($options);
    return $aHeaders;
}

function lovd_formatEmptyColumn($aLine, $sLOVDColumn, $aVariant) {
    $aVariant[$sLOVDColumn] = '';
    return $aVariant;
}

function lovd_postValueAssignmentUpdate($sKey, $aVariant, $aData) {
    return $aData;
}
