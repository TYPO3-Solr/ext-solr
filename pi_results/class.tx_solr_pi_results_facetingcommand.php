<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2010 Ingo Renner <ingo@typo3.org>
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
 * facets view command
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_pi_results_FacetingCommand implements tx_solr_Command {

	/**
	 * @var tx_solr_Search
	 */
	protected $search;

	protected $parentPlugin;
	protected $configuration;

	/**
	 * constructor for class tx_solr_pi_results_FacetingCommand
	 */
	public function __construct(tslib_pibase $parentPlugin) {
		$this->search = t3lib_div::makeInstance('tx_solr_Search');

		$this->parentPlugin  = $parentPlugin;
		$this->configuration = $parentPlugin->conf;
	}

	public function execute() {
		$marker = array();

		if ($this->configuration['search.']['faceting'] != 0 && $this->search->getNumberOfResults()) {
			$configuredFacets = $this->configuration['search.']['faceting.']['facets.'];
			$facetCounts      = $this->search->getFacetCounts();

			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyFacets'])) {
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyFacets'] as $classReference) {
					$facetsModifier = t3lib_div::getUserObj($classReference);

					if ($facetsModifier instanceof tx_solr_FacetsModifier) {
						$responseDocuments = $facetsModifier->modifyFacets($this, $facetCounts);
					} else {
						// TODO throw exception
					}
				}
			}

			$facets = array();
			foreach ($configuredFacets as $facetName => $facetConfiguration) {
				$facetName = substr($facetName, 0, -1);
				$facetField = $facetConfiguration['field'];

				if (empty($facetField)) {
						// TODO later check for query and date, too
					continue;
				}

				$facetOptions = get_object_vars($facetCounts->facet_fields->$facetField);

				if (!empty($facetOptions)) {
					$facetContent = '';

					$facetContent = $this->renderFacetOptions(
						$facetName,
						$facetField,
						$facetCounts->facet_fields->$facetField
					);

					$facets[$facetName] = array(
						'content' => $facetContent,
						'count'   => count((array) $facetCounts->facet_fields->$facetField)
					);
				}
			}

			$marker['subpart_available_facets'] = $this->renderAvailableFacets($facets);
			$marker['subpart_used_facets']      = $this->renderUsedFacets();

			$this->addFacetsJavascript();
		}

		if (count($marker) === 0) {
				// in case we didn't fill any markers - like when there are no
				// search results - we set markers to null to signal that we
				// want to have the subpart removed completely
			$marker = null;
		}

		return $marker;
	}

	protected function renderAvailableFacets($facets) {
		$template = clone $this->parentPlugin->getTemplate();
		$template->workOnSubpart('available_facets');
		$facetContent = '';

		foreach ($facets as $facetName => $facetProperties) {
			$facetTemplate = clone $this->parentPlugin->getTemplate();
			$facetTemplate->workOnSubpart('single_facet');
			$facetTemplate->addSubpart('single_facet_option', $facetProperties['content']);

			$facet = $this->configuration['search.']['faceting.']['facets.'][$facetName . '.'];
			$facet['name'] = $facetName;

			if ($facetProperties['count'] > $this->configuration['search.']['faceting.']['limit']) {
				$showAllLink = '<a href="#" class="tx-solr-facet-show-all">###LLL:faceting_showMore###</a>';
				$showAllLink = tslib_cObj::wrap($showAllLink, $this->configuration['search.']['faceting.']['showAllLink.']['wrap']);
				$facet['show_all_link'] = $showAllLink;
			}

			$facetTemplate->addVariable('facet', $facet);
			$facetContent .= $facetTemplate->render();
		}

		$template->addSubpart('single_facet', $facetContent);

		return $template->render();
	}

	protected function renderUsedFacets() {
		$template = clone $this->parentPlugin->getTemplate();
		$template->workOnSubpart('used_facets');
		$query = $this->search->getQuery();
		$query->setLinkTargetPageId($this->parentPlugin->getLinkTargetPageId());

		$resultParameters = t3lib_div::_GET('tx_solr');
		$filterParameters = array();
		if (isset($resultParameters['filter'])) {
			$filterParameters = (array) array_map('urldecode', $resultParameters['filter']);
		}

		$facetsInUse = array();
		foreach ($filterParameters as $filter) {
			list($filterName, $filterValue) = explode(':', $filter);

			$facetText = $this->renderFacetOption($filterName, $filterValue);

			$removeFacetText = strtr(
				$this->configuration['search.']['faceting.']['removeFacetLinkText'],
				array(
					'@facetValue' => $filterValue,
					'@facetName'  => $filterName,
					'@facetText'  => $facetText
				)
			);

			$removeFacetLink = $this->buildRemoveFacetLink(
				$query, $removeFacetText, $filter
			);
			$removeFacetUrl = $this->buildRemoveFacetUrl($query, $filter);

			$facetToRemove = array(
				'link' => $removeFacetLink,
				'url'  => $removeFacetUrl,
				'text' => $removeFacetText,
				'name' => $filterValue
			);

			$facetsInUse[] = $facetToRemove;
		}
		$template->addLoop('facets_in_use', 'remove_facet', $facetsInUse);

		$template->addVariable('remove_all_facets', array(
			'url'  => $query->getQueryUrl(array('filter' => array())),
			'text' => '###LLL:faceting_removeAllFilters###'
		));

		$content = '';
		if (count($facetsInUse)) {
			$content = $template->render();
		}

		return $content;
	}

		// format for filter URL parameter:
		// tx_solr[filter]=$facetName0:$facetValue0,$facetName1:$facetValue1,$facetName2:$facetValue2
	protected function renderFacetOptions($facetName, $facetField, $facetOptions) {
		$facetConfiguration = $this->configuration['search.']['faceting.']['facets.'][$facetName . '.'];
		$template = clone $this->parentPlugin->getTemplate();
		$template->workOnSubpart('single_facet_option');
		$query = $this->search->getQuery();
		$query->setLinkTargetPageId($this->parentPlugin->getLinkTargetPageId());

		$facetOptionLinks = array();
		$i = 0;
		foreach ($facetOptions as $facetOption => $facetOptionResultCount) {
			if ($facetOption == '_empty_') {
					// TODO - for now we don't handle facet missing.
				continue;
			}

			$facetText    = $this->renderFacetOption($facetName, $facetOption);

			$facetLink    = $this->buildAddFacetLink($query, $facetText, $facetName . ':' . $facetOption);
			$facetLinkUrl = $this->buildAddFacetUrl($query, $facetName . ':' . $facetOption);

			$facetHidden = '';
			if (++$i > $this->configuration['search.']['faceting.']['limit']) {
				$facetHidden = 'tx-solr-facet-hidden';
			}

			$facetSelected = $this->isSelectedFacetOption($facetName, $facetOption);

				// negating the facet option links to remove a filter
			if ($facetConfiguration['selectingSelectedFacetOptionRemovesFilter']
				&& $facetSelected
			) {
				$facetLink    = $this->buildRemoveFacetLink($query, $facetText, $facetName . ':' . $facetOption);
				$facetLinkUrl = $this->buildRemoveFacetUrl($query, $facetName . ':' . $facetOption);
			}

			$facetOptionLinks[] = array(
				'hidden'   => $facetHidden,
				'link'     => $facetLink,
				'url'      => $facetLinkUrl,
				'text'     => $facetText,
				'value'    => $facetOption,
				'count'    => $facetOptionResultCount,
				'selected' => $facetSelected ? '1' : '0'
			);
		}
		$template->addLoop('facet_links', 'facet_link', $facetOptionLinks);

		return $template->render();
	}

	/**
	 * Renders a single facet option according to the rendering instructions
	 * that may be given.
	 *
	 * @param	string	The facet this option belongs to, used to determine the rendering instructions
	 * @param	string	The facet option's raw string value.
	 * @return	string	The facet option rendered according to rendering instructions if available
	 */
	protected function renderFacetOption($facetName, $facetOption) {
		$renderedFacetOption = $facetOption;

		if (isset($this->configuration['search.']['faceting.']['facets.'][$facetName . '.']['renderingInstruction'])) {
			$cObj = t3lib_div::makeInstance('tslib_cObj');

				// TODO provide a data field with information about whether a facet option is selected, and possibly all information from the renderOptions method so that one can use that with TS
			$cObj->start(array('optionValue' => $facetOption));

			$renderedFacetOption = $cObj->cObjGetSingle(
				$this->configuration['search.']['faceting.']['facets.'][$facetName . '.']['renderingInstruction'],
				$this->configuration['search.']['faceting.']['facets.'][$facetName . '.']['renderingInstruction.']
			);
		}

		return $renderedFacetOption;
	}

	/**
	 * Creates a link tag to add a facet to a search result.
	 *
	 * @param	tx_solr_Query	$query
	 * @param	string	$linkText
	 * @param
	 * @return	string	html link tag to add a facet to a search result
	 */
	protected function buildAddFacetLink($query, $linkText, $facetToAdd) {
		$filterParameters = $this->addFacetAndEncodeFilterParameters($facetToAdd);
		return $query->getQueryLink($linkText, array('filter' => $filterParameters));
	}

	/**
	 * Create only the url to add a facet to a search result.
	 *
	 * @param	tx_solr_Query	$query
	 * @param
	 * @return	string	url to a a facet to a search result
	 */
	protected function buildAddFacetUrl($query, $facetToAdd) {
		$filterParameters = $this->addFacetAndEncodeFilterParameters($facetToAdd);
		return $query->getQueryUrl(array('filter' => $filterParameters));
	}

	/**
	 * Returns a link tag with a link to remove a given facet from the search result array.
	 *
	 * @param	tx_solr_Query	$query
	 * @param	string
	 * @param
	 * @return	string	html tag with link to remove a facet
	 */
	protected function buildRemoveFacetLink($query, $linkText, $facetToRemove) {
		$filterParameters = $this->removeFacetAndEncodeFilterParameters($facetToRemove);
		return $query->getQueryLink($linkText, array('filter' => $filterParameters));
	}

	/**
	 * Build the url to remove a facet from a search result.
	 *
	 * @param	tx_solr_Query	$query
	 * @param
	 * @return	string
	 */
	protected function buildRemoveFacetUrl($query, $facetToRemove) {
		$filterParameters = $this->removeFacetAndEncodeFilterParameters($facetToRemove);
		return $query->getQueryUrl(array('filter' => $filterParameters));
	}

	/**
	 * This method retrieves the filter parmeters from the url and adds an additional
	 * facet to create a link to add additional facets to a search result.
	 *
	 * @param
	 * @return array
	 */
	protected function addFacetAndEncodeFilterParameters($facetToAdd){
		$resultParameters = t3lib_div::_GPmerged('tx_solr');
		$filterParameters = array();

		if (isset($resultParameters['filter']) && !$this->configuration['search.']['faceting.']['singleFacetMode']) {
			$filterParameters = array_map('urldecode', $resultParameters['filter']);
		}

		$filterParameters[] = $facetToAdd;
		$filterParameters = array_unique($filterParameters);
		$filterParameters = array_map('urlencode', $filterParameters);

		return $filterParameters;
	}

	/**
	 * This method is used to remove a facet from to filter query.
	 *
	 * @param
	 * @return	array
	 */
	protected function removeFacetAndEncodeFilterParameters($facetToRemove){
		$resultParameters = t3lib_div::_GPmerged('tx_solr');

			//urlencode the array to get the original representation
		$filterParameters = array_values((array) array_map('urldecode', $resultParameters['filter']));
		$filterParameters = array_unique($filterParameters);
		$indexToRemove    = array_search($facetToRemove, $filterParameters);

		if ($indexToRemove !== false) {
			unset($filterParameters[$indexToRemove]);
		}

		$filterParameters = array_map('urlencode', $filterParameters);

		return $filterParameters;
	}

	protected function addFacetsJavascript() {
		$jsFilePath = t3lib_extMgm::siteRelPath('solr') . 'resources/javascript/pi_results/results.js';

			// TODO make configurable once someone wants to use something other than jQuery
		$GLOBALS['TSFE']->additionalHeaderData[$this->parentPlugin->prefixId . '_faceting'] =
			'
			<script type="text/javascript">
			/*<![CDATA[*/

			var tx_solr_facetLabels = {
				\'showMore\' : \'' . $this->parentPlugin->pi_getLL('faceting_showMore') . '\',
				\'showFewer\' : \'' . $this->parentPlugin->pi_getLL('faceting_showFewer') . '\'
			};

			/*]]>*/
			</script>
			';

		if ($this->parentPlugin->conf['addDefaultJs']) {
			$GLOBALS['TSFE']->additionalHeaderData[$this->parentPlugin->prefixId . '_faceting'] .=
				'<script type="text/javascript" src="' . $jsFilePath . '"></script>';
		}
	}

	/**
	 * Determines whether a certain facet otpion is selected / whether it's
	 * filter is active.
	 *
	 * @param	string	The facet configuration name
	 * @param	string	The facet option to check whether it has been applied to the query.
	 * @return	boolean	True if the facet is active, false otherwise
	 */
	protected function isSelectedFacetOption($facetName, $facetOptionValue) {
		$isSelectedOption = false;

		$resultParameters = t3lib_div::_GET('tx_solr');
		$filterParameters = array();
		if (isset($resultParameters['filter'])) {
			$filterParameters = (array) array_map('urldecode', $resultParameters['filter']);
		}

		$facetsInUse = array();
		foreach ($filterParameters as $filter) {
			list($filterName, $filterValue) = explode(':', $filter);

			if ($filterName == $facetName && $filterValue == $facetOptionValue) {
				$isSelectedOption = true;
				break;
			}

		}

		return $isSelectedOption;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_facetingcommand.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_facetingcommand.php']);
}

?>