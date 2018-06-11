<?php
// Leiden specific adapter settings.
$_INSTANCE_CONFIG['conversion']['verbosity_other'] = 9;

$_INSTANCE_CONFIG['viewlists']['Screenings_for_I_VE']['cols_to_show'] = array(
    // The screenings data listing on the individual's detailed view.
    // Select these columns for the screenings listing on the individual's page.
    // Note, that you also need to define the hidden columns that
    //  are to be active, since LOVD+ might be filtering on them.
    // You can change the order of columns to any order you like.
    'id',
    'individualid', // Hidden, but needed for search.
    'Screening/Panel_coverage/Fraction',
    'Screening/Father/Panel_coverage/Fraction',
    'Screening/Mother/Panel_coverage/Fraction',
    'curation_progress_',
    'variants_found_',
    'analysis_status',
);

$_INSTANCE_CONFIG['observation_counts'] = array(
    'genepanel' => array(
        'columns' => array(
            'value' => 'Gene Panel',
            'total_individuals' => 'Total # Individuals',
            'percentage' => 'Percentage (%)'
        ),
        'categories' => array(
            'all',
            'gender',
        ),
        'show_decimals' => 1,
    ),
    'general' => array(
        // if columns is empty, use default columns list
        'columns' => array(
            'label' => 'Category',
            'value' => 'Value',
            'percentage' => 'Percentage (%)'
        ),
        'categories' => array(
            'all',
            'Individual/Gender',
        ),
        'show_decimals' => 1,
        'min_population_size' => 100,
    ),
);





class LOVD_LeidenDataConverter extends LOVD_DefaultDataConverter {
    // Contains the overloaded functions that we want different from the default.

    function cleanHeaders ($aHeaders)
    {
        // Leiden's headers can be appended by the Miracle ID.
        // Clean this off, and verify the identity of this file.
        // Check the child's Miracle ID with that we have in the meta data file, and die if there is a mismatch.
        foreach ($aHeaders as $key => $sHeader) {
            if (preg_match('/(Child|Patient|Father|Mother)_(\d+)$/', $sHeader, $aRegs)) {
                // If Child, check ID.
                if (!empty($this->aScriptVars['nMiracleID']) && in_array($aRegs[1], array('Child', 'Patient')) && $aRegs[2] != $this->aScriptVars['nMiracleID']) {
                    // Here, we won't try and remove the temp file. We need it for diagnostics, and it will save us from running into the same error over and over again.
                    die('Fatal: Miracle ID of ' . $aRegs[1] . ' (' . $aRegs[2] . ') does not match that from the meta file (' . $this->aScriptVars['nMiracleID'] . ')' . "\n");
                }
                // Clean ID from column.
                $aHeaders[$key] = substr($sHeader, 0, -(strlen($aRegs[2]) + 1));
                // Also clean "Child" and "Patient" off.
                $aHeaders[$key] = preg_replace('/_(Child|Patient)$/', '', $aHeaders[$key]);
            }
        }

        return $aHeaders;
    }





    function getInputFilePrefixPattern ()
    {
        // Returns the regex pattern of the prefix of variant input file names.
        // The prefix is often the sample ID or individual ID, and can be formatted to your liking.
        // Data files must be named "prefix.suffix", using the suffixes as defined in the conversion script.

        // If using sub patterns, make sure they are not counted, like so:
        //  (?:subpattern)
        return '(?:Child|Patient)_(?:\d+)';
    }





    function getRequiredHeaderColumns ()
    {
        // Returns an array of required variant input file column headers.
        // The order of these columns does NOT matter.

        return array(
            'chromosome',
            'position',
            'REF',
            'ALT',
            'QUAL',
            'FILTERvcf',
            'GATKCaller',
            'GT',
            'SYMBOL',
            'Feature',
        );
    }





    function ignoreTranscript ($sTranscriptID)
    {
        // Leiden's LOVD+ doesn't ignore transcripts, their pipeline decides.
        return false;
    }





    function prepareGeneAliases ()
    {
        // Return an array of gene aliases, with the gene symbol as given by VEP
        //  as the key, and the symbol as known by LOVD/HGNC as the value.
        // Example:
        // return array(
        //     'C4orf40' => 'PRR27',
        // );

        return array(
            // This list needs to be replaced now and then.
            // These below have been added 2017-07-27. Expire 2018-07-27.
            'AQPEP' => 'LVRN',
            'C10orf112' => 'MALRD1',
            'C11orf34' => 'PLET1',
            'C19orf69' => 'ERICH4',
            'C4orf40' => 'PRR27',
            'C5orf50' => 'SMIM23',
            'C8orf47' => 'ERICH5',
            'C9orf169' => 'CYSRT1',
            'C9orf173' => 'STPG3',
            'C9orf37' => 'ARRDC1-AS1',
            'DOM3Z' => 'DXO',
            'FAM203A' => 'HGH1',
            'FAM25B' => 'FAM25BP',
            'FAM5C' => 'BRINP3',
            'FOLR4' => 'IZUMO1R',
            'GTDC2' => 'POMGNT2',
            'HDGFRP2' => 'HDGFL2',
            'HDGFRP3' => 'HDGFL3',
            'HMP19' => 'NSG2',
            'IL8' => 'CXCL8',
            'KIAA1737' => 'CIPC',
            'KIAA1804' => 'MAP3K21',
            'KIAA1967' => 'CCAR2',
            'LIMS3L' => 'LIMS4',
            'LINC00984' => 'INAFM2',
            'LPPR1' => 'PLPPR1',
            'LPPR2' => 'PLPPR2',
            'LPPR3' => 'PLPPR3',
            'LPPR4' => 'PLPPR4',
            'LPPR5' => 'PLPPR5',
            'LSMD1' => 'NAA38',
            'METTL21D' => 'VCPKMT',
            'MKI67IP' => 'NIFK',
            'MNF1' => 'UQCC2',
            'NAPRT1' => 'NAPRT',
            'NARR' => 'RAB34',
            'NEURL' => 'NEURL1',
            'NIM1' => 'NIM1K',
            'PAPL' => 'ACP7',
            'PCDP1' => 'CFAP221',
            'PHF17' => 'JADE1',
            'PPIAL4B' => 'PPIAL4A',
            'PRMT10' => 'PRMT9',
            'REXO1L1' => 'REXO1L1P',
            'SCXA' => 'SCX',
            'SELK' => 'SELENOK',
            'SELM' => 'SELENOM',
            'SELO' => 'SELENOO',
            'SELT' => 'SELENOT',
            'SELV' => 'SELENOV',
            'SEP15' => 'SELENOF',
            'SGK110' => 'SBK3',
            'SGK223' => 'PRAG1',
            'SMCR7L' => 'MIEF1',
            'TCEB3CL' => 'ELOA3B',
            'YES' => 'YES1',
            'ZAK' => 'MAP3K20',
            // These above have been added 2017-07-27. Expire 2018-07-27.

            // Added 2018-02-20, expire 2019-02-20.
            'CPSF3L' => 'INTS11',
            'GLTPD1' => 'CPTP',
            'C1orf233' => 'FNDC10',
            'KIAA1751' => 'CFAP74',
            'C1orf86' => 'FAAP20',
            'APITD1-CORT' => 'CENPS-CORT',
            'APITD1' => 'CENPS',
            'PTCHD2' => 'DISP3',
            'PRAMEF23' => 'PRAMEF5',
            'HNRNPCP5' => 'HNRNPCL2',

            // Added 2018-04-12, expire 2019-04-12.
            'C10orf137' => 'EDRF1',
            'C11orf93' => 'COLCA2',
            'C12orf52' => 'RITA1',
            'C13orf45' => 'LMO7DN',
            'C19orf82' => 'ZNF561-AS1',
            'C1orf63' => 'RSRP1',
            'C20orf201' => 'LKAAEAR1',
            'C2orf62' => 'CATIP',
            'C3orf37' => 'HMCES',
            'C3orf43' => 'SMCO1',
            'C3orf83' => 'MKRN2OS',
            'C6orf70' => 'ERMARD',
            'C7orf41' => 'MTURN',
            'C9orf123' => 'DMAC1',
            'CCDC111' => 'PRIMPOL',
            'CNIH' => 'CNIH1',
            'CXorf48' => 'CT55',
            'CXorf61' => 'CT83',
            'CXXC11' => 'RTP5',
            'GPER' => 'GPER1',
            'KIAA1704' => 'GPALPP1',
            'KIAA1984' => 'CCDC183',
            'MST4' => 'STK26',
            'PHF15' => 'JADE2',
            'PHF16' => 'JADE3',
            'PLAC1L' => 'OOSP2',
            'PNMA6C' => 'PNMA6A',
            'PRAC' => 'PRAC1',
            'SCXB' => 'SCX',
            'SELRC1' => 'COA7',
            'SGK196' => 'POMK',
            'SMCR7' => 'MIEF2',
            'SPANXB2' => 'SPANXB1',
            'SPATA31A2' => 'SPATA31A1',
            'UQCC' => 'UQCC1',
            'WTH3DI' => 'RAB6D',
            'ZFP112' => 'ZNF112',
        );
    }
}
