<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Ingo Renner <ingo@typo3.org>
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
 * Remote Controller to provide document data for the Index Inspector.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_mod_index_IndexInspectorRemoteController {

	/**
	 * The current page ID.
	 *
	 * @var	integer
	 */
	protected $pageId = 0;

	/**
	 * Search
	 *
	 * @var	tx_solr_Search
	 */
	protected $search = NULL;

	/**
	 * Initialization method to be executed when receiving an ExtDirect call is
	 * received.
	 *
	 * @param	integer	$pageId ID of the current page, the pages' table uid column
	 * @throws	InvalidArgumentException if page ID is 0 or not an integer
	 */
	protected function initialize($pageId) {
		if (empty($pageId) || !is_int($pageId)) {
			throw new InvalidArgumentException('Invalid page ID.', 1303893535);
		}
		$this->pageId = $pageId;

		$this->initializeSearch();
	}

	/**
	 * Initializes the Solr connection.
	 *
	 * @return	void
	 */
	protected function initializeSearch() {
		$connectionManager = t3lib_div::makeInstance('tx_solr_ConnectionManager');
		$solrConnection = $connectionManager->getConnectionByPageId($this->pageId);

		$this->search = t3lib_div::makeInstance('tx_solr_Search', $solrConnection);
	}

	/**
	 * Index Action, provides an array of documents indexed for a given page.
	 *
	 * @param	integer	$pageId The current page's uid.
	 */
	public function indexAction($pageId) {
		$this->initialize($pageId);

		$responseDocuments = array();
		$documents         = $this->getIndexDocuments();

		foreach ($documents as $key => $document) {
			$responseDocuments[$key] = array(
				'id'     => $document->id,
				'type'   => $document->type,
				'title'  => $document->title,
				'__data' => $this->formatDocumentData($document)
			);
		}

		$response = new stdClass();
		$response->success   = TRUE;
		$response->metaData  = $this->buildResponseMetaData();
		$response->numFound  = $this->search->getNumberOfResults();
		$response->documents = $responseDocuments;

		return $response;
	}

	/**
	 * Queries Solr for the current page's documents.
	 *
	 * @return	array	An array of Apache_Solr_Document objects
	 */
	protected function getIndexDocuments() {
		$query = t3lib_div::makeInstance('tx_solr_Query', '');
		$query->setQueryType('standard');
		$query->useRawQueryString(TRUE);
		$query->setQueryString('*:*');
		$query->addFilter('(type:pages AND uid:' . $this->pageId . ') OR (*:* AND pid:' . $this->pageId . ' NOT type:pages)');
		$query->addFilter('siteHash:' . tx_solr_Site::getSiteByPageId($this->pageId)->getSiteHash());
		$query->setFieldList('*');
		$query->setSorting('type asc, title asc');

		$this->search->search($query);

		return $this->search->getResultDocuments();
	}

	/**
	 * Builds the repsonse's meta data / description.
	 *
	 * @return	object	Response meta data
	 */
	protected function buildResponseMetaData() {
		$metaData = new stdClass();
		$metaData->idProperty    = 'id';
		$metaData->root          = 'documents';
		$metaData->totalProperty = 'numFound';
		$metaData->sortInfo      = array(
			'field'     => 'type',
			'direction' => 'ASC'
		);
		$metaData->fields        = $this->buildResponseFieldDescription();

		return $metaData;
	}

	/**
	 * Builds a description of the fields returned to ExtDirect calls.
	 *
	 * @return	array	An array of response field descriptions.
	 */
	protected function buildResponseFieldDescription() {
		$fields     = array();
		$fieldNames = array('id', 'type', 'title', '__data');

		foreach ($fieldNames as $fieldName) {
			$field = new stdClass();
			$field->name = $fieldName;

			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * Formats a document's fields so that they can be iterated by an
	 * ExtJs XTemplate.
	 *
	 * @param	Apache_Solr_Document	$document The Solr document to format
	 * @return	array	Formatted document field data, ready to be used in an ExtJs XTemplate iterator
	 */
	protected function formatDocumentData(Apache_Solr_Document $document) {
		$fields = array();
		foreach ($document as $fieldName => $value) {
			$fields[$fieldName] = $value;
		}
		ksort($fields);

		$sortedData = $fields;

		$formatedData = array();
		foreach ($sortedData as $fieldName => $fieldValue) {
			$formatedData[] = array(
				'fieldName'  => $fieldName,
				'fieldValue' => htmlspecialchars($fieldValue)
			);
		}

		return $formatedData;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/mod_index/class.tx_solr_mod_index_indexinspectorremotecontroller.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/mod_index/class.tx_solr_mod_index_indexinspectorremotecontroller.php']);
}

?>