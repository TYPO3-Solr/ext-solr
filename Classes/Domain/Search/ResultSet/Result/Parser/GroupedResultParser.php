<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\Group;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItemCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use stdClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class GroupedResultParser
 */
class GroupedResultParser extends AbstractResultParser
{
    /**
     * The parse method creates a SearchResultCollection from the Apache_Solr_Response
     * and creates the group object structure.
     */
    public function parse(SearchResultSet $resultSet, bool $useRawDocuments = true): SearchResultSet
    {
        $searchResultCollection = new SearchResultCollection();

        $configuration = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration();
        $groupsConfiguration = $configuration->getSearchGroupingGroupsConfiguration();

        if (empty($groupsConfiguration)) {
            return $resultSet;
        }

        $searchResultCollection = $this->parseGroups($resultSet, $groupsConfiguration, $searchResultCollection);
        $resultSet->setSearchResults($searchResultCollection);

        $this->calculateSummarizedGroupData($resultSet);

        return $resultSet;
    }

    /**
     * Parser the groups depending on the type (fieldGroup or queryGroup) and adds them to the searchResultCollection.
     */
    protected function parseGroups(
        SearchResultSet $resultSet,
        array $groupsConfigurations,
        SearchResultCollection $searchResultCollection,
    ): SearchResultCollection {
        $parsedData = $resultSet->getResponse()->getParsedData();
        if ($parsedData === null) {
            $parsedData = new stdClass();
        }
        $allGroups = new GroupCollection();

        foreach ($groupsConfigurations as $name => $groupsConfiguration) {
            $name = rtrim($name, '.');
            $group = $this->parseGroupDependingOnType($resultSet, $groupsConfiguration, $parsedData, $name);
            if ($group === null) {
                continue;
            }

            $allGroups[] = $group;
            $searchResultCollection = $this->addAllSearchResultsOfGroupToGlobalSearchResults($group, $searchResultCollection);
        }

        $searchResultCollection->setGroups($allGroups);
        return $searchResultCollection;
    }

    /**
     * Returns the parsedGroup, depending on the type.
     */
    protected function parseGroupDependingOnType(
        SearchResultSet $resultSet,
        array $options,
        stdClass $parsedData,
        string $name,
    ): ?Group {
        if (!empty($options['field'])) {
            return $this->parseFieldGroup($resultSet, $parsedData, $name, $options);
        }
        if (!empty($options['queries.']) || !empty($options['query'])) {
            return $this->parseQueryGroup($resultSet, $parsedData, $name, $options);
        }

        return null;
    }

    /**
     * Parses the fieldGroup and creates the group object structure from it.
     */
    protected function parseFieldGroup(
        SearchResultSet $resultSet,
        stdClass $parsedData,
        string $groupedResultName,
        array $groupedResultConfiguration,
    ): Group {
        $resultsPerGroup = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchGroupingResultLimit($groupedResultName);
        $group = GeneralUtility::makeInstance(Group::class, $groupedResultName, $resultsPerGroup);

        if (empty($parsedData->grouped->{$groupedResultConfiguration['field']})) {
            return $group;
        }

        $rawGroupedResult = $parsedData->grouped->{$groupedResultConfiguration['field']};
        $groupItems = new GroupItemCollection();

        foreach ($rawGroupedResult->groups as $rawGroup) {
            $groupValue = (string)$rawGroup->groupValue;
            $groupItem = $this->buildGroupItemAndAddDocuments($resultSet->getUsedSearchRequest(), $group, $groupValue, $rawGroup);

            if ($groupItem->getSearchResults()->count() >= 0) {
                $groupItems[] = $groupItem;
            }
        }

        $group->setGroupItems($groupItems);

        return $group;
    }

    /**
     * Parses the queryGroup and creates the group object structure from it.
     */
    protected function parseQueryGroup(
        SearchResultSet $resultSet,
        stdClass $parsedData,
        string $groupedResultName,
        array $groupedResultConfiguration,
    ): Group {
        $resultsPerGroup = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchGroupingResultLimit($groupedResultName);
        $group = GeneralUtility::makeInstance(Group::class, $groupedResultName, $resultsPerGroup);

        $groupItems = new GroupItemCollection();
        $queries = $this->getQueriesFromConfigurationArray($groupedResultConfiguration);
        foreach ($queries as $queryString) {
            $rawGroup = $this->getGroupedResultForQuery($parsedData, $queryString);

            if ($rawGroup === null) {
                continue;
            }

            if ($rawGroup->doclist->numFound === 0) {
                continue;
            }

            $groupValue = $queryString;
            $groupItem = $this->buildGroupItemAndAddDocuments($resultSet->getUsedSearchRequest(), $group, $groupValue, $rawGroup);

            if ($groupItem->getSearchResults()->count() >= 0) {
                $groupItems[] = $groupItem;
            }
        }

        $group->setGroupItems($groupItems);

        return $group;
    }

    /**
     * Retrieves all configured queries independent if they have been configured in query or queries.
     *
     * @todo This can be merged into TypoScriptConfiguration when solrfluidgrouping was merged to EXT:solr
     */
    protected function getQueriesFromConfigurationArray(array $configurationArray): array
    {
        $queries = [];

        if (!empty($configurationArray['query'])) {
            $queries[] = $configurationArray['query'];
        }

        if (!empty($configurationArray['queries.']) && is_array($configurationArray['queries.'])) {
            $queries = array_merge($queries, $configurationArray['queries.']);
        }

        return $queries;
    }

    /**
     * Parses the raw documents and create SearchResultObjects from it.
     */
    protected function buildGroupItemAndAddDocuments(
        SearchRequest $searchRequest,
        Group $parentGroup,
        string $groupValue,
        stdClass $rawGroup,
    ): GroupItem {
        $groupItem = GeneralUtility::makeInstance(
            GroupItem::class,
            $parentGroup,
            $groupValue,
            $rawGroup->doclist->numFound,
            $rawGroup->doclist->start,
            $rawGroup->doclist->maxScore,
            $searchRequest
        );

        $currentPage = $searchRequest->getGroupItemPage($parentGroup->getGroupName(), $groupValue);
        $perPage = $parentGroup->getResultsPerPage();
        $offset = ($currentPage - 1) * $perPage;

        // since apache solr does not natively support to set the offset per group, we get all documents to the current
        // page and slice the part of the results here, that we need
        $relevantResults = array_slice($rawGroup->doclist->docs, $offset, $perPage);

        foreach ($relevantResults as $rawDoc) {
            $solrDocument = new Document();
            foreach (get_object_vars($rawDoc) as $key => $value) {
                $solrDocument->setField($key, $value);
            }

            $document = $this->searchResultBuilder->fromApacheSolrDocument($solrDocument);
            $document->setGroupItem($groupItem);

            $groupItem->addSearchResult($document);
        }
        return $groupItem;
    }

    /**
     * Extracts the grouped results for a queryGroup from a solr raw response.
     */
    protected function getGroupedResultForQuery(stdClass $parsedData, string $queryString): ?stdClass
    {
        if (!empty($parsedData->grouped->{$queryString})) {
            return $parsedData->grouped->{$queryString};
        }
        return null;
    }

    /**
     * Returns true when GroupingIsEnabled and grouping component is loaded
     */
    public function canParse(SearchResultSet $resultSet): bool
    {
        $configuration = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration();
        $groupsConfiguration = $configuration->getSearchGroupingGroupsConfiguration();
        $groupingEnabled = $configuration->getIsSearchGroupingEnabled();

        return $groupingEnabled
            && $resultSet->getUsedQuery()->getComponent('grouping') !== null
            && (count($groupsConfiguration) > 0);
    }

    /**
     * Adds all results from all groups the global search results to have the available in a none grouped
     * view as well.
     */
    protected function addAllSearchResultsOfGroupToGlobalSearchResults(
        Group $group,
        SearchResultCollection $searchResultCollection,
    ): SearchResultCollection {
        /** @var GroupItem $groupItem */
        foreach ($group->getGroupItems() as $groupItem) {
            foreach ($groupItem->getSearchResults() as $searchResult) {
                $searchResultCollection[] = $searchResult;
            }
        }
        return $searchResultCollection;
    }

    /**
     * Some data maximumScore and allResultCount is summarized from all groups and assigned
     * to the SearchResultSet to have that data available independent of the groups.
     */
    private function calculateSummarizedGroupData(SearchResultSet $resultSet): void
    {
        $overAllMaximumScore = 0.0;
        $allResultCount = 0;
        /** @var Group $group */
        foreach ($resultSet->getSearchResults()->getGroups() as $group) {
            /** @var GroupItem $groupItem */
            foreach ($group->getGroupItems() as $groupItem) {
                if ($groupItem->getMaximumScore() > $overAllMaximumScore) {
                    $overAllMaximumScore = $groupItem->getMaximumScore();
                }

                $allResultCount += $groupItem->getAllResultCount();
            }
        }

        $resultSet->setMaximumScore($overAllMaximumScore);
        $resultSet->setAllResultCount($allResultCount);
    }
}
