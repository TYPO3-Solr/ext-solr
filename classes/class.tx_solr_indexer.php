<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Ingo Renner <ingo.renner@dkd.de>
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
 * Indexer
 *
 * @author	Ingo Renner <ingo.renner@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_Indexer {

	protected $page;

	/**
	 * Constructor for class tx_solr_Indexer
	 */
	public function __construct() {

	}

	/**
	 * Handles the indexing of the page content during post processing of
	 * a generated page.
	 *
	 * @param	tslib_fe	Typoscript Front End
	 */
	public function hook_indexContent(tslib_fe $page) {
		$this->page = $page;

		if ($page->config['config']['index_enable'] && $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['enablePageIndexing']) {
			try {

					// do some checks first
				if ($page->page['no_search']) {
					throw new Exception(
						'Index page? No, The "No Search" flag has been set in the page properties!',
						1234523946
					);
				}

				if ($page->no_cache) {
					throw new Exception(
						'Index page? No, page was set to "no_cache" and so cannot be indexed.',
						1234524030
					);
				}

				if ($page->sys_language_uid != $page->sys_language_content) {
					throw new Exception(
						'Index page? No, ->sys_language_uid was different from sys_language_content which indicates that the page contains fall-back content and that would be falsely indexed as localized content.',
						1234524095
					);
				}

				if ($GLOBALS['TSFE']->beUserLogin && !$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['enableIndexingWhileBeUserLoggedIn']) {
					throw new Exception(
						'Index page? No, Detected a BE user being logged in.',
						1246444055
					);
				}

					// now index the page
				$this->indexPage($page->id);

			} catch (Exception $e) {
				$this->log($e->getMessage() . ' Error code: ' . $e->getCode(), 3);

				if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
					t3lib_div::devLog('Exception while trying to index a page', 'tx_solr', 3, array(
						$e->__toString()
					));
				}
			}
		}
	}

	/**
	 * Indexes a page.
	 *
	 * @param	integer	page uid
	 * @return	boolean	true after successfully indexing the page, false on error
	 * @todo	transform this into a more generic "indexRecord()" function later
	 */
	protected function indexPage($pageId) {
		$documents = array(); // this will become usefull as soon as when starting to index individual records instead of whole pages

		try {
				// get a solr instance
			$solr = t3lib_div::makeInstance('tx_solr_SolrService');

				// do not continue if no server is available
			if (!$solr->ping()) {
				throw new Exception(
					'No Solr instance available during indexing.',
					1234790825
				);
			}
		} catch (Exception $e) {
			$this->log($e->getMessage() . ' Error code: ' . $e->getCode(), 3);

			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
				t3lib_div::devLog('exception while trying to index a page', 'tx_solr', 3, array(
					$e->__toString()
				));
			}

				// intended early return as it doesn't make sense to continue
				// and waste processing time if the solr server isn't available
				// anyways
			return false;
		}

		$pageDocument = $this->pageToDocument($pageId);

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'] as $classReference) {
				$substituteIndexer = &t3lib_div::getUserObj($classReference);

				if ($substituteIndexer instanceof tx_solr_SubstitutePageIndexer) {
					$substituteDocument = $substituteIndexer->getPageDocument();

					if ($substituteDocument instanceof Apache_Solr_Document) {
						$pageDocument = $substituteDocument;
					} else {
						// TODO throw an exception
					}
				} else {
					// TODO throw an exception
				}
			}
		}
		$documents[] = $pageDocument;

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments'] as $classReference) {
				$additionalIndexer = &t3lib_div::getUserObj($classReference);

				if ($additionalIndexer instanceof tx_solr_AdditionalIndexer) {
					$additionalDocuments = $additionalIndexer->getAdditionalDocuments();

					if (is_array($additionalDocuments)) {
						$documents = array_merge($documents, $additionalDocuments);
					} else {
						// TODO throw an exception
					}
				} else {
					// TODO throw an exception
				}
			}
		}

		$documents = $this->addTypoScriptConfiguredFieldsToDocuments($documents);

		if (count($documents)) {
			try {
				$this->log('Adding ' . count($documents) . ' documents.', 0, $documents);

					// chunk adds by 20
				$chunkedDocuments = array_chunk($documents, 20);
				foreach ($chunkedDocuments as $documentsToAdd) {
					$solr->addDocuments($documentsToAdd);
				}

				return true;
			} catch (Exception $e) {
				$this->log($e->getMessage() . ' Error code: ' . $e->getCode(), 2);

				if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
					t3lib_div::devLog('Exception while adding documents', 'tx_solr', 3, array(
						$e->__toString()
					));
				}
			}
		}

		return false;
	}

	/**
	 * Given a page id, returns a document representing that page.
	 *
	 * @param	integer	page id
	 * @return	Apache_Solr_Document	a documment representing the page
	 */
	protected function pageToDocument($pageId) {
		$page     = $GLOBALS['TSFE'];
		$document = false;

		$title = $this->getPageTitle();
		$body  = $this->getPageBody();

		$content = $this->getIndexableContent($body);

			// converts the content to utf8 if necessary
		$content = $GLOBALS['TSFE']->csConvObj->utf8_encode($content, $GLOBALS['TSFE']->renderCharset);
		$content = $this->stripControlChars($content);


		$document = t3lib_div::makeInstance('Apache_Solr_Document');

		$document->addField('id', tx_solr_Util::getPageDocumentId(
			$page->id,
			$page->type,
			$page->sys_language_uid,
			$page->gr_list
		));
		$document->addField('site',     t3lib_div::getIndpEnv('TYPO3_SITE_URL'));
		$document->addField('siteHash', tx_solr_Util::getSiteHash());
		$document->addField('appKey',   'EXT:solr'); // TODO add a more meaningful app key
		$document->addField('type',     'pages');

			// system fields
		$document->addField('uid',      $page->id);
		$document->addField('pid',      $page->page['pid']);
		$document->addField('typeNum',  $page->type);
		$document->addField('created',  tx_solr_Util::timestampToIso($page->page['crdate']));
		$document->addField('changed',  tx_solr_Util::timestampToIso($page->page['tstamp']));
		$document->addField('language', $page->sys_language_uid);

			// access
			// TODO calculate access from content elements
		$access = t3lib_div::intExplode(',', $page->page['fe_group']);
		if ($page->page['fe_group'] == '-1') {
			$access[] = 0;	// make sure that access settings are complete
		}
		foreach ($access as $group) {
			$document->addField('group', $group);
		}

		if ($page->page['endtime']) {
			$document->addField('endtime', tx_solr_Util::timestampToIso($page->page['endtime']));
		}

			// content
		$title = ($page->indexedDocTitle ? $page->indexedDocTitle :
			($page->altPageTitle ? $page->altPageTitle : $page->page['title']));
		$document->addField('title',       $GLOBALS['TSFE']->csConvObj->utf8_encode($title, $GLOBALS['TSFE']->renderCharset));
		$document->addField('subTitle',    $GLOBALS['TSFE']->csConvObj->utf8_encode($page->page['subtitle'], $GLOBALS['TSFE']->renderCharset));
		$document->addField('navTitle',    $GLOBALS['TSFE']->csConvObj->utf8_encode($page->page['nav_title'], $GLOBALS['TSFE']->renderCharset));
		$document->addField('author',      $GLOBALS['TSFE']->csConvObj->utf8_encode($page->page['author'], $GLOBALS['TSFE']->renderCharset));
		$keywords = array_unique(t3lib_div::trimExplode(',', $GLOBALS['TSFE']->csConvObj->utf8_encode($page->page['keywords'], $GLOBALS['TSFE']->renderCharset)));
		foreach ($keywords as $keyword) {
			$document->addField('keywords', $keyword);
		}
		$document->addField('description', trim($GLOBALS['TSFE']->csConvObj->utf8_encode($page->page['description'], $GLOBALS['TSFE']->renderCharset)));
		$document->addField('abstract',    trim($GLOBALS['TSFE']->csConvObj->utf8_encode($page->page['abstract'], $GLOBALS['TSFE']->renderCharset)));

			// content field
		$contentField = $this->cleanContent($content);
		$contentField = html_entity_decode($contentField, ENT_QUOTES, 'UTF-8');
		$contentField = strip_tags($contentField); // after entity decoding we might have tags again
		$contentField = trim($contentField);
		$document->addField('content', $contentField);

		$document->addField('url', t3lib_div::getIndpEnv('TYPO3_REQUEST_URL'));

		$document = $this->addTagsToDocument($document, $content);

			// TODO add a hook to allow post processing of the document

		return $document;
	}

	/**
	 * Extracts HTML tag content from the content and adds it to the document to boost fields.
	 *
	 * @param	Apache_Solr_Document	the document
	 * @param	string	content to parse for special HTML tags
	 * @return	Apache_Solr_Document	the document with tags added
	 */
	static public function addTagsToDocument(Apache_Solr_Document $document, $content) {

		$tagMapping = array(
			'h1'      => 'tagsH1',
			'h2'      => 'tagsH2H3',
			'h3'      => 'tagsH2H3',
			'h4'      => 'tagsH4H5H6',
			'h5'      => 'tagsH4H5H6',
			'h6'      => 'tagsH4H5H6',
			'u'       => 'tagsInline',
			'b'       => 'tagsInline',
			'strong'  => 'tagsInline',
			'i'       => 'tagsInline',
			'em'      => 'tagsInline',
			'a'       => 'tagsA',
		);

			// strip all ignored tags
		$content = strip_tags($content, '<' . implode('><', array_keys($tagMapping)) . '>');

		preg_match_all('@<('. implode('|', array_keys($tagMapping)) .')[^>]*>(.*)</\1>@Ui', $content, $matches);
		foreach ($matches[1] as $key => $tag) {
				// We don't want to index links auto-generated by the url filter.
			if ($tag != 'a' || !preg_match('@(?:http://|https://|ftp://|mailto:|smb://|afp://|file://|gopher://|news://|ssl://|sslv2://|sslv3://|tls://|tcp://|udp://|www\.)[a-zA-Z0-9]+@', $matches[2][$key])) {
				$document->{$tagMapping[$tag]} .= ' '. $matches[2][$key];
			}
		}

		return $document;
	}

	/**
	 * Adds additional fields to the document, that have been defined through
	 * TypoScript.
	 *
	 * @param	array	an array of Apache_Solr_Document objects
	 * @return 	array	an array of Apache_Solr_Document objects with additional fields
	 */
	protected function addTypoScriptConfiguredFieldsToDocuments(array $documents) {
		$additionalFields = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['additionalFields.'];

		if (is_array($additionalFields)) {
			foreach ($documents as $document) {
				foreach ($additionalFields as $fieldName => $fieldValue) {
						// if its just the configuration array skip this field
					if (is_array($fieldValue)) {
						continue;
					}
						// support for cObject if the value is a configuration
					if (is_array($additionalFields[$fieldName . '.'])) {
						$fieldValue = $GLOBALS['TSFE']->cObj->cObjGetSingle(
							$fieldValue,
							$additionalFields[$fieldName . '.']
						);
					}

					if (substr($fieldName, -2) == '_s') {
							// utf8 encode string fields
						$document->addField(
							$fieldName,
							$GLOBALS['TSFE']->csConvObj->utf8_encode(
								$fieldValue,
								$GLOBALS['TSFE']->renderCharset
							)
						);
					} else {
						$document->addField($fieldName, $fieldValue);
					}
				}
			}
		}

		return $documents;
	}


	// retrieving content


	/**
	 * Strips control characters that cause Jetty/Solr to fail.
	 *
	 * @param	string	the content to sanitize
	 * @return	string	the sanitized content
	 * @see	http://w3.org/International/questions/qa-forms-utf-8.html
	 */
	static public function stripControlChars($content) {
			// Printable utf-8 does not include any of these chars below x7F
 		return preg_replace('@[\x00-\x08\x0B\x0C\x0E-\x1F]@', ' ', $content);
	}

	/**
	 * Strips html tags and also control characters that cause Jetty/Solr to fail.
	 *
	 * @param	string	content to clean
	 * @return	string	content cleaned from tags and special characters
	 */
	static public function cleanContent($content) {
		$content = self::stripControlChars($content);
		$content = str_replace('>', '> ', $content); // prevents concatenated words after stripping tags
		$content = strip_tags($content);
		$content = str_replace(array("\t", "\n", "\r"), array(), $content);

		return $content;
	}

	/**
	 * Removes content that shouldn't be indexed according to TYPO3SEARCH-tags.
	 *
	 * @param	string		HTML Content, passed by reference
	 * @return	boolean		Returns true if a TYPOSEARCH_ tag was found, otherwise false.
	 */
	protected function getIndexableContent($content) {
		$explodedContent  = preg_split('/\<\!\-\-[\s]?TYPO3SEARCH_/', $content);
		$indexableContent = '';

		if(count($explodedContent) > 1) {

			foreach($explodedContent as $explodedContentPart) {
				$contentPart = explode('-->', $explodedContentPart, 2);

				if (trim($contentPart[0]) == 'begin') {
					$indexableContent .= $contentPart[1];
					$previousExplodedContentPart = '';
				} elseif (trim($contentPart[0]) == 'end') {
					$indexableContent .= $previousExplodedContentPart;
				} else {
					$previousExplodedContentPart = $explodedContentPart;
				}
			}
		}

		return $indexableContent;
	}

	/**
	 * Retrieves the page's title by checking the indexedDocTitle, altPageTitle,
	 * and regular page title - in that order.
	 *
	 * @return	string	the page's title
	 */
	protected function getPageTitle() {
		$page      = $GLOBALS['TSFE'];
		$pageTitle = '';

		if ($page->indexedDocTitle) {
			$pageTitle = $page->indexedDocTitle;
		} elseif ($page->altPageTitle) {
			$pageTitle = $page->altPageTitle;
		} else {
			$pageTite = $page->page['title'];
		}

		return $pageTitle;
	}

	/**
	 * Retrieves the page's body
	 *
	 * @return	string	the page's body
	 */
	protected function getPageBody() {
		$pageContent = $GLOBALS['TSFE']->content;

		return stristr($pageContent, '<body');
	}


	// Logging


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

		if (!empty($data)) {
			$logData = array();
			foreach ($data as $value) {
				$logData[] = (array) $value;
			}
		}

		if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['indexing']) {
			t3lib_div::devLog($message, 'tx_solr', $errorNum, $logData);
		}
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_indexer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_indexer.php']);
}

?>