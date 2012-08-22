<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
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
 * Renderer for hierarchical facets.
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 * @author Ingo Renner <ingo@typo3.org>
 */
class tx_solr_facet_HierarchicalFacetRenderer extends tx_solr_facet_AbstractFacetRenderer {

	/**
	 * Parent content object, set when called by tslib_cObj->callUserFunction()
	 *
	 * @var tslib_cObj
	 */
	public $cObj;


	/**
	 * Provides the internal type of facets the renderer handles.
	 * The type is one of field, range, or query.
	 *
	 * @return string Facet internal type
	 */
	public static function getFacetInternalType() {
		return tx_solr_facet_Facet::TYPE_FIELD;
	}

	/**
	 * Renders the complete hierarchical facet.
	 *
	 * @see tx_solr_facet_AbstractFacetRenderer::renderFacetOptions()
	 * @return string Facet markup.
	 */
	protected function renderFacetOptions() {
		$facetContent = '';
		$facetOptions = $this->getFacetOptions();

		/* @var $filterEncoder tx_solr_query_filterencoder_Hierarchy */
		$filterEncoder = t3lib_div::makeInstance('tx_solr_query_filterencoder_Hierarchy');

			// enrich the facet options with links before building the menu structure
		$enrichedFacetOptions = array();
		foreach ($facetOptions as $facetOptionValue => $facetOptionResultCount) {
			$facetOption = t3lib_div::makeInstance('tx_solr_facet_FacetOption',
				$this->facetName,
				$facetOptionValue,
				$facetOptionResultCount
			);

			/* @var $facetOption tx_solr_facet_FacetOption */
			$facetOption->setUrlValue($filterEncoder->encodeFilter($facetOptionValue));

			$facetLinkBuilder = t3lib_div::makeInstance('tx_solr_facet_LinkBuilder', $this->search->getQuery(), $this->facetName, $facetOption);

			$optionSelected = $facetOption->isSelectedInFacet($this->facetName);
			$optionLinkUrl  = $facetLinkBuilder->getAddFacetOptionUrl();

				// negating the facet option links to remove a filter
			if ($this->facetConfiguration['selectingSelectedFacetOptionRemovesFilter'] && $optionSelected) {
				$optionLinkUrl = $facetLinkBuilder->getRemoveFacetOptionUrl();
			}

			if ($this->facetConfiguration['singleOptionMode']) {
				$optionLinkUrl = $facetLinkBuilder->getReplaceFacetOptionUrl();
			}

				// by default the facet link builder creates htmlspecialchars()ed URLs
				// HMENU will also apply htmlspecialchars(), to prevent corrupt URLs
				// we're reverting the facet builder's htmlspecials() here
			$optionLinkUrl = htmlspecialchars_decode($optionLinkUrl);

			$enrichedFacetOptions[$facetOption->getValue()] = array(
				'numberOfResults' => $facetOption->getNumberOfResults(),
				'url'             => $optionLinkUrl,
				'selected'        => $optionSelected,
			);
		}

		$facetContent .= $this->renderHierarchicalFacet($enrichedFacetOptions);

		return $facetContent;
	}

	/**
	 * Renders the actual hierarchical facet, usually handing the rendering job
	 * off to a HMENU content object.
	 *
	 * @param array $facetOptions Available facet options.
	 * @return string Hierarchical facet rendered by a cObject
	 */
	protected function renderHierarchicalFacet($facetOptions) {
			// assuming a rendering instruction is always set for hierarchical facets
			// passing field name and facet options to the necessary userFunc
		/* @var $contentObject tslib_cObj */
		$contentObject = t3lib_div::makeInstance('tslib_cObj');
		$contentObject->start(array(
			'facetFieldName' => $this->facetConfiguration['field'],
			'facetOptions'   => $facetOptions
		));

		if (!isset($this->facetConfiguration['hierarchy.']['special'])) {
				// pre-setting some configuration needed to turn the facet options into a menu structure
			$this->facetConfiguration['hierarchy.']['special'] = 'userfunction';
			$this->facetConfiguration['hierarchy.']['special.']['userFunc'] = 'tx_solr_facet_HierarchicalFacetHelper->getMenuStructure';
		}

		$renderedFacet = $contentObject->cObjGetSingle(
			$this->facetConfiguration['hierarchy'],
			$this->facetConfiguration['hierarchy.']
		);

		return $renderedFacet;
	}

	/**
	 * Takes the hierarchical facet option, splits it up and returns the last
	 * path segment from the hierarchy
	 *
	 * @param string $facetOptionKey A complete hierarchical facet option
	 * @return string The last path segment of the hierarchical facet option
	 */
	public static function getLastPathSegmentFromHierarchicalFacetOption($facetOptionKey) {
			// first remove the level indicator in front of the path
		$facetOptionKey = trim($facetOptionKey, '"');
		list(, $path) = explode('-', $facetOptionKey, 2);

		$explodedPath    = explode('/', $path);
		$lastPathSegment = $explodedPath[count($explodedPath) - 1];

		return $lastPathSegment;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_hierarchicalfacetrenderer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_hierarchicalfacetrenderer.php']);
}

?>