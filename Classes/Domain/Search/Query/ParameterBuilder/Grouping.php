<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\AbstractQueryBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * The Grouping ParameterProvider is responsible to build the solr query parameters
 * that are needed for the grouping.
 */
class Grouping extends AbstractDeactivatable implements ParameterBuilderInterface
{
    protected array $fields = [];

    protected array $sortings = [];

    protected array $queries = [];

    protected int $numberOfGroups = 5;

    protected int $resultsPerGroup = 1;

    /**
     * Grouping constructor.
     */
    public function __construct(
        bool $isEnabled,
        array $fields = [],
        array $sortings = [],
        array $queries = [],
        int $numberOfGroups = 5,
        int $resultsPerGroup = 1
    ) {
        $this->isEnabled = $isEnabled;
        $this->fields = $fields;
        $this->sortings = $sortings;
        $this->queries = $queries;
        $this->numberOfGroups = $numberOfGroups;
        $this->resultsPerGroup = $resultsPerGroup;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    public function addField(string $field): void
    {
        $this->fields[] = $field;
    }

    public function getSortings(): array
    {
        return $this->sortings;
    }

    public function addSorting(string $sorting): void
    {
        $this->sortings[] = $sorting;
    }

    public function setSortings(array $sortings): void
    {
        $this->sortings = $sortings;
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function addQuery(string $query): void
    {
        $this->queries[] = $query;
    }

    public function setQueries(array $queries): void
    {
        $this->queries = $queries;
    }

    public function getNumberOfGroups(): int
    {
        return $this->numberOfGroups;
    }

    public function setNumberOfGroups(int $numberOfGroups): void
    {
        $this->numberOfGroups = $numberOfGroups;
    }

    public function getResultsPerGroup(): int
    {
        return $this->resultsPerGroup;
    }

    public function setResultsPerGroup(int $resultsPerGroup): void
    {
        $resultsPerGroup = max($resultsPerGroup, 0);
        $this->resultsPerGroup = $resultsPerGroup;
    }

    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration): Grouping
    {
        if (!$solrConfiguration->getIsSearchGroupingEnabled()) {
            return new Grouping(false);
        }

        $fields = [];
        $queries = [];
        $sortings = [];

        $resultsPerGroup = $solrConfiguration->getSearchGroupingHighestGroupResultsLimit();
        $configuredGroups = $solrConfiguration->getSearchGroupingGroupsConfiguration();
        $numberOfGroups = $solrConfiguration->getSearchGroupingNumberOfGroups();
        $sortBy = $solrConfiguration->getSearchGroupingSortBy();

        foreach ($configuredGroups as $groupConfiguration) {
            if (isset($groupConfiguration['field'])) {
                $fields[] = $groupConfiguration['field'];
            } elseif (isset($groupConfiguration['query'])) {
                $queries[] = $groupConfiguration['query'];
            }
        }

        if (!empty(trim($sortBy))) {
            $sortings[] = $sortBy;
        }

        return new Grouping(true, $fields, $sortings, $queries, $numberOfGroups, $resultsPerGroup);
    }

    public static function getEmpty(): Grouping
    {
        return new Grouping(false);
    }

    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $query = $parentBuilder->getQuery();
        if (!$this->getIsEnabled()) {
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
