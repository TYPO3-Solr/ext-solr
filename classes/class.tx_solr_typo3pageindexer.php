<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Ingo Renner <ingo.renner@dkd.de>
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
 * Page Indexer to index TYPO3 pages used by the Index Queue.
 *
 * @author	Ingo Renner <ingo.renner@dkd.de>
 * @author	Daniel Poetzinger <poetzinger@aoemedia.de>
 * @author	Timo Schmidt <schmidt@aoemedia.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_Typo3PageIndexer {

	/**
	 * Solr server connection.
	 *
	 * @var	tx_solr_SolrService
	 */
	protected $solrConnection = NULL;

	/**
	 * Frontend page object (TSFE).
	 *
	 * @var	tslib_fe
	 */
	protected $page = NULL;

	/**
	 * Content extractor to extract content from TYPO3 pages
	 *
	 * @var	tx_solr_Typo3PageContentExtractor
	 */
	protected $contentExtractor = NULL;

	/**
	 * URL to be indexed as the page's URL
	 *
	 * @var	string
	 */
	protected $pageUrl = '';

	/**
	 * The page's access rootline
	 *
	 * @var	tx_solr_access_Rootline
	 */
	protected $pageAccessRootline = NULL;

	/**
	 * ID of the current page's Solr document.
	 *
	 * @var	string
	 */
	protected static $pageSolrDocumentId = '';

	/**
	 * The Solr document generated for the current page.
	 *
	 * @var	Apache_Solr_Document
	 */
	protected static $pageSolrDocument = NULL;

	/**
	 * Documents that have been sent to Solr
	 *
	 * @var	array
	 */
	protected $documentsSentToSolr = array();


	/**
	 * Constructor for class tx_solr_Indexer
	 *
	 * @param	tslib_fe	$page The page to index
	 */
	public function __construct(tslib_fe $page) {
		$this->page        = $page;
		$this->pageUrl     = t3lib_div::getIndpEnv('TYPO3_REQUEST_URL');

		try {
			$this->initializeSolrConnection();
		} catch (Exception $e) {
			$this->log($e->getMessage() . ' Error code: ' . $e->getCode(), 3);

				// TODO extract to a class "ExceptionLogger"
			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
				t3lib_div::devLog('Exception while trying to index a page', 'solr', 3, array(
					$e->__toString()
				));
			}
		}

		$this->contentExtractor = t3lib_div::makeInstance(
			'tx_solr_Typo3PageContentExtractor',
			$this->page->content,
			$this->page->renderCharset
		);

		$this->pageAccessRootline = t3lib_div::makeInstance(
			'tx_solr_access_Rootline',
			''
		);
	}

	/**
	 * Initializes the Solr server connection.
	 *
	 * @throws	Exception when no Solr connection can be established.
	 */
	protected function initializeSolrConnection() {
		$solr = t3lib_div::makeInstance('tx_solr_ConnectionManager')->getConnectionByPageId(
			$this->page->id,
			$this->page->sys_language_uid
		);

			// do not continue if no server is available
		if (!$solr->ping()) {
			throw new Exception(
				'No Solr instance available while trying to index a page.',
				1234790825
			);
		}

		$this->solrConnection = $solr;
	}

	/**
	 * Allows to provide a Solr server connection other than the one
	 * initialized by the constructor.
	 *
	 * @param tx_solr_SolrService $solrConnection Solr connection
	 * @throws Exception if the Solr server cannot be reached
	 */
	public function setSolrConnection(tx_solr_SolrService $solrConnection) {
		if (!$solrConnection->ping()) {
			throw new Exception(
				'Could not connect to Solr server.',
				1323946472
			);
		}

		$this->solrConnection = $solrConnection;
	}

	/**
	 * Indexes a page.
	 *
	 * @return	boolean	TRUE after successfully indexing the page, FALSE on error
	 */
	public function indexPage() {
		$pageIndexed = FALSE;
		$documents   = array(); // this will become usefull as soon as when starting to index individual records instead of whole pages

		if (is_null($this->solrConnection)) {
				// intended early return as it doesn't make sense to continue
				// and waste processing time if the solr server isn't available
				// anyways
				// FIXME use an exception
			return $pageIndexed;
		}

		$pageDocument = $this->getPageDocument();
		$pageDocument = $this->substitutePageDocument($pageDocument);
		self::$pageSolrDocument = $pageDocument;
		$documents[]  = $pageDocument;
		$documents    = $this->getAdditionalDocuments($pageDocument, $documents);
		$this->processDocuments($documents);

		$pageIndexed = $this->addDocumentsToSolrIndex($documents);
		$this->documentsSentToSolr = $documents;

		return $pageIndexed;
	}

	/**
	 * Builds the Solr document for the current page.
	 *
	 * @return	Apache_Solr_Document	A documment representing the page
	 */
	protected function getPageDocument() {
		$document   = t3lib_div::makeInstance('Apache_Solr_Document');
		/* @var	$document	Apache_Solr_Document */
		$site       = tx_solr_Site::getSiteByPageId($this->page->id);
		$pageRecord = $this->page->page;

		self::$pageSolrDocumentId = $documentId = tx_solr_Util::getPageDocumentId(
			$this->page->id,
			$this->page->type,
			$this->page->sys_language_uid,
			$this->getDocumentIdGroups()
		);
		$document->setField('id',          $documentId);
		$document->setField('site',        $site->getDomain());
		$document->setField('siteHash',    $site->getSiteHash());
		$document->setField('appKey',      'EXT:solr');
		$document->setField('type',        'pages');

			// system fields
		$document->setField('uid',         $this->page->id);
		$document->setField('pid',         $pageRecord['pid']);
		$document->setField('typeNum',     $this->page->type);
		$document->setField('created',     $pageRecord['crdate']);
		$document->setField('changed',     $pageRecord['tstamp']);
		$document->setField('rootline',    $this->page->id);

			// access
		$document->setField('access',      (string) $this->pageAccessRootline);
		if ($this->page->page['endtime']) {
			$document->setField('endtime', $pageRecord['endtime']);
		} else {
			$document->setField('endtime', 0);
		}

			// content
		$document->setField('title',       $this->contentExtractor->getPageTitle());
		$document->setField('subTitle',    $pageRecord['subtitle']);
		$document->setField('navTitle',    $pageRecord['nav_title']);
		$document->setField('author',      $pageRecord['author']);
		$document->setField('description', $pageRecord['description']);
		$document->setField('abstract',    $pageRecord['abstract']);
		$document->setField('content',     $this->contentExtractor->getIndexableContent());
		$document->setField('url',         $this->pageUrl);

			// keywords, multi valued
		$keywords = array_unique(t3lib_div::trimExplode(
			',',
			$pageRecord['keywords'],
			TRUE
		));
		foreach ($keywords as $keyword) {
			$document->addField('keywords', $keyword);
		}

			// content from several tags like headers, anchors, ...
		$tagContent = $this->contentExtractor->getTagContent();
		foreach ($tagContent as $fieldName => $fieldValue) {
			$document->setField($fieldName, $fieldValue);
		}

		return $document;
	}

	/**
	 * Adds the collected documents to the Solr index.
	 *
	 * @param	array	$documents An array of Apache_Solr_Document objects.
	 */
	protected function addDocumentsToSolrIndex(array $documents) {
		$documentsAdded = FALSE;

		if (!count($documents)) {
			return $documentsAdded;
		}

		try {
			$this->log('Adding ' . count($documents) . ' documents.', 0, $documents);

				// chunk adds by 20
			$documentChunks = array_chunk($documents, 20);
			foreach ($documentChunks as $documentChunk) {
				$response = $this->solrConnection->addDocuments($documentChunk);

				if ($response->getHttpStatus() != 200) {
					$transportException =  new Apache_Solr_HttpTransportException($response);
					throw new RuntimeException('Solr Request failed.', 1331834983, $transportException);
				}
			}

			$documentsAdded = TRUE;
		} catch (Exception $e) {
			$this->log($e->getMessage() . ' Error code: ' . $e->getCode(), 2);

			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
				t3lib_div::devLog('Exception while adding documents', 'solr', 3, array(
					$e->__toString()
				));
			}
		}

		return $documentsAdded;
	}

	/**
	 * Allows third party extensions to replace or modify the page document
	 * created by this indexer.
	 *
	 * @param	Apache_Solr_Document	$pageDocument The page document created by this indexer.
	 * @return	Apache_Solr_Document	An Apache Solr document representing the currently indexed page
	 */
	protected function substitutePageDocument(Apache_Solr_Document $pageDocument) {
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'] as $classReference) {
				$substituteIndexer = t3lib_div::getUserObj($classReference);

				if ($substituteIndexer instanceof tx_solr_SubstitutePageIndexer) {
					$substituteDocument = $substituteIndexer->getPageDocument($pageDocument);

					if ($substituteDocument instanceof Apache_Solr_Document) {
						$pageDocument = $substituteDocument;
					} else {
						throw new UnexpectedValueException(
							'The document returned by ' . get_class($substituteIndexer) . ' is not a valid Apache_Solr_Document document.',
							1310490952
						);
					}
				} else {
					throw new UnexpectedValueException(
						get_class($substituteIndexer) . ' must implement interface tx_solr_SubstitutePageIndexer',
						1310491001
					);
				}
			}
		}

		return $pageDocument;
	}

	/**
	 * Allows third party extensions to provide additional documents which
	 * should be indexed for the current page.
	 *
	 * @param	Apache_Solr_Document	$pageDocument The main document representing this page.
	 * @param	array	$existingDocuments An array of documents already created for this page.
	 * @return	array	An array of additional Apache_Solr_Document objects to index
	 */
	protected function getAdditionalDocuments(Apache_Solr_Document $pageDocument, array $existingDocuments) {
		$documents = $existingDocuments;

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments'] as $classReference) {
				$additionalIndexer = t3lib_div::getUserObj($classReference);

				if ($additionalIndexer instanceof tx_solr_AdditionalPageIndexer) {
					$additionalDocuments = $additionalIndexer->getAdditionalPageDocuments($pageDocument, $documents);

					if (is_array($additionalDocuments)) {
						$documents = array_merge($documents, $additionalDocuments);
					}
				} else {
					throw new UnexpectedValueException(
						get_class($additionalIndexer) . ' must implement interface tx_solr_AdditionalPageIndexer',
						1310491024
					);
				}
			}
		}

		return $documents;
	}

	/**
	 * Sends the given documents to the field processing service which takes
	 * care of manipulating fields as defined in the field's configuration.
	 *
	 * @param	array	An array of documents to manipulate
	 */
	protected function processDocuments(array $documents) {
		if (is_array($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['fieldProcessingInstructions.'])) {
			$service = t3lib_div::makeInstance('tx_solr_fieldprocessor_Service');
			$service->processDocuments(
				$documents,
				$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['fieldProcessingInstructions.']
			);
		}
	}


	// Logging
	// TODO replace by a central logger


	/**
	 * Logs messages to devlog and TS log (admin panel)
	 *
	 * @param	string		Message to set
	 * @param	integer		Error number
	 * @return	void
	 */
	protected function log($message, $errorNum = 0, array $data = array()) {
		if (is_object($GLOBALS['TT'])) {
			$GLOBALS['TT']->setTSlogMessage('tx_solr: ' . $message, $errorNum);
		}

		if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['indexing']) {
			if (!empty($data)) {
				$logData = array();
				foreach ($data as $value) {
					$logData[] = (array) $value;
				}
			}

			t3lib_div::devLog($message, 'solr', $errorNum, $logData);
		}
	}


	// Misc


	/**
	 * Gets the current page's URL.
	 *
	 * @return	string	URL of the current page.
	 */
	public function getPageUrl() {
		return $this->pageUrl;
	}

	/**
	 * Sets the URL to use for the page document.
	 *
	 * @param	string	$url The page's URL.
	 */
	public function setPageUrl($url) {
		$this->pageUrl = $url;
	}

	/**
	 * Gets the page's access rootline.
	 *
	 * @return	tx_solr_access_Rootline The page's access rootline
	 */
	public function getPageAccessRootline() {
		return $this->pageAccessRootline;
	}

	/**
	 * Sets the page's access rootline.
	 *
	 * @param	tx_solr_access_Rootline	$accessRootline The page's access rootline
	 */
	public function setPageAccessRootline(tx_solr_access_Rootline $accessRootline) {
		$this->pageAccessRootline = $accessRootline;
	}

	/**
	 * Gets the current page's Solr document ID.
	 *
	 * @return	string|NULL	The page's Solr document ID or NULL in case no document was generated yet.
	 */
	public static function getPageSolrDocumentId() {
		return self::$pageSolrDocumentId;
	}

	/**
	 * Gets the Solr document generated for the current page.
	 *
	 * @return	Apache_Solr_Document|NULL The page's Solr document or NULL if it has not been generated yet.
	 */
	public static function getPageSolrDocument() {
		return self::$pageSolrDocument;
	}

	/**
	 * Gets the documents that have been sent to Solr
	 *
	 * @return	array	An array of Apache_Solr_Document objects
	 */
	public function getDocumentsSentToSolr() {
		return $this->documentsSentToSolr;
	}

	/**
	 * Gets a comma separated list of frontend user groups to use for the
	 * document ID.
	 *
	 * @return	string	A comma separated list of frontend user groups.
	 */
	protected function getDocumentIdGroups() {
		$groups = $this->pageAccessRootline->getGroups();
		$groups = tx_solr_access_Rootline::cleanGroupArray($groups);

		if (empty($groups)) {
			$groups[] = 0;
		}

		$groups = implode(',', $groups);

		return $groups;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_typo3pageindexer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_typo3pageindexer.php']);
}

?>