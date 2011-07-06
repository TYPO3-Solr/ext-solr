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
		$showEmptyFacets = $this->solrConfiguration['search.']['faceting.']['showEmptyFacets'];

			// don't render the given facet if it doesn't have any options and
			// rendering of empty facets is disabled
		if ($showEmptyFacets != '0' || !$this->isEmpty()) {
			$facetTemplate = clone $this->template;
			$facetTemplate->workOnSubpart('single_facet');

			$facetOptions = $this->renderFacetOptions();
			$facetTemplate->addSubpart('single_facet_option', $facetOptions);

			$facet = $this->facetConfiguration;
			$facet['name']  = $this->facetName;
			$facet['count'] = $this->getFacetOptionsCount();

			if ($facet['count'] > $this->solrConfiguration['search.']['faceting.']['limit']) {
				$showAllLink = '<a href="#" class="tx-solr-facet-show-all">###LLL:faceting_showMore###</a>';
				$showAllLink = tslib_cObj::wrap($showAllLink, $this->solrConfiguration['search.']['faceting.']['showAllLink.']['wrap']);
				$facet['show_all_link'] = $showAllLink;
			}

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

		if (!empty($facetOptions)) {
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
				// TODO throw exception
			}

			$facetContent = $facetRenderer->render();
		}

		return $facetContent;
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
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_facetrenderer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_facetrenderer.php']);
}

?>