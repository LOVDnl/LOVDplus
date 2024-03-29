<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2020-03-09
 * Modified    : 2022-12-21
 * For LOVD    : 3.0-29
 *
 * Copyright   : 2004-2022 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *
 * This file is part of LOVD.
 *
 * LOVD is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * LOVD is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LOVD.  If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}



class LOVD_VV
{
    // This class defines the LOVD VV object, handling all Variant Validator calls.

    public $sURL = 'https://rest.variantvalidator.org/'; // The URL of the VV endpoint.
    // public $sURL = 'https://www35.lamp.le.ac.uk/'; // The URL of the VV testing endpoint.
    public $aResponse = array( // The standard response body.
        'data' => array(),
        'messages' => array(),
        'warnings' => array(),
        'errors' => array(),
    );





    function __construct ($sURL = '')
    {
        // Initiates the VV object. Nothing much to do except for filling in the URL.

        if ($sURL) {
            // We don't test given URLs, that would take too much time.
            $this->sURL = rtrim($sURL, '/') . '/';
        }
        // __construct() should return void.
    }





    private function callVV ($sMethod, $aArgs = array())
    {
        // Wrapper function to call VV's JSON webservice.
        // Because we have a wrapper, we can implement CURL, which is much faster on repeated calls.
        global $_CONF, $_SETT;

        // Build URL, regardless of how we'll connect to it.
        $sURL = $this->sURL . $sMethod . '/' . implode('/', array_map('rawurlencode', $aArgs)) . '?content-type=application%2Fjson';
        $sJSONResponse = '';

        if (function_exists('curl_init')) {
            // Initialize curl connection.
            static $hCurl;

            if (!$hCurl) {
                $hCurl = curl_init();
                curl_setopt($hCurl, CURLOPT_RETURNTRANSFER, true); // Return the result as a string.
                if (!empty($_SETT['system']['version'])) {
                    curl_setopt($hCurl, CURLOPT_USERAGENT, 'LOVDv.' . $_SETT['system']['version']); // Return the result as a string.
                }

                // Set proxy.
                if (!empty($_CONF['proxy_host'])) {
                    curl_setopt($hCurl, CURLOPT_PROXY, $_CONF['proxy_host'] . ':' . $_CONF['proxy_port']);
                    if (!empty($_CONF['proxy_username']) || !empty($_CONF['proxy_password'])) {
                        curl_setopt($hCurl, CURLOPT_PROXYUSERPWD, $_CONF['proxy_username'] . ':' . $_CONF['proxy_password']);
                    }
                }
            }

            curl_setopt($hCurl, CURLOPT_URL, $sURL);
            $sJSONResponse = curl_exec($hCurl);

        } elseif (function_exists('lovd_php_file')) {
            // Backup method, no curl installed. We'll try LOVD's file() implementation, which also handles proxies.
            $aJSONResponse = lovd_php_file($sURL);
            if ($aJSONResponse !== false) {
                $sJSONResponse = implode("\n", $aJSONResponse);
            }

        } else {
            // Last fallback. Requires fopen wrappers.
            $aJSONResponse = file($sURL);
            if ($aJSONResponse !== false) {
                $sJSONResponse = implode("\n", $aJSONResponse);
            }
        }



        if ($sJSONResponse) {
            $aJSONResponse = @json_decode($sJSONResponse, true);
            if ($aJSONResponse !== false) {
                return $aJSONResponse;
            }
        }
        // Something went wrong...
        return false;
    }





    private function getRNAProteinPrediction (&$aMapping, $sTranscript = '')
    {
        // Function to predict the RNA change and to improve VV's protein prediction.
        // $aMapping will be extended with 'RNA' and 'protein' if they don't already exist.
        // $sTranscript is just used to check if this is a coding or non-coding transcript.

        if (!is_array($aMapping) || !isset($aMapping['DNA'])) {
            // Without DNA, we can do nothing.
            return false;
        }

        if (!isset($aMapping['RNA'])) {
            $aMapping['RNA'] = 'r.(?)';
        }
        if (!isset($aMapping['protein'])) {
            $aMapping['protein'] = '';
        }

        // Check values, perhaps we can do better.
        if (substr($aMapping['DNA'], -1) == '=') {
            // DNA actually didn't change. Protein will indicate the same.
            $aMapping['RNA'] = 'r.(=)';
            // FIXME: VV returns p.(Ala86=) rather than p.(=); perhaps return r.(257=) instead of r.(=).
            //  If you instead would like to make VV return p.(=), here is where you change this.
            //  If you do, don't forget to check whether you're on a coding transcript.
            // For UTRs or p.Met1, a c.= returns a p.? (safe choice). I prefer a p.(=).
            if ($aMapping['protein'] == 'p.?' || $aMapping['protein'] == 'p.(Met1?)') {
                $aMapping['protein'] = 'p.(=)';
            }

        } elseif (function_exists('lovd_getVariantInfo')
            && in_array($aMapping['protein'], array('', 'p.?', 'p.(=)'))) {
            // lovd_getVariantInfo() is generally fast, so we don't have to worry about slowdowns.
            // But we need to prevent the possible database query for 3' UTR variants,
            //  because we don't even know if we have the transcript.
            // Therefore, passing False as transcript ID.
            $aVariant = lovd_getVariantInfo($aMapping['DNA'], false);
            if ($aVariant) {
                // We'd want to check this.
                // Splicing.
                if (($aVariant['position_start_intron'] && abs($aVariant['position_start_intron']) <= 5)
                    || ($aVariant['position_end_intron'] && abs($aVariant['position_end_intron']) <= 5)
                    || ($aVariant['position_start_intron'] && !$aVariant['position_end_intron'])
                    || (!$aVariant['position_start_intron'] && $aVariant['position_end_intron'])) {
                    $aMapping['RNA'] = 'r.spl?';
                    $aMapping['protein'] = 'p.?';

                } elseif ($aVariant['position_start_intron'] && $aVariant['position_end_intron']
                    && abs($aVariant['position_start_intron']) > 5 && abs($aVariant['position_end_intron']) > 5
                    && ($aVariant['position_start'] == $aVariant['position_end']
                        || ($aVariant['position_start'] + 1) == $aVariant['position_end'])) {
                    // Deep intronic.
                    $aMapping['RNA'] = 'r.(=)';
                    $aMapping['protein'] = 'p.(=)';

                } else {
                    // No introns involved. Note, position fields are sorted.
                    if ($aVariant['position_end'] < 0) {
                        // Variant is upstream.
                        $aMapping['RNA'] = 'r.(?)';
                        $aMapping['protein'] = 'p.(=)';

                    } elseif ($aVariant['position_start'] < 0 && strpos($aMapping['DNA'], '*') !== false) {
                        // Start is upstream, end is downstream.
                        if ($aMapping['type'] == 'del') {
                            $aMapping['RNA'] = 'r.0?';
                            $aMapping['protein'] = 'p.0?';
                        } else {
                            $aMapping['RNA'] = 'r.?';
                            $aMapping['protein'] = 'p.?';
                        }

                    } elseif (substr($aMapping['DNA'], 0, 3) == 'c.*'
                        && ($aVariant['position_start'] == $aVariant['position_end']
                            || substr_count($aMapping['DNA'], '*') > 1)) {
                        // Variant is downstream.
                        $aMapping['RNA'] = 'r.(=)';
                        $aMapping['protein'] = 'p.(=)';

                    } elseif ($aVariant['type'] != 'subst' && $aMapping['protein'] != 'p.(=)') {
                        // Deletion/insertion partially in the transcript, not predicted to do nothing.
                        $aMapping['RNA'] = 'r.?';
                        $aMapping['protein'] = 'p.?';

                    } else {
                        // Substitution on wobble base or so.
                        $aMapping['RNA'] = 'r.(?)';
                    }
                }

                // But wait, did we just fill in a protein field for a non-coding transcript?
                if (substr($sTranscript, 1, 1) == 'R') {
                    $aMapping['protein'] = '';
                }
            }

        } elseif (strpos($aMapping['protein'], 'Ter') !== false) {
            // VV likes to use 'Ter', which is consistent with otherwise using
            //  three-letter aminoacid codes. However, publications and
            //  submitters mostly use *, and it's annoying to have to search for
            //  both * and Ter if we want to find variants causing a stop.
            $aMapping['protein'] = str_replace('Ter', '*', $aMapping['protein']);
        }

        return true;
    }





    public function getTranscriptsByGene ($sSymbol)
    {
        // Returns the available transcripts for the given gene.
        global $_SETT;

        $aJSON = $this->callVV('VariantValidator/tools/gene2transcripts', array(
            'id' => $sSymbol,
        ));
        if (!$aJSON || empty($aJSON['transcripts'])) {
            // Failure.
            return false;
        }

        $aData = $this->aResponse;
        foreach ($aJSON['transcripts'] as $aTranscript) {
            // Clean name.
            $sName = preg_replace(
                array(
                    '/^Homo sapiens\s+/', // Remove species name.
                    '/^' . preg_quote($aJSON['current_name'], '/') . '\s+/', // The current gene name.
                    '/^\(' . preg_quote($aJSON['current_symbol'], '/') . '\),\s+/', // The current symbol.
                    '/, mRNA$/', // mRNA suffix.
                    '/, non-coding RNA$/', // non-coding RNA suffix, replaced to " (non-coding)".
                ), array('', '', '', '', ' (non-coding)'), $aTranscript['description']);

            // Figure out the genomic positions, which are given to us using the NCs.
            $aGenomicPositions = array();
            foreach ($_SETT['human_builds'] as $sBuild => $aBuild) {
                if (!isset($aBuild['ncbi_sequences'])) {
                    continue;
                }
                // See if one of the build's chromosomes match.
                foreach (array_intersect($aBuild['ncbi_sequences'], array_keys($aTranscript['genomic_spans'])) as $sChromosome => $sRefSeq) {
                    if (!isset($aGenomicPositions[$sBuild])) {
                        $aGenomicPositions[$sBuild] = array();
                    }
                    $aGenomicPositions[$sBuild][$sChromosome] = array(
                        'start' => ($aTranscript['genomic_spans'][$sRefSeq]['orientation'] == 1?
                            $aTranscript['genomic_spans'][$sRefSeq]['start_position'] :
                            $aTranscript['genomic_spans'][$sRefSeq]['end_position']),
                        'end' => ($aTranscript['genomic_spans'][$sRefSeq]['orientation'] == 1?
                            $aTranscript['genomic_spans'][$sRefSeq]['end_position'] :
                            $aTranscript['genomic_spans'][$sRefSeq]['start_position']),
                    );
                }
            }

            $aData['data'][$aTranscript['reference']] = array(
                'name' => $sName,
                'id_ncbi_protein' => $aTranscript['translation'],
                'genomic_positions' => $aGenomicPositions,
                'transcript_positions' => array(
                    'cds_start' => $aTranscript['coding_start'],
                    'cds_length' => (!$aTranscript['coding_end']? NULL : ($aTranscript['coding_end'] - $aTranscript['coding_start'] + 1)),
                    'length' => $aTranscript['length'],
                )
            );
        }

        ksort($aData['data']);
        return $aData;
    }





    public function test ()
    {
        // Tests the VV endpoint.

        $aJSON = $this->callVV('hello');
        if (!$aJSON) {
            // Failure.
            return false;
        }

        if (isset($aJSON['status']) && $aJSON['status'] == 'hello_world') {
            // All good.
            return true;
        } else {
            // Something JSON, but perhaps another format?
            return 0;
        }
    }





    public function verifyGenomic ($sVariant, $aOptions = array())
    {
        // Verify a genomic variant, and optionally get mappings and a protein prediction.
        global $_SETT;

        if (empty($aOptions) || !is_array($aOptions)) {
            $aOptions = array();
        }

        $aVariantInfo = false;
        // Perform some extra checks, if we can.
        if (function_exists('lovd_getVariantInfo')) {
            $aVariantInfo = lovd_getVariantInfo($sVariant);
            // VV doesn't support uncertain positions.
            if (isset($aVariantInfo['messages']['IUNCERTAINPOSITIONS'])) {
                return array_merge_recursive(
                    $this->aResponse,
                    array(
                        'errors' => array(
                            'EUNCERTAINPOSITIONS' => 'VariantValidator does not currently support variant descriptions with uncertain positions.',
                        )
                    )
                );
            }

            // Don't send variants that are too big; VV can't currently handle them.
            // These sizes are approximate and slightly on the safe side;
            //  simple measurements have shown a maximum duplication size of
            //  250KB, and a max deletion of 900KB, requiring a full minute.
            // See: https://github.com/openvar/variantValidator/issues/151
            if ($aVariantInfo
                && (($aVariantInfo['type'] == 'dup' && ($aVariantInfo['position_end'] - $aVariantInfo['position_start']) > 200000)
                    || (substr($aVariantInfo['type'], 0, 3) == 'del' && ($aVariantInfo['position_end'] - $aVariantInfo['position_start']) > 800000))) {
                // Variant too big, return error.
                $aReturn = $this->aResponse;
                $aReturn['errors']['ESIZETOOLARGE'] = 'This variant is currently too big to process. It will likely time out after a minute of waiting, so we won\'t send it to VariantValidator.';
                return $aReturn;
            }
        }

        // Append defaults for any remaining options.
        $aOptions = array_replace(
            array(
                'map_to_transcripts' => false, // Should we map the variant to transcripts?
                'predict_protein' => false,    // Should we get protein predictions?
                'lift_over' => false,          // Should we get other genomic mappings of this variant?
                'select_transcripts' => 'all', // Should we limit our output to only a certain set of transcripts?
            ),
            $aOptions);

        // Some options require others.
        // We want to map to transcripts also if we're asking for a liftover, and if we want protein prediction.
        $aOptions['map_to_transcripts'] = ($aOptions['map_to_transcripts'] || $aOptions['lift_over'] || $aOptions['predict_protein']);

        // Allow calling for any build, not just the one we are configured to use.
        // We always need to receive an NC anyway, so we can deduce the build (except for chrM).
        // We can pull this out of the database, but I prefer to rely on an array rather
        //  than a database, in case this object will ever be pulled out of LOVD.
        $sVariantNC = strstr($sVariant, ':', true);
        $sBuild = '';
        foreach ($_SETT['human_builds'] as $sCode => $aBuild) {
            if (isset($aBuild['ncbi_sequences'])) {
                if (in_array($sVariantNC, $aBuild['ncbi_sequences'])) {
                    // We pick the NCBI name here, because for chrM we actually
                    //  use GRCh37's NC_012920.1 instead of hg19's NC_001807.4.
                    $sBuild = $aBuild['ncbi_name'];
                    break;
                }
            }
        }
        // If we didn't get the build right here, then the whole call will fail.
        // Also, only NCs will work.
        if (!$sBuild || substr($sVariantNC, 0, 2) != 'NC') {
            return false;
        }

        // Transcript list should be a list, or 'all'.
        if (!$aOptions['select_transcripts']
            || (!is_array($aOptions['select_transcripts']) && $aOptions['select_transcripts'] != 'all')) {
            $aOptions['select_transcripts'] = 'all';
        }

        $aJSON = $this->callVV('LOVD/lovd', array(
            'genome_build' => $sBuild,
            'variant_description' => $sVariant,
            'transcripts' => 'refseq', // 'all' includes Ensembl transcripts that currently (July 2022) are very slow.
            'select_transcripts' => (!is_array($aOptions['select_transcripts'])?
                $aOptions['select_transcripts'] :
                implode('|', $aOptions['select_transcripts'])),
            'check_only' => ($aOptions['predict_protein']?
                'False' : ($aOptions['map_to_transcripts']? 'tx' : 'True')),
            'lift_over' => ($aOptions['lift_over']? 'primary' : 'False'),
        ));
        if (!$aJSON || empty($aJSON[$sVariant])) {
            // Failure. This happens when VV fails hard or if we can't find our
            //  input back in the output. This happened with #206; |lom variants
            //  are split into two variants; the location and "lom".
            // Catch the methylation-related variants and provide some output.
            if (strpos($sVariant, '|') !== false) {
                // VV failed because of #206.
                $aData = $this->aResponse;
                $aData['errors']['ESYNTAX'] = 'Methylation variants are currently not supported.';
                return $aData;
            }

            return false;
        }

        $aData = $this->aResponse;

        // Discard the meta data.
        $aJSON = $aJSON[$sVariant];

        // We'll copy the errors, but I've never seen them filled in, even with REF errors.
        $aData['errors'] = $aJSON['errors'];
        // Check the flag value.
        if ($aJSON['flag']) {
            switch ($aJSON['flag']) {
                case 'genomic_variant_warning':
                    if ($aJSON[$sVariant]['genomic_variant_error']) {
                        // Clean off variant description.
                        $sError = str_replace($sVariant . ': ', '', $aJSON[$sVariant]['genomic_variant_error']);
                        // VV has declared their error messages are stable.
                        // This means we can parse them and rely on them not to change.
                        // Add error code if possible, so we won't have to parse the error message again somewhere.
                        if ($sError == 'Length implied by coordinates must equal sequence deletion length') {
                            // EINCONSISTENTLENGTH error.
                            $aData['errors']['EINCONSISTENTLENGTH'] = $sError;
                        } elseif (strpos($sError, 'is outside the boundaries of reference sequence') !== false
                            || preg_match('/^Failed to fetch .+ out of range/', $sError)) {
                            // ERANGE error.
                            $aData['errors']['ERANGE'] = $sError;
                        } elseif (strpos($sError, 'does not agree with reference sequence') !== false) {
                            // EREF error.
                            $aData['errors']['EREF'] = $sError;
                        } elseif (strpos($sError, 'is not associated with genome build') !== false) {
                            // EREFSEQ error.
                            $aData['errors']['EREFSEQ'] = $sError;
                        } elseif (substr($sError, 0, 5) == 'char ' || $sError == 'insertion length must be 1') {
                            // ESYNTAX error.
                            $aData['errors']['ESYNTAX'] = $sError;
                        } elseif (substr($sError, 0, 21) == 'Fuzzy/unknown variant') {
                            // EUNCERTAINPOSITIONS error.
                            $aData['errors']['EUNCERTAINPOSITIONS'] = 'VariantValidator does not currently support variant descriptions with uncertain positions.';
                        } elseif (strpos($sError, $sVariant . ' updated to ') !== false) {
                            // Recently, VV published an update that generates an error even when the variant
                            //  description is just updated a bit (e.g., WROLLFORWARD). We are handling them
                            //  elsewhere, so hide that here.
                            $aJSON[$sVariant]['genomic_variant_error'] = '';
                            break;
                        } else {
                            // Unrecognized error.
                            $aData['errors'][] = $sError;
                        }
                        // When we have errors, we don't need 'data' filled in. Just return what I have.
                        return $aData;
                    }
                    break;
                case 'porcessing_error': // Typo, still present in test instance 2020-06-02.
                    $aJSON['flag'] = 'processing_error';
                case 'processing_error':
                    // This happens, for instance, when we ask to select a
                    //  transcript that this variant actually doesn't map on.
                    // Also, sometimes VV (well, UTA) gives us a transcript
                    //  voluntarily, but VV can't map our variant with it.
                    $aFailingTranscripts = array();
                    foreach ($aJSON[$sVariant]['hgvs_t_and_p'] as $sTranscript => $aTranscript) {
                        if (!empty($aTranscript['transcript_variant_error'])) {
                            $aFailingTranscripts[] = $sTranscript;
                        }
                    }

                    // If not all our transcripts failed, just remove the ones that did.
                    if (count($aFailingTranscripts) < count($aJSON[$sVariant]['hgvs_t_and_p'])) {
                        foreach ($aFailingTranscripts as $sTranscript) {
                            unset($aJSON[$sVariant]['hgvs_t_and_p'][$sTranscript]);
                        }
                        // Continue.
                        break;
                    }

                    // If we have selected transcripts, they all failed.
                    // If that is the case, retry without selecting any.
                    if (is_array($aOptions['select_transcripts'])
                        && count($aOptions['select_transcripts'])) {
                        unset($aOptions['select_transcripts']);
                        return $this->verifyGenomic($sVariant, $aOptions);
                    }
                    // No break; if we don't catch the error above here,
                    //  we want the error below.
                default:
                    // Unhandled flag. "processing_error" can still be
                    //  thrown, if all transcripts fail.
                    // FIXME: I've seen "submission_warning" when submitting
                    //  NC_000011.9:g.2018812_2024740|lom; it got split in
                    //  two (see #206) and "lom" threw a submission_warning.
                    // FIXME: NC_000003.11:g.169482398_169482471del   fails.
                    // FIXME: NC_000007.13:g.50468071G>A throws one, and has
                    //  10 failing transcripts. I guess sometimes you can't
                    //  go around this.
                    if ($aJSON['flag'] == 'processing_error') {
                        $aData['warnings']['WFLAG'] = 'VV Flag not handled: ' . $aJSON['flag'] . '. This happens when UTA passes transcripts that VV cannot map to.';
                    } else {
                        $aData['errors']['EFLAG'] = 'VV Flag not recognized: ' . $aJSON['flag'] . '. This indicates a feature is missing in LOVD.';
                    }
                    break;
            }
        }
        // Discard the errors array and the flag value.
        $aJSON = $aJSON[$sVariant];

        // Copy the (corrected) DNA value.
        $aData['data']['DNA'] = $aJSON['g_hgvs'];
        // If description is given but different, then apparently there's been some kind of correction.
        if ($aData['data']['DNA'] && $sVariant != $aData['data']['DNA']) {
            // Check type of correction; silent, WCORRECTED, or WROLLFORWARD.
            if ($aVariantInfo) {
                // Use LOVD's lovd_getVariantInfo() to parse positions and type.
                $aVariantInfoCorrected = lovd_getVariantInfo($aData['data']['DNA']);

                if (array_diff_key($aVariantInfo, array('warnings' => array()))
                    == array_diff_key($aVariantInfoCorrected, array('warnings' => array()))) {
                    // Positions and type are the same, small corrections like delG to del.
                    // We let these pass silently.
                } elseif ($aVariantInfo['type'] != $aVariantInfoCorrected['type']
                    || $aVariantInfo['range'] != $aVariantInfoCorrected['range']) {
                    // An insertion actually being a duplication.
                    // A deletion-insertion which is actually something else.
                    // A g.1_1del that should be g.1del.
                    $aData['warnings']['WCORRECTED'] = 'Variant description has been corrected.';
                } else {
                    // Positions are different, but type is the same.
                    // 3' forwarding of deletions, insertions, duplications
                    //  and deletion-insertion events.
                    $aData['warnings']['WROLLFORWARD'] = 'Variant position' .
                        (!$aVariantInfo['range']? ' has' : 's have') .
                        ' been corrected.';
                }

            } else {
                // Not running as an LOVD object, just complain here.
                $aData['warnings']['WCORRECTED'] = 'Variant description has been corrected.';
            }
        }

        // Any errors given?
        if ($aJSON['genomic_variant_error']) {
            // Not a previously seen error, handled through the flag value.
            // We'll assume a warning.
            // FIXME: Value may need cleaning!
            // FIXME: Does this ever happen? Or is this only filled in when we also have a flag?
            $aData['warnings'][] = $aJSON['genomic_variant_error'];
        }

        // Mappings?
        $aData['data']['genomic_mappings'] = array();
        $aData['data']['transcript_mappings'] = array();
        if ($aJSON['hgvs_t_and_p']) {
            foreach ($aJSON['hgvs_t_and_p'] as $sTranscript => $aTranscript) {
                if ($sTranscript != 'intergenic' && empty($aTranscript['transcript_variant_error'])) {
                    // We silently ignore transcripts here that gave us an error, but not for the liftover feature.
                    $aMapping = array(
                        'DNA' => '',
                        'RNA' => (!$aOptions['predict_protein']? '' : 'r.(?)'),
                        'protein' => '',
                    );
                    if ($aTranscript['gap_statement'] || $aTranscript['gapped_alignment_warning']) {
                        // This message might be repeated for multiple transcripts when there are gapped alignments,
                        //  and perhaps repeated also for multiple genome builds (untested).
                        // Currently, we just store one warning message.
                        $aData['warnings']['WALIGNMENTGAPS'] = 'Given alignments may contain artefacts; there is a gapped alignment between transcript and genome build.';
                    }
                    if ($aTranscript['t_hgvs']) {
                        $aMapping['DNA'] = substr(strstr($aTranscript['t_hgvs'], ':'), 1);
                    }
                    if ($aTranscript['p_hgvs_tlc']) {
                        $aMapping['protein'] = substr(strstr($aTranscript['p_hgvs_tlc'], ':'), 1);
                    }

                    if ($aOptions['predict_protein']) {
                        // Try to improve VV's predictions.
                        $this->getRNAProteinPrediction($aMapping, $sTranscript);
                    }
                    $aData['data']['transcript_mappings'][$sTranscript] = $aMapping;
                }

                // Genomic mappings, when requested, are given per transcript (or otherwise as "intergenic").
                if (empty($aTranscript['primary_assembly_loci'])) {
                    $aTranscript['primary_assembly_loci'] = array();
                }

                foreach ($aTranscript['primary_assembly_loci'] as $sBuild => $aMappings) {
                    // We support only the builds we have...
                    if (!isset($_SETT['human_builds'][$sBuild])) {
                        continue;
                    }

                    // There can be more than one mapping per build in theory...
                    foreach ($aMappings as $sRefSeq => $aMapping) {
                        $aData['data']['genomic_mappings'][$sBuild][] = $aMapping['hgvs_genomic_description'];
                    }
                }

                // Clean up duplicates from multiple transcripts.
                foreach ($aData['data']['genomic_mappings'] as $sBuild => $aMappings) {
                    $aData['data']['genomic_mappings'][$sBuild] = array_unique($aMappings);
                }
            }
        }
        return $aData;
    }





    public function verifyGenomicAndMap ($sVariant, $aTranscripts = array())
    {
        // Wrapper to verify a genomic variant and map it to transcripts as well.

        return $this->verifyGenomic($sVariant,
            array(
                'map_to_transcripts' => true,
                'select_transcripts' => $aTranscripts,
            ));
    }





    public function verifyGenomicAndLiftOver ($sVariant, $aTranscripts = array())
    {
        // Wrapper to verify a genomic variant and lift it over to other genome builds
        //  (through transcript mapping if possible).

        return $this->verifyGenomic($sVariant,
            array(
                'lift_over' => true,
                'select_transcripts' => $aTranscripts,
            ));
    }





    public function verifyGenomicAndPredictProtein ($sVariant, $aTranscripts = array())
    {
        // Wrapper to verify a genomic variant, map it to transcripts, and get protein predictions as well.

        return $this->verifyGenomic($sVariant,
            array(
                'predict_protein' => true,
                'select_transcripts' => $aTranscripts,
            ));
    }





    public function verifyVariant ($sVariant, $aOptions = array())
    {
        // Verify a variant, get mappings and protein predictions.
        // Uses the VariantValidator API, in practice for both genomic and
        //  transcript variants. For genomic variants, we're much happier using
        //  the LOVD endpoint (verifyGenomic()), so just use this method only
        //  for transcript variants.
        // For getting reference base verification, you'll need to pass the NC
        //  as well, in the format NC_000001.10(NM_123456.1):c.100del.
        // We don't want to add code to fetch the NC, since we don't want to use
        //  the database backend here in case we're used as an external lib.
        global $_CONF, $_DB, $_SETT;

        // Disallow NC variants. We should verifyGenomic() for these.
        // Supporting NCs using this function will just take a lot more code,
        //  which wouldn't be useful. Fail hard, to teach users to not do this,
        //  but don't fail on NC_000001.10(NM_123456.1):c. variants.
        if (preg_match('/^NC_[0-9]+\.[0-9]+:/', $sVariant)) {
            return false;
        }

        if (empty($aOptions) || !is_array($aOptions)) {
            $aOptions = array();
        }

        $aVariantInfo = false;
        // Perform some extra checks, if we can.
        if (function_exists('lovd_getVariantInfo')) {
            $aVariantInfo = lovd_getVariantInfo($sVariant);
            // VV doesn't support uncertain positions.
            if (isset($aVariantInfo['messages']['IUNCERTAINPOSITIONS'])) {
                return array_merge_recursive(
                    $this->aResponse,
                    array(
                        'errors' => array(
                            'EUNCERTAINPOSITIONS' => 'VariantValidator does not currently support variant descriptions with uncertain positions.',
                        )
                    )
                );
            }
        }

        // Append defaults for any remaining options.
        // VV doesn't have as many options as the LOVD endpoint, and honestly,
        //  selecting transcripts is only useful when we're using NC's as input.
        $aOptions = array_replace(
            array(
                'select_transcripts' => 'all', // Should we limit our output to only a certain set of transcripts?
            ),
            $aOptions);

        // We only need a genome build to resolve intronic variants.
        $sBuild = '';

        // Try the variant, first.
        if (preg_match('/^NC_0000[0-9]{2}\.[0-9]{1,2}\(/', $sVariant)) {
            $sRefSeq = strstr($sVariant, '(', true);
            foreach ($_SETT['human_builds'] as $sCode => $aBuild) {
                if (isset($aBuild['ncbi_sequences']) && in_array($sRefSeq, $aBuild['ncbi_sequences'])) {
                    $sBuild = $sCode;
                    break;
                }
            }
        }
        if (!$sBuild) {
            // The VV endpoint only throws a warning when an invalid build has been
            //  passed, but does continue. Try $_CONF, but be OK with it not there.
            if (isset($_CONF['refseq_build'])) {
                $sBuild = $_CONF['refseq_build'];
            } else {
                $sBuild = 'hg38';
            }
        }

        // We pick the NCBI name here, because for chrM we actually
        //  use GRCh37's NC_012920.1 instead of hg19's NC_001807.4.
        // We can pull this out of the database, but I prefer to rely on an array rather
        //  than a database, in case this object will ever be pulled out of LOVD.
        foreach ($_SETT['human_builds'] as $sCode => $aBuild) {
            if ($sCode == $sBuild && isset($aBuild['ncbi_sequences'])) {
                $sBuild = $aBuild['ncbi_name'];
                break;
            }
        }

        // Strip the NC off of the variant unless this variant is intronic or
        //  outside of the transcript's boundaries.
        // See https://github.com/openvar/variantValidator/issues/218.
        // When the sequence in the NC and the NM mismatch, we'll get an error,
        //  so dump the NC unless necessary (variants outside of the NM sequence).
        if (preg_match('/^(NC_0000[0-9]{2}\.[0-9]{1,2})\(([NX][MR]_[0-9]+\.[0-9]+)\):/', $sVariant, $aRegs)) {
            list(,, $sRefSeqNM) = $aRegs;
            $sVariantShort = substr(strstr($sVariant, ':'), 1);
            $bKeepNC = false;
            if ($aVariantInfo && isset($_DB)) {
                // Check for intronic and positions outside of the mRNA.

                // Fetch transcript positions from the database.
                $aTranscript = $_DB->q('
                    SELECT position_c_mrna_start, position_c_mrna_end
                    FROM ' . TABLE_TRANSCRIPTS . ' WHERE id_ncbi = ?',
                    array($sRefSeqNM))->fetchAssoc();
                $aVariantInfo = lovd_getVariantInfo($sVariantShort, $sRefSeqNM);

                $bKeepNC = (!empty($aVariantInfo['position_start_intron'])
                    || !empty($aVariantInfo['position_end_intron'])
                    || $aVariantInfo['position_start'] < $aTranscript['position_c_mrna_start']
                    || $aVariantInfo['position_end'] > $aTranscript['position_c_mrna_end']);

            } else {
                // Just a quick simple check; keeping the NC for all intronic
                //  or non-CDS changes. We don't know how long the UTR is, so
                //  we'll just assume everything outside of the CDS is outside
                //  of the transcript's boundaries.
                $bKeepNC = preg_match('/[-+*]/', $sVariantShort);
            }

            if (!$bKeepNC) {
                $sVariant = $sRefSeqNM . ':' . $sVariantShort;
            }
        }

        // Transcript list should be a list, or 'all'.
        if (!$aOptions['select_transcripts']
            || (!is_array($aOptions['select_transcripts']) && $aOptions['select_transcripts'] != 'all')) {
            $aOptions['select_transcripts'] = 'all';
        }

        $aJSON = $this->callVV('VariantValidator/variantvalidator', array(
            'genome_build' => $sBuild,
            'variant_description' => $sVariant,
            'select_transcripts' => (!is_array($aOptions['select_transcripts'])?
                $aOptions['select_transcripts'] :
                implode('|', $aOptions['select_transcripts'])),
        ));
        if (!$aJSON || empty($aJSON['flag'])) {
            // Failure.
            return false;
        }

        // See https://github.com/openvar/variantValidator/issues/421.
        // Sometimes VV returns incomplete output that seems to be based on somebody else's input.
        // But even then, a warning is thrown, so we can detect this easily.
        // We could loop and keep calling VV until we get back what we expect, but this might lead to an endless loop.
        // So, for now, simply repeat the call *once* if we find this problem.
        if ($aJSON['flag'] == 'warning'
            && array_keys(array_diff_key($aJSON, array('metadata' => 1, 'flag' => 1))) == array('validation_warning_1')
            && $aJSON['validation_warning_1']['submitted_variant'] != $sVariant) {
            // We got an empty warning with somebody else's input. We can't use this at all. Repeat or return false.
            if (empty($aOptions['repeated_call'])) {
                // This adds some overhead (re-processing of input and a recursive function call),
                //  but it's the simplest method.
                return $this->verifyVariant($sVariant, array_merge($aOptions, array('repeated_call' => 1)));
            } else {
                return false;
            }
        }

        $aData = $this->aResponse;

        // Discard the meta data.
        unset($aJSON['metadata']);

        // Check the flag value. In contrast to the LOVD endpoint, the VV flag is always filled in.
        switch ($aJSON['flag']) {
            case 'error':
                // VV failed completely. Nothing to do here...
                return false;
            case 'gene_variant':
                // All good. We can still have validation errors, but at least it's not a big warning.
                break;
            case 'intergenic':
                // This can only happen when passing NC-based variants.
                // N[MR]-based variants that are outside of the transcript's
                //  bounds are returning a warning flag.
                // We choose not to support this. We could, but returning
                //  False here will teach us to use verifyGenomic() instead.
                return false;
            case 'warning':
                // Something's wrong. Parse given warning and quit.
                if ($aJSON['validation_warning_1']['validation_warnings']) {
                    foreach ($aJSON['validation_warning_1']['validation_warnings'] as $sError) {
                        // Clean off variant description.
                        // If we'd allow NCs here, we'd have valiations
                        //  warnings of *all* affected transcripts, repeated
                        //  for *all* transcripts. Just a huge array of
                        //  repeated errors. We'd have to make sure the
                        //  errors here would be about the transcript we're
                        //  analyzing now, but since we don't support NCs,
                        //  we don't need to worry about that now.
                        $sError = str_replace(
                            array(
                                $sVariant . ': ',
                                str_replace(array(strstr($sVariant, '(', true), '(', ')'), '', $sVariant) . ': '), '', $sError);

                        // VV has declared their error messages are stable.
                        // This means we can parse them and rely on them not to change.
                        // Add error code if possible, so we won't have to parse the error message again somewhere.
                        if (strpos($sError, 'Invalid genome build has been specified') !== false) {
                            // EBUILD error.
                            $aData['errors']['EBUILD'] = $sError;
                        } elseif ($sError == 'Length implied by coordinates must equal sequence deletion length') {
                            // EINCONSISTENTLENGTH error.
                            $aData['errors']['EINCONSISTENTLENGTH'] = $sError;
                        } elseif (strpos($sError, 'ExonBoundaryError:') !== false) {
                            // We don't catch this here anymore, but keep it in case VV will use flag=warning again.
                            // EINVALIDBOUNDARY error.
                            $aData['errors']['EINVALIDBOUNDARY'] = $sError;
                        } elseif (strpos($sError, ' variant position that lies outside of the reference sequence') !== false
                            || strpos($sError, 'Variant coordinate is out of the bound of CDS region') !== false
                            || strpos($sError, 'The given coordinate is outside the bounds of the reference sequence') !== false) {
                            // ERANGE error. VV throws a range of different messages, depending on using NC-notation or not,
                            //  sending variants 5' or 3' of the transcript, or sending a CDS position that should be in the 3' UTR.
                            // VV doesn't auto-correct CDS positions outside of CDS, we will need to subtract the CDS length ourselves.
                            $aData['errors']['ERANGE'] = $sError;
                        } elseif (strpos($sError, 'does not agree with reference sequence') !== false) {
                            // EREF error.
                            $aData['errors']['EREF'] = $sError;
                        } elseif (strpos($sError, 'No transcript definition for') !== false) {
                            // EREFSEQ error.
                            $aData['errors']['EREFSEQ'] = $sError;
                        } elseif (substr($sError, 0, 5) == 'char ' || $sError == 'insertion length must be 1') {
                            // ESYNTAX error.
                            $aData['errors']['ESYNTAX'] = $sError;
                        } elseif ($sError == 'Uncertain positions are not currently supported') {
                            // EUNCERTAIN error.
                            $aData['errors']['EUNCERTAIN'] = $sError;
                            // FIXME: Asked already for having this in the LOVD endpoint as well - see #92.
                            //  Currently throws an ESYNTAX there.
                        } else {
                            // Unrecognized error.
                            $aData['errors'][] = $sError;
                        }
                    }
                    // When we have errors, we don't need 'data' filled in. Just return what I have.
                    return $aData;
                }
                break;
            // Handled all possible flags, no default needed.
        }
        // Discard the flag value.
        unset($aJSON['flag']);
        // If we'd allow NCs for this function, we'd be ending up with a
        //  possible array of NM mappings. However, since we sent only one
        //  NM, we end up with only one NM here.
        $aJSON = current($aJSON);

        // Add a warning in case we submitted an intronic variant while not
        //  using an NC reference sequence.
        if (preg_match('/^N[MR]_.+[0-9]+[+-][0-9]+/', $sVariant)) {
            $aData['warnings']['WINTRONICWITHOUTNC'] = 'Without using a genomic reference sequence, intronic bases can not be verified.' .
                (empty($aJSON['genome_context_intronic_sequence']) || empty($aJSON['submitted_variant'])? ''
                    : ' Please consider passing the variant as ' .
                    strstr($aJSON['genome_context_intronic_sequence'], ':', true) . strstr($aJSON['submitted_variant'], ':') . '.');
        }

        // Copy the (corrected) DNA value.
        // Handle LRGt submissions.
        if (substr($sVariant, 0, 3) == 'LRG') {
            $aData['data']['DNA'] = $aJSON['hgvs_lrg_transcript_variant'];
            // Also, in this case, we're not interested if new transcripts exist.
            $aJSON['validation_warnings'] = array_filter(
                $aJSON['validation_warnings'],
                function ($sValue)
                {
                    return !preg_match('/^(Reference sequence .+ can be updated to|A more recent version of the selected reference sequence .+ is available)/', $sValue);
                }
            );

        } else {
            $aData['data']['DNA'] = $aJSON['hgvs_transcript_variant'];
        }
        // If description is given but different, then apparently there's been some kind of correction.
        if ($aData['data']['DNA'] && $sVariant != $aData['data']['DNA']) {
            // Check type of correction; silent, WCORRECTED, or WROLLFORWARD.
            if ($aVariantInfo) {
                // Use LOVD's lovd_getVariantInfo() to parse positions and type.
                $aVariantInfoCorrected = lovd_getVariantInfo($aData['data']['DNA']);

                if (array_diff_key($aVariantInfo, array('warnings' => array()))
                    == array_diff_key($aVariantInfoCorrected, array('warnings' => array()))) {
                    // Positions and type are the same, small corrections like delG to del.
                    // We let these pass silently.
                } elseif ($aVariantInfo['type'] != $aVariantInfoCorrected['type']
                    || $aVariantInfo['range'] != $aVariantInfoCorrected['range']) {
                    // An insertion actually being a duplication.
                    // A deletion-insertion which is actually something else.
                    // A c.1_1del that should be c.1del.
                    $aData['warnings']['WCORRECTED'] = 'Variant description has been corrected.';
                } else {
                    // Positions are different, but type is the same.
                    // 3' forwarding of deletions, insertions, duplications
                    //  and deletion-insertion events.
                    $aData['warnings']['WROLLFORWARD'] = 'Variant position' .
                        (!$aVariantInfo['range']? ' has' : 's have') .
                        ' been corrected.';
                }

            } else {
                // Not running as an LOVD object, just complain here.
                $aData['warnings']['WCORRECTED'] = 'Variant description has been corrected.';
            }
        }

        // Although the LOVD endpoint doesn't do this, the VV endpoint
        //  sometimes throws warnings when variants are corrected or mapped
        //  between systems (LRGt to NM, for instance).
        // If we threw a warning or with these inter-system mappings,
        //  we can remove the VV warning.
        if (($aData['warnings'] || substr($sVariant, 0, 3) == 'LRG')
            && $aJSON['validation_warnings']) {
            // Selectively search for the validation warning to remove,
            //  in case there are multiple warnings.
            foreach ($aJSON['validation_warnings'] as $nKey => $sWarning) {
                if (strpos($sWarning, 'automapped to') !== false) {
                    // Toss this error.
                    unset($aJSON['validation_warnings'][$nKey]);
                }
            }
        }

        // Any errors given?
        if ($aJSON['validation_warnings']) {
            // Not a previously seen error, handled through the flag value.
            // We'll assume a warning.

            // This can be a whole list, so loop through it.
            foreach ($aJSON['validation_warnings'] as $nKey => $sWarning) {
                // VV throws two warnings for del100 variants, because of the '100'.
                if ($sWarning == 'Trailing digits are not permitted in HGVS variant descriptions'
                    || strpos($sWarning, 'Refer to http://varnomen.hgvs.org/') !== false) {
                    // We silently skip these warnings.
                    unset($aJSON['validation_warnings'][$nKey]);

                } elseif (strpos($sWarning, ' is pending therefore changes may be made to the LRG reference sequence') !== false
                    || $sWarning == 'RefSeqGene record not available') {
                    // We don't care about this - we started with an NM anyway.
                    unset($aJSON['validation_warnings'][$nKey]);

                } elseif (strpos($sWarning, 'ExonBoundaryError:') !== false) {
                    // EINVALIDBOUNDARY error. This used to throw a flag "warning", but no more, so catch it here.
                    $aData['errors']['EINVALIDBOUNDARY'] = $sWarning;
                    unset($aJSON['validation_warnings'][$nKey]);
                    // Don't accept VV's change of the description.
                    // VV starts moving coordinates around like it's an algebra equation. We don't like that.
                    $aData['data']['DNA'] = '';
                    $aJSON['primary_assembly_loci'] = array();
                    unset($aData['warnings']['WCORRECTED']);
                    unset($aData['warnings']['WROLLFORWARD']);

                } elseif (preg_match(
                    '/^A more recent version of the selected reference sequence (.+) is available \((.+)\):/',
                    $sWarning, $aRegs)) {
                    // This is not that important, but we won't completely discard it, either.
                    $aData['messages']['IREFSEQUPDATED'] = 'Reference sequence ' . $aRegs[1] . ' can be updated to ' . $aRegs[2] . '.';
                    unset($aJSON['validation_warnings'][$nKey]);

                } elseif (strpos($sWarning, 'Caution should be used when reporting the displayed variant descriptions') !== false
                    || strpos($sWarning, 'The displayed variants may be artefacts of aligning') !== false) {
                    // Both these warnings are thrown at the same time when there are mismatches between the
                    //  genomic reference sequence (in general, the genome build) and the transcript.
                    // We could discard one and handle the other, but in this case, we're a bit more flexible.
                    // This message might be repeated when there are gapped alignments with multiple genome builds
                    //  (untested), but currently, we just store one warning message.
                    $aData['warnings']['WALIGNMENTGAPS'] = 'Given alignments may contain artefacts; there is a gapped alignment between transcript and genome build.';
                    unset($aJSON['validation_warnings'][$nKey]);
                }
            }

            // Anything left, gets added to our list.
            $aData['warnings'] += array_values($aJSON['validation_warnings']);
        }

        if ($aData['data']['DNA']) {
            // We silently ignore transcripts here that gave us an error, but not for the liftover feature.
            $aMapping = array(
                'DNA' => substr(strstr($aData['data']['DNA'], ':'), 1),
                'RNA' => 'r.(?)',
                'protein' => '',
            );
            if ($aJSON['hgvs_predicted_protein_consequence']['tlr']) {
                $aMapping['protein'] = substr(strstr($aJSON['hgvs_predicted_protein_consequence']['tlr'], ':'), 1);
            }

            // Try to improve VV's predictions.
            $sTranscript = strstr($sVariant, ':', true);
            $this->getRNAProteinPrediction($aMapping, $sTranscript);
            $aData['data'] = $aMapping;
        }

        // FIXME: PAR region genes, like SHOX, provide X mappings in
        //  primary_assembly_loci and Y mappings in alt_genomic_loci.
        //  Parse them both and add them to the mappings? See VV's #178.
        // Mappings?
        $aData['data']['genomic_mappings'] = array();

        // Since we're in fact using GRCh37 instead of hg19, but our internal codes say hg19...
        // (this won't really affect us unless we'll have MT DNA working, but still...)
        if (isset($aJSON['primary_assembly_loci']['grch37'])) {
            $aJSON['primary_assembly_loci']['hg19'] = $aJSON['primary_assembly_loci']['grch37'];
        }

        foreach ($aJSON['primary_assembly_loci'] as $sBuild => $aMapping) {
            // We support only the builds we have...
            if (!isset($_SETT['human_builds'][$sBuild])) {
                continue;
            }

            // verifyGenomic() makes an array here because multiple values can be expected.
            // We never will have multiple values, so just simplify the output and store a string.
            $aData['data']['genomic_mappings'][$sBuild] = $aMapping['hgvs_genomic_description'];
        }
        return $aData;
    }
}
?>
