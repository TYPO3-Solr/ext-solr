<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2011 Ingo Renner <ingo.renner@dkd.de>
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
	 * Generates a site specific key using the site url, encryption key, and
	 * the extension key sent through md5.
	 *
	 * @param	integer	Optional page ID, if a page ID is provided it is used to determine the site hash, otherwise we try to use TSFE
	 * @return	string	A site specific hash
	 */
	public static function getSiteHash($pageId = 0) {
		static $siteHashes;
		$rootLine = array();

		// TODO caching might be more efficient if using root pid

		if (empty($siteHashes[$pageId])) {
			if ($pageId == 0 && empty($GLOBALS['TSFE']->rootLine)) {
				throw new RuntimeException(
					'Unable to retrieve a rootline while calculating the site hash.',
					1268673589
				);
			}

				// frontend
			if (!empty($GLOBALS['TSFE']->rootLine)) {
				$rootLine = $GLOBALS['TSFE']->rootLine;
			}

				// fallback, backend
			if (empty($rootLine) && $pageId != 0) {
				$pageSelect = t3lib_div::makeInstance('t3lib_pageSelect');
				$rootLine   = $pageSelect->getRootLine($pageId);
			}

			$domain = t3lib_BEfunc::firstDomainRecord($rootLine);

			$siteHashes[$pageId] = md5(
				$domain .
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] .
				'tx_solr'
			);
		}

		return $siteHashes[$pageId];
	}

	/**
	 * Generates a document id for documents representing page records.
	 *
	 * @param	integer	$uid The page's uid
	 * @param	integer $typeNum The page's typeNum
	 * @param	integer	$language the language id, defaults to 0
	 * @param	string	$accessGroups comma separated list of uids of groups that have access to that page
	 * @param	string 	$cHash cHash of the page
	 * @return	string	the document id for that page
	 */
	public static function getPageDocumentId($uid, $typeNum = 0, $language = 0, $accessGroups = '0,-1', $cHash = '') {
		$partialPageRecord = t3lib_BEfunc::getRecord('pages', $uid, 'pid');

		$documentId = self::getSiteHash($uid)
			. '/pages/' . $partialPageRecord['pid'] . '/' . $uid . '/'
			. $typeNum . '/' . $language . '/' . $accessGroups;

		if (!empty($cHash)) {
			$documentId .= '/' . $cHash;
		}

		return $documentId;
	}

	/**
	 * Generates a document id in the form $siteHash/$type/$uid.
	 *
	 * @param	string	the records table name
	 * @param	integer	the record's pid
	 * @param	integer	the record's uid
	 * @param	string	optional record type, can also be used to represent a single view page id
	 * @return	string	a document id
	 */
	public static function getDocumentId($table, $pid, $uid, $type = '') {
		$id = self::getSiteHash($pid) . '/' . $table . '/' . $pid . '/' . $uid;

		if (!empty($type)) {
			$id .= '/' . $type;
		}

		return $id;
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
	 * Shortcut to retrieve the configuration for EXT:solr from TSFE
	 *
	 * @return array	Solr configuration
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
	 * @return	array	The Solr configuration for the requested tree.
	 */
	public static function getSolrConfigurationFromPageId($pageId, $initializeTsfe = FALSE) {
		static $configurationCache = array();
		$solrConfiguration         = array();

			// TODO needs some caching -> caching framework?

		if ($initializeTsfe) {
			self::initializeTsfe($pageId);
			$configurationCache[$pageId] = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.'];
		} else {
			if (!isset($configurationCache[$pageId])) {
				$pageSelect = t3lib_div::makeInstance('t3lib_pageSelect');
				$rootLine   = $pageSelect->getRootLine($pageId);

				if (empty($GLOBALS['TSFE']->sys_page)) {
					$GLOBALS['TSFE']->sys_page = $pageSelect;
				}

				$tmpl = t3lib_div::makeInstance('t3lib_tsparser_ext');
				$tmpl->tt_track = FALSE; // Do not log time-performance information
				$tmpl->init();
				$tmpl->runThroughTemplates($rootLine); // This generates the constants/config + hierarchy info for the template.
				$tmpl->generateConfig();

				$solrConfiguration = $tmpl->ext_getSetup($tmpl->setup, 'plugin.tx_solr');

				$configurationCache[$pageId] = $solrConfiguration[0];
			}
		}

		return $configurationCache[$pageId];
	}

	/**
	 * Initializes the TSFE for a given page Id
	 *
	 * @param	integer	The page id to initialize the TSFE for
	 */
	public static function initializeTsfe($pageId) {
		static $tsfeCache = array();

		if (!is_object($GLOBALS['TT'])) {
			$GLOBALS['TT'] = t3lib_div::makeInstance('t3lib_TimeTrackNull');
		}

		if (!isset($tsfeCache[$pageId])) {
			$GLOBALS['TSFE'] = t3lib_div::makeInstance('tslib_fe', $GLOBALS['TYPO3_CONF_VARS'], $pageId, 0);
			$GLOBALS['TSFE']->initFEuser();
			$GLOBALS['TSFE']->determineId();
			$GLOBALS['TSFE']->initTemplate();
			$GLOBALS['TSFE']->getConfigArray();

			$tsfeCache[$pageId] = $GLOBALS['TSFE'];
		}

			// resetting, a TSFE instance with data from a different page Id could be set already
		unset($GLOBALS['TSFE']);

			// use the requested TSFE instance
		$GLOBALS['TSFE'] = $tsfeCache[$pageId];
	}

	/**
	 * Determines the rootpage ID for a given page.
	 *
	 * @param	integer	A page ID somewhere in a tree.
	 * @return	integer	The page's tree branch's root page ID
	 */
	public static function getRootPageId($pageId) {
		$rootPageId = $pageId;
		$rootline   = t3lib_BEfunc::BEgetRootLine($pageId);

		$rootline = array_reverse($rootline);
		foreach ($rootline as $page) {
			if ($page['is_siteroot']) {
				$rootPageId = $page['uid'];
			}
		}

		return $rootPageId;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_util.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_util.php']);
}

?>