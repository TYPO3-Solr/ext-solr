<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Ingo Renner <ingo@typo3.org>
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
 * Link builder for queries.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class tx_solr_query_LinkBuilder {

	/**
	 * Content object.
	 *
	 * @var tslib_cObj
	 */
	protected $contentObject;

	/**
	 * Solr configuration.
	 *
	 * @var array
	 */
	protected $solrConfiguration;

	/**
	 * Solr query. This query's parameters are used in URL parameters.
	 *
	 * @var tx_solr_Query
	 */
	protected $query = null;

	/**
	 * URL GET parameter prefix
	 *
	 * @var string
	 */
	protected $prefix = 'tx_solr';

	/**
	 * Query GET parameter name for incoming user queries.
	 *
	 * @var string
	 */
	protected static $queryGetParameter = '';

	/**
	 * Link target page ID.
	 *
	 * @var integer
	 */
	protected $linkTargetPageId;

	/**
	 * Additional URL parameters applicaple to all URLs
	 *
	 * @var string
	 */
	protected $urlParameters = '';

	/**
	 * Parameters we do not want to appear in the URL, usually for esthetic
	 * reasons.
	 *
	 * @var array
	 */
	protected $unwantedUrlParameters = array('resultsPerPage', 'page');


	/**
	 * Constructor.
	 *
	 * @param tx_solr_Query $query Solr query
	 */
	public function __construct(tx_solr_Query $query) {
		$this->solrConfiguration = tx_solr_Util::getSolrConfiguration();
		$this->contentObject     = t3lib_div::makeInstance('tslib_cObj');
		$this->query             = $query;

		$this->linkTargetPageId = $this->solrConfiguration['search.']['targetPage'];
		if (empty($this->linkTargetPageId)) {
			$this->linkTargetPageId = $GLOBALS['TSFE']->id;
		}

		if (empty(self::$queryGetParameter)) {
			self::initializeQueryGetParameter();
		}
	}

	/**
	 * Initializes the query GET parameter.
	 *
	 * The GET query parameter name is configurable.
	 */
	protected static function initializeQueryGetParameter() {
		$solrConfiguration = tx_solr_Util::getSolrConfiguration();
		$getParameter      = 'tx_solr|q';

		if (!empty($solrConfiguration['search.']['query.']['getParameter'])) {
			$getParameter = $solrConfiguration['search.']['query.']['getParameter'];
		}

		$getParameterParts = t3lib_div::trimExplode('|', $getParameter, 2);

		if (count($getParameterParts) == 2) {
			$getParameters = t3lib_div::_GET($getParameterParts[0]);

			self::$queryGetParameter = $getParameterParts[0] . '[' . $getParameterParts[1] . ']';
		} else {
			self::$queryGetParameter = $getParameter;
		}
	}

	/**
	 * Gets the plugin parameters from GET and POST parameters.
	 *
	 * Usually known as piVars.
	 *
	 * @return array Array of GET and POST parameters for the extension.
	 */
	protected function getPluginParameters() {
		$getPostParameters = t3lib_div::_GP($this->prefix);
		$pluginParameters  = is_array($getPostParameters) ? $getPostParameters : array();

		return $pluginParameters;
	}

	/**
	 * Sets the target page Id for links
	 *
	 * @param integer Page Id links shall point to.
	 */
	public function setLinkTargetPageId($pageId) {
		$this->linkTargetPageId = intval($pageId);
	}

	/**
	 * Gets the target page Id for links.
	 *
	 * @return integer Page Id links are going to point to.
	 */
	public function getLinkTargetPageId() {
		return $this->linkTargetPageId;
	}

	/**
	 * Sets general URL GET parameters.
	 *
	 * @param string $urlParameters URL GET parameters.
	 */
	public function setUrlParameters($urlParameters) {
		$this->urlParameters = $urlParameters;
	}

	/**
	 * Adds general URL GET parameters.
	 *
	 * @param string $urlParameters URL GET parameters.
	 */
	public function addUrlParameters($urlParameters) {
		if ($urlParameters[0] != '&') {
			$urlParameters = '&' . $urlParameters;
		}

		$this->urlParameters .= $urlParameters;
	}

	/**
	 * Adds an unwanted URL parameter that should get removed if found when
	 * building URLs.
	 *
	 * @param string $unwantedUrlParameter URL GET parameter
	 */
	public function addUnwantedUrlParameter($unwantedUrlParameter) {
		$this->unwantedUrlParameters[] = $unwantedUrlParameter;
	}

	/**
	 * Removes an unwanted URL parameter that should not get removed if found when
	 * building URLs.
	 *
	 * @param string $unwantedUrlParameter URL GET parameter
	 */
	public function removeUnwantedUrlParameter($unwantedUrlParameter) {
		$key = array_search($unwantedUrlParameter,$this->unwantedUrlParameters);
		if($key!==false){
			unset($this->unwantedUrlParameters[$key]);
		}
	}

	/**
	 * Gets general URL GET parameters.
	 *
	 * @return string Additional URL GET parameters.
	 */
	public function getUrlParameters() {
		$urlParameters = $this->urlParameters;

		if (!empty($urlParameters) && $urlParameters[0] != '&') {
			$urlParameters = '&' . $urlParameters;
		}

		return $urlParameters;
	}

	/**
	 * Gets the name of the query GET parameter used in URLs and links.
	 *
	 * @return string Query GET parameter in URLs and links.
	 */
	public static function getQueryGetParameter() {
		if (empty(self::$queryGetParameter)) {
			self::initializeQueryGetParameter();
		}

		return self::$queryGetParameter;
	}

	/**
	 * Generates a html link - an anchor tag.
	 *
	 * TODO currently everything in $additionalQueryParameters is prefixed with tx_solr,
	 * allow arbitrary parameters, too (either filter them out or introduce a new 4th parameter)
	 *
	 * @param string $linkText Link Text
	 * @param array $additionalQueryParameters Additional query parameters
	 * @param array $typolinkOptions Typolink Options
	 * @return string A html link
	 */
	public function getQueryLink($linkText, array $additionalQueryParameters = array(), array $typolinkOptions = array()) {
		$queryParameters = array_merge(
			$this->getPluginParameters(),
			$additionalQueryParameters
		);
		$queryParameters   = $this->removeUnwantedUrlParameters($queryParameters);

		$queryGetParameter = '';

		$keywords = $this->query->getKeywords();
		if (!empty($keywords)) {
			$queryGetParameter = '&' . self::$queryGetParameter . '=' . $keywords;
		}

		$linkConfiguration = array(
			'useCacheHash'     => FALSE,
			'no_cache'         => FALSE,
			'parameter'        => $this->linkTargetPageId,
			'additionalParams' => t3lib_div::implodeArrayForUrl('', array($this->prefix => $queryParameters), '', TRUE)
				. $this->getUrlParameters()
				. $queryGetParameter
		);

			// merge linkConfiguration with typolinkOptions
		$linkConfiguration = array_merge($linkConfiguration, $typolinkOptions);

		return $this->contentObject->typoLink($linkText, $linkConfiguration);
	}

	/**
	 * Generates a URL.
	 *
	 * TODO currently everything in $additionalQueryParameters is prefixed with tx_solr,
	 * allow arbitrary parameters, too (either filter them out or introduce a new 3rd parameter)
	 *
	 * @param array $additionalQueryParameters Additional query parameters
	 * @param array $typolinkOptions Typolink Options
	 * @return string A query URL
	 */
	public function getQueryUrl(array $additionalQueryParameters = array(), array $typolinkOptions = array()) {
		$queryParameters = array_merge(
			$this->getPluginParameters(),
			$additionalQueryParameters
		);
		$queryParameters   = $this->removeUnwantedUrlParameters($queryParameters);

		$queryKeywords = '';
		if ($this->query) {
			$queryKeywords = $this->query->getKeywords();
		}
		$queryGetParameter = '';
		if (!empty($queryKeywords)) {
			$queryGetParameter = '&' . self::$queryGetParameter . '=' . $queryKeywords;
		}

		$linkConfiguration = array(
			'useCacheHash'     => FALSE,
			'no_cache'         => FALSE,
			'parameter'        => $this->linkTargetPageId,
			'additionalParams' => $queryGetParameter
				. t3lib_div::implodeArrayForUrl('', array($this->prefix => $queryParameters), '', TRUE)
				. $this->getUrlParameters()
		);

			// merge linkConfiguration with typolinkOptions
		$linkConfiguration = array_merge($linkConfiguration, $typolinkOptions);

		return htmlspecialchars($this->contentObject->typoLink_URL($linkConfiguration));
	}

	/**
	 * Filters out unwanted parameters when building query URLs
	 *
	 * @param array An array of parameters that shall be used to build a URL.
	 * @return array Array with wanted parameters only, ready to be used for URL building.
	 */
	public function removeUnwantedUrlParameters(array $urlParameters) {
		foreach ($this->unwantedUrlParameters as $unwantedUrlParameter) {
			unset($urlParameters[$unwantedUrlParameter]);
		}

		return $urlParameters;
	}


}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/query/class.tx_solr_query_linkbuilder.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/query/class.tx_solr_query_linkbuilder.php']);
}

?>