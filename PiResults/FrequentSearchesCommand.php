<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2011 Dimitri Ebert <dimitri.ebert@dkd.de>
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
 * Command to list frequent searched terms.
 *
 * @author	Dimitri Ebert <dimitri.ebert@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class Tx_Solr_PiResults_FrequentSearchesCommand implements Tx_Solr_PluginCommand {

	/**
	 * Instance of the caching frontend used to cachethis command's output.
	 *
	 * @var t3lib_cache_frontend_AbstractFrontend
	 */
	protected $cacheInstance;

	/**
	 * Parent plugin
	 *
	 * @var Tx_Solr_PiResults_Results
	 */
	protected $parentPlugin;

	/**
	 * Configuration
	 *
	 * @var	array
	 */
	protected $configuration;

	/**
	 * Constructor.
	 *
	 * @param Tx_Solr_PluginBase_CommandPluginBase $parentPlugin Parent plugin object.
	 */
	public function __construct(Tx_Solr_PluginBase_CommandPluginBase $parentPlugin) {
		$this->parentPlugin  = $parentPlugin;
		$this->configuration = $parentPlugin->conf;

		$this->initializeCache();
	}

	/**
	 * Initializes the cache for this command.
	 *
	 * @return void
	 */
	protected function initializeCache() {
		t3lib_cache::initializeCachingFramework();

		try {
			$this->cacheInstance = $GLOBALS['typo3CacheManager']->getCache('tx_solr');
		} catch (t3lib_cache_exception_NoSuchCache $e) {
			$this->cacheInstance = $GLOBALS['typo3CacheFactory']->create(
				'tx_solr',
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr']['frontend'],
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr']['backend'],
				$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr']['options']
			);
		}
	}

	/**
	 * Provides the values for the markers for the frequent searches links
	 *
	 * @return array	An array containing values for markers for the frequent searches links template
	 */
	public function execute() {
		if ($this->configuration['search.']['frequentSearches'] == 0) {
				// command is not activated, intended early return
			return NULL;
		}

		$marker = array(
			'loop_frequentsearches|term' => $this->getSearchTermMarkerProperties($this->getFrequentSearchTerms())
		);

		return $marker;
	}

	/**
	 * Builds the properties for the frequent search term markers.
	 *
	 * @param	array	Frequent search terms as array with terms as keys and hits as the value
	 * @return	array	An array with content for the frequent terms markers
	 */
	protected function getSearchTermMarkerProperties(array $frequentSearchTerms) {
		$frequentSearches = array();

		$minimumSize = $this->configuration['search.']['frequentSearches.']['minSize'];
		$maximumSize = $this->configuration['search.']['frequentSearches.']['maxSize'];

		if (count($frequentSearchTerms)) {
			$maximumHits = max(array_values($frequentSearchTerms));
			$minimumHits = min(array_values($frequentSearchTerms));
			$spread      = $maximumHits - $minimumHits;
			$step        = ($spread == 0) ? 1 : ($maximumSize - $minimumSize) / $spread;

			foreach ($frequentSearchTerms as $term => $hits) {
				$size = round($minimumSize + (($hits - $minimumHits) * $step));
				$frequentSearches[] = array(
					'term'       => Tx_Solr_Template::escapeMarkers($term),
					'hits'       => $hits,
					'style'      => 'font-size: ' . $size . 'px',
					'class'      => 'tx-solr-frequent-term-' . $size,
					'parameters' => '&q=' . html_entity_decode($term, ENT_NOQUOTES, 'UTF-8'),
					'pid'        => $this->parentPlugin->getLinkTargetPageId()
				);
			}

		}

		return $frequentSearches;
	}

	/**
	 * Generates an array with terms and hits
	 *
	 * @return Tags as array with terms and hits
	 */
	protected function getFrequentSearchTerms() {
		$terms = array();

			// Use configuration as cache identifier
		$identifier = 'frequentSearchesTags';

		if ($this->configuration['search.']['frequentSearches.']['select.']['checkRootPageId']) {
			$identifier.= '_RP' . (int)$GLOBALS['TSFE']->tmpl->rootLine[0]['uid'];
		}
		if ($this->configuration['search.']['frequentSearches.']['select.']['checkLanguage']) {
			$identifier.= '_L' . (int)$GLOBALS['TSFE']->sys_language_uid;
		}

		$identifier.= '_' . md5(serialize($this->configuration['search.']['frequentSearches.']));

		if ($this->cacheInstance->has($identifier)) {
			$terms = $this->cacheInstance->get($identifier);
		} else {
			$terms = $this->getFrequentSearchTermsFromStatistics();

			if($this->configuration['search.']['frequentSearches.']['sortBy'] == 'hits') {
				arsort($terms);
			} else {
				ksort($terms);
			}

			$lifetime = NULL;
			if (isset($this->configuration['search.']['frequentSearches.']['cacheLifetime'])) {
				$lifetime = intval($this->configuration['search.']['frequentSearches.']['cacheLifetime']);
			}

			$this->cacheInstance->set($identifier, $terms, array(), $lifetime);
		}

		return $terms;
	}

	/**
	 * Gets frequent search terms from the statistics tracking table.
	 *
	 * @return	array	Array of frequent search terms, keys are the terms, values are hits
	 */
	protected function getFrequentSearchTermsFromStatistics() {
		$terms = array();

		if ($this->configuration['search.']['frequentSearches.']['select.']['checkRootPageId']) {
			$checkRootPidWhere = 'root_pid = ' . $GLOBALS['TSFE']->tmpl->rootLine[0]['uid'];
		} else {
			$checkRootPidWhere = '1';
		}
		if ($this->configuration['search.']['frequentSearches.']['select.']['checkLanguage']) {
			$checkLanguageWhere = ' AND language =' . $GLOBALS['TSFE']->sys_language_uid;
		} else {
			$checkLanguageWhere = '';
		}

		$sql = $this->configuration['search.']['frequentSearches.'];
		$sql['select.']['ADD_WHERE'] = $checkRootPidWhere . $checkLanguageWhere . ' ' . $sql['select.']['ADD_WHERE'];

		$frequentSearchTerms = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			$sql['select.']['SELECT'],
			$sql['select.']['FROM'],
			$sql['select.']['ADD_WHERE'],
			$sql['select.']['GROUP_BY'],
			$sql['select.']['ORDER_BY'],
			$sql['limit']
		);

		foreach ($frequentSearchTerms as $term) {
			$terms[$term['search_term']] = $term['hits'];
		}

		return $terms;
	}

}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/PiResults/FrequentSearchesCommand.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/PiResults/FrequentSearchesCommand.php']);
}

?>