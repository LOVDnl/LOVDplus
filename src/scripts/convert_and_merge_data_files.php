<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-11-28
 * Modified    : 2016-10-06
 * For LOVD+   : 3.0-16
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *************/

//define('ROOT_PATH', '../');
define('ROOT_PATH', dirname(__FILE__) . '/../');
define('FORMAT_ALLOW_TEXTPLAIN', true);
$_GET['format'] = 'text/plain';
// To prevent notices when running inc-init.php.
$_SERVER = array_merge($_SERVER, array(
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => 'scripts/convert_and_merge_data_files.php',
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
require ROOT_PATH . 'inc-lib-genes.php';
// 128MB was not enough for a 100MB file. We're already no longer using file(), now we're using fgets().
// But still, loading all the gene and transcript data, uses too much memory. After some 18000 lines, the thing dies.
// Setting to 2GB, but still maybe we'll run into problems.
ini_set('memory_limit', '2048M');

// But we don't care about your session (in fact, it locks the whole LOVD if we keep this page running).
session_write_close();
set_time_limit(0);
ignore_user_abort(true);





// This script will be called from localhost by a cron job.
$aSuffixes = array(
    'meta' => 'meta.lovd',
    'vep' => 'directvep.data.lovd',
    'total.tmp' => 'total.data.tmp',
    'total' => 'total.data.lovd',
);

// Define list of genes to ignore, because they can't be found by the HGNC.
// LOC* genes are always ignored, because they never work (HGNC doesn't know them).
$aGenesToIgnore = array(
    // 2015-01-19; Not recognized by HGNC.
    'FLJ12825',
    'FLJ27354',
    'FLJ37453',
    'HEATR8-TTC4',
    'HSD52',
    'LPPR5',
    'MGC34796',
    'MGC27382',
    'SEP15',
    'TNFAIP8L2-SCNM1',
    // 2015-01-20; Not recognized by HGNC.
    'BLOC1S1-RDH5',
    'C10orf32-AS3MT',
    'CAND1.11',
    'DKFZp686K1684',
    'FAM24B-CUZD1',
    'FLJ46300',
    'FLJ46361',
    'GNN',
    'KIAA1804',
    'NS3BP',
    'OVOS',
    'OVOS2',
    'PRH1-PRR4',
    // 2015-01-20; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
    'FLJ26245',
    'DKFZP434H168',
    'FLJ30679',
    'C17orf61-PLSCR3',
    'FLJ36000',
    'RAD51L3-RFFL',
    'MGC57346',
    'FLJ40194',
    'FLJ45513',
    'MTVR2',
    'MGC16275',
    'FLJ45079',
    'C18orf61',
    'KC6',
    'FLJ44313',
    'HDGFRP2',
    'FLJ22184',
    'CYP3A7-CYP3AP1',
    'DKFZp434J0226',
    'DKFZp434L192',
    'EEF1E1-MUTED',
    'FLJ16171',
    'FLJ16779',
    'FLJ25363',
    'FLJ33360',
    'FLJ33534',
    'FLJ34503',
    'FLJ40288',
    'FLJ41941',
    'FLJ42351',
    'FLJ42393',
    'FLJ42969',
    'FLJ43879',
    'FLJ44511',
    'FLJ46066',
    'FLJ46284',
    'GIMAP1-GIMAP5',
    'HMP19',
    'HOXA10-HOXA9',
    'IPO11-LRRC70',
    'KIAA1656',
    'LGALS17A',
    'LPPR2',
    'MGC45922',
    'MGC72080',
    'NHEG1',
    'NSG1',
    'PAPL',
    'PHOSPHO2-KLHL23',
    'PP12613',
    'PP14571',
    'PP7080',
    'SELK',
    'SELO',
    'SELT',
    'SELV',
    'SF3B14',
    'SGK223',
    'SLED1',
    'SLMO2-ATP5E',
    'SMA5',
    'WTH3DI',
    'ZAK',
    // 2015-01-22; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
    'LPPR1',
    'FLJ44635',
    'MAGEA10-MAGEA5',
    'ZNF664-FAM101A',
    // 2015-03-05; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
    'TARP',
    'LPPR3',
    'THEG5',
    'LZTS3',
    // 2015-03-12; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
    'HDGFRP3',
    'HGC6.3',
    'NARR',
    // 2015-03-13; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
    'SELM',
    // 2015-03-16; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
    'DKFZP434L187',
    'DKFZp566F0947',
    'DKFZP586I1420',
    'FLJ22447',
    'FLJ31662',
    'FLJ36777',
    'FLJ38576',
    'GM140',
    'LINC00417',
    'MGC27345',
    'PER4',
    'UQCRHL',
    'LPPR4',
    // 2016-03-04; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!
    'SGK494',
    // 2016-03-04; Not recognized by HGNC, BUT NOT CONFIRMED AT HGNC WEBSITE (because it might be a different class)!





    // 2015-01-19; No UD could be loaded.
    'RNU6-2',
    'BRWD1-AS2',
    'GSTTP2',
    'PRSS3P2',
    // 2015-01-22; No UD could be loaded.
    'DUX2',
    // 2015-03-04; No UD could be loaded.
    'PRAMEF22',
    'AGAP10',
    'ANXA8L2',
    'TRIM49D2P',
    'C12orf68',
    'CXorf64',
    // 2015-03-13; No UD could be loaded.
    'LMO7DN',
    'MKRN2OS',
    'RTP5',
    'MALRD1',
    // 2015-03-16; No UD could be loaded.
    'LINC01193',
    'LINC01530',
    'IZUMO1R',
    'MRLN',
    'LINC01184',
    'LINC01185',
    'NBPF17P',
    'PCDHB17P',
    'PERM1',
    'COA7',
    'NIM1K',
    'ZNF561-AS1',
    // 2015-06-25; No UD could be loaded.
    'SCX',
    'HGH1',
    // 2015-08-??; No UD could be loaded.
    'ARRDC1-AS1',
    // 2016-02-19; No UD could be loaded.
    'AZIN2',
    'ADGRB2',
    'KDF1',
    'ERICH3',
    'LEXM',
    'CIART',
    'FAAP20',
    'ADGRL4',
    'CPTP',
    'MFSD14A',
    'CFAP74',
    'P3H1',
    'ADGRL2',
    'NRDC',
    'PLPP3',
    'DISP3',
    'CFAP57',
    // 2016-03-04; No UD could be loaded.
    'ADGRD2',
    'ADGRE5',
    // 2016-03-04; No UD could be loaded.






    // 2015-01-16; No transcripts could be found.
    'HNRNPCL2',
    'MST1L',
    'PIK3CD-AS1',
    'PLEKHM2',
    // 2015-01-19; No transcripts could be found.
    'AKR1C8P',
    'ATP1A1OS',
    'C1orf213',
    'DARC',
    'FALEC',
    'MIR664A',
    'RSRP1',
    'SNORA42',
    'SRGAP2B',
    // 2015-01-20; No transcripts could be found.
    // Still needs to be sorted.
    'C10orf115',
    'C10orf126',
    'ZNF487',
    'TIMM23B',
    'OLMALINC',
    'LINC01561',
    'PHRF1',
    'EWSAT1',
    'ST20-AS1',
    'NR2F2-AS1',
    'LINC00273',
    'SNORA76A',
    'MIR4520-2',
    'MIR4520-1',
    'CDRT8',
    'LRRC75A-AS1',
    'C17orf76-AS1',
    'KANSL1',
    'SNF8',
    'ZNF271',
    'TCEB3CL',
    'INSL3',
    'ZNF738',
    'ERVV-1',
    'KIR3DX1',
    'SMYD5',
    'DIRC3',
    'SNPH',
    'FAM182A',
    'FRG1B',
    'PPP4R1L',
    'C21orf37',
    'KRTAP20-4',
    'C21orf49',
    'C21orf54',
    'C21orf67',
    'RFPL3S',
    'C22orf34',
    'SNORA76C',
    'ERICH4',
    'ZNF350-AS1',
    'CFAP221',
    'CATIP',
    'MIR3648-1',
    'MIR3687-1',
    'CYP4F29P',
    'MIR99AHG',
    'UMODL1-AS1',
    'LINC00692',
    'C3orf49',
    'PLD1',
    'C3orf65',
    'KLF3-AS1',
    'SERPINB9P1',
    'MIR219A1',
    'RNF217-AS1',
    'UMAD1',
    'LINC01446',
    'FKBP9P1',
    'ABHD11-AS1',
    'APTR',
    'LINC-PINT',
    'INAFM2',
    'ZNF767P',
    'MIR124-2HG',
    'LINC01298',
    'DUX4',
    'LTC4S',
    'ERVFRD-1',
    'HCG8',
    'C6orf147',
    'INTS4L2',
    'SPDYE6',
    'ST7-OT4',
    'ZNF783',
    // 2015-01-22; No transcripts could be found.
    'TMEM210', // getTranscriptsAndInfo() gets me an HTTP 500.
    'STK26',
    'ZNF75D',
    // 2015-03-05; No transcripts could be found.
    'TMEM56',
    'PRR27',
    'CXCL8',
    'LVRN',
    'ERICH5',
    'NAPRT',
    'POLE3',
    'CYSRT1',
    'TPTE2',
    'WDR72',
    // 2015-03-12; No transcripts could be found.
    'ABHD11',
    'AMN1',
    'APH1A',
    'ATP5L',
    'AVPR2',
    'SMCO1',
    'CDK2AP2',
    'ESR2',
    'FAM230A',
    'GHDC',
    'LYPD8',
    'PRKAR1A',
    'RIPPLY2',
    'SAT1',
    'SBK3',
    'SLC52A2',
    'TMEM134',
    'ZNF625',
    // 2015-03-13; No transcripts could be found.
    'ARL6IP4',
    'C9orf173',
    'C9orf92',
    'IFITM3',
    'MROH7',
    'PPP2R2B',
    'SRSF2',
    'UXT',
    'VCPKMT',
    'DXO',
    'NT5C',
    'PAXBP1',
    'RGS8',
    // 2015-03-16; No transcripts could be found.
    'ADAMTS9-AS2',
    'ALG1L9P',
    'ALMS1P',
    'ANKRD26P1',
    'ANKRD30BL',
    'BANCR',
    'BCRP3',
    'BOK-AS1',
    'C21orf91-OT1',
    'C5orf56',
    'CASC9',
    'CEP170P1',
    'CMAHP',
    'CXorf28',
    'DDX11L2',
    'DGCR10',
    'DIO2-AS1',
    'EFCAB10',
    'FAM27E2',
    'FAM41C',
    'FAM83H-AS1',
    'FAM86JP',
    'GMDS-AS1',
    'GOLGA2P5',
    'GUSBP1',
    'HCCAT5',
    'HCG4',
    'HCG9',
    'HERC2P3',
    'HERC2P7',
    'HLA-F-AS1',
    'HTT-AS',
    'IQCH-AS1',
    'KCNQ1DN',
    'KLHDC9',
    'KRT16P3',
    'SPACA6P',
    'LINC00112',
    'LINC00184',
    'LINC00189',
    'LINC00202-1',
    'LINC00202-2',
    'LINC00238',
    'LINC00239',
    'LINC00240',
    'LINC00254',
    'LINC00290',
    'LINC00293',
    'LINC00310',
    'LINC00317',
    'LINC00324',
    'LINC00326',
    'LINC00333',
    'LINC00379',
    'LINC00421',
    'LINC00424',
    'LINC00443',
    'LINC00446',
    'LINC00467',
    'LINC00476',
    'LINC00491',
    'BMS1P18',
    'LINC00525',
    'LINC00540',
    'LINC00545',
    'LINC00558',
    'LINC00563',
    'LINC00589',
    'LINC00592',
    'LINC00605',
    'LINC00613',
    'LINC00620',
    'LINC00635',
    'LINC00636',
    'TRERNA1',
    'LINC00652',
    'LINC00656',
    'LINC00661',
    'LINC00665',
    'LINC00701',
    'LINC00707',
    'LINC00890',
    'LINC00899',
    'LINC00910',
    'LINC00925',
    'LINC00929',
    'LINC00959',
    'LINC00963',
    'LINC00968',
    'LINC00977',
    'LINC00982',
    'LINC01003',
    'LINC01005',
    'LINC01061',
    'LINC01121',
    'LY86-AS1',
    'MAGI2-AS3',
    'MEG9',
    'MEIS1-AS3',
    'MIR4458HG',
    'MIR4477A',
    'MIRLET7BHG',
    'MLK7-AS1',
    'MST1P2',
    'NACAP1',
    'NPHP3-AS1',
    'PCGEM1',
    'PDXDC2P',
    'PGAM1P5',
    'PRKY',
    'PSORS1C3',
    'RNF126P1',
    'RNF216P1',
    'ROCK1P1',
    'RSU1P2',
    'SDHAP1',
    'SDHAP2',
    'SH3RF3-AS1',
    'SIGLEC16',
    'SMEK3P',
    'SNHG11',
    'SNHG7',
    'SPATA41',
    'SPATA42',
    'SRP14-AS1',
    'SSR4',
    'ST3GAL6-AS1',
    'TDRG1',
    'TEKT4P2',
    'TEX21P',
    'TEX26-AS1',
    'THTPA',
    'TPTEP1',
    'TRIM52-AS1',
    'WASH2P',
    'WASH7P',
    'ZNF252P-AS1',
    'ZNF252P',
    'ZNF525',
    'ZNF667-AS1',
    'ZNF876P',
    'ZNRD1-AS1',
    'MIB2',
    'AKR1E2',
    'C11orf82',
    'CORO1C',
    'PRSS23',
    'RWDD3',
    'SMYD3',
    'C15orf38',
    'CLK3',
    'ELFN2',
    'GNL3L',
    'GOLGA6L4',
    'GPR128',
    'KCTD2',
    'KLK8',
    'KTN1',
    'PFKFB4',
    'POMK',
    'SP9',
    'UQCC1',
    'ZNF112',
    'ZSCAN23',
    'MYL4',
    'OOSP2',
    'PRAC1',
    'TNFSF13',
    'UQCC2',
    'VASH2',
    'ZNF429',
    'ZNF577',
    'GDNF-AS1',
    'HOXA10',
    'TCL1A',
    // 2015-08-??; No transcripts could be found.
    'ARL6',
    'CXCL1',
    'GPER1',
    'MRPL45',
    'SRGAP2C',
    // 2016-02-19; No transcripts could be found.
    'NBPF9',
    // 2016-03-04; No transcripts could be found.
    'ADGRA1',
    'ADGRA2',
    'ADGRE2',
    'ADGRG5',
    'ADGRV1',
    'C1R',
    'CCAR2',
    'CCDC191',
    'CEP126',
    'CEP131',
    'CEP295',
    'CFAP100',
    'CFAP20',
    'CFAP43',
    'CFAP45',
    'CFAP47',
    'CRACR2A',
    'CRACR2B',
    'CRAMP1',
    'DOCK1',
    'EEF2KMT',
    'ERC1',
    'EXOC3-AS1',
    'GAREM2',
    'HEATR9',
    'ICE1',
    'IKZF1',
    'KIF5C',
    'LRRC75A',
    'MIR1-1HG',
    'MTCL1',
    'MUC19',
    'NECTIN1',
    'NWD2',
    'P3H3',
    'PCNX1',
    'PCNX3',
    'PIDD1',
    'PLPP6',
    'POMGNT2',
    'PRELID3A',
    'PRELID3B',
    'PRR35',
    'SHTN1',
    'SLF1',
    'SMIM11A',
    'STKLD1',
    'SUSD6',
    'TMEM247',
    'TMEM94',
    'TYMSOS',
    'USF3',
    'WAPL',
    'ZNF812P',
    'ZPR1',
    // 2016-03-04; No transcripts could be found.
);

// Define list of gene aliases. Genes not mentioned in here, are searched for in the database. If not found,
// HGNC will be queried and gene will be added. If the symbols don't match, we'll get a duplicate key error.
// Insert those genes here.
$aGeneAliases = array(
    // Sort? Keep forever?
    'C1orf63' => 'RSRP1',
    'C1orf170' => 'PERM1',
    'C1orf200' => 'PIK3CD-AS1',
    'FAM5C' => 'BRINP3',
    'HNRNPCP5' => 'HNRNPCL2',
    'SELRC1' => 'COA7',
    'C1orf191' => 'SSBP3-AS1',
    'LINC00568' => 'FALEC',
    'MIR664' => 'MIR664A',
    'C1orf148' => 'IBA57-AS1',
    'AKR1CL1' => 'AKR1C8P',
    'C10orf112' => 'MALRD1',
    'LINC00263' => 'OLMALINC',
    'NEURL' => 'NEURL1',
    'C10orf85' => 'LINC01561',
    'C11orf92' => 'COLCA1',
    'C11orf34' => 'PLET1',
    'FLI1-AS1' => 'SENCR',
    'HOXC-AS5' => 'HOXC13-AS',
    'LINC00277' => 'EWSAT1',
    'C15orf37' => 'ST20-AS1',
    'RPS17L' => 'RPS17',
    'SNORA50' => 'SNORA76A',
    'MIR4520B' => 'MIR4520-2',
    'MIR4520A' => 'MIR4520-1',
    'LSMD1' => 'NAA38',
    'C17orf76-AS1' => 'LRRC75A-AS1',
    'PRAC' => 'PRAC1',
    'HOXB-AS5' => 'PRAC2',
    'KIAA1704' => 'GPALPP1',
    'KIAA1737' => 'CIPC',
    'CNIH' => 'CNIH1',
    'METTL21D' => 'VCPKMT',
    'LINC00984' => 'INAFM2',
    'SNORA76' => 'SNORA76C',
    'FLJ37644' => 'SOX9-AS1',
    'RPL17-C18ORF32' => 'RPL17-C18orf32',
    'CYP2B7P1' => 'CYP2B7P',
    'C19orf69' => 'ERICH4',
    'ZFP112' => 'ZNF112',
    'HCCAT3' => 'ZNF350-AS1',
    'SGK110' => 'SBK3',
    'UNQ6975' => 'LINC01121',
    'FLJ30838' => 'LINC01122',
    'FLJ16341' => 'LINC01185',
    'PCDP1' => 'CFAP221',
    'C2orf62' => 'CATIP',
    'UQCC' => 'UQCC1',
    'C20orf201' => 'LKAAEAR1',
    'MIR3648' => 'MIR3648-1',
    'MIR3687' => 'MIR3687-1',
    'C21orf15' => 'CYP4F29P',
    'LINC00478' => 'MIR99AHG',
    'BRWD1-IT2' => 'BRWD1-AS2',
    'C21orf128' => 'UMODL1-AS1',
    'SETD5-AS1' => 'THUMPD3-AS1',
    'GTDC2' => 'POMGNT2',
    'CT64' => 'LINC01192',
    'HTT-AS1' => 'HTT-AS',
    'FLJ13197' => 'KLF3-AS1',
    'FLJ14186' => 'LINC01061',
    'PHF17' => 'JADE1',
    'PRMT10' => 'PRMT9',
    'CCDC111' => 'PRIMPOL',
    'NIM1' => 'NIM1K',
    'FLJ33630' => 'LINC01184',
    'PHF15' => 'JADE2',
    'C5orf50' => 'SMIM23',
    'MGC39372' => 'SERPINB9P1',
    'LINC00340' => 'CASC15',
    'MIR219-1' => 'MIR219A1',
    'STL' => 'RNF217-AS1',
    'RPA3-AS1' => 'UMAD1',
    'HOXA-AS4' => 'HOXA10-AS',
    'C7orf41' => 'MTURN',
    'FLJ45974' => 'LINC01446',
    'FKBP9L' => 'FKBP9P1',
    'LINC00035' => 'ABHD11-AS1',
    'RSBN1L-AS1' => 'APTR',
    'MKLN1-AS1' => 'LINC-PINT',
    'ZNF767' => 'ZNF767P',
    'KIAA1967' => 'CCAR2',
    'SGK196' => 'POMK',
    'LINC00966' => 'MIR124-2HG',
    'REXO1L1' => 'REXO1L1P',
    'C8orf69' => 'LINC01298',
    'C8orf56' => 'BAALC-AS2',
    'PHF16' => 'JADE3',
    'MST4' => 'STK26',
    'SMCR7L' => 'MIEF1',
    'C4orf40' => 'PRR27',
    'IL8' => 'CXCL8',
    'AQPEP' => 'LVRN',
    'MNF1' => 'UQCC2',
    'GPER' => 'GPER1',
    'C8orf47' => 'ERICH5',
    'NAPRT1' => 'NAPRT',
    'C9orf123' => 'TMEM261',
    'KIAA1984' => 'CCDC183',
    'C9orf169' => 'CYSRT1',
    'C11orf93' => 'COLCA2',
    'C12orf52' => 'RITA1',
    'SMCR7' => 'MIEF2',
    'C3orf37' => 'HMCES',
    'C3orf43' => 'SMCO1',
    'C6orf70' => 'ERMARD',
    'C9orf37' => 'ARRDC1-AS1',
    'CXorf48' => 'CT55',
    'TGIF2-C20ORF24' => 'TGIF2-C20orf24',
    'C13orf45' => 'LMO7DN',
    'C3orf83' => 'MKRN2OS',
    'CXorf61' => 'CT83',
    'CXXC11' => 'RTP5',
    'DOM3Z' => 'DXO',
    'SPATA31A2' => 'SPATA31A1',
    'CT60' => 'LINC01193',
    'FLJ30403' => 'LINC01530',
    'FOLR4' => 'IZUMO1R',
    'GOLGA6L5' => 'GOLGA6L5P',
    'LINC00085' => 'SPACA6P',
    'LINC00516' => 'BMS1P18',
    'LINC00651' => 'TRERNA1',
    'LINC00948' => 'MRLN',
    'NBPF23' => 'NBPF17P',
    'PCDHB17' => 'PCDHB17P',
    'C10orf137' => 'EDRF1',
    'PLAC1L' => 'OOSP2',
    'MKI67IP' => 'NIFK',
    'C19orf82' => 'ZNF561-AS1',
    'SPANXB2' => 'SPANXB1',
    'SCXB' => 'SCX',
    'FAM203B' => 'HGH1',
    'PNMA6C' => 'PNMA6A',
    // 2016-02-19; New aliases.
    'ADC' => 'AZIN2',
    'BAI2' => 'ADGRB2',
    'C1orf172' => 'KDF1',
    'C1orf173' => 'ERICH3',
    'C1orf177' => 'LEXM',
    'C1orf51' => 'CIART',
    'C1orf86' => 'FAAP20',
    'ELTD1' => 'ADGRL4',
    'GLTPD1' => 'CPTP',
    'HIAT1' => 'MFSD14A',
    'KIAA1751' => 'CFAP74',
    'LEPRE1' => 'P3H1',
    'LPHN2' => 'ADGRL2',
    'NBPF16' => 'NBPF15',
    'NRD1' => 'NRDC',
    'PPAP2B' => 'PLPP3',
    'PTCHD2' => 'DISP3',
    'WDR65' => 'CFAP57',
    // 2016-03-04; New aliases.
    'ANKRD32' => 'SLF1',
    'AZI1' => 'CEP131',
    'C16orf11' => 'PRR35',
    'C16orf80' => 'CFAP20',
    'C17orf66' => 'HEATR9',
    'C18orf56' => 'TYMSOS',
    'C20orf166' => 'MIR1-1HG',
    'C5orf55' => 'EXOC3-AS1',
    'C9orf117' => 'CFAP157',
    'C9orf96' => 'STKLD1',
    'CCDC19' => 'CFAP45',
    'CCDC37' => 'CFAP100',
    'CD97' => 'ADGRE5',
    'CRAMP1L' => 'CRAMP1',
    'CXorf30' => 'CFAP47',
    'EFCAB4A' => 'CRACR2B',
    'EFCAB4B' => 'CRACR2A',
    'EMR2' => 'ADGRE2',
    'FAM211A' => 'LRRC75A',
    'FAM86A' => 'EEF2KMT',
    'GAREML' => 'GAREM2',
    'GPR114' => 'ADGRG5',
    'GPR123' => 'ADGRA1',
    'GPR124' => 'ADGRA2',
    'GPR144' => 'ADGRD2',
    'GPR98' => 'ADGRV1',
    'HIATL1' => 'MFSD14B',
    'IGJ' => 'JCHAIN',
    'KIAA0195' => 'TMEM94',
    'KIAA0247' => 'SUSD6',
    'KIAA0947' => 'ICE1',
    'KIAA1239' => 'NWD2',
    'KIAA1377' => 'CEP126',
    'KIAA1407' => 'CCDC191',
    'KIAA1598' => 'SHTN1',
    'KIAA1731' => 'CEP295',
    'KIAA2018' => 'USF3',
    'LEPREL2' => 'P3H3',
    'NARG2' => 'ICE2',
    'PCNXL3' => 'PCNX3',
    'PCNX' => 'PCNX1',
    'PIDD' => 'PIDD1',
    'PPAPDC2' => 'PLPP6',
    'PVRL1' => 'NECTIN1',
    'SLMO1' => 'PRELID3A',
    'SLMO2' => 'PRELID3B',
    'SMIM11' => 'SMIM11A',
    'SOGA2' => 'MTCL1',
    'WAPAL' => 'WAPL',
    'WDR96' => 'CFAP43',
    'ZNF259' => 'ZPR1',
    'ZNF812' => 'ZNF812P',
    // 2016-03-04; New aliases.
);





// Define list of columns that we are recognizing.
$aColumnMappings = array(
    'chromosome' => 'chromosome',
    'position' => 'position', // lovd_getVariantDescription() needs this.
    'QUAL' => 'VariantOnGenome/Sequencing/Quality',
    'FILTERvcf' => 'VariantOnGenome/Sequencing/Filter',
    'GATKCaller' => 'VariantOnGenome/Sequencing/GATKcaller',
    'Feature' => 'transcriptid',
    'GVS' => 'VariantOnTranscript/GVS/Function',
    'CDS_position' => 'VariantOnTranscript/Position',
//    'PolyPhen' => 'VariantOnTranscript/PolyPhen', // We don't use this anymore.
    'HGVSc' => 'VariantOnTranscript/DNA',
    'HGVSp' => 'VariantOnTranscript/Protein',
    'Grantham' => 'VariantOnTranscript/Prediction/Grantham',
    'INDB_COUNT_UG' => 'VariantOnGenome/InhouseDB/Count/UG',
    'INDB_COUNT_HC' => 'VariantOnGenome/InhouseDB/Count/HC',
    'GLOBAL_VN' => 'VariantOnGenome/InhouseDB/Position/Global/Samples_with_coverage',
    'GLOBAL_VF_HET' => 'VariantOnGenome/InhouseDB/Count/Global/Heterozygotes',
    'GLOBAL_VF_HOM' => 'VariantOnGenome/InhouseDB/Count/Global/Homozygotes',
    'WITHIN_PANEL_VN' => 'VariantOnGenome/InhouseDB/Position/InPanel/Samples_with_coverage',
    'WITHIN_PANEL_VF_HET' => 'VariantOnGenome/InhouseDB/Count/InPanel/Heterozygotes',
    'WITHIN_PANEL_VF_HOM' => 'VariantOnGenome/InhouseDB/Count/InPanel/Homozygotes',
    'OUTSIDE_PANEL_VN' => 'VariantOnGenome/InhouseDB/Position/OutOfPanel/Samples_with_coverage',
    'OUTSIDE_PANEL_VF_HET' => 'VariantOnGenome/InhouseDB/Count/OutOfPanel/Heterozygotes',
    'OUTSIDE_PANEL_VF_HOM' => 'VariantOnGenome/InhouseDB/Count/OutOfPanel/Homozygotes',
    'AF1000G' => 'VariantOnGenome/Frequency/1000G',
    'rsID' => 'VariantOnGenome/dbSNP',
    'AFESP5400' => 'VariantOnGenome/Frequency/EVS', // Will be divided by 100 later.
    'AFGONL' => 'VariantOnGenome/Frequency/GoNL',
    'EXAC_AF' => 'VariantOnGenome/Frequency/ExAC',
    'MutationTaster_pred' => 'VariantOnTranscript/Prediction/MutationTaster',
    'MutationTaster_score' => 'VariantOnTranscript/Prediction/MutationTaster/Score',
    'Polyphen2_HDIV_score' => 'VariantOnTranscript/PolyPhen/HDIV',
    'Polyphen2_HVAR_score' => 'VariantOnTranscript/PolyPhen/HVAR',
    'SIFT_score' => 'VariantOnTranscript/Prediction/SIFT',
    'CADD_raw' => 'VariantOnGenome/CADD/Raw',
    'CADD_phred' => 'VariantOnGenome/CADD/Phred',
    'HGMD_association' => 'VariantOnGenome/HGMD/Association',
    'HGMD_reference' => 'VariantOnGenome/HGMD/Reference',
    'phyloP' => 'VariantOnGenome/Conservation_score/PhyloP',
    'scorePhastCons' => 'VariantOnGenome/Conservation_score/Phast',
    'GT_Child' => 'allele',
    'GT_Patient' => 'allele',
    'GQ_Child' => 'VariantOnGenome/Sequencing/GenoType/Quality',
    'GQ_Patient' => 'VariantOnGenome/Sequencing/GenoType/Quality',
    'DP_Child' => 'VariantOnGenome/Sequencing/Depth/Total',
    'DP_Patient' => 'VariantOnGenome/Sequencing/Depth/Total',
    'DPREF_Child' => 'VariantOnGenome/Sequencing/Depth/Ref',
    'DPREF_Patient' => 'VariantOnGenome/Sequencing/Depth/Ref',
    'DPALT_Child' => 'VariantOnGenome/Sequencing/Depth/Alt',
    'DPALT_Patient' => 'VariantOnGenome/Sequencing/Depth/Alt',
    'ALTPERC_Child' => 'VariantOnGenome/Sequencing/Depth/Alt/Fraction', // Will be divided by 100 later.
    'ALTPERC_Patient' => 'VariantOnGenome/Sequencing/Depth/Alt/Fraction', // Will be divided by 100 later.
    'GT_Father' => 'VariantOnGenome/Sequencing/Father/GenoType',
    'GQ_Father' => 'VariantOnGenome/Sequencing/Father/GenoType/Quality',
    'DP_Father' => 'VariantOnGenome/Sequencing/Father/Depth/Total',
    'ALTPERC_Father' => 'VariantOnGenome/Sequencing/Father/Depth/Alt/Fraction', // Will be divided by 100 later.
    'ISPRESENT_Father' => 'VariantOnGenome/Sequencing/Father/VarPresent',
    'GT_Mother' => 'VariantOnGenome/Sequencing/Mother/GenoType',
    'GQ_Mother' => 'VariantOnGenome/Sequencing/Mother/GenoType/Quality',
    'DP_Mother' => 'VariantOnGenome/Sequencing/Mother/Depth/Total',
    'ALTPERC_Mother' => 'VariantOnGenome/Sequencing/Mother/Depth/Alt/Fraction', // Will be divided by 100 later.
    'ISPRESENT_Mother' => 'VariantOnGenome/Sequencing/Mother/VarPresent',
//    '' => '',
//    'distanceToSplice' => 'VariantOnTranscript/Distance_to_splice_site',
);
// These columns will be taken out of $aVariant and stored as the VOG data.
// This array is also used to build the LOVD file.
$aColumnsForVOG = array(
    'id',
    'allele',
    'effectid',
    'chromosome',
    'position_g_start',
    'position_g_end',
    'type',
    'mapping_flags',
    'average_frequency',
    'owned_by',
    'statusid',
    'created_by',
    'created_date',
    'edited_by',
    'edited_date',
    'VariantOnGenome/DBID',
);
// These columns will be taken out of $aVariant and stored as the VOT data.
// This array is also used to build the LOVD file.
$aColumnsForVOT = array(
    'id',
    'transcriptid',
    'effectid',
    'position_c_start',
    'position_c_start_intron',
    'position_c_end',
    'position_c_end_intron',
);
// Default values.
$aDefaultValues = array(
    'effectid' => $_SETT['var_effect_default'],
    'mapping_flags' => '0',
//    'owned_by' => 0, // '0' is not a valid value, because "LOVD" is removed from the selection list. When left empty, it will default to the user running LOVD, though.
    'statusid' => STATUS_HIDDEN,
    'created_by' => 0,
    'created_date' => date('Y-m-d H:i:s'),
);







$nFilesBeingMerged = 0; // We're counting how many files are being merged at the time, because we don't want to stress the system too much.
$nMaxFilesBeingMerged = 5; // We're allowing only five processes working concurrently on merging files (or so many failed attempts that have not been cleaned up).
$aFiles = array(); // array(ID => array(files), ...);





function lovd_getVariantDescription (&$aVariant, $sRef, $sAlt)
{
    // Constructs a variant description from $sRef and $sAlt and adds it to $aVariant in a new 'VariantOnGenome/DNA' key.
    // The 'position_g_start' and 'position_g_end' keys in $aVariant are adjusted accordingly and a 'type' key is added too.
    // The numbering scheme is either g. or m. and depends on the 'chromosome' key in $aVariant.

    // Make all bases uppercase.
    $sRef = strtoupper($sRef);
    $sAlt = strtoupper($sAlt);

    // Use the right prefix for the numbering scheme.
    $sHGVSPrefix = 'g.';
    if ($aVariant['chromosome'] == 'M') {
        $sHGVSPrefix = 'm.';
    }

    // Even substitutions are sometimes mentioned as longer Refs and Alts, so we'll always need to isolate the actual difference.
    $aVariant['position_g_start'] = $aVariant['position'];
    $aVariant['position_g_end'] = $aVariant['position'] + strlen($sRef) - 1;

    // 'Eat' letters from either end - first left, then right - to isolate the difference.
    $sAltOriginal = $sAlt;
    while (strlen($sRef) > 0 && strlen($sAlt) > 0 && $sRef{0} == $sAlt{0}) {
        $sRef = substr($sRef, 1);
        $sAlt = substr($sAlt, 1);
        $aVariant['position_g_start'] ++;
    }
    while (strlen($sRef) > 0 && strlen($sAlt) > 0 && $sRef[strlen($sRef) - 1] == $sAlt[strlen($sAlt) - 1]) {
        $sRef = substr($sRef, 0, -1);
        $sAlt = substr($sAlt, 0, -1);
        $aVariant['position_g_end'] --;
    }

    // Substitution, or something else?
    if (strlen($sRef) == 1 && strlen($sAlt) == 1) {
        // Substitutions.
        $aVariant['type'] = 'subst';
        $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . $sRef . '>' . $sAlt;
    } else {
        // Insertions/duplications, deletions, inversions, indels.

        // Now find out the variant type.
        if (strlen($sRef) > 0 && strlen($sAlt) == 0) {
            // Deletion.
            $aVariant['type'] = 'del';
            if ($aVariant['position_g_start'] == $aVariant['position_g_end']) {
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . 'del';
            } else {
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'del';
            }
        } elseif (strlen($sAlt) > 0 && strlen($sRef) == 0) {
            // Something has been added... could be an insertion or a duplication.
            if (substr($sAltOriginal, strrpos($sAltOriginal, $sAlt) - strlen($sAlt), strlen($sAlt)) == $sAlt) {
                // Duplicaton.
                $aVariant['type'] = 'dup';
                $aVariant['position_g_start'] -= strlen($sAlt);
                if ($aVariant['position_g_start'] == $aVariant['position_g_end']) {
                    $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . 'dup';
                } else {
                    $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'dup';
                }
            } else {
                // Insertion.
                $aVariant['type'] = 'ins';
                // Exchange g_start and g_end; after the 'letter eating' we did, start is actually end + 1!
                $aVariant['position_g_start'] --;
                $aVariant['position_g_end'] ++;
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'ins' . $sAlt;
            }
        } elseif ($sRef == strrev(str_replace(array('a', 'c', 'g', 't'), array('T', 'G', 'C', 'A'), strtolower($sAlt)))) {
            // Inversion.
            $aVariant['type'] = 'inv';
            $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'inv';
        } else {
            // Deletion/insertion.
            $aVariant['type'] = 'delins';
            if ($aVariant['position_g_start'] == $aVariant['position_g_end']) {
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . 'delins' . $sAlt;
            } else {
                $aVariant['VariantOnGenome/DNA'] = $sHGVSPrefix . $aVariant['position_g_start'] . '_' . $aVariant['position_g_end'] . 'delins' . $sAlt;
            }
        }
    }
}





function lovd_getVariantPosition ($sVariant, $aTranscript = array())
{
    // Constructs an array with the position fields 'start', 'start_intron', 'end', 'end_intron', from the variant description.
    // Whether the input is chromosomal or transcriptome positions, doesn't matter.

    $aReturn = array(
        'start' => 0,
        'start_intron' => 0,
        'end' => 0,
        'end_intron' => 0,
    );

    if (preg_match('/^[cgmn]\.((?:\-|\*)?\d+)([-+]\d+)?(?:[ACGT]>[ACGT]|(?:_((?:\-|\*)?\d+)([-+]\d+)?)?(?:d(?:el(?:ins)?|up)|inv|ins)(?:[ACGT])*|\[[0-9]+\](?:[ACGT]+)?)$/', $sVariant, $aRegs)) {
        foreach (array(1, 3) as $i) {
            if (isset($aRegs[$i]) && $aRegs[$i]{0} == '*') {
                // Position in 3'UTR. Add CDS offset.
                if ($aTranscript && isset($aTranscript['position_c_cds_end'])) {
                    $aRegs[$i] = (int) substr($aRegs[$i], 1) + $aTranscript['position_c_cds_end'];
                } else {
                    // Whatever we'll do, it will be wrong anyway.
                    return $aReturn;
                }
            }
        }

        $aReturn['start'] = (int) $aRegs[1];
        if (isset($aRegs[2]) && $aRegs[2]) {
            $aReturn['start_intron'] = (int) $aRegs[2]; // (int) to get rid of the '+' if it's there.
        }
        if (isset($aRegs[4]) && $aRegs[4]) {
            $aReturn['end_intron'] = (int) $aRegs[4]; // (int) to get rid of the '+' if it's there.
        }
        if (isset($aRegs[3])) {
            $aReturn['end'] = (int) $aRegs[3];
        } else {
            $aReturn['end'] = $aReturn['start'];
            $aReturn['end_intron'] = $aReturn['start_intron'];
        }
    }

    return $aReturn;
}





// Loop through the files in the dir and try and find a meta and data file, that match but have no total data file.
$h = opendir($_INI['paths']['data_files']);
if (!$h) {
    die('Can\'t open directory.' . "\n");
}
while (($sFile = readdir($h)) !== false) {
    if ($sFile{0} == '.') {
        // Current dir, parent dir, and hidden files.
        continue;
    }

    if (preg_match('/^((?:Child|Patient)_(?:\d+))\.(' . implode('|', array_map('preg_quote', array_values($aSuffixes))) . ')$/', $sFile, $aRegs)) {
        //             1                            2
        // Files we need to merge.
        list(, $sID, $sFileType) = $aRegs;
        if (!isset($aFiles[$sID])) {
            $aFiles[$sID] = array();
        }
        $aFiles[$sID][] = $sFileType;
    }
}

// Die here, if we have nothing to work with.
if (!$aFiles) {
    die('No files found.' . "\n");
}

// Filter the list of files, to see which ones are already complete.
foreach ($aFiles as $sID => $aFileTypes) {
    if (in_array($aSuffixes['total'], $aFileTypes)) {
        // Already merged.
        unset($aFiles[$sID]);
        continue;
    }
}

// Die here, if we have nothing to do anymore.
if (!$aFiles) {
    die('No files found available for merging.' . "\n");
}

// Report incomplete data sets; meta data without variant data, for instance, and data sets still running (maybe split that, if this happens more often).
foreach ($aFiles as $sID => $aFileTypes) {
    if (!in_array($aSuffixes['meta'], $aFileTypes)) {
        // No meta data.
        unset($aFiles[$sID]);
        print('Meta data missing: ' . $sID . "\n");
    }
    if (!in_array($aSuffixes['vep'], $aFileTypes)) {
        // No variant data.
        unset($aFiles[$sID]);
        print('VEP data missing: ' . $sID . "\n");
    }
    if (in_array($aSuffixes['total.tmp'], $aFileTypes)) {
        // Already working on a merge. We count these, because we don't want too many processes in parallel.
        // FIXME: Should we check the timestamp on the file? Remove really old files, so we can continue?
        $nFilesBeingMerged ++;
        unset($aFiles[$sID]);
        print('Already being merged: ' . $sID . "\n");
    }
}

// Report what we have left.
$nFiles = count($aFiles);
if (!$nFiles) {
    die('No files left to merge.' . "\n");
} else {
    print(str_repeat('-', 60) . "\n" . $nFiles . ' patient' . ($nFiles == 1? '' : 's') . ' with data files ready to be merged.' . "\n");
}

// But don't run, if too many are still active...
if ($nFilesBeingMerged >= $nMaxFilesBeingMerged) {
    die('Too many files being merged at the same time, stopping here.' . "\n");
}





// We're simply taking the first one, with the lowest ID (or actually, alphabetically the lowest ID, since we have the Child|Patient prefix.
// To make sure that we don't hang if one file is messed up, we'll start parsing them one by one, and the first one with an OK header, we take.
$aFiles = array_keys($aFiles);
sort($aFiles);
define('LOG_EVENT', 'ConvertVEPToLOVD');
require ROOT_PATH . 'inc-lib-actions.php';
flush();
@ob_end_flush(); // Can generate errors on the screen if no buffer found.
foreach ($aFiles as $sID) {
    // Try and open the file, check the first line if it conforms to the standard, and start converting.
    print('Working on: ' . $sID . "...\n");
    flush();
    $sFileToConvert = $_INI['paths']['data_files'] . '/' . $sID . '.' . $aSuffixes['vep'];
    $sFileMeta = $_INI['paths']['data_files'] . '/' . $sID . '.' . $aSuffixes['meta'];
    $sFileTmp = $_INI['paths']['data_files'] . '/' . $sID . '.' . $aSuffixes['total.tmp'];
    $sFileDone = $_INI['paths']['data_files'] . '/' . $sID . '.' . $aSuffixes['total'];

    $fInput = fopen($sFileToConvert, 'r');
    if ($fInput === false) {
        die('Error opening file: ' . $sFileToConvert . ".\n");
    }

    $sHeaders = fgets($fInput);
    if (substr($sHeaders, 0, 53) != "chromosome\tposition\tREF\tALT\tQUAL\tFILTERvcf\tGATKCaller") {
        // Not a fatal error, because otherwise we can't import anything anymore if this ever happens...
        print('Ignoring file, does not conform to format: ' . $sFileToConvert . ".\n");
        continue; // Continue to try the next file.
    }

    // Start creating the output file, based on the meta file. We just add the analysis_status, so the analysis can start directly after importing.
    $aFileMeta = file($sFileMeta, FILE_IGNORE_NEW_LINES);
    foreach ($aFileMeta as $nLine => $sLine) {
        if (strpos($sLine, '{{Screening/') !== false) {
            $aFileMeta[$nLine]   .= "\t\"{{analysis_statusid}}\"";
            $aFileMeta[$nLine+1] .= "\t\"" . ANALYSIS_STATUS_READY . '"';
            break;
        }
    }
    $fOutput = @fopen($sFileTmp, 'w');
    if (!$fOutput || !fputs($fOutput, implode("\r\n", $aFileMeta))) {
        print('Error copying meta file to target: ' . $sFileTmp . ".\n");
        fclose($fOutput);
        continue; // Continue to try the next file.
    }
    fclose($fOutput);

    // Isolate the used Screening ID, so we'll connect the variants to the right ID.
    // It could just be 1 always, but they use the Miracle ID.
    // FIXME: This is quite a lot of code, for something simple as that... Can't we do this in an easier way? More assumptions, less checks?
    $nScreeningID = 0;
    $aMetaData = file($sFileTmp, FILE_IGNORE_NEW_LINES);
    if (!$aMetaData) {
        print('Error reading out temporary output file: ' . $sFileTmp . ".\n");
        unlink($sFileTmp);
        continue; // Continue to try the next file.
    }
    $bParseColumns = false;
    $nColumnIndexIDMiracle = false;
    $nColumnIndexIDScreening = false;
    $nScreeningID = 0;
    $nMiracleID = 0;
    foreach ($aMetaData as $nLine => $sLine) {
        if (!trim($sLine)) {
            continue;
        }
        $nLine ++;
        if (!$bParseColumns) {
            if (substr($sLine, 0, 17) == '## Individuals ##') {
                $bParseColumns = 'Individuals';
            } elseif (substr($sLine, 0, 16) == '## Screenings ##') {
                $bParseColumns = 'Screenings';
            }
        } else {
            if ($nColumnIndexIDMiracle === false && $nColumnIndexIDScreening === false) {
                // We are expecting columns now, because we just started a new section.
                if (!preg_match('/^(("\{\{[A-Za-z0-9_\/]+\}\}"|\{\{[A-Za-z0-9_\/]+\}\})\t)+$/', $sLine . "\t")) { // FIXME: Can we make this a simpler regexp?
                    // Columns not found; either we have data without a column header, or a malformed column header. Abort import.
                    print('Error while parsing meta file (line ' . $nLine . '): Expected column header, but got something else.' . "\n");
                    continue 2; // Continue to try the next file.
                }

                $aColumns = explode("\t", $sLine);
                $nColumns = count($aColumns);
                $aColumns = array_map('trim', $aColumns, array_fill(0, $nColumns, '"{ }'));
                if ($bParseColumns == 'Individuals' && $nColumnIndexIDMiracle === false) {
                    $nColumnIndexIDMiracle = array_search('id_miracle', $aColumns);
                } elseif ($bParseColumns == 'Screenings') {
                    $nColumnIndexIDScreening = array_search('id', $aColumns);
                }
                if ($nColumnIndexIDScreening === false && $nColumnIndexIDMiracle === false) {
                    print('Error while parsing meta file (line ' . $nLine . '): Expected ID column header, could not find it.' . "\n");
                    continue 2; // Continue to try the next file.
                }
                continue; // Data is on the next line.

            } else {
                // We've got a line of data here. Isolate the values.
                $aLine = explode("\t", rtrim($sLine, "\r\n"));
                // For any category, the number of columns should be the same as the number of fields.
                // However, less fields may be encountered because the spreadsheet program just put tabs and no quotes in empty fields.
                if (count($aLine) < $nColumns) {
                    $aLine = array_pad($aLine, $nColumns, '');
                }
                if ($nColumnIndexIDMiracle !== false) {
                    $nMiracleID = trim($aLine[$nColumnIndexIDMiracle], '"');
                    $nColumnIndexIDMiracle = false;
                } elseif ($nColumnIndexIDScreening !== false) {
                    $nScreeningID = trim($aLine[$nColumnIndexIDScreening], '"');
                    $nColumnIndexIDScreening = false;
                }
                $bParseColumns = false;
                if ($nMiracleID && $nScreeningID) {
                    break;
                }
            }
        }
    }
    if (!$nScreeningID || !$nMiracleID) {
        print('Error while parsing meta file: Unable to find the Screening ID and/or Miracle ID.' . "\n");
        // Here, we won't try and remove the temp file. We need it for diagnostics, and it will save us from running into the same error over and over again.
        continue; // Continue to try the next file.
    }
    $nScreeningID = sprintf('%010d', $nScreeningID);
    print('Isolated Screening ID: ' . $nScreeningID . "...\n");
    flush();





    // Now open and parse the file for real, appending to the temporary file.
    // It's usually a big file, and we don't want to use too much memory... so using fgets().
    // First line should be headers, we already read it out somewhere above here.
    $aHeaders = explode("\t", rtrim($sHeaders, "\r\n"));
    // $aHeaders = array_map('trim', $aHeaders, array_fill(0, count($aHeaders), '"')); // In case we ever need to trim off quotes.
    $nHeaders = count($aHeaders);

    // Verify the identity of this file. Some columns are appended by the Miracle ID.
    // Check the child's Miracle ID with that we have in the meta data file, and remove all the IDs so the headers are recognized normally.
    foreach ($aHeaders as $key => $sHeader) {
        if (preg_match('/(Child|Patient|Father|Mother)_(\d+)$/', $sHeader, $aRegs)) {
            // If Child, check ID.
            if ($nMiracleID && in_array($aRegs[1], array('Child', 'Patient')) && $aRegs[2] != $nMiracleID) {
                // Here, we won't try and remove the temp file. We need it for diagnostics, and it will save us from running into the same error over and over again.
                die('Fatal: Miracle ID of ' . $aRegs[1] . ' (' . $aRegs[2] . ') does not match that from the meta file (' . $nMiracleID . ')' . "\n");
            }
            // Clean ID from column.
            $aHeaders[$key] = substr($sHeader, 0, -(strlen($aRegs[2])+1));
        }
    }

    // Now start parsing the file, reading it out line by line, building up the variant data in $aData.
    $dStart = time();
    $aMutalyzerCalls = array(
        'getTranscriptsAndInfo' => 0,
        'numberConversion' => 0,
        'runMutalyzer' => 0,
    );
    $tMutalyzerCalls = 0; // Time spent doing Mutalyzer calls.
    $aData = array(); // 'chr1:1234567C>G' => array(array(genomic_data), array(transcript1), array(transcript2), ...)
    print('Parsing file. Current time: ' . date('Y-m-d H:i:s') . ".\n");
    flush();

    $nLine = 0;
    $sLastChromosome = '';
    while ($sLine = fgets($fInput)) {
        $nLine ++;
        if (!trim($sLine)) {
            continue;
        }

        // We've got a line of data here. Isolate the values.
        $aLine = explode("\t", rtrim($sLine, "\r\n"));
        // The number of columns should be the same as the number of fields.
        // However, less fields may be encountered, if the last fields were empty.
        if (count($aLine) < $nHeaders) {
            $aLine = array_pad($aLine, $nHeaders, '');
        }
        $aLine = array_combine($aHeaders, $aLine);
        $aVariant = array(); // Will contain the mapped, possibly modified, data.
        // $aLine = array_map('trim', $aLine, array_fill(0, count($aLine), '"')); // In case we ever need to trim off quotes.

        // VCF 4.2 can contain lines with an ALT allele of "*", indicating the allele is
        //  not WT at this position, but affected by an earlier mentioned variant instead.
        // Because these are not actually variants, we ignore them.
        if ($aLine['ALT'] == '*') {
            continue;
        }

        // When seeing a new chromosome, reset these variables. We don't want them too big; it's useless and takes up a lot of memory.
        if ($sLastChromosome != $aLine['chromosome']) {
            $sLastChromosome = $aLine['chromosome'];
            $aGenes = array(); // GENE => array(<gene_info_from_database>)
            $aTranscripts = array(); // NM_000001.1 => array(<transcript_info>)
            $aMappings = array(); // chrX:g.123456del => array(NM_000001.1 => 'c.123del', ...); // To prevent us from running numberConversion too many times.
        }

        // Map VEP columns to LOVD columns.
        foreach ($aColumnMappings as $sVEPColumn => $sLOVDColumn) {
            // 2015-10-28; But don't let columns overwrite each other! Problem because we have double mappings; two MAGPIE columns pointing to the same LOVD column.
            if (!isset($aLine[$sVEPColumn]) && isset($aVariant[$sLOVDColumn])) {
                // VEP column doesn't actually exist in the file, but we do already have created the column in the $aVariant array...
                // Never mind then!
                continue;
            }
            if (empty($aLine[$sVEPColumn]) || $aLine[$sVEPColumn] == 'unknown' || $aLine[$sVEPColumn] == '.') {
                $aVariant[$sLOVDColumn] = '';
            } else {
                $aVariant[$sLOVDColumn] = $aLine[$sVEPColumn];
            }
        }

        // Now "fix" certain values.
        // First, VOG fields.
        // Allele.
        if ($aVariant['allele'] == '1/1') {
            $aVariant['allele'] = 3; // Homozygous.
        } elseif (isset($aVariant['VariantOnGenome/Sequencing/Father/VarPresent']) && isset($aVariant['VariantOnGenome/Sequencing/Mother/VarPresent'])) {
            if ($aVariant['VariantOnGenome/Sequencing/Father/VarPresent'] >= 5 && $aVariant['VariantOnGenome/Sequencing/Mother/VarPresent'] <= 3) {
                // From father, inferred.
                $aVariant['allele'] = 10;
            } elseif ($aVariant['VariantOnGenome/Sequencing/Mother/VarPresent'] >= 5 && $aVariant['VariantOnGenome/Sequencing/Father/VarPresent'] <= 3) {
                // From mother, inferred.
                $aVariant['allele'] = 20;
            } else {
                $aVariant['allele'] = 0;
            }
        } else {
            $aVariant['allele'] = 0;
        }
        // Chromosome.
        $aVariant['chromosome'] = substr($aVariant['chromosome'], 3); // chr1 -> 1
        // VOG/DNA and the position fields.
        lovd_getVariantDescription($aVariant, $aLine['REF'], $aLine['ALT']);
        // dbSNP.
        if ($aVariant['VariantOnGenome/dbSNP'] && strpos($aVariant['VariantOnGenome/dbSNP'], ';') !== false) {
            // Sometimes we get two dbSNP IDs. Store the first one, only.
            $aDbSNP = explode(';', $aVariant['VariantOnGenome/dbSNP']);
            $aVariant['VariantOnGenome/dbSNP'] = $aDbSNP[0];
        } elseif (!$aVariant['VariantOnGenome/dbSNP'] && $aLine['Existing_variation'] && $aLine['Existing_variation'] != 'unknown') {
            $aIDs = explode('&', $aLine['Existing_variation']);
            foreach ($aIDs as $sID) {
                if (substr($sID, 0, 2) == 'rs') {
                    $aVariant['VariantOnGenome/dbSNP'] = $sID;
                    break;
                }
            }
        }
        // Fixing some other VOG fields.
        foreach (array('VariantOnGenome/Sequencing/Father/GenoType', 'VariantOnGenome/Sequencing/Father/GenoType/Quality', 'VariantOnGenome/Sequencing/Mother/GenoType', 'VariantOnGenome/Sequencing/Mother/GenoType/Quality') as $sCol) {
            if (!empty($aVariant[$sCol]) && $aVariant[$sCol] == 'None') {
                $aVariant[$sCol] = '';
            }
        }

        // Some percentages we get need to be turned into decimals before it can be stored.
        // 2015-10-28; Because of the double column mappings, we ended up with values divided twice.
        // Flipping the array makes sure we get rid of double mappings.
        foreach (array_flip($aColumnMappings) as $sLOVDColumn => $sVEPColumn) {
            if ($sVEPColumn == 'AFESP5400' || strpos($sVEPColumn, 'ALTPERC_') === 0) {
                $aVariant[$sLOVDColumn] /= 100;
            }
        }

        // Now, VOT fields.
        // Find gene && transcript in database. When not found, try to create it. Otherwise, throw a fatal error.
        // Trusting the gene symbol information from VEP is by far the easiest method, and the fastest. This can fail, therefore we also created an alias list.
        if (isset($aGeneAliases[$aLine['SYMBOL']])) {
            $aLine['SYMBOL'] = $aGeneAliases[$aLine['SYMBOL']];
        }
        // Get gene information. LOC* genes always fail here, so those we don't try.
        if (!isset($aGenes[$aLine['SYMBOL']]) && !in_array($aLine['SYMBOL'], $aGenesToIgnore) && !preg_match('/^LOC[0-9]+$/', $aLine['SYMBOL'])) {
            // First try to get this gene from the database.
            // FIXME: This is duplicated code. Make it into a function, perhaps?
            if ($aGene = $_DB->query('SELECT g.id, g.refseq_UD, g.name FROM ' . TABLE_GENES . ' AS g WHERE g.id = ?', array($aLine['SYMBOL']))->fetchAssoc()) {
                // We've got it in the database.
                // Sometimes, we don't have an UD there. It happens, because now and then we manually created genes and transcripts.
                if (!$aGene['refseq_UD']) {
                    $aGene['refseq_UD'] = lovd_getUDForGene($_CONF['refseq_build'], $aGene['id']);
                }
                if ($aGene['refseq_UD']) {
                    // Silent error if not found. We were already like this. But we'll ignore the gene.
                    $aGenes[$aLine['SYMBOL']] = array_merge($aGene, array('transcripts_in_UD' => array()));
                }
            } else {
print('Loading gene information for ' . $aLine['SYMBOL'] . '...' . "\n");
                // Getting all gene information from the HGNC takes a few seconds.
                $aGeneInfo = lovd_getGeneInfoFromHGNC($aLine['SYMBOL'], true);
                if (!$aGeneInfo) {
                    // We can't gene information from the HGNC, so we can't add them.
                    // This is a major problem and we can't just continue.
//                    die('Gene ' . $aLine['SYMBOL'] . ' can\'t be identified by the HGNC.' . "\n\n");
print('Gene ' . $aLine['SYMBOL'] . ' can\'t be identified by the HGNC.' . "\n");
                }

                // Detect alias. We should store these, for next run (which will crash on a duplicate key error).
                if ($aGeneInfo && $aLine['SYMBOL'] != $aGeneInfo['symbol']) {
                    print('\'' . $aLine['SYMBOL'] . '\' => \'' . $aGeneInfo['symbol'] . '\',' . "\n");
                    // In fact, let's try not to die if we know we'll die.
                    // FIXME: This is duplicated code. Make it into a function, perhaps?
                    if ($aGene = $_DB->query('SELECT g.id, g.refseq_UD, g.name FROM ' . TABLE_GENES . ' AS g WHERE g.id = ?', array($aGeneInfo['symbol']))->fetchAssoc()) {
                        // We've got the alias already in the database.
                        $aGenes[$aLine['SYMBOL']] = array_merge($aGene, array('transcripts_in_UD' => array()));
                    }
                }

                if ($aGeneInfo && !isset($aGenes[$aLine['SYMBOL']])) {
                    $sRefseqUD = lovd_getUDForGene($_CONF['refseq_build'], $aGeneInfo['symbol']);
                    if (!$sRefseqUD) {
//                        die('Can\'t load UD for gene ' . $aLine['SYMBOL'] . '.' . "\n");
print('Can\'t load UD for gene ' . $aGeneInfo['symbol'] . '.' . "\n");
                    }

                    // Not getting an UD no longer kills the script, so...
                    if ($sRefseqUD) {
                        if (!$_DB->query('INSERT INTO ' . TABLE_GENES . ' (id, name, chromosome, chrom_band, refseq_genomic, refseq_UD, id_hgnc, id_entrez, id_omim, created_by, created_date, updated_by, updated_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())',
                            array($aGeneInfo['symbol'], $aGeneInfo['name'], $aGeneInfo['chromosome'], $aGeneInfo['chrom_band'], $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aGeneInfo['chromosome']], $sRefseqUD, $aGeneInfo['hgnc_id'], $aGeneInfo['entrez_id'], $aGeneInfo['omim_id'], 0, 0))) {
                            die('Can\'t create gene ' . $aLine['SYMBOL'] . '.' . "\n");
                        }

                        // Add the default custom columns to this gene.
                        lovd_addAllDefaultCustomColumns('gene', $aGeneInfo['symbol']);

                        // Write to log...
                        lovd_writeLog('Event', LOG_EVENT, 'Created gene information entry ' . $aGeneInfo['symbol'] . ' (' . $aGeneInfo['name'] . ')');
                        print('Created gene ' . $aGeneInfo['symbol'] . ".\n");
                        flush();

                        // Store this gene.
                        $aGenes[$aLine['SYMBOL']] = array('id' => $aGeneInfo['symbol'], 'refseq_UD' => $sRefseqUD, 'name' => $aGeneInfo['name'], 'transcripts_in_UD' => array());
                    }
                }
            }
        }



        // Store transcript ID without version, we'll use it plenty of times.
        $aLine['transcript_noversion'] = substr($aVariant['transcriptid'], 0, strpos($aVariant['transcriptid'] . '.', '.')+1);
        if (!isset($aGenes[$aLine['SYMBOL']]) || !$aGenes[$aLine['SYMBOL']]) {
            // We really couldn't do anything with this gene (now, or last time).
            $aGenes[$aLine['SYMBOL']] = false;

        } elseif (!empty($aLine['Feature']) && !isset($aTranscripts[$aLine['Feature']])) {
            // Gene found, transcript given but not yet seen before. Get transcript information.
            // First try to get this transcript from the database, ignoring (but preferring) version.
            if ($aTranscript = $_DB->query('SELECT id, geneid, id_mutalyzer, id_ncbi, position_c_cds_end, position_g_mrna_start, position_g_mrna_end FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi LIKE ? ORDER BY (id_ncbi = ?) DESC, id DESC LIMIT 1', array($aLine['transcript_noversion'] . '%', $aVariant['transcriptid']))->fetchAssoc()) {
                // We've got it in the database.
                $aTranscripts[$aLine['Feature']] = $aTranscript;

            } elseif ($aGenes[$aLine['SYMBOL']]['refseq_UD']) {
                // To prevent us from having to check the available transcripts all the time, we store the available transcripts, but only insert those we need.
                if ($aGenes[$aLine['SYMBOL']]['transcripts_in_UD']) {
                    $aTranscriptInfo = $aGenes[$aLine['SYMBOL']]['transcripts_in_UD'];

                } else {
print('Loading transcript information for ' . $aGenes[$aLine['SYMBOL']]['id'] . '...' . "\n");

                    $aTranscriptInfo = array();
                    $aMutalyzerCalls['getTranscriptsAndInfo'] ++;
                    $tMutalyzerStart = microtime(true);
                    $sJSONResponse = file_get_contents('https://mutalyzer.nl/json/getTranscriptsAndInfo?genomicReference=' . $aGenes[$aLine['SYMBOL']]['refseq_UD'] . '&geneName=' . $aGenes[$aLine['SYMBOL']]['id']);
                    $tMutalyzerCalls += (microtime(true) - $tMutalyzerStart);
                    if ($sJSONResponse && $aResponse = json_decode($sJSONResponse, true)) {
                        // Before we had to go two layers deep; through the result, then read out the info.
                        // But now apparently this service just returns the string with quotes (the latter are removed by json_decode()).
                        $aTranscriptInfo = $aResponse;
                    }
                    if (empty($aTranscriptInfo)) {
//                        die('Can\'t load available transcripts for gene ' . $aLine['SYMBOL'] . '.' . "\n");
//print('Can\'t load available transcripts for gene ' . $aLine['SYMBOL'] . '.' . "\n");
print('No available transcripts for gene ' . $aGenes[$aLine['SYMBOL']]['id'] . ' found.' . "\n"); // Usually this is the case. Not always an error. We might get an error, but that will show now.
$aTranscripts[$aLine['Feature']] = false; // Ignore transcript.
$aTranscriptInfo = array(array('id' => 'NO_TRANSCRIPTS')); // Basically, any text will do. Just stop searching for other transcripts for this gene.
                    }
                    // Store for next time.
                    $aGenes[$aLine['SYMBOL']]['transcripts_in_UD'] = $aTranscriptInfo;
                }

                // Loop transcript options, add the one we need.
                foreach($aTranscriptInfo as $aTranscript) {
                    // Comparison is made without looking at version numbers!
                    if (substr($aTranscript['id'], 0, strpos($aTranscript['id'] . '.', '.')+1) == $aLine['transcript_noversion']) {
                        // Store in database, prepare values.
                        $sTranscriptName = str_replace($aGenes[$aLine['SYMBOL']]['name'] . ', ', '', $aTranscript['product']);
                        $aTranscript['id_mutalyzer'] = str_replace($aGenes[$aLine['SYMBOL']]['id'] . '_v', '', $aTranscript['name']);
                        $aTranscript['id_ncbi'] = $aTranscript['id'];
                        $sTranscriptProtein = (!isset($aTranscript['proteinTranscript']['id'])? '' : $aTranscript['proteinTranscript']['id']);
                        $aTranscript['position_c_cds_end'] = $aTranscript['cCDSStop']; // To calculate VOT variant position, if in 3'UTR.

                        // Add transcript to gene.
                        if (!$_DB->query('INSERT INTO ' . TABLE_TRANSCRIPTS . ' (id, geneid, name, id_mutalyzer, id_ncbi, id_ensembl, id_protein_ncbi, id_protein_ensembl, id_protein_uniprot, position_c_mrna_start, position_c_mrna_end, position_c_cds_end, position_g_mrna_start, position_g_mrna_end, created_date, created_by) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
                            array($aGenes[$aLine['SYMBOL']]['id'], $sTranscriptName, $aTranscript['id_mutalyzer'], $aTranscript['id_ncbi'], '', $sTranscriptProtein, '', '', $aTranscript['cTransStart'], $aTranscript['sortableTransEnd'], $aTranscript['cCDSStop'], $aTranscript['chromTransStart'], $aTranscript['chromTransEnd'], 0))) {
                            die('Can\'t create transcript ' . $aTranscript['id_ncbi'] . ' for gene ' . $aLine['SYMBOL'] . '.' . "\n");
                        }

                        // Write to log...
                        lovd_writeLog('Event', LOG_EVENT, 'Transcript entry successfully added to gene ' . $aGenes[$aLine['SYMBOL']]['id'] . ' - ' . $sTranscriptName);
                        print('Created transcript ' . $aTranscript['id'] . ".\n");
                        flush();

                        // Store in memory.
                        $aTranscripts[$aLine['Feature']] = array_merge($aTranscript, array('id' => $_DB->lastInsertId())); // Contains a lot more info than needed, but whatever.
                    }
                }

                if (!isset($aTranscripts[$aLine['Feature']])) {
                    // We don't have it, we can't get it... Stop looking for it, please!
                    $aTranscripts[$aLine['Feature']] = false;
                }
            }
        }

        // Now check, if we managed to get the transcript ID. If not, then we'll have to continue without it.
        if (empty($aLine['Feature']) || !isset($aTranscripts[$aLine['Feature']]) || !$aTranscripts[$aLine['Feature']]) {
            // When the transcript still doesn't exist, or it evaluates to false (we don't have it, we can't get it), then skip it.
            $aVariant['transcriptid'] = '';
        } else {
            // Translate to correct ID.
            $aVariant['transcriptid'] = $aTranscripts[$aLine['Feature']]['id'];

            // Handle the rest of the VOT columns.
            // First, take off the transcript name, so we can easily check for a del/ins checking for an underscore.
            $aVariant['VariantOnTranscript/DNA'] = substr($aVariant['VariantOnTranscript/DNA'], strpos($aVariant['VariantOnTranscript/DNA'], ':')+1); // NM_000000.1:c.1del -> c.1del
            if (!$aVariant['VariantOnTranscript/DNA'] || strpos($aVariant['VariantOnTranscript/DNA'], '_') !== false) {
                // We don't have a DNA field from VEP, or we get them with an underscore which we don't trust, because
                //  at VEP they don't understand that when the gene is on reverse, they have to switch the positions.
                // Also, sometimes a delins is simply a substitution, when the VCF file is all messed up (ACGT to ACCT for example).
                // No other option, call Mutalyzer.
                // But first check if I did that before.
                if (!isset($aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']])) {
                    $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']] = array();
//print('Running position converter, DNA was: "' . $aVariant['VariantOnTranscript/DNA'] . '"' . "\n");
                    $aMutalyzerCalls['numberConversion'] ++;
                    $tMutalyzerStart = microtime(true);
                    $sJSONResponse = file_get_contents('https://mutalyzer.nl/json/numberConversion?build=' . $_CONF['refseq_build'] . '&variant=' . $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']] . ':' . $aVariant['VariantOnGenome/DNA']);
                    // FIXME: We need a better solution for this...
                    // Try one extra time, if Mutalyzer fails.
                    if ($sJSONResponse === false) {
                        $aMutalyzerCalls['numberConversion'] ++;
                        $sJSONResponse = file_get_contents('https://mutalyzer.nl/json/numberConversion?build=' . $_CONF['refseq_build'] . '&variant=' . $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_sequences'][$aVariant['chromosome']] . ':' . $aVariant['VariantOnGenome/DNA']);
                    }
                    $tMutalyzerCalls += (microtime(true) - $tMutalyzerStart);
                    if ($sJSONResponse && $aResponse = json_decode($sJSONResponse, true)) {
                        // Before we had to go two layers deep; through the result, then read out the string.
                        // But now apparently this service just returns the string with quotes (the latter are removed by json_decode()).
                        foreach ($aResponse as $sResponse) {
                            list($sRef, $sDNA) = explode(':', $sResponse, 2);
                            $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$sRef] = $sDNA;
                        }
                    }
                }
                if (!isset($aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aTranscripts[$aLine['Feature']]['id_ncbi']])) {
                    // Somehow, we can't find the transcript in the mapping info.
                    // This sometimes happens when the slice has a newer transcript than the one we have in the position converter database.
                    // This can also happen, when VEP says the variant maps, but Mutalyzer disagrees (boundaries may be different, variant may be outside of gene).
                    // Try the version we actually requested.
                    if ($aLine['Feature'] != $aTranscripts[$aLine['Feature']]['id_ncbi']) {
                        // The database has selected a different version; just copy that...
                        $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aTranscripts[$aLine['Feature']]['id_ncbi']] = $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aLine['Feature']];
                    } else {
                        // Do one more attempt, finding the transcript for other versions. Just take first one you find.
                        $aAlternativeVersions = array();
                        foreach ($aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']] as $sRef => $sDNA) {
                            if (strpos($sRef, $aLine['transcript_noversion']) === 0) {
                                $aAlternativeVersions[] = $sRef;
                            }
                        }
                        if ($aAlternativeVersions) {
                            var_dump('Found alternative by searching: ', $aLine['Feature'], $aAlternativeVersions);
                            $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aTranscripts[$aLine['Feature']]['id_ncbi']] = $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aAlternativeVersions[0]];
                        } else {
                            // This happens when VEP says we can map on a known transcript, but doesn't provide us a valid mapping,
                            // *and* Mutalyzer at the same time doesn't seem to be able to map to this transcript at all.
                            // This happens sometimes with variants outside of genes, that VEP apparently considers close enough.
                            // Getting here will trigger an error in the next block, because no valid mapping has been provided.
                        }
                    }

                    if (!isset($aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aTranscripts[$aLine['Feature']]['id_ncbi']])) {
                        die('Can\'t map variant ' . $aVariant['VariantOnGenome/DNA'] . ' (' . $aLine['chromosome'] . ':' . $aLine['position'] . $aLine['REF'] . '>' . $aLine['ALT'] . ') onto transcript ' . $aTranscripts[$aLine['Feature']]['id_ncbi'] . '.' . "\n");
                    }
                }
                $aVariant['VariantOnTranscript/DNA'] = $aMappings[$aVariant['chromosome'] . ':' . $aVariant['VariantOnGenome/DNA']][$aTranscripts[$aLine['Feature']]['id_ncbi']];
            }
            // For the position fields, there is VariantOnTranscript/Position (coming from CDS_position), but it's hardly usable. Calculate ourselves.
            list($aVariant['position_c_start'], $aVariant['position_c_start_intron'], $aVariant['position_c_end'], $aVariant['position_c_end_intron']) = array_values(lovd_getVariantPosition($aVariant['VariantOnTranscript/DNA'], $aTranscripts[$aLine['Feature']]));

            // VariantOnTranscript/Position is an integer column; so just copy the c_start.
            $aVariant['VariantOnTranscript/Position'] = $aVariant['position_c_start'];
            $aVariant['VariantOnTranscript/Distance_to_splice_site'] = ((bool) $aVariant['position_c_start_intron'] == (bool) $aVariant['position_c_end_intron']? min(abs($aVariant['position_c_start_intron']), abs($aVariant['position_c_end_intron'])) : ($aVariant['position_c_start_intron']? abs($aVariant['position_c_start_intron']) : abs($aVariant['position_c_end_intron'])));

            // VariantOnTranscript/RNA && VariantOnTranscript/Protein.
            // Try to do as much as possible by ourselves.
            $aVariant['VariantOnTranscript/RNA'] = '';
            if ($aVariant['VariantOnTranscript/Protein']) {
                // VEP came up with something...
                $aVariant['VariantOnTranscript/RNA'] = 'r.(?)';
                if ($aVariant['VariantOnTranscript/Protein'] == $aLine['HGVSc'] . '(p.%3D)') {
                    // But sometimes VEP messes up; DNA: NM_000093.4:c.4482G>A; Prot: NM_000093.4:c.4482G>A(p.%3D)
                    $aVariant['VariantOnTranscript/Protein'] = 'p.(=)';
                } else {
                    $aVariant['VariantOnTranscript/Protein'] = substr($aVariant['VariantOnTranscript/Protein'], strpos($aVariant['VariantOnTranscript/Protein'], ':')+1); // NP_000000.1:p.Met1? -> p.Met1?
                    $aVariant['VariantOnTranscript/Protein'] = str_replace('p.', 'p.(', $aVariant['VariantOnTranscript/Protein'] . ')');
                }
            } elseif (($aVariant['position_c_start'] < 0 && $aVariant['position_c_end'] < 0)
                || ($aVariant['position_c_start'] > $aTranscripts[$aLine['Feature']]['position_c_cds_end'] && $aVariant['position_c_end'] > $aTranscripts[$aLine['Feature']]['position_c_cds_end'])
                || ($aVariant['position_c_start_intron'] && $aVariant['position_c_end_intron'] && min(abs($aVariant['position_c_start_intron']), abs($aVariant['position_c_end_intron'])) > 5
                    && ($aVariant['position_c_start'] == $aVariant['position_c_end'] || ($aVariant['position_c_start'] == ($aVariant['position_c_end']-1) && $aVariant['position_c_start_intron'] > 0 && $aVariant['position_c_end_intron'] < 0)))) {
                // 5'UTR, 3'UTR, fully intronic in one intron only (at least 5 bases away from exon border).
                $aVariant['VariantOnTranscript/RNA'] = 'r.(=)';
                $aVariant['VariantOnTranscript/Protein'] = 'p.(=)';
            } elseif (($aVariant['position_c_start_intron'] && (!$aVariant['position_c_end_intron'] || abs($aVariant['position_c_start_intron']) <= 5))
                || ($aVariant['position_c_end_intron'] && (!$aVariant['position_c_start_intron'] || abs($aVariant['position_c_end_intron']) <= 5))) {
                // Partially intronic, or variants spanning multiple introns, or within first/last 5 bases of an intron.
                $aVariant['VariantOnTranscript/RNA'] = 'r.spl?';
                $aVariant['VariantOnTranscript/Protein'] = 'p.?';
            } else {
                // OK, too bad, we need to run Mutalyzer anyway.

                // It sometimes happens that we don't have a id_mutalyzer value. Before, we used to create transcripts manually if we couldn't recognize them.
                // This is now working against us, as we really need this ID now.
                if ($aTranscripts[$aLine['Feature']]['id_mutalyzer'] == '000') {
                    // Normally, we would implement a cache here, but we rarely run Mutalyzer, and if we do, we will not likely run it on a variant on the same transcript.
                    // So, first just check if we still don't have a Mutalyzer ID.
print('Reloading Mutalyzer ID for ' . $aTranscripts[$aLine['Feature']]['id_ncbi'] . ' in ' . $aGenes[$aLine['SYMBOL']]['refseq_UD'] . ' (' . $aGenes[$aLine['SYMBOL']]['id'] . ')' . "\n");
                    $aMutalyzerCalls['getTranscriptsAndInfo'] ++;
                    $tMutalyzerStart = microtime(true);
                    $sJSONResponse = file_get_contents('https://mutalyzer.nl/json/getTranscriptsAndInfo?genomicReference=' . rawurlencode($aGenes[$aLine['SYMBOL']]['refseq_UD']) . '&geneName=' . rawurlencode($aGenes[$aLine['SYMBOL']]['id']));
                    $tMutalyzerCalls += (microtime(true) - $tMutalyzerStart);
                    if ($sJSONResponse && $aResponse = json_decode($sJSONResponse, true)) {
                        // Loop transcripts, find the one in question, then isolate Mutalyzer ID.
                        foreach ($aResponse as $aTranscript) {
                            if ($aTranscript['id'] == $aTranscripts[$aLine['Feature']]['id_ncbi'] && $aTranscript['name']) {
                                $sMutalyzerID = str_replace($aGenes[$aLine['SYMBOL']]['id'] . '_v', '', $aTranscript['name']);

                                // Store locally, then store in database.
                                $aTranscripts[$aLine['Feature']]['id_mutalyzer'] = $sMutalyzerID;
                                $_DB->query('UPDATE ' . TABLE_TRANSCRIPTS . ' SET id_mutalyzer = ? WHERE id_ncbi = ?', array($sMutalyzerID, $aTranscript['id']));
                                break;
                            }
                        }
                    }
                }

print('Running mutalyzer to predict protein change for ' . $aGenes[$aLine['SYMBOL']]['refseq_UD'] . '(' . $aGenes[$aLine['SYMBOL']]['id'] . '_v' . $aTranscripts[$aLine['Feature']]['id_mutalyzer'] . '):' . $aVariant['VariantOnTranscript/DNA'] . "\n");
                $aMutalyzerCalls['runMutalyzer'] ++;
                $tMutalyzerStart = microtime(true);
                $sJSONResponse = file_get_contents('https://mutalyzer.nl/json/runMutalyzer?variant=' . rawurlencode($aGenes[$aLine['SYMBOL']]['refseq_UD'] . '(' . $aGenes[$aLine['SYMBOL']]['id'] . '_v' . $aTranscripts[$aLine['Feature']]['id_mutalyzer'] . '):' . $aVariant['VariantOnTranscript/DNA']));
                $tMutalyzerCalls += (microtime(true) - $tMutalyzerStart);
//var_dump('https://mutalyzer.nl/json/runMutalyzer?variant=' . rawurlencode($aGenes[$aLine['SYMBOL']]['refseq_UD'] . '(' . $aGenes[$aLine['SYMBOL']]['id'] . '_v' . $aTranscripts[$aLine['Feature']]['id_mutalyzer'] . '):' . $aVariant['VariantOnTranscript/DNA']));
                if ($sJSONResponse && $aResponse = json_decode($sJSONResponse, true)) {
                    if (!isset($aResponse['proteinDescriptions'])) {
                        // Not sure if this can happen using JSON.
                        $aResponse['proteinDescriptions'] = array();
                    }

//var_dump($aResponse);
                    // Predict RNA && Protein change.
                    // 'Intelligent' error handling.
                    foreach ($aResponse['messages'] as $aError) {
                        // Pass other errors on to the users?
                        // FIXME: This is implemented as well in inc-lib-variants.php (LOVD3.0-15).
                        //  When we update LOVD+ to LOVD 3.0-15, use this lib so we don't duplicate code...
                        if (isset($aError['errorcode']) && $aError['errorcode'] == 'ERANGE') {
                            // Ignore 'ERANGE' as an actual error, because we can always interpret this as p.(=), p.? or p.0.
                            $aVariantRange = explode('_', $aVariant['VariantOnTranscript/DNA']);
                            // Check what the variant looks like and act accordingly.
                            if (count($aVariantRange) === 2 && preg_match('/-\d+/', $aVariantRange[0]) && preg_match('/-\d+/', $aVariantRange[1])) {
                                // Variant has 2 positions. Variant has both the start and end positions upstream of the transcript, we can assume that the product will not be affected.
                                $sPredictR = 'r.(=)';
                                $sPredictP = 'p.(=)';
                            } elseif (count($aVariantRange) === 2 && preg_match('/-\d+/', $aVariantRange[0]) && preg_match('/\*\d+/', $aVariantRange[1])) {
                                // Variant has 2 positions. Variant has an upstream start position and a downstream end position, we can assume that the product will not be expressed.
                                $sPredictR = 'r.0?';
                                $sPredictP = 'p.0?';
                            } elseif (count($aVariantRange) == 2 && preg_match('/\*\d+/', $aVariantRange[0]) && preg_match('/\*\d+/', $aVariantRange[1])) {
                                // Variant has 2 positions. Variant has both the start and end positions downstream of the transcript, we can assume that the product will not be affected.
                                $sPredictR = 'r.(=)';
                                $sPredictP = 'p.(=)';
                            } elseif (count($aVariantRange) == 1 && preg_match('/-\d+/', $aVariantRange[0]) || preg_match('/\*\d+/', $aVariantRange[0])) {
                                // Variant has 1 position and is either upstream or downstream from the transcript, we can assume that the product will not be affected.
                                $sPredictR = 'r.(=)';
                                $sPredictP = 'p.(=)';
                            } else {
                                // One of the positions of the variant falls within the transcript, so we can not make any assumptions based on that.
                                $sPredictR = 'r.?';
                                $sPredictP = 'p.?';
                            }
                            // Fill in our assumption to forge that this information came from Mutalyzer.
                            $aVariant['VariantOnTranscript/RNA'] = $sPredictR;
                            $aVariant['VariantOnTranscript/Protein'] = $sPredictP;
                            break;
                        } elseif (isset($aError['errorcode']) && $aError['errorcode'] == 'WSPLICE') {
                            $aVariant['VariantOnTranscript/RNA'] = 'r.spl?';
                            $aVariant['VariantOnTranscript/Protein'] = 'p.?';
                            break;
                        } elseif (isset($aError['errorcode']) && $aError['errorcode'] == 'EREF') {
                            // This can happen, because we have UDs from hg38, but the alignment and variant calling is done on hg19... :(  Sequence can be different.
                            $aVariant['VariantOnTranscript/RNA'] = 'r.(?)';
                            $aVariant['VariantOnTranscript/Protein'] = 'p.?';
print('Mutalyzer returned EREF error, hg19/hg38 error?' . "\n");
                            // We don't break here, because if there is also a WSPLICE we rather go with that one.
                        }
                    }
                    if (!$aVariant['VariantOnTranscript/Protein'] && !empty($aResponse['proteinDescriptions'])) {
                        foreach ($aResponse['proteinDescriptions'] as $sVariantOnProtein) {
                            if (($nPos = strpos($sVariantOnProtein, $aGenes[$aLine['SYMBOL']]['id'] . '_i' . $aTranscripts[$aLine['Feature']]['id_mutalyzer'] . '):p.')) !== false) {
                                // FIXME: Since this code is the same as the code used in the variant mapper (2x), better make a function out of it.
                                $aVariant['VariantOnTranscript/Protein'] = substr($sVariantOnProtein, $nPos + strlen($aGenes[$aLine['SYMBOL']]['id'] . '_i' . $aTranscripts[$aLine['Feature']]['id_mutalyzer'] . '):'));
                                if ($aVariant['VariantOnTranscript/Protein'] == 'p.?') {
                                    $aVariant['VariantOnTranscript/RNA'] = 'r.?';
                                } elseif ($aVariant['VariantOnTranscript/Protein'] == 'p.(=)') {
                                    // FIXME: Not correct in case of substitutions e.g. in the third position of the codon, not leading to a protein change.
                                    $aVariant['VariantOnTranscript/RNA'] = 'r.(=)';
                                } else {
                                    // RNA will default to r.(?).
                                    $aVariant['VariantOnTranscript/RNA'] = 'r.(?)';
                                }
                                break;
                            }
                        }
                    }
                }
                // Any errors related to the prediction of Exon, RNA or Protein are silently ignored.
            }

if (!$aVariant['VariantOnTranscript/RNA']) {
    // Script dies here, because I want to know if I missed something. This happens with NR transcripts, but those were ignored anyway, right?
    var_dump($aVariant);
    exit;
}

            // DNA fields and protein field can be super long with long inserts.
            foreach (array('VariantOnGenome/DNA', 'VariantOnTranscript/DNA') as $sField) {
                if (strlen($aVariant[$sField]) > 100 && preg_match('/ins([ACTG]+)$/', $aVariant[$sField], $aRegs)) {
                    $aVariant[$sField] = str_replace('ins' . $aRegs[1], 'ins' . strlen($aRegs[1]), $aVariant[$sField]);
                }
            }
            $sField = 'VariantOnTranscript/Protein';
            if (strlen($aVariant[$sField]) > 100 && preg_match('/ins(([A-Z][a-z]{2})+)\)$/', $aVariant[$sField], $aRegs)) {
                $aVariant[$sField] = str_replace('ins' . $aRegs[1], 'ins' . strlen($aRegs[1]), $aVariant[$sField]);
            }
        }





        // Now store the variants, first the genomic stuff, then the VOT stuff.
        // If the VOG data has already been stored, we will *not* overwrite it.
        // Build the key.
        $sKey = $aLine['chromosome'] . ':' . $aLine['position'] . $aLine['REF'] . '>' . $aLine['ALT'];
        if (!isset($aData[$sKey])) {
            // Create key, put in VOG data.
            $aVOG = array();
            foreach ($aVariant as $sCol => $sVal) {
                if (in_array($sCol, $aColumnsForVOG) || substr($sCol, 0, 16) == 'VariantOnGenome/') {
                    $aVOG[$sCol] = $sVal;
                }
            }
            $aData[$sKey] = array($aVOG);
        }

        // Now, store VOT data. Because I had received test files with repeated lines, and allowing repeated lines will break import, also here we will check for the key.
        // Also check for a set transcriptid, because it can be empty (transcript could not be created).
        if (!isset($aData[$sKey][$aVariant['transcriptid']]) && $aVariant['transcriptid']) {
            $aVOT = array();
            foreach ($aVariant as $sCol => $sVal) {
                if (in_array($sCol, $aColumnsForVOT) || substr($sCol, 0, 20) == 'VariantOnTranscript/') {
                    $aVOT[$sCol] = $sVal;
                }
            }
            $aData[$sKey][$aVariant['transcriptid']] = $aVOT;
        }

        // Some reporting of where we are...
        if (!($nLine % 500)) {
            print('------- Line ' . $nLine . ' -------' . str_repeat(' ', 7-strlen($nLine)) . date('Y-m-d H:i:s') . "\n");
            flush();
        }
    }
    fclose($fInput); // Close input file.

    print('Done parsing file. Current time: ' . date('Y-m-d H:i:s') . ".\n");
    if (!$aData) {
        // No variants!
        print('No variants found to import.' . "\n");
        // Here, we won't try and remove the temp file. It will save us from running into the same error over and over again.
        continue; // Try the next file.
    }
    print('Now creating output...' . "\n");





    // Prepare VOG and VOT column arrays, include the found columns.
    // $aVOG should still exist. Take VOG columns from there.
    foreach (array_keys($aVOG) as $sCol) {
        if (substr($sCol, 0, 16) == 'VariantOnGenome/') {
            $aColumnsForVOG[] = $sCol;
        }
    }
    // Assuming here that *all* variants actually have at least one VOT... Take VOT columns.
    foreach (array_keys($aVOT) as $sCol) {
        if (substr($sCol, 0, 20) == 'VariantOnTranscript/') {
            $aColumnsForVOT[] = $sCol;
        }
    }



    // Start storing the data into the total data file.
    $fOutput = fopen($sFileTmp, 'a');
    if ($fOutput === false) {
        die('Error opening file for appending: ' . $sFileTmp . ".\n");
    }



    // VOG data.
    fputs($fOutput, "\r\n" .
        '## Genes ## Do not remove or alter this header ##' . "\r\n" . // Needed to load the existing genes, otherwise we'll only have errors.
        '## Transcripts ## Do not remove or alter this header ##' . "\r\n" . // Needed to load the existing transcripts, otherwise we'll only have errors.
        '## Variants_On_Genome ## Do not remove or alter this header ##' . "\r\n" .
        '## Count = ' . count($aData) . "\r\n" .
        '{{' . implode("}}\t{{", $aColumnsForVOG) . '}}' . "\r\n");
    $nVariant = 0;
    $nVOTs = 0;
    foreach ($aData as $sKey => $aVariant) {
        $nVariant ++;
        $nVOTs += count($aVariant) - 1;
        $nID = sprintf('%010d', $nVariant);
        $aData[$sKey][0]['id'] = $aVariant[0]['id'] = $nID;
        foreach ($aDefaultValues as $sCol => $sValue) {
            if (empty($aVariant[0][$sCol])) {
                $aVariant[0][$sCol] = $sValue;
            }
        }
        foreach ($aColumnsForVOG as $nKey => $sCol) {
            fputs($fOutput, (!$nKey? '' : "\t") . '"' . (!isset($aVariant[0][$sCol])? '' : str_replace(array("\r\n", "\r", "\n"), array('\r\n', '\r', '\n'), addslashes($aVariant[0][$sCol]))) . '"');
        }
        fputs($fOutput, "\r\n");
    }



    // VOT data.
    fputs($fOutput, "\r\n\r\n" .
        '## Variants_On_Transcripts ## Do not remove or alter this header ##' . "\r\n" .
        '## Count = ' . $nVOTs . "\r\n" .
        '{{' . implode("}}\t{{", $aColumnsForVOT) . '}}' . "\r\n");
    foreach ($aData as $aVariant) {
        $nID = $aVariant[0]['id'];
        unset($aVariant[0]);
        foreach ($aVariant as $aVOT) {
            // Loop through all VOTs.
            $aVOT['id'] = $nID;
            foreach ($aDefaultValues as $sCol => $sValue) {
                if (empty($aVOT[$sCol])) {
                    $aVOT[$sCol] = $sValue;
                }
            }
            foreach ($aColumnsForVOT as $nKey => $sCol) {
                fputs($fOutput, (!$nKey? '' : "\t") . '"' . (!isset($aVOT[$sCol])? '' : str_replace(array("\r\n", "\r", "\n"), array('\r\n', '\r', '\n'), addslashes($aVOT[$sCol]))) . '"');
            }
            fputs($fOutput, "\r\n");
        }
    }



    // Link all variants to the screening.
    fputs($fOutput, "\r\n" .
        '## Screenings_To_Variants ## Do not remove or alter this header ##' . "\r\n" .
        '## Count = ' . count($aData) . "\r\n" .
        '{{screeningid}}' . "\t" . '{{variantid}}' . "\r\n");
    for ($nVariant = 1; $nVariant <= count($aData); $nVariant ++) {
        $nID = sprintf('%010d', $nVariant);
        fputs($fOutput, '"' . $nScreeningID . "\"\t\"" . $nID . "\"\r\n");
    }



    fclose($fOutput); // Close output file.
    // Now move the tmp to the final file, and close this loop.
    if (!rename($sFileTmp, $sFileDone)) {
        // Fatal error, because we're all done actually!
        die('Error moving temp file to target: ' . $sFileDone . ".\n");
    }

    // OK, so file is done, and can be scheduled now. Just auto-schedule it.
    if ($_DB->query('INSERT IGNORE INTO ' . TABLE_SCHEDULED_IMPORTS . ' (filename, scheduled_by, scheduled_date) VALUES (?, 0, NOW())', array(basename($sFileDone)))->rowCount()) {
        print('File scheduled for import.' . "\n");
    } else {
        print('Error scheduling file for import!' . "\n");
    }

    print('All done, ' . $sFileDone . ' ready for import.' . "\n" . 'Current time: ' . date('Y-m-d H:i:s') . "\n" .
          '  Took ' . round((time() - $dStart)/60) . ' minutes, Mutalyzer calls taking ' . round($tMutalyzerCalls/60) . ' minutes.' . "\n");
    foreach ($aMutalyzerCalls as $sFunction => $nCalls) {
        print('    ' . $sFunction . ': ' . $nCalls . "\n");
    }
    print("\n");
    break;// Keep this break in the loop, so we will only continue the loop to the next file when there is a continue;
}
?>
