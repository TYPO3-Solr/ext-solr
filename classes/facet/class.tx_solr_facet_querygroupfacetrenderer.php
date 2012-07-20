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
 * Query group facet renderer.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_facet_QueryGroupFacetRenderer extends tx_solr_facet_SimpleFacetRenderer {

	/**
	 * Provides the internal type of facets the renderer handles.
	 * The type is one of field, range, or query.
	 *
	 * @return string Facet internal type
	 */
	public static function getFacetInternalType() {
		return tx_solr_facet_Facet::TYPE_QUERY;
	}

	/**
	 * Encodes the facet option values from raw Lucene queries to values that
	 * can be easily used in rendering instructions and URL generation.
	 *
	 * (non-PHPdoc)
	 * @see tx_solr_facet_AbstractFacetRenderer::getFacetOptions()
	 */
	public function getFacetOptions() {
		$facetOptions    = array();
		$facetOptionsRaw = parent::getFacetOptions();

		$filterEncoder = t3lib_div::makeInstance('tx_solr_query_filterencoder_QueryGroup');
		foreach ($facetOptionsRaw as $facetOption => $numberOfResults) {
			$facetOption = $filterEncoder->encodeFilter($facetOption, $this->facetConfiguration);
			$facetOptions[$facetOption] = $numberOfResults;
		}

		return $facetOptions;
	}


}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_querygroupfacetrenderer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_querygroupfacetrenderer.php']);
}

?>