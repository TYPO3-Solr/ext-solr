<?php
namespace ApacheSolrForTypo3\Solr\Facet;

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

use ApacheSolrForTypo3\Solr\Query\FilterEncoder\FilterEncoder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Facet renderer factory, creates facet renderers depending on the configured
 * type of a facet.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class FacetRendererFactory
{

    /**
     * Registration information for facet types.
     *
     * @var array
     */
    protected static $facetTypes = [];
    /**
     * Facets configuration from plugin.tx_solr.search.faceting.facets
     *
     * @var array
     */
    protected $facetsConfiguration = [];

    /**
     * Constructor.
     *
     * @param array $facetsConfiguration Facets configuration from plugin.tx_solr.search.faceting.facets
     */
    public function __construct(array $facetsConfiguration)
    {
        $this->facetsConfiguration = $facetsConfiguration;
    }

    /**
     * Register a facet type with its helper classes.
     *
     * @param string $facetType Facet type that can be used in a TypoScript facet configuration
     * @param string $filterEncoderClassName Class used to translate filter parameter from the URL to Lucene filter syntax
     * @param string $queryFacetBuilderClassName Class used to build the facet parameters according to the facet's configuration
     */
    public static function registerFacetType(
        $facetType,
        $filterEncoderClassName = '',
        $queryFacetBuilderClassName = ''
    ) {
        self::$facetTypes[$facetType] = [
            'type' => $facetType,
            'filterEncoder' => $filterEncoderClassName,
            'queryFacetBuilder' => $queryFacetBuilderClassName
        ];
    }

    /**
     * Looks up a facet's configuration and gets an instance of a filter parser
     * if one is configured.
     *
     * @param string $facetName Facet name
     * @return NULL|FilterEncoder NULL if no filter parser is configured for the facet's type or an instance of ApacheSolrForTypo3\Solr\Query\FilterEncoder\FilterEncoder otherwise
     */
    public function getFacetFilterEncoderByFacetName($facetName)
    {
        $filterEncoder = null;
        $facetConfiguration = $this->facetsConfiguration[$facetName . '.'];

        if (isset($facetConfiguration['type'])
            && !empty(self::$facetTypes[$facetConfiguration['type']]['filterEncoder'])
        ) {
            $filterEncoderClassName = self::$facetTypes[$facetConfiguration['type']]['filterEncoder'];

            $filterEncoder = GeneralUtility::makeInstance($filterEncoderClassName);
            $this->validateObjectIsQueryFilterEncoder($filterEncoder);
        }

        return $filterEncoder;
    }

    /**
     * Validates an object for implementing the ApacheSolrForTypo3\Solr\Query\FilterEncoder\FilterEncoder interface.
     *
     * @param object $object A potential filter parser object to check for implementing the ApacheSolrForTypo3\Solr\Query\FilterEncoder\FilterEncoder interface
     * @throws \UnexpectedValueException if $object does not implement ApacheSolrForTypo3\Solr\Query\FilterEncoder\FilterEncoder
     */
    protected function validateObjectIsQueryFilterEncoder($object)
    {
        if (!($object instanceof FilterEncoder)) {
            throw new \UnexpectedValueException(
                get_class($object) . ' is not an implementation of ApacheSolrForTypo3\Solr\Query\FilterEncoder\FilterEncoder',
                1328105893
            );
        }
    }

    /**
     * Looks up a facet's configuration and gets an instance of a query facet
     * builder if one is configured.
     *
     * @param string $facetName Facet name
     * @return NULL|FacetBuilder NULL if no query facet builder is configured for the facet's type or an instance of ApacheSolrForTypo3\Solr\Facet\FacetBuilder otherwise
     */
    public function getQueryFacetBuilderByFacetName($facetName)
    {
        $queryFacetBuilder = null;
        $facetConfiguration = $this->facetsConfiguration[$facetName . '.'];

        if (isset($facetConfiguration['type'])
            && !empty(self::$facetTypes[$facetConfiguration['type']]['queryFacetBuilder'])
        ) {
            $queryFacetBuilderClassName = self::$facetTypes[$facetConfiguration['type']]['queryFacetBuilder'];

            $queryFacetBuilder = GeneralUtility::makeInstance($queryFacetBuilderClassName);
            $this->validateObjectIsQueryFacetBuilder($queryFacetBuilder);
        }

        return $queryFacetBuilder;
    }

    /**
     * Validates an object for implementing the ApacheSolrForTypo3\Solr\Facet\FacetBuilder interface.
     *
     * @param object $object A potential query facet builder object to check for implementing the ApacheSolrForTypo3\Solr\Facet\FacetBuilder interface
     * @throws \UnexpectedValueException if $object does not implement ApacheSolrForTypo3\Solr\Facet\FacetBuilder
     */
    protected function validateObjectIsQueryFacetBuilder($object)
    {
        if (!($object instanceof FacetBuilder)) {
            throw new \UnexpectedValueException(
                get_class($object) . ' is not an implementation of ApacheSolrForTypo3\Solr\Facet\FacetBuilder',
                1328115265
            );
        }
    }
}
