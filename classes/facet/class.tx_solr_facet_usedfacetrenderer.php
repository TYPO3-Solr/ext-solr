<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
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
 * @author	Markus Goldbach  <markus.goldbach@dkd.de>
 */
class tx_solr_facet_UsedFacetRenderer extends tx_solr_facet_SimpleFacetRenderer {

	/**
	 * The name of the facet the filter is applied to.
	 *
	 * @var	string
	 */
	protected $filter;

	/**
	 * The filter value that has been applied to a query.
	 *
	 * @var	string
	 */
	protected $filterValue;

		// FIXME join into default renderer as renderUsedFacetOption()
	public function __construct($facetName, $filterValue, $filter , array $facetConfiguration,  tx_solr_Template $template, tx_solr_Query $query) {
		parent::__construct($facetName, array(), $facetConfiguration, $template, $query);

		$this->filter      = $filter;
		$this->filterValue = $filterValue;
	}

	/**
	 * Renders the block of used / applied facets.
	 *
	 * @see tx_solr_FacetRenderer::render()
	 * @return	string	Rendered HTML representing the used facet.
	 */
	public function render() {
		$solrConfiguration = tx_solr_Util::getSolrConfiguration();

		$facetText = $this->renderOption($this->filterValue);

		$removeFacetText = strtr(
			$solrConfiguration['search.']['faceting.']['removeFacetLinkText'],
			array(
				'@facetValue' => $this->filterValue,
				'@facetName'  => $this->facetName,
				'@facetText'  => $facetText
			)
		);

		$removeFacetLink = $this->buildRemoveFacetLink(
			$removeFacetText, $this->filter, $this->filterValue
		);
		$removeFacetUrl = $this->buildRemoveFacetUrl($this->filter);

		$facetToRemove = array(
			'link' => $removeFacetLink,
			'url'  => $removeFacetUrl,
			'text' => $removeFacetText,
			'name' => $this->filterValue
		);

		return $facetToRemove;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_usedfacetrenderer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_usedfacetrenderer.php']);
}

?>