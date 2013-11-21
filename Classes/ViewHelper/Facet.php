<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Stefan Sprenger <stefan.sprenger@dkd.de>
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
 * Subpart viewhelper class to render facets
 *
 * @author	Stefan Sprenger <stefan.sprenger@dkd.de>
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class Tx_Solr_ViewHelper_Facet extends Tx_Solr_ViewHelper_AbstractSubpartViewHelper {

	/**
	 * TypoScript configuration of tx_solr
	 *
	 * @var	array
	 */
	protected $configuration = NULL;

	/**
	 * Constructor for class Tx_Solr_ViewHelper_Facet
	 *
	 */
	public function __construct(array $arguments = array()) {
		if (is_null($this->configuration)) {
			$this->configuration = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.'];
		}
	}

	/**
	 * Renders a facet.
	 *
	 * @param	array	$arguments
	 * @return	string
	 */
	public function execute(array $arguments = array()) {
		$facetName        = trim($arguments[0]);
		$configuredFacets = $this->configuration['search.']['faceting.']['facets.'];
		$facetContent     = '';
		$template         = clone $this->template;
		$search           = t3lib_div::makeInstance('Tx_Solr_Search');

		if (!array_key_exists($facetName . '.', $configuredFacets)) {
			throw new UnexpectedValueException(
				'Tried rendering facet "' . $facetName . '", no configuration found.',
				1329138206
			);
		}

		if ($search->hasSearched()) {
			$facetRendererFactory = t3lib_div::makeInstance(
				'Tx_Solr_Facet_FacetRendererFactory',
				$configuredFacets
			);

			$facet = t3lib_div::makeInstance(
				'Tx_Solr_Facet_Facet',
				$facetName,
				$facetRendererFactory->getFacetInternalType($facetName)
			);

			$facetRenderer = $facetRendererFactory->getFacetRendererByFacet($facet);
			$facetRenderer->setTemplate($this->template);
			$facetRenderer->setLinkTargetPageId($this->configuration['search.']['targetPage']);

			$facet = $facetRenderer->getFacetProperties();
			$template->addVariable('facet', $facet);

			$facetContent = $facetRenderer->renderFacet();
		}

		$template->addSubpart('single_facet', $facetContent);

		return $template->render();
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/ViewHelper/Facet.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/ViewHelper/Facet.php']);
}

?>