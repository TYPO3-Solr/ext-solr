<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2012 Ingo Renner <ingo@typo3.org>
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
 * Exception that is thrown when no Solr connection could be found.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_NoSolrConnectionFoundException extends Exception {

	protected $pageId;

	protected $languageId;

	protected $rootPageId;


	public function setPageId($pageId) {
		$this->pageId = intval($pageId);
	}

	public function getPageId() {
		return $this->pageId;
	}

	public function setLanguageId($languageId) {
		$this->languageId = intval($languageId);
	}

	public function getLanguageId() {
		return $this->languageId;
	}

	public function setRootPageId($rootPageId) {
		$this->rootPageId = intval($rootPageId);
	}

	public function getRootPageId() {
		return $this->rootPageId;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_nosolrconnectionfoundexception.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_nosolrconnectionfoundexception.php']);
}

?>