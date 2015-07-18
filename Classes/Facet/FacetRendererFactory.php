<?php
/***************************************************************
*  Copyright notice
*
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
use ApacheSolrForTypo3\Solr\Facet\Facet;
/**
 * Facet renderer factory, creates facet renderers depending on the configured
 * type of a facet.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Tx_Solr_Facet_FacetRendererFactory {

	/**
	 * Registration information for facet types.
	 *
	 * @var array
	 */
	protected static $facetTypes = array();

	/**
	 * The default facet render, good for most cases.
	 *
	 * @var string
	 */
	private $defaultFacetRendererClassName = 'Tx_Solr_Facet_SimpleFacetRenderer';

	/**
	 * Facets configuration from plugin.tx_solr.search.faceting.facets
	 *
	 * @var array
	 */
	protected $facetsConfiguration = array();


	/**
	 * Constructor.
	 *
	 * @param array $facetsConfiguration Facets configuration from plugin.tx_solr.search.faceting.facets
	 */
	public function __construct(array $facetsConfiguration) {
		$this->facetsConfiguration = $facetsConfiguration;
	}

	/**
	 * Register a facet type with its helper classes.
	 *
	 * @param string $facetType Facet type that can be used in a TypoScript facet configuration
	 * @param string $rendererClassName Class used to render the facet UI
	 * @param string $filterEncoderClassName Class used to translate filter parameter from the URL to Lucene filter syntax
	 * @param string $queryFacetBuilderClassName Class used to build the facet parameters according to the facet's configuration
	 */
	public static function registerFacetType($facetType, $rendererClassName, $filterEncoderClassName = '', $queryFacetBuilderClassName = '') {
		self::$facetTypes[$facetType] = array(
			'type'              => $facetType,
			'renderer'          => $rendererClassName,
			'filterEncoder'     => $filterEncoderClassName,
			'queryFacetBuilder' => $queryFacetBuilderClassName
		);
	}

	/**
	 * Looks up a facet's configuration and creates a facet renderer accordingly.
	 *
	 * @param Facet $facet Facet
	 * @return Tx_Solr_FacetRenderer Facet renderer as defined by the facet's configuration
	 */
	public function getFacetRendererByFacet($facet) {
		$facetRenderer      = NULL;
		$facetConfiguration = $this->facetsConfiguration[$facet->getName() . '.'];

		$facetRendererClassName = $this->defaultFacetRendererClassName;
		if (isset($facetConfiguration['type'])) {
			$facetRendererClassName = $this->getFacetRendererClassNameByFacetType($facetConfiguration['type']);
		}

		$facetRenderer = GeneralUtility::makeInstance($facetRendererClassName, $facet);
		$this->validateObjectIsFacetRenderer($facetRenderer);

		return $facetRenderer;
	}

	/**
	 * Gets the facet's internal type. Uses the renderer class registered for a
	 * facet to get this information.
	 *
	 * @param string $facetName Name of a configured facet.
	 * @return string Internal type of the facet
	 */
	public function getFacetInternalType($facetName) {
		$facetConfiguration = $this->facetsConfiguration[$facetName . '.'];

		$facetRendererClassName = $this->defaultFacetRendererClassName;
		if (isset($facetConfiguration['type'])) {
			$facetRendererClassName = $this->getFacetRendererClassNameByFacetType($facetConfiguration['type']);
		}

		return call_user_func(array($facetRendererClassName, 'getFacetInternalType'));
	}

	/**
	 * Gets the facet renderer class name for a given facet type.
	 *
	 * @param string $facetType Facet type
	 * @return string Facet renderer class name
	 * @throws InvalidArgumentException
	 */
	protected function getFacetRendererClassNameByFacetType($facetType) {
		if (!array_key_exists($facetType, self::$facetTypes)) {
			throw new InvalidArgumentException(
				'No renderer configured for facet type "' . $facetType .'"',
				1328041286
			);
		}

		return self::$facetTypes[$facetType]['renderer'];
	}

	/**
	 * Validates an object for implementing the Tx_Solr_FacetRenderer interface.
	 *
	 * @param object $object A potential facet renderer object to check for implementing the Tx_Solr_FacetRenderer interface
	 * @throws UnexpectedValueException if $object does not implement Tx_Solr_FacetRenderer
	 */
	protected function validateObjectIsFacetRenderer($object) {
		if (!($object instanceof Tx_Solr_FacetRenderer)) {
			throw new UnexpectedValueException(
				get_class($object) . ' is not an implementation of Tx_Solr_FacetRenderer',
				1328038100
			);
		}
	}

	/**
	 * Looks up a facet's configuration and gets an instance of a filter parser
	 * if one is configured.
	 *
	 * @param string $facetName Facet name
	 * @return NULL|Tx_Solr_QueryFilterEncoder NULL if no filter parser is configured for the facet's type or an instance of Tx_Solr_QueryFilterEncoder otherwise
	 */
	public function getFacetFilterEncoderByFacetName($facetName) {
		$filterEncoder       = NULL;
		$facetConfiguration = $this->facetsConfiguration[$facetName . '.'];

		if (isset($facetConfiguration['type'])
		&& !empty(self::$facetTypes[$facetConfiguration['type']]['filterEncoder'])) {
			$filterEncoderClassName = self::$facetTypes[$facetConfiguration['type']]['filterEncoder'];

			$filterEncoder = GeneralUtility::makeInstance($filterEncoderClassName);
			$this->validateObjectIsQueryFilterEncoder($filterEncoder);
		}

		return $filterEncoder;
	}

	/**
	 * Validates an object for implementing the Tx_Solr_QueryFilterEncoder interface.
	 *
	 * @param object $object A potential filter parser object to check for implementing the Tx_Solr_QueryFilterEncoder interface
	 * @throws UnexpectedValueException if $object does not implement Tx_Solr_QueryFilterEncoder
	 */
	protected function validateObjectIsQueryFilterEncoder($object) {
		if (!($object instanceof Tx_Solr_QueryFilterEncoder)) {
			throw new UnexpectedValueException(
				get_class($object) . ' is not an implementation of Tx_Solr_QueryFilterEncoder',
				1328105893
			);
		}
	}

	/**
	 * Looks up a facet's configuration and gets an instance of a query facet
	 * builder if one is configured.
	 *
	 * @param string $facetName Facet name
	 * @return NULL|Tx_Solr_QueryFacetBuilder NULL if no query facet builder is configured for the facet's type or an instance of Tx_Solr_QueryFacetBuilder otherwise
	 */
	public function getQueryFacetBuilderByFacetName($facetName) {
		$queryFacetBuilder  = NULL;
		$facetConfiguration = $this->facetsConfiguration[$facetName . '.'];

		if (isset($facetConfiguration['type'])
		&& !empty(self::$facetTypes[$facetConfiguration['type']]['queryFacetBuilder'])) {
			$queryFacetBuilderClassName = self::$facetTypes[$facetConfiguration['type']]['queryFacetBuilder'];

			$queryFacetBuilder = GeneralUtility::makeInstance($queryFacetBuilderClassName);
			$this->validateObjectIsQueryFacetBuilder($queryFacetBuilder);
		}

		return $queryFacetBuilder;
	}

	/**
	 * Validates an object for implementing the Tx_Solr_QueryFacetBuilder interface.
	 *
	 * @param object $object A potential query facet builder object to check for implementing the Tx_Solr_QueryFacetBuilder interface
	 * @throws UnexpectedValueException if $object does not implement Tx_Solr_QueryFacetBuilder
	 */
	protected function validateObjectIsQueryFacetBuilder($object) {
		if (!($object instanceof Tx_Solr_QueryFacetBuilder)) {
			throw new UnexpectedValueException(
				get_class($object) . ' is not an implementation of Tx_Solr_QueryFacetBuilder',
				1328115265
			);
		}
	}

}
