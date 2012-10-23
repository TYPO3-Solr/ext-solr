<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Ingo Renner <ingo.renner@dkd.de>
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
 * A site is a branch in a TYPO3 installation. Each site's root page is marked
 * by the "Use as Root Page" flag.
 *
 * @author	Ingo Renner <ingo.renner@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_Site {

	/**
	 * Root page record.
	 *
	 * @var	array
	 */
	protected $rootPage = array();

	/**
	 * The site's sys_language_mode
	 *
	 * @var string
	 */
	protected $sysLanguageMode = null;

	/**
	 * Cache for tx_solr_Site objects
	 *
	 * @var array
	 */
	protected static $sitesCache = array();

	/**
	 * Small cache for the list of pages in a site, so that the results of this
	 * rather expensive operation can be used by all initializers without having
	 * each initializer do it again.
	 *
	 * TODO Move to caching framework once TYPO3 4.6 is the minimum required
	 * version.
	 *
	 * @var array
	 */
	protected static $sitePagesCache = array();


	/**
	 * Constructor.
	 *
	 * @param integer $rootPageId Site root page ID (uid). The page must be marked as site root ("Use as Root Page" flag).
	 */
	public function __construct($rootPageId) {
		$page = t3lib_BEfunc::getRecord('pages', $rootPageId);

		if (!$page['is_siteroot']) {
			throw new InvalidArgumentException(
				'The page for the given page ID \'' . $rootPageId
					. '\' is not marked as root page and can therefore not be used as site root page.',
				1309272922
			);
		}

		$this->rootPage = $page;
	}

	/**
	 * Gets the Site for a specific page Id.
	 *
	 * @param integer $pageId The page Id to get a Site object for.
	 * @return tx_solr_Site Site for the given page Id.
	 */
	public static function getSiteByPageId($pageId) {
		$rootPageId = tx_solr_Util::getRootPageId($pageId);

		if (!isset(self::$sitesCache[$rootPageId])) {
			self::$sitesCache[$rootPageId] = t3lib_div::makeInstance(__CLASS__, $rootPageId);
		}

		return self::$sitesCache[$rootPageId];
	}

	/**
	 * Gets all available TYPO3 sites with Solr configured.
	 *
	 *  @return	array	An array of available sites
	 */
	public static function getAvailableSites() {
		$sites = array();

		$registry = t3lib_div::makeInstance('t3lib_Registry');
		$servers  = $registry->get('tx_solr', 'servers', array());

		foreach ($servers as $server) {
			if (!isset($sites[$server['rootPageUid']])) {
				$sites[$server['rootPageUid']] = t3lib_div::makeInstance(__CLASS__, $server['rootPageUid']);
			}
		}

		return $sites;
	}

	/**
	 * Creates a dropdown selector of available TYPO3 sites with Solr
	 * configured.
	 *
	 * @param	string	$selectorName Name to be used in the select's name attribute
	 * @param	tx_solr_Site	$selectedSite Optional, currently selected site
	 * @return	string	Site selector HTML code
	 */
	public static function getAvailableSitesSelector($selectorName, tx_solr_Site $selectedSite = NULL) {
		$sites    = self::getAvailableSites();
		$selector = '<select name="' . $selectorName . '">';

		foreach ($sites as $site) {
			$selectedAttribute = '';
			if ($site == $selectedSite) {
				$selectedAttribute = ' selected="selected"';
			}

			$selector .= '<option value="' . $site->getRootPageId() . '"' . $selectedAttribute . '>'
				. $site->getLabel()
				. '</option>';
		}

		$selector .= '</select>';

		return $selector;
	}

	/**
	 * Gets the site's Solr TypoScript configuration (plugin.tx_solr.*)
	 *
	 * @return array The Solr TypoScript configuration
	 */
	public function getSolrConfiguration() {
		return tx_solr_Util::getSolrConfigurationFromPageId($this->rootPage['uid']);
	}

	/**
	 * Gets the site's main domain. More specifically the first domain record in
	 * the site tree.
	 *
	 * @return	string	The site's main domain.
	 */
	public function getDomain() {
		$pageSelect = t3lib_div::makeInstance('t3lib_pageSelect');
		$rootLine   = $pageSelect->getRootLine($this->rootPage['uid']);

		return t3lib_BEfunc::firstDomainRecord($rootLine);
	}

	/**
	 * Gets the system languages (IDs) for which Solr connections have been
	 * configured.
	 *
	 * @return array Array of system language IDs for which connections have been configured on this site.
	 */
	public function getLanguages() {
		$siteLanguages = array();

		$registry        = t3lib_div::makeInstance('t3lib_Registry');
		$solrConnections = $registry->get('tx_solr', 'servers');

		foreach ($solrConnections as $connectionKey => $solrConnection) {
			list($siteRootPageId, $systemLanguageId) = explode('|', $connectionKey);

			if ($siteRootPageId == $this->rootPage['uid']) {
				$siteLanguages[] = $systemLanguageId;
			}
		}

		return $siteLanguages;
	}

	/**
	 * Gets the site's default language as configured in
	 * config.sys_language_uid. If sys_language_uid is not set, 0 is assumed to
	 * be the default.
	 *
	 * @return integer The site's default language.
	 */
	public function getDefaultLanguage() {
		$siteDefaultLanguage = 0;

		$configuration = tx_solr_Util::getConfigurationFromPageId(
			$this->rootPage['uid'],
			'config',
			FALSE,
			FALSE
		);

		if (isset($configuration['sys_language_uid'])) {
			$siteDefaultLanguage = $configuration['sys_language_uid'];
		}

			// default language is set through default L GET parameter -> overruling config.sys_language_uid
		if (isset($configuration['defaultGetVars.']['L'])) {
			$siteDefaultLanguage = intval($configuration['defaultGetVars.']['L']);
		}

		return $siteDefaultLanguage;
	}

	/**
	 * Generates a list of page IDs in this site. Attention, this includes
	 * all page types! Deleted pages are not included.
	 *
	 * @param	integer		Page ID from where to start collection sub pages
	 * @param	integer		Maximum depth to decend into the site tree
	 * @return	array		Array of pages (IDs) in this site
	 */
	public function getPages($rootPageId = 'SITE_ROOT', $maxDepth = 999) {
		$pageIds  = array();
		$maxDepth = intval($maxDepth);

		if (empty(self::$sitePagesCache[$rootPageId])) {
			$recursionRootPageId = intval($rootPageId);
			if ($rootPageId == 'SITE_ROOT') {
				$recursionRootPageId = $this->rootPage['uid'];
				$pageIds[]           = $this->rootPage['uid'];
			}

			if ($maxDepth > 0) {
				$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'uid',
					'pages',
					'pid = ' . $recursionRootPageId . ' ' . t3lib_BEfunc::deleteClause('pages')
				);

				while ($page = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
					$pageIds[] = $page['uid'];

					if ($maxDepth > 1) {
						$pageIds = array_merge(
							$pageIds,
							$this->getPages($page['uid'], $maxDepth - 1)
						);
					}
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($result);
			}
		} else {
			$pageIds = self::$sitePagesCache[$rootPageId];
		}

		if (empty(self::$sitePagesCache[$rootPageId])) {
				// exiting the recursion loop, may write to cache now
			self::$sitePagesCache[$rootPageId] = $pageIds;
		}

		return $pageIds;
	}

	/**
	 * Generates the site's unique Site Hash.
	 *
	 * The Site Hash is build from the site's main domain, the system encryption
	 * key, and the extension "tx_solr". These components are concatenated and
	 * sha1-hashed.
	 *
	 * @return	string	Site Hash.
	 */
	public function getSiteHash() {
		return tx_solr_Util::getSiteHashForDomain($this->getDomain());
	}

	/**
	 * Gets the site's root page.
	 *
	 * @return array The site's root page.
	 */
	public function getRootPage() {
		return $this->rootPage;
	}

	/**
	 * Gets the site's root page ID (uid).
	 *
	 * @return	integer	The site's root page ID.
	 */
	public function getRootPageId() {
		return $this->rootPage['uid'];
	}

	/**
	 * Gets the site's root page's title.
	 *
	 * @return	string	The site's root page's title
	 */
	public function getTitle() {
		return $this->rootPage['title'];
	}

	/**
	 * Gets the site's label. The label is build from the the site title and root
	 * page ID (uid).
	 *
	 * @return	string	The site's label.
	 */
	public function getLabel() {
		return $this->rootPage['title'] . ', Root Page ID: ' . $this->rootPage['uid'];
	}

	/**
	 * Gets the site's config.sys_language_mode setting
	 *
	 * @return string The site's config.sys_language_mode
	 */
	public function getSysLanguageMode() {
		if (is_null($this->sysLanguageMode)) {
			tx_solr_Util::initializeTsfe($this->getRootPageId());
			$this->sysLanguageMode = $GLOBALS['TSFE']->sys_language_mode;
		}

		return $this->sysLanguageMode;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_site.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_site.php']);
}

?>