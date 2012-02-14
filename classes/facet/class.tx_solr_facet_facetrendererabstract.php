<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Ingo Renner <ingo@typo3.org>
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
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
abstract class tx_solr_facet_FacetRendererAbstract implements tx_solr_FacetRenderer {

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
	 * Template
	 *
	 * @var tx_solr_Template
	 */
	protected $template = NULL;

	/**
	 * Link target page id.
	 *
	 * @var	integer
	 */
	protected $linkTargetPageId = 0;

	/**
	 * Default facet options renderer
	 *
	 * @var string
	 */
	private $defaultFacetOptionsRendererClass = 'tx_solr_facet_SimpleFacetOptionsRenderer';


	/**
	 * Constructor.
	 *
	 * @param string $facetName The name of the facet to render.
	 */
	public function __construct($facetName) {
		$this->search = t3lib_div::makeInstance('tx_solr_Search');

		$this->facetName          = $facetName;

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
			// TODO must check whether $this->template is set

		$facetContent = '';

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
	protected function renderFacetOptions() {
		$facetContent = '';
		$facetField   = $this->facetConfiguration['field'];
		$facetOptions = $this->getFacetOptions();

		if (!empty($facetOptions) || !empty($this->facetConfiguration['showEvenWhenEmpty'])) {
				// TODO remove renderer option, renderer is now determined by type
			$facetOptionsRendererClass = $this->defaultFacetOptionsRendererClass;
			if (!empty($this->facetConfiguration['renderer'])) {
				$facetOptionsRendererClass = $this->facetConfiguration['renderer'];
			}

			$facetOptionsRenderer = t3lib_div::makeInstance(
				$facetOptionsRendererClass,
				$this->facetName,
				$facetOptions,
				$this->facetConfiguration,
				$this->template,
				$this->search->getQuery()
			);
			$facetOptionsRenderer->setLinkTargetPageId($this->linkTargetPageId);

			if (!($facetOptionsRenderer instanceof tx_solr_FacetOptionsRenderer)) {
				throw new UnexpectedValueException(
					get_class($facetOptionsRenderer) . ' must implement interface tx_solr_FacetOptionsRenderer',
					1310387079
				);
			}

			$facetContent = $facetOptionsRenderer->renderFacetOptions();
		}

		return $facetContent;
	}

	/**
	 * (non-PHPdoc)
	 * @see tx_solr_FacetRenderer::getFacet()
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
	 * (non-PHPdoc)
	 * @see tx_solr_FacetRenderer::getFacetOptions()
	 */
	public function getFacetOptions() {
		$facetField   = $this->facetConfiguration['field'];
		$facetOptions = $this->search->getFacetFieldOptions($facetField);

		return $facetOptions;
	}

	/**
	 * (non-PHPdoc)
	 * @see tx_solr_FacetRenderer::getFacetOptionsCount()
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

	/**
	 * (non-PHPdoc)
	 * @see tx_solr_FacetRenderer::setTemplate()
	 */
	public function setTemplate(tx_solr_Template $template) {
		$this->template = $template;
	}

	/**
	 * (non-PHPdoc)
	 * @see tx_solr_FacetRenderer::setLinkTargetPageId()
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
	protected function buildResetFacetUrl() {
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
