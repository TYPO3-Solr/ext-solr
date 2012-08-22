<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Daniel Poetzinger <poetzinger@aoemedia.de>
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
 * Service class that modifies fields in a Apache_Solr_Document, used for
 * common field processing during indexing or resolving
 *
 * @author	Daniel Poetzinger <poetzinger@aoemedia.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_fieldprocessor_Service {

	/**
	 * Modifies a list of documents
	 *
	 * @param	Apache_Solr_Document[]	$documents
	 * @param	array	$processingConfiguration
	 */
	public function processDocuments(array $documents, array $processingConfiguration) {
		foreach ($documents as $document) {
			$this->processDocument($document, $processingConfiguration);
		}
	}

	/**
	 * modifies a document according to the given configuration
	 *
	 * @param	Apache_Solr_Document	$document
	 * @param	array	$processingConfiguration
	 */
	public function processDocument(Apache_Solr_Document $document, array $processingConfiguration) {
		foreach ($processingConfiguration as $fieldName => $instruction) {
			$fieldInformation = $document->getField($fieldName);
			$isSingleValueField = FALSE;

			if ($fieldInformation !== FALSE) {
				$fieldValue = $fieldInformation['value'];

				if (!is_array($fieldValue)) {
						// turn single value field into multi value field
					$fieldValue = array($fieldValue);
					$isSingleValueField = TRUE;
				}

				switch ($instruction) {
					case 'timestampToIsoDate':
						$processor  = t3lib_div::makeInstance('tx_solr_fieldprocessor_TimestampToIsoDate');
						$fieldValue = $processor->process($fieldValue);
						break;
					case 'pathToHierarchy':
						$processor  = t3lib_div::makeInstance('tx_solr_fieldprocessor_PathToHierarchy');
						$fieldValue = $processor->process($fieldValue);
						break;
					case 'pageUidToHierarchy':
						$processor  = t3lib_div::makeInstance('tx_solr_fieldprocessor_PageUidToHierarchy');
						$fieldValue = $processor->process($fieldValue);
						break;
					case 'uppercase':
						$fieldValue = array_map('strtoupper', $fieldValue);
						break;
				}

				if ($isSingleValueField) {
						// turn multi value field back into single value field
					$fieldValue = $fieldValue[0];
				}

				$document->setField($fieldName, $fieldValue);
			}
		}
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/fieldprocessor/class.tx_solr_fieldprocessor_service.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/fieldprocessor/class.tx_solr_fieldprocessor_service.php']);
}

?>