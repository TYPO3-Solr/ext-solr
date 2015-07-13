<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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
 * sorting view command
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Tx_Solr_PiResults_SortingCommand implements Tx_Solr_PluginCommand {

	/**
	 * Search instance
	 *
	 * @var Tx_Solr_Search
	 */
	protected $search;

	/**
	 * Parent plugin
	 *
	 * @var Tx_Solr_PiResults_Results
	 */
	protected $parentPlugin;

	/**
	 * Configuration
	 *
	 * @var array
	 */
	protected $configuration;

	/**
	 * Constructor.
	 *
	 * @param Tx_Solr_PluginBase_CommandPluginBase $parentPlugin Parent plugin object.
	 */
	public function __construct(Tx_Solr_PluginBase_CommandPluginBase $parentPlugin) {
		$this->search = GeneralUtility::makeInstance('Tx_Solr_Search');

		$this->parentPlugin  = $parentPlugin;
		$this->configuration = $parentPlugin->conf;
	}

	public function execute() {
		$marker = array();

		if ($this->configuration['search.']['sorting'] != 0 && $this->search->getNumberOfResults()) {
			$marker['loop_sort|sort'] = $this->getSortingLinks();
		}

		if (count($marker) === 0) {
				// in case we didn't fill any markers - like when there are no
				// search results - we set markers to NULL to signal that we
				// want to have the subpart removed completely
			$marker = NULL;
		}

		return $marker;
	}

	protected function getSortingLinks() {
		$sortHelper = GeneralUtility::makeInstance('Tx_Solr_Sorting', $this->configuration['search.']['sorting.']['options.']);

		$query = $this->search->getQuery();

		$queryLinkBuilder = GeneralUtility::makeInstance('Tx_Solr_Query_LinkBuilder', $query);
		$queryLinkBuilder->setLinkTargetPageId($this->parentPlugin->getLinkTargetPageId());

		$sortOptions = array();

		$urlParameters     = GeneralUtility::_GP('tx_solr');
		$urlSortParameters = GeneralUtility::trimExplode(',', $urlParameters['sort']);

		$configuredSortOptions = $sortHelper->getSortOptions();

		foreach ($configuredSortOptions as $sortOptionName => $sortOption) {
			$sortDirection = $this->configuration['search.']['sorting.']['defaultOrder'];
			if (!empty($this->configuration['search.']['sorting.']['options.'][$sortOptionName . '.']['defaultOrder'])) {
				$sortDirection = $this->configuration['search.']['sorting.']['options.'][$sortOptionName . '.']['defaultOrder'];
			}

			$sortIndicator        = $sortDirection;
			$currentSortOption    = '';
			$currentSortDirection = '';
			foreach($urlSortParameters as $urlSortParameter){
				$explodedUrlSortParameter = explode(' ', $urlSortParameter);
				if($explodedUrlSortParameter[0] == $sortOptionName){
					list($currentSortOption, $currentSortDirection) = $explodedUrlSortParameter;
					break;
				}
			}

				// toggle sorting direction for the current sorting field
			if ($currentSortOption == $sortOptionName) {
				switch ($currentSortDirection) {
					case 'asc':
						$sortDirection = 'desc';
						$sortIndicator = 'asc';
						break;
					case 'desc':
						$sortDirection = 'asc';
						$sortIndicator = 'desc';
						break;
				}
			}

			if (!empty($this->configuration['search.']['sorting.']['options.'][$sortOptionName . '.']['fixedOrder'])) {
				$sortDirection = $this->configuration['search.']['sorting.']['options.'][$sortOptionName . '.']['fixedOrder'];
			}

			$sortParameter = $sortOptionName . ' ' . $sortDirection;

			$temp = array(
				'link'       => $queryLinkBuilder->getQueryLink(
					$sortOption['label'],
					array('sort' => $sortParameter)
				),
				'url'        => $queryLinkBuilder->getQueryUrl(
					array('sort' => $sortParameter)
				),
				'optionName' => $sortOptionName,
				'field'      => $sortOption['field'],
				'label'      => $sortOption['label'],
				'is_current' => '0',
				'direction'  => $sortDirection,
				'indicator'  => $sortIndicator,
				'current_direction' => ' '
			);

				// set sort indicator for the current sorting field
			if ($currentSortOption == $sortOptionName) {
				$temp['selected']          = 'selected="selected"';
				$temp['current']           = 'current';
				$temp['is_current']        = '1';
				$temp['current_direction'] = $sortIndicator;
			}

				// special case relevance: just reset the search to normal behavior
			if ($sortOptionName == 'relevance') {
				$temp['link'] = $queryLinkBuilder->getQueryLink(
					$sortOption['label'],
					array('sort' => NULL)
				);
				$temp['url'] = $queryLinkBuilder->getQueryUrl(
					array('sort' => NULL)
				);
				unset($temp['direction'], $temp['indicator']);
			}

			$sortOptions[] = $temp;
		}

		return $sortOptions;
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/PiResults/SortingCommand.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/PiResults/SortingCommand.php']);
}

?>