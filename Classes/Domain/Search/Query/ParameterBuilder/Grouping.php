<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\AbstractQueryBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * The Grouping ParameterProvider is responsible to build the solr query parameters
 * that are needed for the grouping.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder
 */
class Grouping extends AbstractDeactivatable implements ParameterBuilder
{

    /**
     * @var array
     */
    protected $fields = [];

    /**
     * @var array
     */
    protected $sortings = [];

    /**
     * @var array
     */
    protected $queries = [];

    /**
     * @var int
     */
    protected $numberOfGroups = 5;

    /**
     * @var int
     */
    protected $resultsPerGroup = 1;

    /**
     * Grouping constructor.
     *
     * @param bool $isEnabled
     * @param array $fields
     * @param array $sortings
     * @param array $queries
     * @param int $numberOfGroups
     * @param int $resultsPerGroup
     */
    public function __construct($isEnabled, array $fields = [], array $sortings = [], array $queries = [], $numberOfGroups = 5, $resultsPerGroup = 1)
    {
        $this->isEnabled = $isEnabled;
        $this->fields = $fields;
        $this->sortings = $sortings;
        $this->queries = $queries;
        $this->numberOfGroups = $numberOfGroups;
        $this->resultsPerGroup = $resultsPerGroup;
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
     * @param string $field
     */
    public function addField(string $field)
    {
        $this->fields[] = $field;
    }

    /**
     * @return array
     */
    public function getSortings()
    {
        return $this->sortings;
    }

    /**
     * @param string $sorting
     */
    public function addSorting($sorting)
    {
        $this->sortings[] = $sorting;
    }

    /**
     * @param array $sortings
     */
    public function setSortings(array $sortings)
    {
        $this->sortings = $sortings;
    }

    /**
     * @return array
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * @param string $query
     */
    public function addQuery($query)
    {
        $this->queries[] = $query;
    }

    /**
     * @param array $queries
     */
    public function setQueries(array $queries)
    {
        $this->queries = $queries;
    }

    /**
     * @return int
     */
    public function getNumberOfGroups()
    {
        return $this->numberOfGroups;
    }

    /**
     * @param int $numberOfGroups
     */
    public function setNumberOfGroups($numberOfGroups)
    {
        $this->numberOfGroups = $numberOfGroups;
    }

    /**
     * @return int
     */
    public function getResultsPerGroup()
    {
        return $this->resultsPerGroup;
    }

    /**
     * @param int $resultsPerGroup
     */
    public function setResultsPerGroup($resultsPerGroup)
    {
        $resultsPerGroup = max(intval($resultsPerGroup), 0);
        $this->resultsPerGroup = $resultsPerGroup;
    }

    /**
     * @param TypoScriptConfiguration $solrConfiguration
     * @return Grouping
     */
    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration)
    {
        $isEnabled = $solrConfiguration->getSearchGrouping();
        if (!$isEnabled) {
            return new Grouping(false);
        }

        $fields = [];
        $queries = [];
        $sortings = [];

        $resultsPerGroup = $solrConfiguration->getSearchGroupingHighestGroupResultsLimit();
        $configuredGroups = $solrConfiguration->getSearchGroupingGroupsConfiguration();
        $numberOfGroups = $solrConfiguration->getSearchGroupingNumberOfGroups();
        $sortBy = $solrConfiguration->getSearchGroupingSortBy();

        foreach ($configuredGroups as $groupName => $groupConfiguration) {
            if (isset($groupConfiguration['field'])) {
                $fields[] = $groupConfiguration['field'];
            } elseif (isset($groupConfiguration['query'])) {
                $queries[] = $groupConfiguration['query'];
            }
        }

        if (!empty(trim($sortBy))) {
            $sortings[] = $sortBy;
        }

        return new Grouping($isEnabled, $fields, $sortings, $queries, $numberOfGroups, $resultsPerGroup);
    }

    /**
     * @return Grouping
     */
    public static function getEmpty()
    {
        return new Grouping(false);
    }

    /**
     * @param AbstractQueryBuilder $parentBuilder
     * @return AbstractQueryBuilder
     */
    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $query = $parentBuilder->getQuery();
        if(!$this->getIsEnabled()) {
            $query->removeComponent($query->getGrouping());
            return $parentBuilder;
        }

        $query->getGrouping()->setFields($this->getFields());
        $query->getGrouping()->setLimit($this->getResultsPerGroup());
        $query->getGrouping()->setQueries($this->getQueries());
        $query->getGrouping()->setFormat('grouped');
        $query->getGrouping()->setNumberOfGroups(true);

        $query->setRows($this->getNumberOfGroups());

        $sorting = implode(' ', $this->getSortings());
        $query->getGrouping()->setSort($sorting);
        return $parentBuilder;
    }
}
