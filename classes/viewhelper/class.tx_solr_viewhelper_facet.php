<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Stefan Sprenger <stefan.sprenger@dkd.de>
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
class tx_solr_viewhelper_Facet extends tx_solr_viewhelper_AbstractSubpartViewHelper {

	/**
	 * TypoScript configuration of tx_solr
	 *
	 * @var	array
	 */
	protected $configuration = NULL;

	/**
	 * Constructor for class tx_solr_viewhelper_Facet
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
		$facetName = trim($arguments[0]);

		$facetRenderer = t3lib_div::makeInstance(
			'tx_solr_facet_FacetRenderer',
			$facetName,
			$this->template
		);
		$facetRenderer->setLinkTargetPageId($this->configuration['search.']['targetPage']);

		return $facetRenderer->renderFacet();
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_facet.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_facet.php']);
}

?>