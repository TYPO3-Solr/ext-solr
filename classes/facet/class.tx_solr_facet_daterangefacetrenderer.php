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
 * Date range facet renderer.
 *
 * @author	Markus Goldbach <markus.goldbach@dkd.de>
 */
class tx_solr_facet_DateRangeFacetRenderer extends tx_solr_facet_AbstractFacetRenderer {

	/**
	 * Provides the internal type of facets the renderer handles.
	 * The type is one of field, range, or query.
	 *
	 * @return string Facet internal type
	 */
	public static function getFacetInternalType() {
		return tx_solr_facet_Facet::TYPE_RANGE;
	}

	/**
	 * Renders a date renage facet by providing two input fields, enhanced with
	 * date pickers.
	 *
	 * @see tx_solr_facet_SimpleFacetRenderer::render()
	 */
	public function renderFacetOptions() {
		$this->loadJavaScriptFiles();

		$content = '
			<li>
				<script type="text/javascript">
				/*<![CDATA[*/
					jQuery(document).ready(function() {
					jQuery(".dateselector").datepicker();
					jQuery(".dateselector").change(function(){ solrRequest("'
						. $this->facetName
						. '", "'
						. tx_solr_query_filterencoder_DateRange::DELIMITER
						. '") });
					});
				/*]]>*/
				</script>

				<input type="hidden" id="' . $this->facetName . '_url" value="' . $this->buildAddFacetUrl($this->facetName) . '" />
				<input type="text" id="start_date_' . $this->facetName . '" class="dateselector" />
				###LLL:rangebarrier###
				<input type="text" id="end_date_' . $this->facetName . '" class="dateselector" />
			</li>
		';

		return $content;
	}

	/**
	 * tbd
	 */
	protected function buildAddFacetUrl($facetName) {
		$facetOption      = t3lib_div::makeInstance('tx_solr_facet_FacetOption', $this->facetName, '');
		$facetLinkBuilder = t3lib_div::makeInstance('tx_solr_facet_LinkBuilder', $this->search->getQuery(), $this->facetName, $facetOption);
		$facetLinkBuilder->setLinkTargetPageId($this->linkTargetPageId);

		return $facetLinkBuilder->getAddFacetOptionUrl();
	}

	/**
	 * Loads jQuery libraries for the date pickers.
	 *
	 */
	protected function loadJavaScriptFiles() {
		$javascriptManager = t3lib_div::makeInstance('tx_solr_JavascriptManager');

		$javascriptManager->loadFile('library');
		$javascriptManager->loadFile('ui');
		$javascriptManager->loadFile('ui.datepicker');

		$language = $GLOBALS['TSFE']->tmpl->setup['config.']['language'];
		if ($language != 'en') {
				// load date picker translation
			$javascriptManager->loadFile('ui.datepicker.' . $language);
		}

		$javascriptManager->loadFile('faceting.dateRangeHelper');

		$javascriptManager->addJavascriptToPage();
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_daterangefacetrenderer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_daterangefacetrenderer.php']);
}

?>