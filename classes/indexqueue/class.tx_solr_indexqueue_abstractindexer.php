<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Ingo Renner <ingo@typo3.org>
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
 * An abstract indexer class to collect a few common methods shared with other
 * indexers.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
abstract class tx_solr_indexqueue_AbstractIndexer {

	/**
	 * Holds the type of the data to be indexed, usually that is the table name.
	 *
	 * @var string
	 */
	protected $type = '';


	/**
	 * Adds fields to the document as defined in $indexingConfiguration
	 *
	 * @param Apache_Solr_Document $document base document to add fields to
	 * @param array $indexingConfiguration Indexing configuration / mapping
	 * @return Apache_Solr_Document Modified document with added fields
	 */
	protected function addDocumentFieldsFromTyposcript(Apache_Solr_Document $document, array $indexingConfiguration, array $data) {

			// mapping of record fields => solr document fields, resolving cObj
		foreach ($indexingConfiguration as $solrFieldName => $recordFieldName) {
			if (is_array($recordFieldName)) {
					// configuration for a content object, skipping
				continue;
			}

			$fieldValue = $this->resolveFieldValue($indexingConfiguration, $solrFieldName, $data);

			if (is_array($fieldValue)) {
					// multi value
				foreach ($fieldValue as $multiValue) {
					$document->addField($solrFieldName, $multiValue);
				}
			} else {
				$document->setField($solrFieldName, $fieldValue);
			}
		}

		return $document;
	}

	/**
	 * Resolves a field to its value depending on its configuration.
	 *
	 * This enables you to configure the indexer to put the item/record through
	 * cObj processing if wanted / needed. Otherwise the plain item/record value
	 * is taken.
	 *
	 * @param array $indexingConfiguration Indexing configuration as defined in plugin.tx_solr_index.queue.[indexingConfigurationName].fields
	 * @param string $solrFieldName A Solr field name that is configured in the indexing configuration
	 * @param array $data A record or item's data
	 * @return string The resolved string value to be indexed
	 */
	protected function resolveFieldValue(array $indexingConfiguration, $solrFieldName, array $data) {
		$fieldValue    = '';
		$contentObject = t3lib_div::makeInstance('tslib_cObj');

		if (isset($indexingConfiguration[$solrFieldName . '.'])) {
				// configuration found => need to resolve a cObj

				// need to change directory to make IMAGE content objects work in BE context
				// see http://blog.netzelf.de/lang/de/tipps-und-tricks/tslib_cobj-image-im-backend
			$backupWorkingDirectory = getcwd();
			chdir(PATH_site);

			$contentObject->start($data, $this->type);
			$fieldValue = $contentObject->cObjGetSingle(
				$indexingConfiguration[$solrFieldName],
				$indexingConfiguration[$solrFieldName . '.']
			);

			chdir($backupWorkingDirectory);

			if ($this->isSerializedValue($indexingConfiguration, $solrFieldName)) {
				$fieldValue = unserialize($fieldValue);
			}
		} else {
			$fieldValue = $data[$indexingConfiguration[$solrFieldName]];
		}

		return $fieldValue;
	}


	// Utility methods


	/**
	 * Uses a field's configuration to detect whether its value returned by a
	 * content object is expected to be serialized and thus needs to be
	 * unserialized.
	 *
	 * @param array $indexingConfiguration Current item's indexing configuration
	 * @param string $solrFieldName	Current field being indexed
	 * @return boolean TRUE if the value is expected to be serialized, FALSE otherwise
	 */
	public static function isSerializedValue(array $indexingConfiguration, $solrFieldName) {
		$isSerialized = FALSE;

			// SOLR_MULTIVALUE - always returns serialized array
		if ($indexingConfiguration[$solrFieldName] == tx_solr_contentobject_Multivalue::CONTENT_OBJECT_NAME) {
			$isSerialized = TRUE;
		}

			// SOLR_RELATION - returns serialized array if multiValue option is set
		if ($indexingConfiguration[$solrFieldName] == tx_solr_contentobject_Relation::CONTENT_OBJECT_NAME
			&& !empty($indexingConfiguration[$solrFieldName . '.']['multiValue'])
		) {
			$isSerialized = TRUE;
		}

		return $isSerialized;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_abstractindexer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_abstractindexer.php']);
}

?>