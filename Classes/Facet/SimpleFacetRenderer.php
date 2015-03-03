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
 * The simple / default facet renderer.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Tx_Solr_Facet_SimpleFacetRenderer extends Tx_Solr_Facet_AbstractFacetRenderer {

	/**
	 * Provides the internal type of facets the renderer handles.
	 * The type is one of field, range, or query.
	 *
	 * @return string Facet internal type
	 */
	public static function getFacetInternalType() {
		return Tx_Solr_Facet_Facet::TYPE_FIELD;
	}

	/**
	 * Renders the facet's options.
	 *
	 * @return string The rendered facet options.
	 */
	protected function renderFacetOptions() {
		$facetContent = '';
		$facetField   = $this->facetConfiguration['field'];
		$facetOptions = $this->getFacetOptions();

		if (!empty($facetOptions) || !empty($this->facetConfiguration['showEvenWhenEmpty'])) {
			$facetOptionsRenderer = t3lib_div::makeInstance(
				'Tx_Solr_Facet_SimpleFacetOptionsRenderer',
				$this->facetName,
				$facetOptions,
				$this->template,
				$this->search->getQuery()
			);
			$facetOptionsRenderer->setLinkTargetPageId($this->linkTargetPageId);

			$facetContent = $facetOptionsRenderer->renderFacetOptions();
		}

		return $facetContent;
	}

	/**
	 * Provides a "show all link" if a certain limit of facet options is
	 * reached.
	 *
	 * (non-PHPdoc)
	 * @see Tx_Solr_Facet_AbstractFacetRenderer::getFacetProperties()
	 */
	public function getFacetProperties() {
		$facet = parent::getFacetProperties();

		if ($facet['count'] > $this->solrConfiguration['search.']['faceting.']['limit']) {
			$showAllLink = '<a href="#" class="tx-solr-facet-show-all">###LLL:faceting_showMore###</a>';
			$showAllLink = $GLOBALS['TSFE']->cObj->wrap($showAllLink, $this->solrConfiguration['search.']['faceting.']['showAllLink.']['wrap']);
			$facet['show_all_link'] = $showAllLink;
		}

		return $facet;
	}

}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/Facet/SimpleFacetRenderer.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/Facet/SimpleFacetRenderer.php']);
}

?>
