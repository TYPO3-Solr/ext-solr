<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Ingo Renner <ingo@typo3.org>
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

// TODO do not index items with starttime > indexing time or add starttime support for schema and search

/**
 * A general purpose indexer to be used for indexing of any kind of regular
 * records like tt_news, tt_address, and so on.
 * Specialized indexers can extend this class to handle advanced stuff like
 * category resolution in tt_news or file indexing.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_indexqueue_Indexer {


	# TODO change to singular $document instead of plural $documents


	/**
	 * A Solr service instance to interact with the Solr server
	 *
	 * @var	tx_solr_SolrService
	 */
	protected $solr;

	/**
	 * @var	tx_solr_ConnectionManager
	 */
	protected $connectionManager;

	/**
	 * Content Object
	 *
	 * @var	tslib_cObj
	 */
	protected $contentObject = NULL;

	/**
	 * Holds options for a specific indexer
	 *
	 * @var	array
	 */
	protected $options = array();

	/**
	 * To log or not to log... #Shakespear
	 *
	 * @var	boolean
	 */
	protected $loggingEnabled = FALSE;

	/**
	 * Constructor
	 *
	 * @param	array	Array of indexer options
	 */
	public function __construct(array $options = array()) {
		$this->contentObject = t3lib_div::makeInstance('tslib_cObj');
		$this->options       = $options;

		$this->connectionManager = t3lib_div::makeInstance('tx_solr_ConnectionManager');
	}

	/**
	 * Indexes an item from the indexing queue.
	 *
	 * @param	tx_solr_indexqueue_Item	An index queue item
	 * @return	Apache_Solr_Response	The Apache Solr response
	 */
	public function index(tx_solr_indexqueue_Item $item) {
		$indexed = FALSE;

		$solrConnections = $this->getSolrConnectionsByItem($item);
		$this->setLogging($item);

		foreach ($solrConnections as $systemLanguageUid => $solrConnection) {
			$this->solr = $solrConnection;
			$indexed    = $this->indexItem($item, $systemLanguageUid);
		}

		return $indexed;
	}

	/**
	 * Creates a single Solr Document for an item in a specific language.
	 *
	 * @param	tx_solr_indexqueue_Item	An index queue item to index.
	 * @param	integer	The language to use.
	 * @return	boolean	TRUE if item was indexed successfully, FALSE on failure
	 */
	protected function indexItem(tx_solr_indexqueue_Item $item, $language = 0) {
		$itemIndexed = FALSE;
		$documents   = array();

		$itemDocument = $this->itemToDocument($item, $language);
		$documents[]  = $itemDocument;
		$documents    = array_merge($documents, $this->getAdditionalDocuments(
			$item,
			$language,
			$itemDocument
		));
		$documents = $this->processDocuments($item, $documents);

		$documents = $this->preAddModifyDocuments(
			$item,
			$language,
			$documents
		);

		$response = $this->solr->addDocuments($documents);
		if ($response->getHttpStatus() == 200) {
			$itemIndexed = TRUE;
		}

		$this->log($item, $documents, $response);

		return $itemIndexed;
	}

	/**
	 * Gets the full item record.
	 *
	 * This general record indexer simply gets the record from the item. Other
	 * more specialized indexers may provide more data for there specific item
	 * types.
	 *
	 * @param	tx_solr_indexqueue_Item	The item to be indexed
	 * @param	integer	Language Id (sys_language.uid)
	 * @return	array	The full record with fields of data to be used for indexing
	 */
	protected function getFullItemRecord(tx_solr_indexqueue_Item $item, $language = 0) {
		$itemRecord = $item->getRecord();

		if ($language > 0) {
			$page = t3lib_div::makeInstance('t3lib_pageSelect');
			$page->init(FALSE);

			$itemRecord = $page->getRecordOverlay(
				$item->getType(),
				$itemRecord,
				$language
			);
			$itemRecord['__solr_index_language'] =  $language;
		}

		return $itemRecord;
	}

	/**
	 * Gets the configuration how to process an item's fields for indexing.
	 *
	 * @param	tx_solr_indexqueue_Item	An index queue item
	 * @param	integer	Language ID
	 * @return	array	Configuration array from TypoScript
	 */
	protected function getItemTypeConfiguration(tx_solr_indexqueue_Item $item, $language = 0) {
		$solrConfiguration = tx_solr_Util::getSolrConfigurationFromPageId($item->getRootPageUid(), TRUE, $language);

		return $solrConfiguration['index.']['queue.'][$item->getIndexingConfigurationName() . '.']['fields.'];
	}

	/**
	 * Converts an item array (record) to a Solr document by mapping the
	 * record's fields onto Solr document fields as configured in TypoScript.
	 *
	 * @param	tx_solr_indexqueue_Item	An index queue item
	 * @param	integer	Language Id
	 * @return	Apache_Solr_Document	The Solr document converted from the record
	 */
	protected function itemToDocument(tx_solr_indexqueue_Item $item, $language = 0) {
		$itemIndexingConfiguration = $this->getItemTypeConfiguration($item, $language);
		$itemRecord                = $this->getFullItemRecord($item, $language);

		$document = $this->getBaseDocument($item);

			// setting the document's language
		if (isset($GLOBALS['TCA'][$item->getType()]['ctrl']['languageField'])) {
			$document->setField(
				'language',
				$itemRecord[$GLOBALS['TCA'][$item->getType()]['ctrl']['languageField']]
			);
		} else {
			$document->setField('language', $language);
		}

			// mapping of record fields => solr document fields, resolving cObj
		foreach ($itemIndexingConfiguration as $solrFieldName => $recordFieldName) {
			if (is_array($recordFieldName)) {
					// configuration for a content object, skipping
				continue;
			}

			$fieldValue = $this->resolveFieldValue($item, $itemRecord, $solrFieldName, $language);

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
	 * Creates a Solr document with the basic / core fields set already.
	 *
	 * @param	tx_solr_indexqueue_Item	$item The item to index
	 * @return	Apache_Solr_Document	A basic Solr document
	 */
	protected function getBaseDocument(tx_solr_indexqueue_Item $item) {
		$itemRecord = $this->getFullItemRecord($item, $language);
		$site       = t3lib_div::makeInstance('tx_solr_Site', $item->getRootPageUid());
		$document   = t3lib_div::makeInstance('Apache_Solr_Document');
		/* @var $document Apache_Solr_Document */

			// required fields
		$document->setField('id', tx_solr_Util::getDocumentId(
			$item->getType(),
			$itemRecord['pid'],
			$itemRecord['uid']
		));
		$document->setField('type',   $item->getType());
		$document->setField('appKey', 'EXT:solr');

			// site, siteHash
		$document->setField('site',     $site->getDomain());
		$document->setField('siteHash', $site->getSiteHash());

			// uid, pid
		$document->setField('uid', $itemRecord['uid']);
		$document->setField('pid', $itemRecord['pid']);

			// created, changed
		if (!empty($GLOBALS['TCA'][$item->getType()]['ctrl']['crdate'])) {
			$document->setField('created', $itemRecord[$GLOBALS['TCA'][$item->getType()]['ctrl']['crdate']]);
		}
		if (!empty($GLOBALS['TCA'][$item->getType()]['ctrl']['tstamp'])) {
			$document->setField('changed', $itemRecord[$GLOBALS['TCA'][$item->getType()]['ctrl']['tstamp']]);
		}

			// access, endtime
		$document->setField('access', $this->getAccessRootline($item));
		if (!empty($GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['endtime'])
		&& $itemRecord[$GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['endtime']] != 0) {
			$document->setField('endtime', $itemRecord[$GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['endtime']]);
		}

			// TODO implement start time support
			// start time
/*
		if (!empty($GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['starttime'])
		&& $itemRecord[$GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['starttime']] != 0) {
			$document->setField('starttime', $itemRecord[$GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['starttime']]);
		} else {
			$document->setField('starttime', 'NOW');
		}
*/
		return $document;
	}

	/**
	 * Generates an Access Rootline for an item.
	 *
	 * @param	tx_solr_indexqueue_Item	$item Index Queue item to index.
	 * @return	string	The Access Rootline for the item
	 */
	protected function getAccessRootline(tx_solr_indexqueue_Item $item) {
		$accessRestriction = '0';
		$itemRecord        = $item->getRecord();

			// TODO support access restrictions set on storage page

		if (isset($GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['fe_group'])) {
			$accessRestriction = $itemRecord[$GLOBALS['TCA'][$item->getType()]['ctrl']['enablecolumns']['fe_group']];

			if (empty($accessRestriction)) {
					// public
				$accessRestriction = '0';
			}
		}

		return 'r:' . $accessRestriction;
	}

	/**
	 * Resolves a field to its value depending on its configuration.
	 *
	 * This enables you to configure the indexer to put the item/record through
	 * cObj processing if wanted / needed. Otherwise the plain item/record value
	 * is taken.
	 *
	 * @param	tx_solr_indexqueue_Item	An index queue item
	 * @param	array	The complete item record as an array
	 * @param	string	The Solr field name to resolve the value from the item's record
	 * @param	integer	The language uid of the documents
	 * @return	string	The resolved string value to be indexed
	 */
	protected function resolveFieldValue(tx_solr_indexqueue_Item $item, array $itemRecord, $solrFieldName, $language) {
		$itemIndexingConfiguration = $this->getItemTypeConfiguration($item, $language);
		$fieldValue                = '';

		if (isset($itemIndexingConfiguration[$solrFieldName . '.'])) {
				// configuration found => need to resolve a cObj

				// need to change directory to make IMAGE content objects work in BE context
				// see http://blog.netzelf.de/lang/de/tipps-und-tricks/tslib_cobj-image-im-backend
			$currentWorkingDirectory = getcwd();
			chdir(PATH_site);

			$this->contentObject->start($itemRecord, $item->getType());
			$fieldValue = $this->contentObject->cObjGetSingle(
				$itemIndexingConfiguration[$solrFieldName],
				$itemIndexingConfiguration[$solrFieldName . '.']
			);

			chdir($currentWorkingDirectory);

			if ($this->isSerializedValue($itemIndexingConfiguration, $solrFieldName)) {
				$fieldValue = unserialize($fieldValue);
			}
		} else {
			$fieldValue = $itemRecord[$itemIndexingConfiguration[$solrFieldName]];
		}

		return $fieldValue;
	}

	/**
	 * Sends the documents to the field processing service which takes care of
	 * manipulating fields as defined in the field's configuration.
	 *
	 * @param tx_solr_indexqueue_Item An index queue item
	 * @param array An array of Apache_Solr_Document objects to manipulate.
	 * @return array Array of manipulated Apache_Solr_Document objects.
	 */
	protected function processDocuments(tx_solr_indexqueue_Item $item, array $documents) {
			// needs to respect the TS settings for the page the item is on, conditions may apply
		$solrConfiguration = tx_solr_Util::getSolrConfigurationFromPageId($item->getRootPageUid());
		$fieldProcessingInstructions = $solrConfiguration['index.']['fieldProcessingInstructions.'];

			// same as in the FE indexer
		if (is_array($fieldProcessingInstructions)) {
			$service = t3lib_div::makeInstance('tx_solr_fieldprocessor_Service');
			$service->processDocuments(
				$documents,
				$fieldProcessingInstructions
			);
			$itemRecord['__solr_index_language'] =  $language;
		}

		return $documents;
	}

	/**
	 * Allows third party extensions to provide additional documents which
	 * should be indexed for the current item.
	 *
	 * @param tx_solr_indexqueue_Item The item currently being indexed.
	 * @param integer The language uid currently being indexed.
	 * @param Apache_Solr_Document	$itemDocument The document representing the item for the given language.
	 * @return array An array of additional Apache_Solr_Document objects to index.
	 */
	protected function getAdditionalDocuments(tx_solr_indexqueue_Item $item, $language, Apache_Solr_Document $itemDocument) {
		$documents = array();

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments'] as $classReference) {
				$additionalIndexer = t3lib_div::getUserObj($classReference);

				if ($additionalIndexer instanceof tx_solr_AdditionalIndexQueueItemIndexer) {
					$additionalDocuments = $additionalIndexer->getAdditionalItemDocuments($item, $language, $itemDocument);

					if (is_array($additionalDocuments)) {
						$documents = array_merge($documents, $additionalDocuments);
					}
				} else {
					throw new UnexpectedValueException(
						get_class($additionalIndexer) . ' must implement interface tx_solr_AdditionalIndexQueueItemIndexer',
						1326284551
					);
				}
			}
		}

		return $documents;
	}

	/**
	 * Provides a hook to manipulate documents right before they get added to
	 * the Solr index.
	 *
	 * @param	tx_solr_indexqueue_Item	The item currently being indexed.
	 * @param	integer	The language uid of the documents
	 * @param	array	An array of documents to be indexed
	 * @return	array	An array of modified documents
	 */
	protected function preAddModifyDocuments(tx_solr_indexqueue_Item $item, $language, array $documents) {

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['preAddModifyDocuments'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['preAddModifyDocuments'] as $classReference) {
				$documentsModifier = &t3lib_div::getUserObj($classReference);

				if ($documentsModifier instanceof tx_solr_IndexQueuePageIndexerDocumentsModifier) {
					$documents = $documentsModifier->modifyDocuments($item, $language, $documents);
				} else {
					throw new RuntimeException(
						'The class "' . get_class($documentsModifier)
							. '" registered as document modifier in hook
							preAddModifyDocuments must implement interface
							tx_solr_IndexQueuePageIndexerDocumentsModifier',
						1309522677
					);
				}
			}
		}

		return $documents;
	}


	// Initialization


	/**
	 * Gets the Solr connections applicaple for an item.
	 *
	 * The connections include the default connection and connections to be used
	 * for translations of an item.
	 *
	 * @param	tx_solr_indexqueue_Item	$item An index queue item
	 * @return	array	An array of tx_solr_SolrService connections, the array's keys are the sys_language_uid of the language of the connection
	 */
	protected function getSolrConnectionsByItem(tx_solr_indexqueue_Item $item) {
		$solrConnections = array();

		$pageId = $item->getRootPageUid();
		if ($item->getType() == 'pages') {
			$pageId = $item->getRecordUid();
		}

		$defaultConnection      = $this->connectionManager->getConnectionByPageId($pageId);
		$translationOverlays    = $this->getTranslationOverlaysForPage($pageId);
		$translationConnections = $this->getConnectionsForIndexableLanguages($translationOverlays);

		$solrConnections[0] = $defaultConnection;
		foreach ($translationConnections as $systemLanguageUid => $solrConnection) {
			$solrConnections[$systemLanguageUid] = $solrConnection;
		}

		return $solrConnections;
	}

	/**
	* Finds the alternative page language overlay records for a page.
	*
	* @param	integer	Page ID.
	* @return	array	An array of translation overlays found for the given page.
	*/
	protected function getTranslationOverlaysForPage($pageId) {
		$translationOverlays = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'uid, pid, sys_language_uid',
			'pages_language_overlay',
			'pid = ' . $pageId
				. t3lib_BEfunc::deleteClause('pages_language_overlay')
				. t3lib_BEfunc::BEenableFields('pages_language_overlay')
		);

		return $translationOverlays;
	}

	/**
	* Checks for which languages connections have been configured and returns
	* these connections.
	*
	* @param	array	An array of translation overlays to check for configured connections.
	* @return	array	An array of tx_solr_SolrService connections.
	*/
	protected function getConnectionsForIndexableLanguages(array $translationOverlays) {
		$connections = array();

		foreach ($translationOverlays as $translationOverlay) {
			$pageId     = $translationOverlay['pid'];
			$languageId = $translationOverlay['sys_language_uid'];

			try {
				$connection = $this->connectionManager->getConnectionByPageId($pageId, $languageId);
				$connections[$languageId] = $connection;
			} catch (tx_solr_NoSolrConnectionFoundException $e) {
				// ignore the exception as we seek only those connections
				// actually available
			}
		}

		return $connections;
	}


	// Utility methods


	/**
	 * Enables logging dependent on the configuration of the item's site
	 *
	 * @param	tx_solr_indexqueue_Item	$item An item being indexed
	 * @return	void
	 */
	protected function setLogging(tx_solr_indexqueue_Item $item) {
			// reset
		$this->loggingEnabled = FALSE;

		$solrConfiguration = tx_solr_Util::getSolrConfigurationFromPageId($item->getRootPageUid());

		if (!empty($solrConfiguration['logging.']['indexing'])
			|| !empty($solrConfiguration['logging.']['indexing.']['queue'])
			|| !empty($solrConfiguration['logging.']['indexing.']['queue.'][$item->getIndexingConfigurationName()])
		) {
			$this->loggingEnabled = TRUE;
		}
	}


	/**
	 * Logs the item and what document was created from it
	 *
	 * @param	tx_solr_indexqueue_Item	The item that is being indexed.
	 * @param	array	An array of Solr documents created from the item's data
	 * @param	Apache_Solr_Response	The Solr response for the particular index document
	 */
	protected function log(tx_solr_indexqueue_Item $item, array $itemDocuments, Apache_Solr_Response $response) {
		if (!$this->loggingEnabled) {
			return;
		}

		$message = 'Index Queue indexing ' . $item->getType() . ':'
			. $item->getRecordUid() . ' - ';
		$severity = 0; // info

			// preparing data
		$documents = array();
		foreach ($itemDocuments as $document) {
			$documents[] = (array) $document;
		}

		$logData = array(
			'item'      => (array) $item,
			'documents' => $documents,
			'response'  => (array) $response
		);

		if ($response->getHttpStatus() == 200) {
			$severity = -1;
			$message .= 'Success';
		} else {
			$severity = 3;
			$message .= 'Failure';

			$logData['status']         = $response->getHttpStatus();
			$logData['status message'] = $response->getHttpStatusMessage();
		}

		t3lib_div::devLog($message, 'solr', $severity, $logData);
	}

	/**
	 * Uses a field's configuration to detect whether its value returned by a
	 * content object is expected to be serialized and thus needs to be
	 * unserialized.
	 *
	 * @param	array	$indexingConfiguration Current item's indexing configuration
	 * @param	string	$solrFieldName	Current field being indexed
	 * @return	boolean	TRUE if the value is expected to be serialized, FALSE otherwise
	 */
	protected function isSerializedValue(array $indexingConfiguration, $solrFieldName) {
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


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_indexer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_indexer.php']);
}

?>