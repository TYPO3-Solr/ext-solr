<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Ingo Renner <ingo.renner@dkd.de>
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
	 * Constructor.
	 *
	 * @param	integer	$rootPageId Site root page ID (uid). The page must be marked as site root ("Use as Root Page" flag).
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
	* @param	integer	$pageId The page Id to get a Site object for.
	* @return	tx_solr_Site	Site for the given page Id.
	*/
	public static function getSiteByPageId($pageId) {
		$rootPageId = tx_solr_Util::getRootPageId($pageId);

		return t3lib_div::makeInstance(__CLASS__, $rootPageId);
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
	 * Generates the site's unique Site Hash.
	 *
	 * The Site Hash is build from the site's main domain, the system encryption
	 * key, and the extension "tx_solr". These components are concatenated and
	 * md5-hashed.
	 *
	 * @return	string	Site Hash.
	 */
	public function getSiteHash() {
		$siteHash = md5(
			$this->getDomain() .
			$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] .
			'tx_solr'
		);

		return $siteHash;
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
	 * Gets the site's label. The label is build from the the site titla and root
	 * page ID (uid).
	 *
	 * @return	string	The site's label.
	 */
	public function getLabel() {
		return $this->rootPage['title'] . ', Root Page ID: ' . $this->rootPage['uid'];
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_site.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_site.php']);
}

?>