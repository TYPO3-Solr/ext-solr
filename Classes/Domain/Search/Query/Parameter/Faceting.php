<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\Parameter;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\SortingExpression;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * The Faceting ParameterProvider is responsible to build the solr query parameters
 * that are needed for the highlighting.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder
 */
class Faceting extends AbstractDeactivatable
{

    /**
     * @var string
     */
    protected $sorting = '';

    /**
     * @var int
     */
    protected $minCount = 1;

    /**
     * @var
     */
    protected $limit = 10;

    /**
     * @var array
     */
    protected $fields = [];

    /**
     * @var array
     */
    protected $additionalParameters = [];

    /**
     * Faceting constructor.
     *
     * @param bool $isEnabled
     * @param string $sorting
     * @param int $minCount
     * @param int $limit
     * @param array $fields
     * @param array $additionalParameters
     */
    public function __construct($isEnabled, $sorting = '', $minCount = 1, $limit = 10, $fields = [], $additionalParameters = [])
    {
        $this->isEnabled = $isEnabled;
        $this->sorting = $sorting;
        $this->minCount = $minCount;
        $this->limit = $limit;
        $this->fields = $fields;
        $this->additionalParameters = $additionalParameters;
    }

    /**
     * @return string
     */
    public function getSorting()
    {
        return $this->sorting;
    }

    /**
     * @param string $sorting
     */
    public function setSorting($sorting)
    {
        $this->sorting = $sorting;
    }

    /**
     * @return int
     */
    public function getMinCount()
    {
        return $this->minCount;
    }

    /**
     * @param int $minCount
     */
    public function setMinCount($minCount)
    {
        $this->minCount = $minCount;
    }

    /**
     * @return mixed
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param mixed $limit
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param array $fields
     */
    public function setFields(array $fields)
    {
        $this->fields = $fields;
    }

    /**
     * @param string $fieldName
     */
    public function addField($fieldName)
    {
        $this->fields[] = $fieldName;
    }

    /**
     * @return array
     */
    public function getAdditionalParameters(): array
    {
        return $this->additionalParameters;
    }

    /**
     * @param array $additionalParameters
     */
    public function setAdditionalParameters(array $additionalParameters)
    {
        $this->additionalParameters = $additionalParameters;
    }

    /**
     * @param array $value
     */
    public function addAdditionalParameter($key, $value)
    {
        $this->additionalParameters[$key] = $value;
    }

    /**
     * Reads the facet sorting configuration and applies it to the queryParameters.
     *
     * @param array $facetParameters
     * @return array
     */
    protected function applySorting(array $facetParameters)
    {
        $sortingExpression = new SortingExpression();
        $globalSortingExpression = $sortingExpression->getForFacet($this->sorting);

        if (!empty($globalSortingExpression)) {
            $facetParameters['facet.sort'] = $globalSortingExpression;
        }

        return $facetParameters;
    }

    /**
     * @param TypoScriptConfiguration $solrConfiguration
     * @return Faceting
     */
    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration)
    {
        $isEnabled = $solrConfiguration->getSearchFaceting();
        if (!$isEnabled) {
            return new Faceting(false);
        }

        $minCount = $solrConfiguration->getSearchFacetingMinimumCount();
        $limit = $solrConfiguration->getSearchFacetingFacetLimit();
        $sorting = $solrConfiguration->getSearchFacetingSortBy();

        return new Faceting($isEnabled, $sorting, $minCount, $limit);
    }

    /**
     * @return Faceting
     */
    public static function getEmpty()
    {
        return new Faceting(false);
    }
}
