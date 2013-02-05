<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2013 Ingo Renner <ingo.renner@dkd.de>
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
 * Utility class for tx_solr
 *
 * @author	Ingo Renner <ingo.renner@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_Util {

	/**
	 * Generates a document id for documents representing page records.
	 *
	 * @param integer $uid The page's uid
	 * @param integer $typeNum The page's typeNum
	 * @param integer $language the language id, defaults to 0
	 * @param string $accessGroups comma separated list of uids of groups that have access to that page
	 * @return string The document id for that page
	 */
	public static function getPageDocumentId($uid, $typeNum = 0, $language = 0, $accessGroups = '0,-1') {
		$documentId = self::getDocumentId(
			'pages',
			$uid,
			$uid,
			$typeNum . '/' . $language . '/' . $accessGroups
		);

		return $documentId;
	}

	/**
	 * Returns the document id for a given file.
	 *
	 * @param tx_solr_fileindexer_File $file	The file
	 * @return string The document id for that file
	 */
	public static function getFileDocumentId(tx_solr_fileindexer_File $file) {

		try {
			$documentId = self::getDocumentId(
				'tx_solr_file',
				$file->getReferencePageId(),
				$file->getFileIndexQueueId(),
				str_replace('/', ':', $file->getMimeType())
			);
		} catch (InvalidArgumentException $e) {
			if ($e->getCode() == 1309272922) {
				$documentId = self::getDocumentId(
					'tx_solr_file',
					$file->getReferenceRootPageId(),
					$file->getFileIndexQueueId(),
					str_replace('/', ':', $file->getMimeType())
				);
			} else {
				throw $e;
			}
		}

		return $documentId;
	}

	/**
	 * Generates a document id in the form $siteHash/$type/$uid.
	 *
	 * @param string $table The records table name
	 * @param integer $pid The record's pid
	 * @param integer $uid The record's uid
	 * @param string $additionalIdParameters Additional ID parameters
	 * @return string A document id
	 */
	public static function getDocumentId($table, $pid, $uid, $additionalIdParameters = '') {
		$siteHash = tx_solr_Site::getSiteByPageId($pid)->getSiteHash();

		$documentId = $siteHash . '/' . $table . '/' . $uid;
		if (!empty($additionalIdParameters)) {
			$documentId .= '/' . $additionalIdParameters;
		}

		return $documentId;
	}

	/**
	 * Converts a date from unix timestamp to ISO 8601 format.
	 *
	 * @param	integer	unix timestamp
	 * @return	string	the date in ISO 8601 format
	 */
	public static function timestampToIso($timestamp) {
		return date('Y-m-d\TH:i:s\Z', $timestamp);
	}

	/**
	 * Returns given word as CamelCased.
	 *
	 * Converts a word like "send_email" to "SendEmail". It
	 * will remove non alphanumeric characters from the word, so
	 * "who's online" will be converted to "WhoSOnline"
	 *
	 * @param	string	Word to convert to camel case
	 * @return	string	UpperCamelCasedWord
	 */
	public static function camelize($word) {
		return str_replace(' ', '', ucwords(preg_replace('![^A-Z^a-z^0-9]+!', ' ', $word)));
	}

	/**
	 * Returns a given CamelCasedString as an lowercase string with underscores.
	 * Example: Converts BlogExample to blog_example, and minimalValue to minimal_value
	 *
	 * @param	string		$string: String to be converted to lowercase underscore
	 * @return	string		lowercase_and_underscored_string
	 */
	public static function camelCaseToLowerCaseUnderscored($string) {
		return strtolower(preg_replace('/(?<=\w)([A-Z])/', '_\\1', $string));
	}

	/**
	 * Returns a given string with underscores as UpperCamelCase.
	 * Example: Converts blog_example to BlogExample
	 *
	 * @param	string		$string: String to be converted to camel case
	 * @return	string		UpperCamelCasedWord
	 */
	public static function underscoredToUpperCamelCase($string) {
		return str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($string))));
	}

	/**
	 * Lowercases the first letter of a string.
	 *
	 * @param	string	String to lowercase the first letter
	 * @return	string	Input string with lowercased first letter
	 */
	public static function lcfirst($string) {
		$string{0} = strtolower($string{0});

		return $string;
	}

	/**
	 * Shortcut to retrieve the TypoScript configuration for EXT:solr
	 * (plugin.tx_solr) from TSFE.
	 *
	 * @return array EXT:solr TypoScript configuration from plugin.tx_solr
	 */
	public static function getSolrConfiguration() {
			// TODO if in BE, create a fake TSFE and retrieve the configuration
			// TODO merge flexform configuration
		return $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.'];
	}

	/**
	 * Gets the Solr configuration for a specific root page id.
	 * To be used from the backend.
	 *
	 * @param	integer	Id of the (root) page to get the Solr configuration from.
	 * @param	boolean	Optionally initializes a full TSFE to get the configuration, defaults to FALSE
	 * @param	integer	System language uid, optional, defaults to 0
	 * @return	array	The Solr configuration for the requested tree.
	 */
	public static function getSolrConfigurationFromPageId($pageId, $initializeTsfe = FALSE, $language = 0) {
		return self::getConfigurationFromPageId($pageId, 'plugin.tx_solr', $initializeTsfe, $language);
	}

	/**
	 * Loads the TypoScript configuration for a given page id and language.
	 * Language usage may be disabled to get the default TypoScript
	 * configuration.
	 *
	 * @param	integer	Id of the (root) page to get the Solr configuration from.
	 * @param	string	The TypoScript configuration path to retrieve.
	 * @param	boolean	Optionally initializes a full TSFE to get the configuration, defaults to FALSE
	 * @param	integer|boolean	System language uid or FALSE to disable language usage, optional, defaults to 0
	 * @return	array	The Solr configuration for the requested tree.
	 */
	public static function getConfigurationFromPageId($pageId, $path, $initializeTsfe = FALSE, $language = 0) {
		static $configurationCache = array();
		$configuration             = array();

			// If we're on UID 0, we cannot retrieve a configuration currently.
			// getRootline() below throws an exception (since #typo3-60 )
			// as UID 0 cannot have any parent rootline by design.
		if ($pageId == 0) {
			return array();
		}

			// TODO needs some caching -> caching framework?
		$cacheId = $pageId . '|' . $path . '|' . $language;

		if ($initializeTsfe) {
			self::initializeTsfe($pageId, $language);

			$tmpl = t3lib_div::makeInstance('t3lib_tsparser_ext');
			$configuration = $tmpl->ext_getSetup(
				$GLOBALS['TSFE']->tmpl->setup,
				$path
			);

			$configurationCache[$cacheId] = $configuration[0];
		} else {
			if (!isset($configurationCache[$cacheId])) {
				if (is_int($language)) {
					t3lib_div::_GETset($language, 'L');
				}

				$pageSelect = t3lib_div::makeInstance('t3lib_pageSelect');
				$rootLine   = $pageSelect->getRootLine($pageId);

				if (empty($GLOBALS['TSFE']->sys_page)) {
					if (empty($GLOBALS['TSFE'])) {
						$GLOBALS['TSFE'] = new stdClass();
					}

					$GLOBALS['TSFE']->sys_page = $pageSelect;
				}

				$tmpl = t3lib_div::makeInstance('t3lib_tsparser_ext');
				$tmpl->tt_track = FALSE; // Do not log time-performance information
				$tmpl->init();
				$tmpl->runThroughTemplates($rootLine); // This generates the constants/config + hierarchy info for the template.
				$tmpl->generateConfig();

				$configuration = $tmpl->ext_getSetup($tmpl->setup, $path);

				$configurationCache[$cacheId] = $configuration[0];
			}
		}

		return $configurationCache[$cacheId];
	}

	/**
	 * Initializes the TSFE for a given page ID and language.
	 *
	 * @param	integer	The page id to initialize the TSFE for
	 * @param	integer	System language uid, optional, defaults to 0
	 * @param	boolean	Use cache to reuse TSFE
	 * @return	void
	 */
	public static function initializeTsfe($pageId, $language = 0, $useCache = TRUE) {
		static $tsfeCache = array();

			// resetting, a TSFE instance with data from a different page Id could be set already
		unset($GLOBALS['TSFE']);

		$cacheId = $pageId . '|' . $language;

		if (!is_object($GLOBALS['TT'])) {
			$GLOBALS['TT'] = t3lib_div::makeInstance('t3lib_TimeTrackNull');
		}

		if (!isset($tsfeCache[$cacheId]) || !$useCache) {
			t3lib_div::_GETset($language, 'L');

			$GLOBALS['TSFE'] = t3lib_div::makeInstance('tslib_fe', $GLOBALS['TYPO3_CONF_VARS'], $pageId, 0);

				// for certain situations we need to trick TSFE into granting us
				// access to the page in any case to make getPageAndRootline() work
				// see http://forge.typo3.org/issues/42122
			$pageRecord = t3lib_BEfunc::getRecord('pages', $pageId);
			$groupListBackup = $GLOBALS['TSFE']->gr_list;
			$GLOBALS['TSFE']->gr_list = $pageRecord['fe_group'];

			$GLOBALS['TSFE']->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
			$GLOBALS['TSFE']->getPageAndRootline();

				// restore gr_list
			$GLOBALS['TSFE']->gr_list = $groupListBackup;

			$GLOBALS['TSFE']->initTemplate();
			$GLOBALS['TSFE']->forceTemplateParsing = TRUE;
			$GLOBALS['TSFE']->initFEuser();
			$GLOBALS['TSFE']->initUserGroups();
			// $GLOBALS['TSFE']->getCompressedTCarray(); // seems to cause conflicts sometimes

			$GLOBALS['TSFE']->no_cache = TRUE;
			$GLOBALS['TSFE']->tmpl->start($GLOBALS['TSFE']->rootLine);
			$GLOBALS['TSFE']->no_cache = FALSE;
			$GLOBALS['TSFE']->getConfigArray();

			$GLOBALS['TSFE']->settingLanguage();
			$GLOBALS['TSFE']->newCObj();
			$GLOBALS['TSFE']->absRefPrefix = ($GLOBALS['TSFE']->config['config']['absRefPrefix'] ? trim($GLOBALS['TSFE']->config['config']['absRefPrefix']) : '');

			if ($useCache) {
				$tsfeCache[$cacheId] = $GLOBALS['TSFE'];
			}
		}

		if ($useCache) {
			$GLOBALS['TSFE'] = $tsfeCache[$cacheId];
		}
	}

	/**
	 * Determines the rootpage ID for a given page.
	 *
	 * @param	integer	A page ID somewhere in a tree.
	 * @return	integer	The page's tree branch's root page ID
	 */
	public static function getRootPageId($pageId = 0) {
		$rootLine   = array();
		$rootPageId = intval($pageId) ? intval($pageId) : $GLOBALS['TSFE']->id;

			// frontend
		if (!empty($GLOBALS['TSFE']->rootLine)) {
			$rootLine = $GLOBALS['TSFE']->rootLine;
		}

			// fallback, backend
		if ($pageId != 0 && (empty($rootLine) || !self::rootlineContainsRootPage($rootLine))) {
			$pageSelect = t3lib_div::makeInstance('t3lib_pageSelect');
			$rootLine   = $pageSelect->getRootLine($pageId, '', TRUE);
		}

		$rootLine = array_reverse($rootLine);
		foreach ($rootLine as $page) {
			if ($page['is_siteroot']) {
				$rootPageId = $page['uid'];
			}
		}

		return $rootPageId;
	}

	/**
	 * Checks whether a given root line contains a page marked as root page.
	 *
	 * @param array $rootLine A root line array of page records
	 * @return boolean TRUE if the root line contains a root page record, FALSE otherwise
	 */
	protected static function rootlineContainsRootPage(array $rootLine) {
		$containsRootPage = FALSE;

		foreach ($rootLine as $page) {
			if ($page['is_siteroot']) {
				$containsRootPage = TRUE;
				break;
			}
		}

		return $containsRootPage;
	}

	/**
	 * Takes a page Id and checks whether the page is marked as root page.
	 *
	 * @param integer $pageId Page ID
	 * @return boolean TRUE if the page is marked as root page, FALSE otherwise
	 */
	public static function isRootPage($pageId) {
		$isRootPage = FALSE;

		$page = t3lib_BEfunc::getRecord('pages', $pageId);
		if ($page['is_siteroot']) {
			$isRootPage = TRUE;
		}

		return $isRootPage;
	}

	/**
	 * Gets the parent TypoScript Object from a given TypoScript path.
	 *
	 * Example: plugin.tx_solr.index.queue.tt_news.fields.content
	 * returns $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['queue.']['tt_news.']['fields.']['content.']
	 * which is a SOLR_CONTENT cObj.
	 *
	 * @param	string	$path TypoScript path
	 * @return	array	The TypoScript object defined by the given path
	 * @throws	InvalidArgumentException
	 */
	public static function getTypoScriptObject($path) {
		if (!is_string($path)) {
			throw new InvalidArgumentException('Parameter $path is not a string', 1325627243);
		}

		$pathExploded = explode('.', trim($path));
			// remove last object
		$lastPathSegment = array_pop($pathExploded);
		$pathBranch      = $GLOBALS['TSFE']->tmpl->setup;

		foreach ($pathExploded as $segment) {
			if (!array_key_exists($segment . '.', $pathBranch)) {
				throw new InvalidArgumentException(
					'TypoScript object path "' . htmlspecialchars($path) . '" does not exist',
					1325627264
				);
			}
			$pathBranch = $pathBranch[$segment . '.'];
		}

		return $pathBranch;
	}

	/**
	 * Gets the value from a given TypoScript path.
	 *
	 * Example: plugin.tx_solr.search.targetPage
	 * returns $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['targetPage']
	 *
	 * @param string $path TypoScript path
	 * @return array The TypoScript object defined by the given path
	 * @throws InvalidArgumentException
	 */
	public static function getTypoScriptValue($path) {
		if (!is_string($path)) {
			throw new InvalidArgumentException('Parameter $path is not a string', 1325623321);
		}

		$pathExploded = explode('.', trim($path));
		$pathBranch   = $GLOBALS['TSFE']->tmpl->setup;

		$segmentCount = count($pathExploded);
		for ($i = 0; $i < $segmentCount; $i++) {
			$segment = $pathExploded[$i];

			if ($i == ($segmentCount - 1)) {
				$pathBranch = $pathBranch[$segment];
			} else {
				$pathBranch = $pathBranch[$segment . '.'];
			}
		}

		return $pathBranch;
	}

	/**
	 * Checks whether a given TypoScript path is valid.
	 *
	 * @param string $path TypoScript path
	 * @return boolean TRUE if the path resolves, FALSE otherwise
	 */
	public static function isValidTypoScriptPath($path) {
		$isValidPath = FALSE;

		$pathValue = self::getTypoScriptValue($path);
		if (!is_null($pathValue)) {
			$isValidPath = TRUE;
		}

		return $isValidPath;
	}

	/**
	 * Gets the site hash for a domain
	 *
	 * @param string $domain Domain to calculate the site hash for.
	 * @return string site hash for $domain
	 */
	public static function getSiteHashForDomain($domain) {
		$siteHash = sha1(
			$domain .
			$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] .
			'tx_solr'
		);

		return $siteHash;
	}

	/**
	 * Check if record ($table, $uid) is a workspace record
	 *
	 * @param string $table The table the record belongs to
	 * @param integer $uid The record's uid
	 * @return boolean TRUE if the record is in a draft workspace, FALSE if it's a LIVE record
	 */
	public static function isDraftRecord($table, $uid) {
		$isWorkspaceRecord = FALSE;

		if (t3lib_BEfunc::isTableWorkspaceEnabled($table)) {
			$record = t3lib_BEfunc::getRecord($table, $uid);

			if ($record['pid'] == '-1' || $record['t3ver_state'] > 0) {
				$isWorkspaceRecord = TRUE;
			}
		}

		return $isWorkspaceRecord;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_util.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_util.php']);
}

?>