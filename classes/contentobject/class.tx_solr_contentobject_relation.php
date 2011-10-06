<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Ingo Renner <ingo.renner@dkd.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * A content object (cObj) to resolve relations between database records
 *
 * Configuration options:
 *
 * localField: the record's field to use to resolve relations
 * foreignLabelField: Usually the label field to retrieve from the related records is determined automatically using TCA, using this option the desired field can be specified explicitly
 * multiValue: whether to return related records suitable for a multi value field
 * singleValueGlue: when not using multiValue, the related records need to be concatened using a glue string, by default this is ", ". Using this option a custom glue can be specified. The custom value must be wrapped by pipe (|) characters.
 *
 * @author	Ingo Renner <ingo.renner@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_contentobject_Relation {

	const CONTENT_OBJECT_NAME = 'SOLR_RELATION';

	/**
	 * Executes the SOLR_RELATION content object.
	 *
	 * Resolves relations between records. Currently supported relations are
	 * TYPO3-style m:n relations.
	 * May resolve single value and multi value relations.
	 *
	 * @param	string	$name content object name 'SOLR_RELATION'
	 * @param	array	$configuration for the content object
	 * @param	string	$TyposcriptKey not used
	 * @param	tslib_cObj	$contentObject parent content object
	 * @return	string	serialized array representation of the given list
	 */
	public function cObjGetSingleExt($name, array $configuration, $TyposcriptKey, $contentObject) {
		$result = '';

		$relatedItems = $this->getRelatedItems($configuration, $contentObject);

		if (empty($configuration['multiValue'])) {
				// single value, need to concatenate related items
			$singleValueGlue = ', ';

			if (!empty($configuration['singleValueGlue'])) {
				$singleValueGlue = trim($configuration['singleValueGlue'], '|');
			}

			$result = implode($singleValueGlue, $relatedItems);
		} else {
				// multi value, need to serialize as content objects must return strings
			$result = serialize($relatedItems);
		}

		return $result;
	}

	/**
	 * Gets the related items of the current record's configured field.
	 *
	 * @param	array	$configuration for the content object
	 * @param	tslib_cObj	$parentContentObject parent content object
	 * @return	array	Array of related items, values already resolved from related records
	 */
	protected function getRelatedItems($configuration, $parentContentObject) {
		$relatedItems = array();

		list($localTableName, $localRecordUid) = explode(':', $parentContentObject->currentRecord);

		t3lib_div::loadTCA($localTableName);
		$localTableTca  = $GLOBALS['TCA'][$localTableName];

		$localFieldName = $configuration['localField'];
		$localFieldTca  = $localTableTca['columns'][$localFieldName];

		$mmTableName = $localFieldTca['config']['MM'];

		$foreignTableName = $localFieldTca['config']['foreign_table'];
		t3lib_div::loadTCA($foreignTableName);
		$foreignTableTca  = $GLOBALS['TCA'][$foreignTableName];

		$foreignTableLabelField = $foreignTableTca['ctrl']['label'];
		if (!empty($configuration['foreignLabelField'])) {
			$foreignTableLabelField = $configuration['foreignLabelField'];
		}

		$relatedRecordsResource = $GLOBALS['TYPO3_DB']->exec_SELECT_mm_query(
			$foreignTableName . '.' . $foreignTableLabelField,
			$localTableName,
			$mmTableName,
			$foreignTableName,
			'AND ' . $localTableName . '.uid = ' . (int) $localRecordUid
		);

		while ($relatedRecord = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($relatedRecordsResource)) {
			$relatedItems[] = $relatedRecord[$foreignTableLabelField];
		}

		return $relatedItems;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/contentobject/class.tx_solr_contentobject_relation.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/contentobject/class.tx_solr_contentobject_relation.php']);
}

?>