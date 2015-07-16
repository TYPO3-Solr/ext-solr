<?php
namespace ApacheSolrForTypo3\Solr\Facet;
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
*  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Date range facet renderer.
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 */
class DateRangeFacetRenderer extends ApacheSolrForTypo3\Solr\Facet\AbstractFacetRenderer {

	/**
	 * Provides the internal type of facets the renderer handles.
	 * The type is one of field, range, or query.
	 *
	 * @return string Facet internal type
	 */
	public static function getFacetInternalType() {
		return \Tx_Solr_Facet_Facet::TYPE_RANGE;
	}

	/**
	 * Renders a date renage facet by providing two input fields, enhanced with
	 * date pickers.
	 *
	 * @see \Tx_Solr_Facet_SimpleFacetRenderer::render()
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
						. \Tx_Solr_Query_FilterEncoder_DateRange::DELIMITER
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
		$facetOption      = GeneralUtility::makeInstance('Tx_Solr_Facet_FacetOption', $this->facetName, '');
		$facetLinkBuilder = GeneralUtility::makeInstance('Tx_Solr_Facet_LinkBuilder', $this->search->getQuery(), $this->facetName, $facetOption);
		$facetLinkBuilder->setLinkTargetPageId($this->linkTargetPageId);

		return $facetLinkBuilder->getAddFacetOptionUrl();
	}

	/**
	 * Loads jQuery libraries for the date pickers.
	 *
	 */
	protected function loadJavaScriptFiles() {
		$javascriptManager = GeneralUtility::makeInstance('Tx_Solr_JavascriptManager');

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
