<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Ingo Renner <ingo@typo3.org>
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
 * An URI builder
 *
 *	Credits: Most code taken from EXT:extbase's UriBuilder c
 *lass
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_indexqueue_UriBuilder {

	/**
	 * An instance of tslib_cObj
	 *
	 * @var tslib_cObj
	 */
	protected $contentObject;

	/**
	 * @var integer
	 */
	protected $targetPageUid = NULL;

	/**
	 * @var integer
	 */
	protected $targetPageType = 0;

	/**
	 * @var array
	 */
	protected $arguments = array();

	/**
	 * @var string
	 */
	protected $anchor = '';

	/**
	 * @var boolean
	 */
	protected $useCaching = TRUE;

	/**
	 * @var boolean
	 */
	protected $useCacheHash = TRUE;

	/**
	 * @var boolean
	 */
	protected $linkAccessRestrictedPages = FALSE;

	/**
	 * Constructs this URI builder
	 *
	 * @param tslib_cObj $contentObject
	 */
	public function __construct(tslib_cObj $contentObject = NULL) {
		$this->contentObject = $contentObject !== NULL ? $contentObject : t3lib_div::makeInstance('tslib_cObj');
	}

	/**
	 * Resets all UriBuilder options to their default value	 *
	 */
	public function reset() {
		$this->arguments                 = array();
		$this->anchor                    = '';
		$this->linkAccessRestrictedPages = FALSE;
		$this->targetPageUid             = NULL;
		$this->targetPageType            = 0;
		$this->useCaching                = TRUE;
		$this->useCacheHash              = TRUE;
	}

	/**
	 * Sets additional query parameters.
	 *
	 * If you want to "prefix" arguments, you can pass in multidimensional arrays:
	 * array('prefix1' => array('foo' => 'bar')) gets "&prefix1[foo]=bar"
	 *
	 * @param array $arguments
	 */
	public function setArguments(array $arguments) {
		$this->arguments = $arguments;
	}

	/**
	 * Sets a single query parameter.
	 *
	 * If the parameter is not set yet, it's added.
	 * If the parameter already exists, it gets overriden.
	 *
	 * @param	string	The parameter's name
	 * @param	string	The parameter's value
	 */
	public function setArgument($name, $value) {
		$this->arguments[$name] = $value;
	}

	/**
	 * Returns the currently set query parameters for the URI.
	 *
	 * @return array
	 */
	public function getArguments() {
		return $this->arguments;
	}

	/**
	 * If specified, adds a given HTML anchor to the URI (#...)
	 *
	 * @param string $section
	 */
	public function setAnchor($anchor) {
		$this->anchor = $anchor;
	}

	/**
	 * Gets the currently set anchor.
	 *
	 * @return string
	 */
	public function getAnchor() {
		return $this->anchor;
	}

	/**
	 * If set, URIs for pages without access permissions will be created
	 *
	 * @param boolean $linkAccessRestrictedPages
	 */
	public function setLinkAccessRestrictedPages($linkAccessRestrictedPages) {
		$this->linkAccessRestrictedPages = (boolean) $linkAccessRestrictedPages;
	}

	/**
	 * Returns whether access restricted pages are going to be linked.
	 *
	 * @return boolean
	 */
	public function getLinkAccessRestrictedPages() {
		return $this->linkAccessRestrictedPages;
	}

	/**
	 * Uid of the target page
	 *
	 * @param integer $pageUid
	 */
	public function setTargetPageUid($targetPageUid) {
		$this->targetPageUid = $targetPageUid;
	}

	/**
	 * returns $this->targetPageUid.
	 *
	 * @return integer
	 */
	public function getTargetPageUid() {
		return $this->targetPageUid;
	}

	/**
	 * Sets the page type of the target URI. Defaults to 0
	 *
	 * @param integer $pageType
	 */
	public function setTargetPageType($targetPageType) {
		$this->targetPageType = (integer) $targetPageType;
	}

	/**
	 * Gets the page type for the current URI.
	 *
	 * @return integer
	 */
	public function getTargetPageType() {
		return $this->targetPageType;
	}

	/**
	 * TRUE by default; if FALSE, &no_cache=1 will be appended to the URI
	 * This overrules the useCacheHash setting
	 *
	 * @param boolean $noCache
	 */
	public function setUseCaching($useCaching) {
		$this->useCaching = (boolean) $useCaching;
	}

	/**
	 * Returns whether caching is enabled or not.
	 *
	 * @return boolean
	 */
	public function getUseCaching() {
		return $this->useCaching;
	}

	/**
	 * TRUE by default; if FALSE, no cHash parameter will be appended to the URI
	 * If useCaching is set to FALSE, this setting will be ignored.
	 *
	 * @param boolean $useCacheHash
	 */
	public function setUseCacheHash($useCacheHash) {
		$this->useCacheHash = (boolean) $useCacheHash;
	}

	/**
	 * Returns whether a cacheHash is generated or not.
	 *
	 * @return boolean
	 */
	public function getUseCacheHash() {
		return $this->useCacheHash;
	}

	/**
	 * Builds the URI
	 *
	 * @return string The URI
	 * @see buildTypolinkConfiguration()
	 */
	public function buildUri() {
		$typolinkConfiguration = $this->buildTypolinkConfiguration();
		$uri = $this->contentObject->typoLink_URL($typolinkConfiguration);

		return $uri;
	}

	/**
	 * Builds a TypoLink configuration array from the current settings
	 *
	 * @return array typolink configuration array
	 * @see TSref/typolink
	 */
	protected function buildTypolinkConfiguration() {
		$typolinkConfiguration = array();

		$typolinkConfiguration['parameter'] = $this->targetPageUid;
		if ($this->targetPageType !== 0) {
			$typolinkConfiguration['parameter'] .= ',' . $this->targetPageType;
		}

		if (count($this->arguments) > 0) {
			$typolinkConfiguration['additionalParams'] = '&' . http_build_query($arguments, NULL, '&');
		}

		if ($this->useCaching === FALSE) {
			$typolinkConfiguration['no_cache'] = 1;
		} elseif ($this->useCacheHash) {
			$typolinkConfiguration['useCacheHash'] = 1;
		}

		if ($this->anchor !== '') {
			$typolinkConfiguration['section'] = $this->anchor;
		}

		if ($this->linkAccessRestrictedPages === TRUE) {
			$typolinkConfiguration['linkAccessRestrictedPages'] = 1;
		}

		return $typolinkConfiguration;
	}


}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_uribuilder.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/class.tx_solr_indexqueue_uribuilder.php']);
}

?>