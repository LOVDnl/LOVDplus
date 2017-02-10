<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2010-03-04
 * Modified    : 2016-08-31
 * For LOVD    : 3.0-17
 *
 * Copyright   : 2004-2016 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmers : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *               Ing. Ivar C. Lugtenburg <I.C.Lugtenburg@LUMC.nl>
 *               M. Kroon <m.kroon@lumc.nl>
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

function lovd_describeFormType ($zData) {
    // Returns sensible form type information based on form type code.

    if (!is_array($zData) || empty($zData['form_type']) || substr_count($zData['form_type'], '|') < 2) {
        return false;
    }

    $aFormType = explode('|', $zData['form_type']);
    $sFormType = ucfirst($aFormType[2]);
    switch ($aFormType[2]) {
        case 'text':
        case 'password':
            $sFormType .= ' (' . $aFormType[3] . ' chars)';
            break;
        case 'textarea':
            $sFormType .= ' (' . $aFormType[3] . ' cols, ' . $aFormType[4] . ' rows)';
            break;
        case 'select':
            $nOptions = substr_count($zData['select_options'], "\r\n") + 1;
            if ($nOptions) {
                $sFormType .= ' (' . ($aFormType[5] == 'true'? 'multiple; ' : '') . $nOptions . ' option' . ($nOptions == 1? '' : 's') . ')';
            }
            break;
    }

    return $sFormType;
}





function lovd_getCategoryCustomColFromName ($sName)
{
    // Returns category (object type) for custom column fieldname. Fieldname
    // may be anything used in code or SQL to refer to that column.
    // Examples:
    //      "Phenotype/Age" => "Phenotype"
    //      "vot.`VariantOnTranscript/DNA`" => "VariantOnTranscript"
    //      "`VariantOnTranscript/Enzyme/Kinase_activity`" =>
    //          "VariantOnTranscript"

    preg_match('/^(\w+\.)?`?(\w+)\/.+$/', $sName, $aMatches);
    if ($aMatches) {
        return $aMatches[2];
    }

    // Unable to parse name.
    return false;
}





function lovd_getTableInfoByCategory ($sCategory)
{
    // Returns information on the LOVD table that holds the data for this given
    // custom column category.

    $aTables =
         array(
                'Individual' =>
                     array(
                            'table_sql' => TABLE_INDIVIDUALS,
                            'table_name' => 'Individual',
                            'table_alias' => 'i',
                            'shared' => false,
                            'unit' => '',
                          ),
                'Phenotype' =>
                     array(
                            'table_sql' => TABLE_PHENOTYPES,
                            'table_name' => 'Phenotype',
                            'table_alias' => 'p',
                            'shared' => (LOVD_plus? false : true),
                            'unit' => 'disease', // Is also used to determine the key (diseaseid).
                          ),
                'Screening' =>
                     array(
                            'table_sql' => TABLE_SCREENINGS,
                            'table_name' => 'Screening',
                            'table_alias' => 's',
                            'shared' => false,
                            'unit' => '',
                          ),
                'VariantOnGenome' =>
                     array(
                            'table_sql' => TABLE_VARIANTS,
                            'table_name' => 'Genomic Variant',
                            'table_alias' => 'vog',
                            'shared' => false,
                            'unit' => '',
                          ),
                'VariantOnTranscript' =>
                     array(
                            'table_sql' => TABLE_VARIANTS_ON_TRANSCRIPTS,
                            'table_name' => 'Transcript Variant',
                            'table_alias' => 'vot',
                            'shared' => (LOVD_plus? false : true),
                            'unit' => 'gene', // Is also used to determine the key (geneid).
                          ),
                'SummaryAnnotation' =>
                     array(
                            'table_sql' => TABLE_SUMMARY_ANNOTATIONS,
                            'table_sql_rev' => TABLE_SUMMARY_ANNOTATIONS_REV,
                            'table_name' => 'Summary Annotations',
                            'shared' => false,
                            'unit' => '',
                          ),
              );
    if (!array_key_exists($sCategory, $aTables)) {
        return false;
    }
    return $aTables[$sCategory];
}






function lovd_getActivateCustomColumnQuery($aColumns = array(), $bActivate = false) {
    // Create custom columns based on the column listed in inc-sql-columns.php file
    
    global $aColSQL;

    // Make sure it's an array.
    if (!empty($aColumns) && !is_array($aColumns)) {
        $aColumns = array($aColumns);
    }

    // When no column is specified, we want to make sure $nColsLEft never reaches zero.
    $nColsLeft = (empty($aColumns)? -1 : count($aColumns));

    $aSql = array();
    foreach ($aColSQL as $sInsertSQL) {
        // Make sure we stop looping once we have processed all columns listed in $aColumns.
        if ($nColsLeft === 0) {
            break;
        }

        // Find the beginning of field values of an SQL INSERT query
        // INSERT INTO table_name VALUES(...)
        $nIndex = strpos($sInsertSQL, '(');
        $aValues = array();
        if ($nIndex !== false) {
            // Get the string inside brackets VALUES(...)
            $sInsertFields = rtrim(substr($sInsertSQL, $nIndex+1), ')');
            
            // Split the string into an array
            $aValues = str_getcsv($sInsertFields);

            // When no column is specified, process all columns
            if (empty($aColumns) || in_array($aValues[0], $aColumns)) {
                $nColsLeft--;

                $aSql[] = $sInsertSQL;

                // Only activate column of they a hgvs and standard column
                if ($bActivate && ($aValues[3] == '1' || $aValues[4] == '1')) {
                    $sColId = stripslashes(trim($aValues[0], ' "'));
                    $sColType = stripslashes(trim($aValues[10], ' "'));

                    list($sCategory) = explode('/', $sColId);
                    $aTableInfo = lovd_getTableInfoByCategory($sCategory);

                    $aSql = array_merge($aSql, array(
                        'INSERT INTO ' . TABLE_ACTIVE_COLS . ' VALUES ("' . $sColId . '", "00000", NOW())',
                        'ALTER TABLE ' . $aTableInfo['table_sql'] . ' ADD COLUMN `' . $sColId . '` ' . $sColType
                    ));

                    if (!empty($aTableInfo['table_sql_rev'])) {
                        $aSql[] = 'ALTER TABLE ' . $aTableInfo['table_sql_rev'] . ' ADD COLUMN `' . $sColId . '` ' . $sColType;
                    }
                }
            }
        }
    }

    return $aSql;
}

?>
