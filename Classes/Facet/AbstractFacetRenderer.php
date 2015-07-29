<?php
namespace ApacheSolrForTypo3\Solr\Facet;
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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
 * Facet renderer.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
abstract class AbstractFacetRenderer implements \Tx_Solr_FacetRenderer {

	/**
	 * @var \Tx_Solr_Search
	 */
	protected $search;

	/**
	 * The name of the facet being rendered
	 *
	 * @var string
	 */
	protected $facetName;

	/**
	 * The facet to render.
	 *
	 * @var Facet
	 */
	protected $facet;

	/**
	 * @var array
	 */
	protected $facetConfiguration;

	/**
	 * @var array
	 */
	protected $solrConfiguration;

	/**
	 * Template
	 *
	 * @var \Tx_Solr_Template
	 */
	protected $template = NULL;

	/**
	 * Link target page id.
	 *
	 * @var integer
	 */
	protected $linkTargetPageId = 0;

	/**
	 * Query link builder
	 *
	 * @var \Tx_Solr_Query_LinkBuilder
	 */
	protected $queryLinkBuilder;



	/**
	 * Constructor.
	 *
	 * @param Facet $facet The facet to render.
	 */
	public function __construct(Facet $facet) {
		$this->search = GeneralUtility::makeInstance('Tx_Solr_Search');

		$this->facet              = $facet;
		$this->facetName          = $facet->getName();

		$this->solrConfiguration  = \Tx_Solr_Util::getSolrConfiguration();
		$this->facetConfiguration = $this->solrConfiguration['search.']['faceting.']['facets.'][$this->facetName . '.'];
		$this->linkTargetPageId   = $GLOBALS['TSFE']->id;

		$this->queryLinkBuilder = GeneralUtility::makeInstance('Tx_Solr_Query_LinkBuilder', $this->search->getQuery());
	}

	/**
	 * Renders the complete facet.
	 *
	 * @return string Facet markup.
	 */
	public function renderFacet() {
			// TODO must check whether $this->template is set

		$facetContent = '';

		$showEmptyFacets = FALSE;
		if (!empty($this->solrConfiguration['search.']['faceting.']['showEmptyFacets'])) {
			$showEmptyFacets = TRUE;
		}

		$showEvenWhenEmpty = FALSE;
		if (!empty($this->solrConfiguration['search.']['faceting.']['facets.'][$this->facetName . '.']['showEvenWhenEmpty'])) {
			$showEvenWhenEmpty = TRUE;
		}

			// if the facet doesn't provide any options, don't render it unless
			// it is configured to be rendered nevertheless
		if (!$this->facet->isEmpty() || $showEmptyFacets || $showEvenWhenEmpty) {
			$facetTemplate = clone $this->template;
			$facetTemplate->workOnSubpart('single_facet');

			$facetOptions = $this->renderFacetOptions();
			$facetTemplate->addSubpart('single_facet_option', $facetOptions);

			$facet = $this->getFacetProperties();

				// remove properties irrelevant for rendering in the template engine
			unset(
				$facet['renderingInstruction'],
				$facet['renderingInstruction.'],
				$facet[$facet['type'] . '.'],
				$facet['type']
			);

			$facetTemplate->addVariable('facet', $facet);
			$facetContent = $facetTemplate->render();
		}

		return $facetContent;
	}

	/**
	 * Renders a numeric range facet by providing a slider
	 *
	 */
	abstract protected function renderFacetOptions();

	/**
	 * (non-PHPdoc)
	 * @see \Tx_Solr_FacetRenderer::getFacetProperties()
	 */
	public function getFacetProperties() {
		$facet = $this->facetConfiguration;

			// TODO move these properties into Facet and provide them via ArrayAccess interface

		$facet['name']      = $this->facetName;
		$facet['count']     = $this->getFacetOptionsCount();
		$facet['active']    = $this->facet->isActive() ? '1' : '0';
		$facet['empty']     = $this->facet->isEmpty() ? '1' : '0';
		$facet['reset_url'] = $this->buildResetFacetUrl();

		$contentObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
		$facet['label'] = $contentObject->stdWrap(
			$this->facetConfiguration['label'],
			$this->facetConfiguration['label.']
		);

		$facet['type'] = 'default';
		if (!empty($this->facetConfiguration['type'])) {
			$facet['type'] = $this->facetConfiguration['type'];
		}

		return $facet;
	}

	/**
	 * (non-PHPdoc)
	 * @see \Tx_Solr_FacetRenderer::getFacetOptions()
	 */
	public function getFacetOptions() {
		return $this->facet->getOptionsRaw();
	}

	/**
	 * (non-PHPdoc)
	 * @see \Tx_Solr_FacetRenderer::getFacetOptionsCount()
	 */
	public function getFacetOptionsCount() {
		return $this->facet->getOptionsCount();
	}

	/**
	 * (non-PHPdoc)
	 * @see \Tx_Solr_FacetRenderer::setTemplate()
	 */
	public function setTemplate(\Tx_Solr_Template $template) {
		$this->template = $template;
	}

	/**
	 * (non-PHPdoc)
	 * @see \Tx_Solr_FacetRenderer::setLinkTargetPageId()
	 */
	public function setLinkTargetPageId($linkTargetPageId){
		$this->linkTargetPageId = intval($linkTargetPageId);
		$this->queryLinkBuilder->setLinkTargetPageId($this->linkTargetPageId);
	}

	/**
	 * Builds the URL to reset all options of a facet - removing all its applied
	 * filters from a result set.
	 *
	 * @return string  Url to remove a facet
	 */
	protected function buildResetFacetUrl() {
		$resetFacetUrl    = '';
		$resultParameters = GeneralUtility::_GPmerged('tx_solr');

		if (is_array($resultParameters['filter'])) {
				// urldecode the array to get the original representation
			$filterParameters = array_values((array) array_map('urldecode', $resultParameters['filter']));
			$filterParameters = array_unique($filterParameters);

				// find and remove all options for this facet
			foreach ($filterParameters as $key => $filter) {
				list($filterName, $filterValue) = explode(':', $filter);
				if ($filterName == $this->facetName) {
					unset($filterParameters[$key]);
				}
			}
			$filterParameters = array_map('urlencode', $filterParameters);

			$resetFacetUrl = $this->queryLinkBuilder->getQueryUrl(array('filter' => $filterParameters));
		} else {
			$resetFacetUrl = $this->queryLinkBuilder->getQueryUrl();
		}

		return $resetFacetUrl;
	}

}