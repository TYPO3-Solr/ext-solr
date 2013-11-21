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
 * Renderer for Used Facets.
 *
 * FIXME merge into default renderer as renderUsedFacetOption()
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 * @author Ingo Renner <ingo@typo3.org>
 */
class Tx_Solr_Facet_UsedFacetRenderer extends Tx_Solr_Facet_SimpleFacetOptionsRenderer {

	/**
	 * The name of the facet the filter is applied to.
	 *
	 * @var	string
	 */
	protected $filter;

	/**
	 * The filter value that has been applied to a query.
	 *
	 * @var string
	 */
	protected $filterValue;

	public function __construct($facetName, $filterValue, $filter , Tx_Solr_Template $template, Tx_Solr_Query $query) {
		parent::__construct($facetName, array(), $template, $query);

		$this->filter      = $filter;
		$this->filterValue = $filterValue;
	}

	/**
	 * Renders the block of used / applied facets.
	 *
	 * @see Tx_Solr_FacetRenderer::render()
	 * @return string Rendered HTML representing the used facet.
	 */
	public function render() {
		$solrConfiguration = Tx_Solr_Util::getSolrConfiguration();

		$facetOption = t3lib_div::makeInstance('Tx_Solr_Facet_FacetOption',
			$this->facetName,
			$this->filterValue
		);

		$facetLinkBuilder = t3lib_div::makeInstance('Tx_Solr_Facet_LinkBuilder',
			$this->query,
			$this->facetName,
			$facetOption
		); /* @var $facetLinkBuilder Tx_Solr_Facet_LinkBuilder */
		$facetLinkBuilder->setLinkTargetPageId($this->linkTargetPageId);

		if ($this->facetConfiguration['type'] == 'hierarchy') {
				// FIXME decouple this
			$filterEncoder = t3lib_div::makeInstance('Tx_Solr_Query_FilterEncoder_Hierarchy');
			$facet         = t3lib_div::makeInstance('Tx_Solr_Facet_Facet', $this->facetName);
			$facetRenderer = t3lib_div::makeInstance('Tx_Solr_Facet_HierarchicalFacetRenderer', $facet);

			$facetText = $facetRenderer->getLastPathSegmentFromHierarchicalFacetOption($filterEncoder->decodeFilter($this->filterValue));
		} else {
			$facetText = $facetOption->render();
		}

		$contentObject = t3lib_div::makeInstance('tslib_cObj');
		$facetLabel = $contentObject->stdWrap(
			$solrConfiguration['search.']['faceting.']['facets.'][$this->facetName . '.']['label'],
			$solrConfiguration['search.']['faceting.']['facets.'][$this->facetName . '.']['label.']
		);

		$removeFacetText = strtr(
			$solrConfiguration['search.']['faceting.']['removeFacetLinkText'],
			array(
				'@facetValue' => $this->filterValue,
				'@facetName'  => $this->facetName,
				'@facetLabel' => $facetLabel,
				'@facetText'  => $facetText
			)
		);

		$removeFacetLink = $facetLinkBuilder->getRemoveFacetOptionLink($removeFacetText);
		$removeFacetUrl  = $facetLinkBuilder->getRemoveFacetOptionUrl();

		$facetToRemove = array(
			'link'       => $removeFacetLink,
			'url'        => $removeFacetUrl,
			'text'       => $removeFacetText,
			'value'      => $this->filterValue,
			'facet_name' => $this->facetName
		);

		return $facetToRemove;
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/Facet/UsedFacetRenderer.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/Facet/UsedFacetRenderer.php']);
}

?>