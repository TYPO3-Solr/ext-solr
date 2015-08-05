<?php
namespace ApacheSolrForTypo3\Solr\FieldProcessor;

/***************************************************************
*  Copyright notice
*
*  (c) 2009-2015 Daniel Poetzinger <poetzinger@aoemedia.de>
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

use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * Service class that modifies fields in a Apache_Solr_Document, used for
 * common field processing during indexing or resolving
 *
 * @author Daniel Poetzinger <poetzinger@aoemedia.de>
 * @package TYPO3
 * @subpackage solr
 */
class Service {

	/**
	 * Modifies a list of documents
	 *
	 * @param \Apache_Solr_Document[] $documents
	 * @param array $processingConfiguration
	 */
	public function processDocuments(array $documents, array $processingConfiguration) {
		foreach ($documents as $document) {
			$this->processDocument($document, $processingConfiguration);
		}
	}

	/**
	 * modifies a document according to the given configuration
	 *
	 * @param \Apache_Solr_Document $document
	 * @param array $processingConfiguration
	 */
	public function processDocument(\Apache_Solr_Document $document, array $processingConfiguration) {
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
					case 'timestampToUtcIsoDate':
						$processor  = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\FieldProcessor\\TimestampToUtcIsoDate');
						$fieldValue = $processor->process($fieldValue);
						break;
					case 'timestampToIsoDate':
						$processor  = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\FieldProcessor\\TimestampToIsoDate');
						$fieldValue = $processor->process($fieldValue);
						break;
					case 'pathToHierarchy':
						$processor  = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\FieldProcessor\\PathToHierarchy');
						$fieldValue = $processor->process($fieldValue);
						break;
					case 'pageUidToHierarchy':
						$processor  = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\FieldProcessor\\PageUidToHierarchy');
						$fieldValue = $processor->process($fieldValue);
						break;
					case 'categoryUidToHierarchy':
						$processor = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\FieldProcessor\\CategoryUidToHierarchy');
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

