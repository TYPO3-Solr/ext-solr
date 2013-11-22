<?php
namespace ApacheSolrForTypo3\Solr\Domain\Model;

/***************************************************************
*  Copyright notice
*
*  (c) 2013 Ingo Renner <ingo@typo3.org>
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
 * Persistent module data.
 *
 */
class ModuleData {

	/**
	 * @var \tx_solr_Site
	 */
	protected $site = NULL;

	/**
	 * @var string
	 */
	protected $core = '';


	/**
	 * Sets the site to work with.
	 *
	 * @param \tx_solr_Site $site
	 * @return void
	 */
	public function setSite(\tx_solr_Site $site) {
		$this->site = $site;
	}

	/**
	 * Gets the site to work with.
	 *
	 * @return \tx_solr_Site
	 */
	public function getSite() {
		return $this->site;
	}

	/**
	 * Sets the name of the currently selected core
	 *
	 * @param string $core Selected core name
	 */
	public function setCore($core) {
		$this->core = $core;
	}

	/**
	 * Gets the name of the currently selected core
	 *
	 * @return string Selected core name
	 */
	public function getCore() {
		return $this->core;
	}

}

?>