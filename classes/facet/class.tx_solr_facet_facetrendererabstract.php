<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Ingo Renner <ingo@typo3.org>
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
 * Facet renderer.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_facet_FacetRenderer {

	/**
	 * @var tx_solr_Search
	 */
	protected $search;

	/**
	 * @var	string
	 */
	protected $facetName;

	/**
	 * @var	array
	 */
	protected $facetConfiguration;

	/**
	 * @var	array
	 */
	protected $solrConfiguration;

	/**
	 * @var	tx_solr_Template
	 */
	protected $template;

	/**
	 * Link target page id.
	 *
	 * @var	integer
	 */
	protected $linkTargetPageId = 0;

	/**
	 * Constructor for class tx_solr_facet_FacetRenderer
	 *
	 * @param	string	$facetName The name of the facet to render.
	 * @param	tx_solr_Template	$template The template to use to render the facet.
	 */
	public function __construct($facetName, tx_solr_Template $template) {
		$this->search = t3lib_div::makeInstance('tx_solr_Search');

		$this->facetName          = $facetName;
		$this->template           = $template;

		$this->solrConfiguration  = tx_solr_Util::getSolrConfiguration();
		$this->facetConfiguration = $this->solrConfiguration['search.']['faceting.']['facets.'][$facetName . '.'];
		$this->linkTargetPageId   = $GLOBALS['TSFE']->id;
	}

	/**
	 * Renders the complete facet.
	 *
	 * @return	string	Facet markup.
	 */
	public function renderFacet() {
		$facetContent    = '';

		$showEmptyFacets = FALSE;
		if (!empty($this->solrConfiguration['search.']['faceting.']['showEmptyFacets'])) {
			$showEmptyFacets = TRUE;
		}

		$showEvenWhenEmpty = FALSE;
		if (!empty($this->solrConfiguration['search.']['faceting.']['facets.'][$this->facetName . '.']['showEvenWhenEmpty'])) {
			$showEvenWhenEmpty = TRUE;
		}

			// if the facet doesn't provide any options, don't render it unless
			// it is configured to be rendered nevertheless
		if (!$this->isEmpty() || $showEmptyFacets || $showEmptyFacet) {
			$facetTemplate = clone $this->template;
			$facetTemplate->workOnSubpart('single_facet');

			$facetOptions = $this->renderFacetOptions();
			$facetTemplate->addSubpart('single_facet_option', $facetOptions);

			$facet = $this->getFacet();

			$facetTemplate->addVariable('facet', $facet);
			$facetContent = $facetTemplate->render();
		}

		return $facetContent;
	}

	/**
	 * Renders the facet's options.
	 *
	 * @return	string	The rendered facet options.
	 */
	public function renderFacetOptions() {
		$facetContent = '';
		$facetField   = $this->facetConfiguration['field'];
		$facetOptions = $this->search->getFacetFieldOptions($facetField);

		if (!empty($facetOptions) || !empty($this->facetConfiguration['showEvenWhenEmpty'])) {
				// default facet renderer
			$facetRendererClass = 'tx_solr_facet_SimpleFacetRenderer';
			if (!empty($this->facetConfiguration['renderer'])) {
				$facetRendererClass = $this->facetConfiguration['renderer'];
			}

			$facetRenderer = t3lib_div::makeInstance(
				$facetRendererClass,
				$this->facetName,
				$facetOptions,
				$this->facetConfiguration,
				$this->template,
				$this->search->getQuery()
			);
			$facetRenderer->setLinkTargetPageId($this->linkTargetPageId);

			if (!($facetRenderer instanceof tx_solr_FacetRenderer)) {
				throw new UnexpectedValueException(
					get_class($facetRenderer) . ' must implement interface tx_solr_FacetRenderer',
					1310387079
				);
			}

			$facetContent = $facetRenderer->render();
		}

		return $facetContent;
	}

	/**
	 * Gets the facet object markers for use in templates.
	 *
	 * @return	array	An array with facet object markers.
	 */
	public function getFacet() {
		$facet = $this->facetConfiguration;
		$facet['name']      = $this->facetName;
		$facet['count']     = $this->getFacetOptionsCount();
		$facet['active']    = $this->isActive() ? '1' : '0';
		$facet['reset_url'] = $this->buildResetFacetUrl();

		if ($facet['count'] > $this->solrConfiguration['search.']['faceting.']['limit']) {
			$showAllLink = '<a href="#" class="tx-solr-facet-show-all">###LLL:faceting_showMore###</a>';
			$showAllLink = tslib_cObj::wrap($showAllLink, $this->solrConfiguration['search.']['faceting.']['showAllLink.']['wrap']);
			$facet['show_all_link'] = $showAllLink;
		}

		return $facet;
	}

	/**
	 * Gets the number of options for a facet.
	 *
	 * @return	integer	number of facet options for the current facet.
	 */
	public function getFacetOptionsCount() {
		$facetCounts = $this->search->getFacetCounts();
		$facetField  = $this->facetConfiguration['field'];

		return count((array) $facetCounts->facet_fields->$facetField);
	}

	/**
	 * Determines if a facet has any options.
	 *
	 * @return boolean	TRUE if no facet options are given, FALSE if facet options are given
	 */
	protected function isEmpty() {
		$isEmpty = FALSE;

		$facetCounts       = $this->search->getFacetCounts();
		$facetField        = $this->facetConfiguration['field'];
		$facetOptions      = (array) $facetCounts->facet_fields->$facetField;
		$facetOptionsCount = count($facetOptions);

			// facet options include '_empty_', if no options are given
		if ($facetOptionsCount == 0
			|| ($facetOptionsCount == 1 && array_key_exists('_empty_', $facetOptions))
		) {
			$isEmpty = TRUE;
		}

		return $isEmpty;
	}

	/**
	 * Checks whether an option of the facet has been selected by the user by
	 * checking the URL GET parameters.
	 *
	 * @return	boolean	TRUE if any option of the facet is applied, FALSE otherwise
	 */
	protected function isActive() {
		$isActive = FALSE;

		$resultParameters = t3lib_div::_GET('tx_solr');
		$filterParameters = array();
		if (isset($resultParameters['filter'])) {
			$filterParameters = (array) array_map('urldecode', $resultParameters['filter']);
		}

		foreach ($filterParameters as $filter) {
			list($filterName, $filterValue) = explode(':', $filter);

			if ($filterName == $this->facetName) {
				$isActive = TRUE;
				break;
			}
		}

		return $isActive;
	}

	/**	 * Gets the link target page id.
	 *
	 * @return	integer	link target page id.
	 */
	public function getLinkTargetPageId(){
		return $this->linkTargetPageId;
	}

	/**
	 * Sets the link target page id.
	 *
	 * @param	integer	$linkTargetPageId link target page id.
	 * @return	void
	 */
	public function setLinkTargetPageId($linkTargetPageId){
		$this->linkTargetPageId = intval($linkTargetPageId);
	}

	/**
	 * Builds the URL to reset all options of a facet - removing all its applied
	 * filters from a result set.
	 *
	 * @return string  Url to remove a facet
	 */
	public function buildResetFacetUrl() {
		$resetFacetUrl    = '';
		$resultParameters = t3lib_div::_GPmerged('tx_solr');

		if (is_array($resultParameters['filter'])) {
				// urldecode the array to get the original representation
			$filterParameters = array_values((array) array_map('urldecode', $resultParameters['filter']));
			$filterParameters = array_unique($filterParameters);

				// find and remove all options for this facet
			foreach ($filterParameters as $key => $filter) {
				list($filterName, $filterValue) = explode(':', $filter);
				if ($filterName == $this->facetName) {
					unset($filterParameters[$key]);
				}
			}
			$filterParameters = array_map('urlencode', $filterParameters);

			$resetFacetUrl = $this->search->getQuery()->getQueryUrl(array('filter' => $filterParameters));
		} else {
			$resetFacetUrl = $this->search->getQuery()->getQueryUrl();
		}

		return $resetFacetUrl;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_facetrenderer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_facetrenderer.php']);
}

?>
