<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2011 Ingo Renner <ingo@typo3.org>
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
 * A special purpose indexer to index pages.
 *
 * In the case of pages we can't directly index the page records, we need to
 * retrieve the content that belongs to a page from tt_content, too. Also
 * plugins may be included on a page and thus may need to be executed.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_indexqueue_PageIndexer extends tx_solr_indexqueue_Indexer {

	/**
	 * Indexes an item from the indexing queue.
	 *
	 * @param	tx_solr_indexqueue_Item	An index queue item
	 * @return	Apache_Solr_Response	The Apache Solr response
	 */
	public function index(tx_solr_indexqueue_Item $item) {

			// check whether we should move on at all
		if (!$this->isPageIndexable($item)) {
			return FALSE;
		}

		$solrConnections = $this->getSolrConnectionsByItem($item);
		foreach ($solrConnections as $systemLanguageUid => $solrConnection) {
			$contentAccessGroups = $this->getAccessGroupsFromContent($item, $systemLanguageUid);

			if (empty($contentAccessGroups)) {
					// might be an empty page w/no content elements or some TYPO3 error / bug
					// FIXME logging needed
				continue;
			}

			foreach ($contentAccessGroups as $userGroup) {
				$response = $this->indexPage($item, $systemLanguageUid, $userGroup);
			}
		}

		$indexed = TRUE;

		// FIXME do some logging!!!

		return $indexed;
	}

	/**
	 * Creates a single Solr Document for a page in a specific language and for
	 * a specific frontend user group.
	 *
	 * @param	tx_solr_indexqueue_Item	The index queue item representing the page.
	 * @param	integer	The language to use.
	 * @param	integer	The frontend user group to use.
	 * @return	tx_solr_indexqueue_PageIndexerResponse	page indexer response
	 */
	protected function indexPage(tx_solr_indexqueue_Item $item, $language = 0, $userGroup = 0) {
		$accessRootline = $this->getAccessRootline($item, $language, $userGroup);

		$request = t3lib_div::makeInstance('tx_solr_indexqueue_PageIndexerRequest');
		$request->setIndexQueueItem($item);
		$request->addAction('indexPage');
		$request->setParameter('accessRootline', (string) $accessRootline);

		if (!empty($this->options['authorization.'])) {
			$request->setAuthorizationCredentials(
				$this->options['authorization.']['username'],
				$this->options['authorization.']['password']
			);
		}

		$response = $request->send($this->getDataUrl(
			$item->getRecordUid(),
			$language
		));

			// TODO log response, success / failure

		return $response;
	}

	/**
	 * Checks whether we can index this page.
	 *
	 * @param	tx_solr_indexqueue_Item	The page we want to index encapsulated in an index queue item
	 * @return	boolean	True if we can index this page, FALSE otherwise
	 */
	protected function isPageIndexable(tx_solr_indexqueue_Item $item) {

			// TODO do we still need this?
			// shouldn't those be sorted out by the record monitor / garbage collector already?

		$isIndexable = TRUE;
		$record = $item->getRecord();

		if (isset($GLOBALS['TCA']['pages']['ctrl']['enablecolumns']['disabled'])
		&& $record[$GLOBALS['TCA']['pages']['ctrl']['enablecolumns']['disabled']]) {
			$isIndexable = FALSE;
		}

		return $isIndexable;
	}


	// Utility methods


	/**
	 * Determines a page ID's URL.
	 *
	 * Tries to find a domain record to use to build an URL for a given page ID
	 * and then actually build and return the page URL.
	 *
	 * @param	integer	The page id
	 * @param	integer	The language id
	 * @return	string	URL for a page ID
	 */
	protected function getDataUrl($pageId, $language = 0) {
		$dataUrl = '';
		$scheme  = 'http';
		$path    = '/';

		$rootline = t3lib_BEfunc::BEgetRootLine($pageId);
		$host     = t3lib_BEfunc::firstDomainRecord($rootline);

			// deprecated
		if (!empty($this->options['scheme'])) {
			t3lib_div::devLog(
				'Using deprecated option "scheme" to set the scheme (http / https) for the page indexer frontend helper. Use plugin.tx_solr.index.queue.pages.indexer.frontendDataHelper.scheme instead',
				'solr',
				2
			);
			$scheme = $this->options['scheme'];
		}

			// check whether we should use ssl / https
		if (!empty($this->options['frontendDataHelper.']['scheme'])) {
			$scheme = $this->options['frontendDataHelper.']['scheme'];
		}

			// overwriting the host
		if (!empty($this->options['frontendDataHelper.']['host'])) {
			$host = $this->options['frontendDataHelper.']['host'];
		}

			// setting a path if TYPO3 is installed in a sub directory
		if (!empty($this->options['frontendDataHelper.']['path'])) {
			$path = $this->options['frontendDataHelper.']['path'];
		}

		$dataUrl = $scheme . '://' . $host . $path . 'index.php?id=' . $pageId;
		if (!t3lib_div::isValidUrl($dataUrl)) {
			t3lib_div::devLog(
				'Could not create a valid URL to get frontend data while trying to index a page.',
				'solr',
				3,
				array(
					'constructed URL' => $dataUrl,
					'scheme'          => $scheme,
					'host'            => $host,
					'path'            => $path,
					'page ID'         => $pageId,
					'indexer options' => $this->options
				)
			);

			throw new RuntimeException(
				'Could not create a valid URL to get frontend data while trying to index a page. Created URL: ' . $dataUrl,
				1311080805
			);
		}

		if ($language) {
			$dataUrl .= '&L=' . $language;
		}


		if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueuePageIndexer']['dataUrlModifier']) {
			$dataUrlModifier = t3lib_div::getUserObj($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueuePageIndexer']['dataUrlModifier']);

			if ($dataUrlModifier instanceof tx_solr_IndexQueuePageIndexerDataUrlModifier) {
				$dataUrl = $dataUrlModifier->modifyDataUrl($dataUrl, array(
					'scheme'   => $scheme,
					'host'     => $host,
					'path'     => $path,
					'pageId'   => $pageId,
					'language' => $language
				));
			} else {
					throw t3lib_div::makeInstance('RuntimeException',
						$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueuePageIndexer']['dataUrlModifier']
						. ' is not an implementation of tx_solr_IndexQueuePageIndexerDataUrlModifier',
						1290523345
					) ;

			}
		}

		return $dataUrl;
	}


	#
	# Frontend User Groups Access
	#

	/**
	 * Generates a page document's "Access Rootline".
	 *
	 * The Access Rootline collects frontend user group access restrcitions set
	 * for pages up in a page's rootline extended to sub-pages.
	 *
	 * The format is like this:
	 * pageId1:group1,group2|groupId2:group3|c:group1,group4,groupN
	 *
	 * The single elements of the access rootline are separated by a pipe
	 * character. All but the last elements represent pages, the last element
	 * defines the access restrictions applied to the page's content elements
	 * and records shown on the page.
	 * Each page element is composed by the page ID of the page setting frontend
	 * user access restrictions, a colon, and a comma separated list of frontend
	 * user group IDs restricting access to the page.
	 * The content access element does not have a page ID, instead it replaces
	 * the ID by a lower case C.
	 *
	 * @param	tx_solr_indexqueue_Item	Index queue item representing the current page
	 * @param	integer	The sys_language_uid language ID
	 * @param	integer	The user group to use for the content access rootline element. Optional, will be determined automatically if not set.
	 * @return	string	An Access Rootline.
	 */
	protected function getAccessRootline(tx_solr_indexqueue_Item $item, $language = 0, $contentAccessGroup = FALSE) {
		static $accessRootlineCache;

		$accessRootlineCacheEntryId = $item->getRecordUid() . '|' . $language;
		if ($contentAccessGroup !== FALSE) {
			$accessRootlineCacheEntryId .= '|' . $contentAccessGroup;
		}

		if (!isset($accessRootlineCache[$accessRootlineCacheEntryId])) {
			$accessRootline = tx_solr_access_Rootline::getAccessRootlineByPageId(
				$item->getRecordUid()
			);

				// current page's content access groups
			$contentAccessGroups = array($contentAccessGroup);
			if ($contentAccessGroup === FALSE) {
				$contentAccessGroups = $this->getAccessGroupsFromContent($item, $language);
			}
			$accessRootline->push(t3lib_div::makeInstance(
				'tx_solr_access_RootlineElement',
				'c:' . implode(',', $contentAccessGroups)
			));

			$accessRootlineCache[$accessRootlineCacheEntryId] = $accessRootline;
		}

		return $accessRootlineCache[$accessRootlineCacheEntryId];
	}

	/**
	 * Finds the FE user groups used on a page including all groups of content
	 * elements and groups of records of extensions that have correctly been
	 * pushed through tslib_cObj during rendering.
	 *
	 * @param	tx_solr_indexqueue_Item	Index queue item representing the current page to get the user groups from
	 * @param	integer	The sys_language_uid language ID
	 * @return	array	Array of user group IDs
	 */
	protected function getAccessGroupsFromContent(tx_solr_indexqueue_Item $item, $language = 0) {
		static $accessGroupsCache;

		$accessGroupsCacheEntryId = $item->getRecordUid() . '|' . $language;
		if (!isset($accessGroupsCache[$accessGroupsCacheEntryId])) {
			$request = t3lib_div::makeInstance('tx_solr_indexqueue_PageIndexerRequest');
			$request->setIndexQueueItem($item);
			$request->addAction('findUserGroups');

			if (!empty($this->options['authorization.'])) {
				$request->setAuthorizationCredentials(
					$this->options['authorization.']['username'],
					$this->options['authorization.']['password']
				);
			}

			$response = $request->send($this->getDataUrl(
				$item->getRecordUid(),
				$language
			));

			$groups = $response->getActionResult('findUserGroups');
			if (is_array($groups)) {
				$accessGroupsCache[$accessGroupsCacheEntryId] = $groups;
			}
		}

		return $accessGroupsCache[$accessGroupsCacheEntryId];
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_pageindexer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_pageindexer.php']);
}

?>