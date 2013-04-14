<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Ingo Renner <ingo.renner@dkd.de>
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
 * relationTableSortingField: field in an mm relation table to sort by, usually "sorting"
 *
 * @author	Ingo Renner <ingo.renner@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_contentobject_Relation {

	const CONTENT_OBJECT_NAME = 'SOLR_RELATION';

	/**
	 * Content object configuration
	 *
	 * @var array
	 */
	protected $configuration = array();


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
	public function cObjGetSingleExt($name, array $configuration, $TyposcriptKey, $parentContentObject) {
		$result = '';

		$this->configuration = $configuration;

		$relatedItems = $this->getRelatedItems($parentContentObject);

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
	protected function getRelatedItems(tslib_cObj $parentContentObject) {
		$relatedItems = array();

		list($localTableName, $localRecordUid) = explode(':', $parentContentObject->currentRecord);

		$GLOBALS['TSFE']->includeTCA();
		if (version_compare(TYPO3_version, '6.0.0', '>=')) {
			\TYPO3\CMS\Core\Utility\GeneralUtility::loadTCA($localTableName);
		} else {
			t3lib_div::loadTCA($localTableName);
		}
		$localTableTca  = $GLOBALS['TCA'][$localTableName];

		$localFieldName = $this->configuration['localField'];
		$localFieldTca  = $localTableTca['columns'][$localFieldName];

		if (isset($localFieldTca['config']['MM']) && trim($localFieldTca['config']['MM']) !== '') {
			$relatedItems = $this->getRelatedItemsFromMMTable($localTableName, $localRecordUid, $localFieldTca);
		} else {
			$relatedItems = $this->getRelatedItemsFromForeignTable($localFieldName, $localRecordUid, $localFieldTca, $parentContentObject);
		}

		return $relatedItems;
	}

	/**
	 * Gets the related items from a table using a 1:n relation.
	 *
	 * @param string $localFieldName Local table field name
	 * @param integer $localRecordUid Local record uid
	 * @param array $localFieldTca The local table's TCA
	 * @param tslib_cObj $parentContentObject parent content object
	 * @return array Array of related items, values already resolved from related records
	 */
	protected function getRelatedItemsFromForeignTable($localFieldName, $localRecordUid, array $localFieldTca, tslib_cObj $parentContentObject) {
		$relatedItems = array();

		$foreignTableName = $localFieldTca['config']['foreign_table'];
		t3lib_div::loadTCA($foreignTableName);
		$foreignTableTca  = $GLOBALS['TCA'][$foreignTableName];

		$foreignTableLabelField = $this->resolveForeignTableLabelField($foreignTableTca);

		$whereClause = '';
		if (!empty($localFieldTca['config']['foreign_field'])) {
			$foreignTableField = $localFieldTca['config']['foreign_field'];

			$whereClause = $foreignTableName . '.' . $foreignTableField . ' = ' . (int) $localRecordUid;
		} else {
			$foreignTableUids = t3lib_div::intExplode(',', $parentContentObject->data[$localFieldName]);

			if (count($foreignTableUids) > 1) {
				$whereClause = $foreignTableName . '.uid IN (' . implode(',', $foreignTableUids) . ')';
			} else {
				$whereClause = $foreignTableName . '.uid = ' . (int) array_shift($foreignTableUids);
			}
		}
		$pageSelector = t3lib_div::makeInstance('t3lib_pageSelect');
		$whereClause .= $pageSelector->enableFields( $foreignTableName );

		$relatedRecordsResource = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			$foreignTableName . '.' . $foreignTableLabelField,
			$foreignTableName,
			$whereClause
		);

		while ($relatedRecord = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($relatedRecordsResource)) {
			$relatedItems[] = $this->resolveRelatedValue(
				$relatedRecord,
				$foreignTableTca,
				$foreignTableLabelField,
				$parentContentObject
			);
		}

		$GLOBALS['TYPO3_DB']->sql_free_result($relatedRecordsResource);

		return $relatedItems;
	}

	/**
	 * Resolves the value of the related field. If the related field's value is
	 * a relation itself, this method takes care of resolving it recursively.
	 *
	 * @param array $relatedRecord Related record as array
	 * @param unknown_type $foreignTableTca TCA of the related table
	 * @param unknown_type $foreignTableLabelField Field name of the foreign label field
	 * @param unknown_type $parentContentObject cObject
	 */
	protected function resolveRelatedValue(array $relatedRecord, $foreignTableTca, $foreignTableLabelField, $parentContentObject) {

		$value = $relatedRecord[$foreignTableLabelField];

		if (isset($foreignTableTca['columns'][$foreignTableLabelField]['config']['foreign_table'])) {
				// backup
			$backupRecord                             = $parentContentObject->data;
			$backupField                              = $this->configuration['foreignLabelField'];
			$parentContentObject->data                = $relatedRecord;
			$this->configuration['foreignLabelField'] = '';

				// recursion
			$value = array_pop($this->getRelatedItemsFromForeignTable(
				$foreignTableLabelField,
				intval($value),
				$foreignTableTca['columns'][$foreignTableLabelField],
				$parentContentObject
			));

				// restore
			$this->configuration['foreignLabelField'] = $backupField;
			$parentContentObject->data                = $backupRecord;
		}

		return $value;
	}

	/**
	 * Gets the related items from a table using a n:m relation.
	 *
	 * @param string $localTableName Local table name
	 * @param integer $localRecordUid Local record uid
	 * @param array $localFieldTca The local table's TCA
	 * @return array Array of related items, values already resolved from related records
	 */
	protected function getRelatedItemsFromMMTable($localTableName, $localRecordUid, array $localFieldTca) {
		$relatedItems = array();

		$mmTableName = $localFieldTca['config']['MM'];

		$mmTableSortingField = '';
		if (isset($this->configuration['relationTableSortingField'])) {
			$mmTableSortingField = $mmTableName . '.' . $this->configuration['relationTableSortingField'];
		}

		$foreignTableName = $localFieldTca['config']['foreign_table'];
		t3lib_div::loadTCA($foreignTableName);
		$foreignTableTca  = $GLOBALS['TCA'][$foreignTableName];

		$foreignTableLabelField = $this->resolveForeignTableLabelField($foreignTableTca);

		$relatedRecordsResource = $GLOBALS['TYPO3_DB']->exec_SELECT_mm_query(
			$foreignTableName . '.' . $foreignTableLabelField,
			$localTableName,
			$mmTableName,
			$foreignTableName,
			'AND ' . $localTableName . '.uid = ' . (int) $localRecordUid . $GLOBALS['TSFE']->sys_page->enableFields($foreignTableName),
			'',
			$mmTableSortingField
		);

		while ($relatedRecord = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($relatedRecordsResource)) {
			$relatedItems[] = $relatedRecord[$foreignTableLabelField];
		}

		return $relatedItems;
	}

	/**
	 * Resolves the field to use as the related item's label depending on TCA
	 * and TypoScript configuration
	 *
	 * @param array $foreignTableTca The foreign table's TCA
	 * @return string The field to use for the related item's label
	 */
	protected function resolveForeignTableLabelField(array $foreignTableTca) {
		$foreignTableLabelField = $foreignTableTca['ctrl']['label'];

		if (!empty($this->configuration['foreignLabelField'])) {
			$foreignTableLabelField = $this->configuration['foreignLabelField'];
		}

		return $foreignTableLabelField;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/contentobject/class.tx_solr_contentobject_relation.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/contentobject/class.tx_solr_contentobject_relation.php']);
}

?>