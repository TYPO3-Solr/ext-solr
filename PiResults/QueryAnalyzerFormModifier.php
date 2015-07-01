<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Hans Höchtl <hans.hoechtl@typovision.de>
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
 * QueryAnalyzer form modifier, outputs parsed lucene query
 *
 * @author Hans Höchtl <hans.hoechtl@typovision.de>
 * @package TYPO3
 * @subpackage solr
 */
class Tx_Solr_PiResults_QueryAnalyzerFormModifier implements Tx_Solr_FormModifier, Tx_Solr_CommandPluginAware {

	/**
	 * Configuration
	 *
	 * @var array
	 */
	protected $configuration;

	/**
	 * The currently active plugin
	 *
	 * @var Tx_Solr_PluginBase_CommandPluginBase
	 */
	protected $parentPlugin;

	/**
	 * Constructor for class Tx_Solr_PiResults_QueryAnalyzerFormModifier
	 *
	 */
	public function __construct() {
		$this->configuration = Tx_Solr_Util::getSolrConfiguration();
	}

	/**
	 * Sets the currently active parent plugin.
	 *
	 * @param Tx_Solr_PluginBase_CommandPluginBase Currently active parent plugin
	 */
	public function setParentPlugin(Tx_Solr_PluginBase_CommandPluginBase $parentPlugin) {
		$this->parentPlugin = $parentPlugin;
	}

	/**
	 * Modifies the search form by providing an additional marker showing
	 * the parsed lucene query used by Solr.
	 *
	 * @param array An array of existing form markers
	 * @param tx_solr_Template An instance of the template engine
	 * @return array Array with additional markers for queryAnalysis
	 */
	public function modifyForm(array $markers, tx_solr_Template $template) {
		$markers['debug_query'] = '<br><strong>Parsed Query:</strong><br>' .
			$this->parentPlugin->getSearch()->getDebugResponse()->parsedquery;

		return $markers;
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/PiResults/Tx_Solr_PiResults_QueryAnalyzerFormModifier.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/PiResults/Tx_Solr_PiResults_QueryAnalyzerFormModifier.php']);
}

?>