<?php

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
