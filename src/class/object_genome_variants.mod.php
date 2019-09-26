<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2014-04-03
 * Modified    : 2019-09-26
 * For LOVD    : 3.0-22
 *
 * Copyright   : 2004-2019 Leiden University Medical Center; http://www.LUMC.nl/
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
// Require parent class definition.
require_once ROOT_PATH . 'class/object_genome_variants.php';





class LOVD_GenomeVariantMOD extends LOVD_GenomeVariant {
    // This class extends the basic Object class and it handles the GenomeVariant object.
    var $sObject = 'Genome_Variant';
    var $sCategory = 'VariantOnGenome';
    var $sTable = 'TABLE_VARIANTS';
    var $bShared = false;





    function __construct ()
    {
        // Default constructor.
        global $_CONF, $_SETT;

        // Start with the parent constructor, we'll overwrite some settings afterwards.
        parent::__construct();

        // Overloading the VE to add columns on the curation status and the summary annotation record.
        // SQL code for viewing an entry.
        $this->aSQLViewEntry['SELECT']   = 'vog.*, ' .
                                           'vog.`VariantOnGenome/DBID` AS DBID, ' . // We need a copy before prepareData() messes it up.
                                           'a.name AS allele_, ' .
                                           'GROUP_CONCAT(DISTINCT i.id, ";", i.statusid SEPARATOR ";;") AS __individuals, ' .
                                           'GROUP_CONCAT(s2v.screeningid SEPARATOR "|") AS screeningids, ' .
                                           'sa.id AS summaryannotationid, ' .
                                           'uo.name AS owned_by_, ' .
                                           'uc.name AS created_by_, ' .
                                           'ue.name AS edited_by_, ' .
                                           'curs.name AS curation_status_, ' .
                                           'cons.name AS confirmation_status_';

        if (lovd_verifyInstance('mgha') || lovd_verifyInstance('mgha_cpipe_lymphoma')) {
            $this->aSQLViewEntry['SELECT'] .= ', ROUND(vog.`VariantOnGenome/Sequencing/Depth/Alt/Fraction`, 2) as `VariantOnGenome/Sequencing/Depth/Alt/Fraction` ' .
                ', ROUND(vog.`VariantOnGenome/Sequencing/Father/Depth/Alt/Fraction`, 2) as `VariantOnGenome/Sequencing/Father/Depth/Alt/Fraction` ' .
                ', ROUND(vog.`VariantOnGenome/Sequencing/Mother/Depth/Alt/Fraction`, 2) as `VariantOnGenome/Sequencing/Mother/Depth/Alt/Fraction` ';
        }

        $this->aSQLViewEntry['FROM']     = TABLE_VARIANTS . ' AS vog ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCR2VAR . ' AS s2v ON (vog.id = s2v.variantid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_SCREENINGS . ' AS s ON (s.id = s2v.screeningid) ' .
                                           'LEFT OUTER JOIN ' . TABLE_INDIVIDUALS . ' AS i ON (s.individualid = i.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_ALLELES . ' AS a ON (vog.allele = a.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_SUMMARY_ANNOTATIONS . ' AS sa ON (vog.`VariantOnGenome/DBID` = sa.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uo ON (vog.owned_by = uo.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS uc ON (vog.created_by = uc.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_USERS . ' AS ue ON (vog.edited_by = ue.id) ' .
                                           'LEFT OUTER JOIN ' . TABLE_CURATION_STATUS . ' AS curs ON (vog.curation_statusid = curs.id)' .
                                           'LEFT OUTER JOIN ' . TABLE_CONFIRMATION_STATUS . ' AS cons ON (vog.confirmation_statusid = cons.id)';
        $this->aSQLViewEntry['GROUP_BY'] = 'vog.id';

        // List of columns and (default?) order for viewing an entry.
        $this->aColumnsViewEntry = array_merge(
                 array(
                        'individualid_' => 'Individual ID',
                        'chromosome' => 'Chromosome',
                        'allele_' => 'Allele',
                        'effect_reported' => 'Classification (proposed)',
                        'effect_concluded' => 'Classification (final)',
                        'curation_status_' => 'Curation status',
                        'confirmation_status_' => 'Confirmation status',
                      ),
                 $this->buildViewEntry(),
                 array(
                        'mapping_flags_' => array('Automatic mapping', LEVEL_COLLABORATOR),
                        'average_frequency_' => 'Average frequency (large NGS studies)',
                        'owned_by_' => 'Owner',
                        'status' => array('Variant data status', LEVEL_COLLABORATOR),
                        'created_by_' => array('Created by', LEVEL_COLLABORATOR),
                        'created_date_' => array('Date created', LEVEL_COLLABORATOR),
                        'edited_by_' => array('Last edited by', LEVEL_COLLABORATOR),
                        'edited_date_' => array('Date last edited', LEVEL_COLLABORATOR),
                      ));
        if (!LOVD_plus) {
            unset($this->aColumnsViewEntry['curation_status_']);
            unset($this->aColumnsViewEntry['confirmation_status_']);
        }

        // 2015-10-09; 3.0-14; Add genome build name to the VOG/DNA field.
        $this->aColumnsViewEntry['VariantOnGenome/DNA'] .= ' (Relative to ' . $_CONF['refseq_build'] . ' / ' . $_SETT['human_builds'][$_CONF['refseq_build']]['ncbi_name'] . ')';

        // Because the information is publicly available, remove some columns for the public.
        $this->unsetColsByAuthLevel();
    }





    function buildForm ($sPrefix = '')
    {
        $aForm = parent::buildForm($sPrefix);
        // Remove all except the remarks.
        $aFormFiltered = array();

        foreach($aForm as $sCol => $val) {
            if (strpos($sCol, 'VariantOnGenome/Curation/') !== false) {
                $aFormFiltered[$sCol] = $aForm[$sCol];
            }
        }

        if (isset($aForm['VariantOnGenome/Remarks'])) {
            $aFormFiltered['VariantOnGenome/Remarks'] = $aForm['VariantOnGenome/Remarks'];
        }
        return $aFormFiltered;
    }





    function checkFields ($aData, $zData = false, $aOptions = array())
    {
        parent::checkFields($aData, $zData, $aOptions);
    }





    function getForm ()
    {
        global $_AUTH, $_SETT;
        // Build the form.

        // If we've built the form before, simply return it. Especially imports will repeatedly call checkFields(), which calls getForm().
        if (!empty($this->aFormData)) {
            return parent::getForm();
        }

        // Array which will make up the form table.
        $this->aFormData = array_merge(
                 array(
                        array('POST', '', '', '', '50%', '14', '50%'),
   'effect_reported' => array('Classification (proposed)', '', 'select', 'effect_reported', 1, $_SETT['var_effect'], false, false, false),
  'effect_concluded' => array('Classification (final)', '', 'select', 'effect_concluded', 1, $_SETT['var_effect'], false, false, false)
                      ),
                 $this->buildForm(),
                 array(
     'authorization' => array('Enter your password for authorization', '', 'password', 'password', 20),
                      ));

        if ($_AUTH['level'] < $_SETT['user_level_settings']['set_concluded_effect']) {
            unset($this->aFormData['effect_concluded']);
        }
        if (!lovd_verifyInstance('mgha', false)) {
            unset($this->aFormData['authorization']);
        }

        return parent::getForm();
    }
}
?>
