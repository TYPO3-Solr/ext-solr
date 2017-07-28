<?php
namespace ApacheSolrForTypo3\Solr;

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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper\EscapeService;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Faceting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Filters;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Grouping;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Highlighting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\QueryFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\ReturnFields;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\FieldProcessor\PageUidToHierarchy;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A Solr search query
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class Query
{

    // FIXME extract link building from the query, it's not the query's domain

    const SORT_ASC = 'ASC';
    const SORT_DESC = 'DESC';

    const OPERATOR_AND = 'AND';
    const OPERATOR_OR = 'OR';

    /**
     * Used to identify the queries.
     *
     * @var int
     */
    protected static $idCount = 0;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var TypoScriptConfiguration
     */
    protected $solrConfiguration;

    /**
     * @var string
     */
    protected $keywords;

    /**
     * @var string
     */
    protected $keywordsRaw;

    /**
     * ParameterBuilder for filters.
     *
     * @var Filters
     */
    protected $filters = null;

    /**
     * @var string
     */
    protected $sorting;

    // TODO check usage of these two variants, especially the check for $rawQueryString in getQueryString()
    /**
     * @var
     */
    protected $queryString;

    /**
     * @var array
     */
    protected $queryParameters = [];

    /**
     * @var int
     */
    protected $resultsPerPage;

    /**
     * @var int
     */
    protected $page;

    /**
     * @var int
     */
    protected $linkTargetPageId;

    /**
     * Holds the query fields with their associated boosts. The key represents
     * the field name, value represents the field's boost. These are the fields
     * that will actually be searched.
     *
     * Used in Solr's qf parameter
     *
     * @var QueryFields
     * @see http://wiki.apache.org/solr/DisMaxQParserPlugin#qf_.28Query_Fields.29
     */
    protected $queryFields = null;

    /**
     * List of fields that will be returned in the result documents.
     *
     * used in Solr's fl parameter
     *
     * @var ReturnFields
     * @see http://wiki.apache.org/solr/CommonQueryParameters#fl
     */
    protected $returnFields = null;

    /**
     * ParameterBuilder for the highlighting.
     *
     * @var Highlighting
     */
    protected $highlighting = null;

    /**
     * ParameterBuilder for the faceting.
     *
     * @var Faceting
     */
    protected $faceting = null;

    /**
     * ParameterBuilder for the grouping.
     *
     * @var Grouping
     */
    protected $grouping = null;

    /**
     * @var bool
     */
    private $rawQueryString = false;

    /**
     * The field by which the result will be collapsed
     * @var string
     */
    protected $variantField = 'variantId';

    /**
     * @var SiteHashService
     */
    protected $siteHashService = null;

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager
     */
    protected $logger = null;

    /**
     * @var EscapeService
     */
    protected $escapeService = null;

    /**
     * Query constructor.
     * @param string $keywords
     * @param TypoScriptConfiguration $solrConfiguration
     * @param SiteHashService|null $siteHashService
     * @param EscapeService|null $escapeService
     * @param SolrLogManager|null $solrLogManager
     */
    public function __construct($keywords, $solrConfiguration = null, SiteHashService $siteHashService = null, EscapeService $escapeService = null, SolrLogManager $solrLogManager = null)
    {
        $keywords = (string)$keywords;

        $this->logger = is_null($solrLogManager) ? GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__) : $solrLogManager;
        $this->solrConfiguration = is_null($solrConfiguration) ? Util::getSolrConfiguration() : $solrConfiguration;
        $this->siteHashService = is_null($siteHashService) ? GeneralUtility::makeInstance(SiteHashService::class) : $siteHashService;
        $this->escapeService = is_null($escapeService) ? GeneralUtility::makeInstance(EscapeService::class) : $escapeService;
        $this->setKeywords($keywords);
        $this->sorting = '';

        $this->linkTargetPageId = $this->solrConfiguration->getSearchTargetPage();

        $this->initializeQuery();

        $this->id = ++self::$idCount;
    }

    /**
     * @return void
     */
    protected function initializeQuery()
    {
        // Filters
        $this->initializeFilters();

        // What fields to search
        $queryFields = QueryFields::fromString($this->solrConfiguration->getSearchQueryQueryFields());
        $this->setQueryFields($queryFields);

        // What fields to return from Solr
        $returnFieldsArray = $this->solrConfiguration->getSearchQueryReturnFieldsAsArray(['*', 'score']);
        $returnFields = ReturnFields::fromArray($returnFieldsArray);
        $this->setReturnFields($returnFields);

        // Configure highlighting
        $highlighting = Highlighting::fromTypoScriptConfiguration($this->solrConfiguration);
        $this->setHighlighting($highlighting);

        // Configure faceting
        $this->initializeFaceting();

        // Initialize grouping
        $this->initializeGrouping();

        // Configure collapsing
        $this->initializeCollapsingFromConfiguration();
    }

    /**
     * Takes a string of comma separated query fields and _overwrites_ the
     * currently set query fields. Boost can also be specified in through the
     * given string.
     *
     * Example: "title^5, subtitle^2, content, author^0.5"
     * This sets the query fields to title with  a boost of 5.0, subtitle with
     * a boost of 2.0, content with a default boost of 1.0 and the author field
     * with a boost of 0.5
     *
     * @deprecated use setQueryFields with QueryFields instead, will be removed in 8.0
     * @param string $queryFields A string defining which fields to query and their associated boosts
     * @return void
     */
    public function setQueryFieldsFromString($queryFields)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->setQueryFields(QueryFields::fromString($queryFields));
    }

    /**
     * Sets a query field and its boost. If the field does not exist yet, it
     * gets added. Boost is optional, if left out a default boost of 1.0 is
     * applied.
     *
     * @deprecated use getQueryFields()->set($fieldName, $boost) instead, will be removed in 8.0
     * @param string $fieldName The field's name
     * @param float $boost Optional field boost, defaults to 1.0
     * @return void
     */
    public function setQueryField($fieldName, $boost = 1.0)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->getQueryFields()->set($fieldName, $boost);
    }

    /**
     * @param QueryFields $queryFields
     */
    public function setQueryFields(QueryFields $queryFields)
    {
        $this->queryFields = $queryFields;
    }

    /**
     * @return QueryFields
     */
    public function getQueryFields()
    {
        return $this->queryFields;
    }

    /**
     * magic implementation for clone(), makes sure that the id counter is
     * incremented
     *
     * @return void
     */
    public function __clone()
    {
        $this->id = ++self::$idCount;
    }

    /**
     * returns a string representation of the query
     *
     * @return string the string representation of the query
     */
    public function __toString()
    {
        return $this->getQueryString();
    }

    /**
     * Builds the query string which is then used for Solr's q parameters
     *
     * @return string Solr query string
     */
    public function getQueryString()
    {
        if (!$this->rawQueryString) {
            $this->buildQueryString();
        }

        return $this->queryString;
    }

    /**
     * Sets the query string without any escaping.
     *
     * Be cautious with this function!
     * TODO remove this method as it basically just sets the q parameter / keywords
     *
     * @param string $queryString The raw query string.
     */
    public function setQueryString($queryString)
    {
        $this->queryString = $queryString;
    }

    /**
     * Creates the string that is later used as the q parameter in the solr query
     *
     * @return void
     */
    protected function buildQueryString()
    {
        // very simple for now
        $this->queryString = $this->keywords;
    }

    /**
     * Sets whether a raw query sting should be used, that is, whether the query
     * string should be escaped or not.
     *
     * @param bool $useRawQueryString TRUE to use raw queries (like Lucene Query Language) or FALSE for regular, escaped queries
     */
    public function useRawQueryString($useRawQueryString)
    {
        $this->rawQueryString = (boolean)$useRawQueryString;
    }

    /**
     * Returns the query's ID.
     *
     * @return int The query's ID.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Quote and escape search strings
     *
     * @param string $string String to escape
     * @deprecated Please use EscapeService noew, will be removed in 8.0
     * @return string The escaped/quoted string
     */
    public function escape($string)
    {
        GeneralUtility::logDeprecatedFunction();
            /** @var EscapeService $escapeService */
        $escapeService = GeneralUtility::makeInstance(EscapeService::class);
        return $escapeService->escape($string);
    }

    /**
     * Gets the currently showing page's number
     *
     * @return int page number currently showing
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Sets the page that should be shown
     *
     * @param int $page page number to show
     * @return void
     */
    public function setPage($page)
    {
        $this->page = max(intval($page), 0);
    }

    /**
     * Gets the index of the first result document we're showing
     *
     * @return int index of the currently first document showing
     */
    public function getStartIndex()
    {
        return ($this->page - 1) * $this->resultsPerPage;
    }

    /**
     * Gets the index of the last result document we're showing
     *
     * @return int index of the currently last document showing
     */
    public function getEndIndex()
    {
        return $this->page * $this->resultsPerPage;
    }

    // query elevation

    /**
     * Activates and deactivates query elevation for the current query.
     *
     * @param bool $elevation True to enable query elevation (default), FALSE to disable query elevation.
     * @param bool $forceElevation Optionally force elevation so that the elevated documents are always on top regardless of sorting, default to TRUE.
     * @param bool $markElevatedResults Mark elevated results
     * @return void
     */
    public function setQueryElevation($elevation = true, $forceElevation = true, $markElevatedResults = true)
    {
        if ($elevation) {
            $this->queryParameters['enableElevation'] = 'true';
            $this->setForceElevation($forceElevation);
            if ($markElevatedResults) {
                $this->getReturnFields()->add('isElevated:[elevated]');
            }
        } else {
            $this->queryParameters['enableElevation'] = 'false';
            unset($this->queryParameters['forceElevation']);
            $this->getReturnFields()->remove('isElevated:[elevated]');
            $this->getReturnFields()->remove('[elevated]'); // fallback
        }
    }

    /**
     * Enables or disables the forceElevation query parameter.
     *
     * @param bool $forceElevation
     */
    protected function setForceElevation($forceElevation)
    {
        if ($forceElevation) {
            $this->queryParameters['forceElevation'] = 'true';
        } else {
            $this->queryParameters['forceElevation'] = 'false';
        }
    }

    // collapsing

    /**
     * Check whether collapsing is active
     *
     * @return bool
     */
    public function getIsCollapsing()
    {
        return $this->getFilters()->hasWithName('collapsing');
    }

    /**
     * @param string $fieldName
     */
    public function setVariantField($fieldName)
    {
        $this->variantField = $fieldName;
    }

    /**
     * @return string
     */
    public function getVariantField()
    {
        return $this->variantField;
    }

    /**
     * @param bool $collapsing
     */
    public function setCollapsing($collapsing = true)
    {
        if ($collapsing) {
            $this->getFilters()->add('{!collapse field=' . $this->variantField . '}', 'collapsing');
            if ($this->solrConfiguration->getSearchVariantsExpand()) {
                $this->queryParameters['expand'] = 'true';
                $this->queryParameters['expand.rows'] = $this->solrConfiguration->getSearchVariantsLimit();
            }
        } else {
            $this->getFilters()->removeByName('collapsing');
            unset($this->queryParameters['expand']);
            unset($this->queryParameters['expand.rows']);
        }
    }


    /**
     * Adds a field to the list of fields to return. Also checks whether * is
     * set for the fields, if so it's removed from the field list.
     *
     * @deprecated Use getReturnFields()->add() instead, will be removed in 8.0
     * @param string $fieldName Name of a field to return in the result documents
     */
    public function addReturnField($fieldName)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->returnFields->add($fieldName);
    }

    /**
     * Removes a field from the list of fields to return (fl parameter).
     *
     * @deprecated Use getReturnFields()->remove() instead, will be removed in 8.0
     * @param string $fieldName Field to remove from the list of fields to return
     */
    public function removeReturnField($fieldName)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->returnFields->remove($fieldName);
    }

    // grouping

    /**
     * Activates and deactivates grouping for the current query.
     *
     * @param bool|Grouping $grouping TRUE to enable grouping, FALSE to disable grouping
     * @return void
     */
    public function setGrouping($grouping = true)
    {
        if ($grouping instanceof Grouping) {
            $this->grouping = $grouping;
            return;
        }

        /**
         * @deprecated
         * @todo When starting with 8.0 we can add a typehint Grouping to the grouping argument, to drop backwards compatibility.
         */
        $grouping = (bool)$grouping;

        if ($grouping) {
            GeneralUtility::deprecationLog('Usage of setGrouping with boolean deprecated please use getGrouping()->setIsEnabled()');
            $this->getGrouping()->setIsEnabled($grouping);
        } else {
            $this->initializeGrouping();
        }
    }

    /**
     * @return Grouping
     */
    public function getGrouping()
    {
        return $this->grouping;
    }

    /**
     * Sets the number of groups to return per group field or group query
     *
     * Internally uses the rows parameter.
     *
     * @deprecated Use getGrouping()->setNumberOfGroups() instead, will be removed in 8.0
     * @param int $numberOfGroups Number of groups per group.field or group.query
     */
    public function setNumberOfGroups($numberOfGroups)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->getGrouping()->setNumberOfGroups($numberOfGroups);
    }

    /**
     * Gets the number of groups to return per group field or group query
     *
     * Internally uses the rows parameter.
     *
     * @deprecated Use getGrouping()->getNumberOfGroups() instead, will be removed in 8.0
     * @return int Number of groups per group.field or group.query
     */
    public function getNumberOfGroups()
    {
        GeneralUtility::logDeprecatedFunction();
        return $this->getGrouping()->getNumberOfGroups();
    }

    /**
     * Returns the number of results that should be shown per page
     *
     * @return int number of results to show per page
     */
    public function getResultsPerPage()
    {
        if ($this->getGrouping() instanceof Grouping && $this->getGrouping()->getIsEnabled()) {
            return $this->getGrouping()->getNumberOfGroups();
        }

        return $this->resultsPerPage;
    }

    /**
     * Sets the number of results that should be shown per page
     *
     * @param int $resultsPerPage Number of results to show per page
     * @return void
     */
    public function setResultsPerPage($resultsPerPage)
    {
        $this->resultsPerPage = max(intval($resultsPerPage), 0);
    }

    /**
     * Adds a field that should be used for grouping.
     *
     * @deprecated Use getGrouping()->addField() instead, will be removed in 8.0
     * @param string $fieldName Name of a field for grouping
     */
    public function addGroupField($fieldName)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->getGrouping()->addField($fieldName);
    }

    /**
     * Gets the fields set for grouping.
     *
     * @deprecated Use getGrouping()->getFields() instead, will be removed in 8.0
     * @return array An array of fields set for grouping.
     */
    public function getGroupFields()
    {
        GeneralUtility::logDeprecatedFunction();
        return $this->getGrouping()->getFields();
    }

    /**
     * Adds sorting configuration for grouping.
     *
     * @deprecated Use getGrouping()->addSorting() instead, will be removed in 8.0
     * @param string $sorting value of sorting configuration
     * @param string $sorting value of sorting configuration
     */
    public function addGroupSorting($sorting)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->getGrouping()->addSorting($sorting);
    }

    /**
     * Gets the sorting set for grouping.
     *
     * @deprecated Use getGrouping()->getSortings() instead, will be removed in 8.0
     * @return array An array of sorting configurations for grouping.
     */
    public function getGroupSortings()
    {
        GeneralUtility::logDeprecatedFunction();
        return $this->getGrouping()->getSortings();
    }

    // faceting

    /**
     * Adds a query that should be used for grouping.
     *
     * @deprecated Use getGrouping()->addQuery() instead, will be removed in 8.0
     * @param string $query Lucene query for grouping
     */
    public function addGroupQuery($query)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->getGrouping()->addQuery($query);
    }

    /**
     * Gets the queries set for grouping.
     *
     * @deprecated Use getGrouping()->getQueries() instead, will be removed in 8.0
     * @return array An array of queries set for grouping.
     */
    public function getGroupQueries()
    {
        GeneralUtility::logDeprecatedFunction();
        return $this->getGrouping()->getQueries();
    }

    /**
     * Sets the maximum number of results to be returned per group.
     *
     * @deprecated Use getGrouping()->setResultsPerGroup() instead, will be removed in 8.0
     * @param int $numberOfResults Maximum number of results per group to return
     */
    public function setNumberOfResultsPerGroup($numberOfResults)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->getGrouping()->setResultsPerGroup($numberOfResults);
    }


    /**
     * Gets the maximum number of results to be returned per group.
     *
     * @deprecated Use getGrouping()->getResultsPerGroup() instead, will be removed in 8.0
     * @return int Maximum number of results per group to return
     */
    public function getNumberOfResultsPerGroup()
    {
        GeneralUtility::logDeprecatedFunction();
        return $this->getGrouping()->getResultsPerGroup();
    }

    /**
     * Activates and deactivates faceting for the current query.
     *
     * @param bool|Faceting $faceting TRUE to enable faceting, FALSE to disable faceting
     * @return void
     */
    public function setFaceting($faceting = true)
    {
        if ($faceting instanceof Faceting) {
            $this->faceting = $faceting;
            return;
        }

        /**
         * @deprecated
         * @todo When starting with 8.0 we can add a typehint Faceting to the faceting argument, to drop backwards compatibility.
         */
        $faceting = (bool)$faceting;

        if ($faceting) {
            GeneralUtility::deprecationLog('Usage of setFaceting with boolean deprecated please use getFaceting()->setIsEnabled()');
            $this->getFaceting()->setIsEnabled($faceting);
        } else {
            $this->initializeFaceting();
        }
    }

    /**
     * @return Faceting
     */
    public function getFaceting()
    {
        return $this->faceting;
    }

    /**
     * Sets facet fields for a query.
     *
     * @deprecated Use getFaceting()->setFields() instead, will be removed in 8.0
     * @param array $facetFields Array of field names
     */
    public function setFacetFields(array $facetFields)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->getFaceting()->setIsEnabled(true);
        $this->getFaceting()->setFields($facetFields);
    }

    /**
     * Adds a single facet field.
     *
     * @deprecated Use getFaceting()->addField() instead, will be removed in 8.0
     * @param string $facetField field name
     */
    public function addFacetField($facetField)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->getFaceting()->setIsEnabled(true);
        $this->getFaceting()->addField($facetField);
    }

    /**
     * Removes a filter on a field
     *
     * @deprecated Use getFilters()->removeByFieldName() instead, will be removed in 8.0
     * @param string $filterFieldName The field name the filter should be removed for
     * @return void
     */
    public function removeFilter($filterFieldName)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->getFilters()->removeByFieldName($filterFieldName);
    }

    /**
     * Removes a filter based on key of filter array
     *
     * @deprecated Use getFilters()->removeByName() instead, will be removed in 8.0
     * @param string $key array key
     */
    public function removeFilterByKey($key)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->getFilters()->removeByName($key);
    }

    /**
     * Removes a filter by the filter value. The value has the following format:
     *
     * "fieldname:value"
     *
     * @deprecated Use getFilters()->removeByValue() instead, will be removed in 8.0
     * @param string $filterString The filter to remove, in the form of field:value
     */
    public function removeFilterByValue($filterString)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->getFilters()->removeByValue($filterString);
    }

    /**
     * Gets all currently applied filters.
     *
     * @return Filters Array of filters
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Sets the filters to use.
     *
     * @param Filters $filters
     */
    public function setFilters(Filters $filters)
    {
        $this->filters = $filters;
    }

    // sorting

    /**
     * Sets access restrictions for a frontend user.
     *
     * @param array $groups Array of groups a user has been assigned to
     */
    public function setUserAccessGroups(array $groups)
    {
        $groups = array_map('intval', $groups);
        $groups[] = 0; // always grant access to public documents
        $groups = array_unique($groups);
        sort($groups, SORT_NUMERIC);

        $accessFilter = '{!typo3access}' . implode(',', $groups);
        $this->getFilters()->removeByPrefix('{!typo3access}');
        $this->getFilters()->add($accessFilter);
    }

    /**
     * Adds a filter parameter.
     *
     * @deprecated Use getFilters()->add() instead, will be removed in 8.0
     * @param string $filterString The filter to add, in the form of field:value
     * @return void
     */
    public function addFilter($filterString)
    {
        GeneralUtility::logDeprecatedFunction();

        $this->getFilters()->add($filterString);
    }


    // query parameters

    /**
     * Limits the query to certain sites
     *
     * @param string $allowedSites Comma-separated list of domains
     */
    public function setSiteHashFilter($allowedSites)
    {
        if (trim($allowedSites) === '*') {
            return;
        }

        $allowedSites = GeneralUtility::trimExplode(',', $allowedSites);
        $filters = [];

        foreach ($allowedSites as $site) {
            $siteHash = $this->siteHashService->getSiteHashForDomain($site);
            $filters[] = 'siteHash:"' . $siteHash . '"';
        }

        $this->getFilters()->add(implode(' OR ', $filters));
    }

    /**
     * Limits the query to certain page tree branches
     *
     * @param string $pageIds Comma-separated list of page IDs
     */
    public function setRootlineFilter($pageIds)
    {
        $pageIds = GeneralUtility::trimExplode(',', $pageIds);
        $filters = [];

            /** @var $processor PageUidToHierarchy */
        $processor = GeneralUtility::makeInstance(PageUidToHierarchy::class);
        $hierarchies = $processor->process($pageIds);

        foreach ($hierarchies as $hierarchy) {
            $lastLevel = array_pop($hierarchy);
            $filters[] = 'rootline:"' . $lastLevel . '"';
        }

        $this->getFilters()->add(implode(' OR ', $filters));
    }

    /**
     * Gets the list of fields a query will return.
     *
     * @deprecated Use method getReturnFields() instead, will be removed in 8.0
     * @return array List of field names the query will return
     */
    public function getFieldList()
    {
        GeneralUtility::logDeprecatedFunction();
        return $this->getReturnFields()->getValues();
    }

    /**
     * Sets the fields to return by a query.
     *
     * @deprecated Use method setReturnFields() instead, will be removed in 8.0
     * @param array|string $fieldList an array or comma-separated list of field names
     * @throws \UnexpectedValueException on parameters other than comma-separated lists and arrays
     */
    public function setFieldList($fieldList = ['*', 'score'])
    {
        GeneralUtility::logDeprecatedFunction();
        if ($fieldList === null) {
            $this->setReturnFields(ReturnFields::fromArray(['*', 'score']));
            return;
        }


        if (is_string($fieldList)) {
            $this->setReturnFields(ReturnFields::fromString($fieldList));
            return;
        }

        if (is_array($fieldList)) {
            $this->setReturnFields(ReturnFields::fromArray($fieldList));
            return;
        }

        throw new \UnexpectedValueException('Field list must be a FieldList object.', 1310740308);
    }

    /**
     * @param ReturnFields $returnFields
     */
    public function setReturnFields(ReturnFields $returnFields)
    {
        $this->returnFields = $returnFields;
    }

    /**
     * @return ReturnFields
     */
    public function getReturnFields()
    {
        return $this->returnFields;
    }

    /**
     * Gets the query type, Solr's qt parameter.
     *
     * @return string Query type, qt parameter.
     */
    public function getQueryType()
    {
        return $this->queryParameters['qt'];
    }

    /**
     * Sets the query type, Solr's qt parameter.
     *
     * @param string|bool $queryType String query type or boolean FALSE to disable / reset the qt parameter.
     * @see http://wiki.apache.org/solr/CoreQueryParameters#qt
     */
    public function setQueryType($queryType)
    {
        $this->setQueryParameterWhenStringOrUnsetWhenEmpty('qt', $queryType);
    }

    /**
     * Sets the query operator to AND or OR. Unsets the query operator (actually
     * sets it back to default) for FALSE.
     *
     * @param string|bool $operator AND or OR, FALSE to unset
     */
    public function setOperator($operator)
    {
        if (in_array($operator, [self::OPERATOR_AND, self::OPERATOR_OR])) {
            $this->queryParameters['q.op'] = $operator;
        }

        if ($operator === false) {
            unset($this->queryParameters['q.op']);
        }
    }

    /**
     * Gets the alternative query, Solr's q.alt parameter.
     *
     * @return string Alternative query, q.alt parameter.
     */
    public function getAlternativeQuery()
    {
        return $this->queryParameters['q.alt'];
    }

    /**
     * Sets an alternative query, Solr's q.alt parameter.
     *
     * This query supports the complete Lucene Query Language.
     *
     * @param mixed $alternativeQuery String alternative query or boolean FALSE to disable / reset the q.alt parameter.
     * @see http://wiki.apache.org/solr/DisMaxQParserPlugin#q.alt
     */
    public function setAlternativeQuery($alternativeQuery)
    {
        $this->setQueryParameterWhenStringOrUnsetWhenEmpty('q.alt', $alternativeQuery);
    }

    // keywords

    /**
     * Set the query to omit the response header
     *
     * @param bool $omitHeader TRUE (default) to omit response headers, FALSE to re-enable
     */
    public function setOmitHeader($omitHeader = true)
    {
        $omitHeader = ($omitHeader === true) ? 'true' : $omitHeader;
        $this->setQueryParameterWhenStringOrUnsetWhenEmpty('omitHeader', $omitHeader);
    }

    /**
     * Get the query keywords, keywords are escaped.
     *
     * @return string query keywords
     */
    public function getKeywords()
    {
        return $this->keywords;
    }

    /**
     * Sets the query keywords, escapes them as needed for Solr/Lucene.
     *
     * @param string $keywords user search terms/keywords
     */
    public function setKeywords($keywords)
    {
        $this->keywords = $this->escapeService->escape($keywords);
        $this->keywordsRaw = $keywords;
    }

    /**
     * Gets the cleaned keywords so that it can be used in templates f.e.
     *
     * @return string The cleaned keywords.
     */
    public function getKeywordsCleaned()
    {
        return $this->cleanKeywords($this->keywordsRaw);
    }

    /**
     * Helper method to escape/encode keywords for use in HTML
     *
     * @param string $keywords Keywords to prepare for use in HTML
     * @return string Encoded keywords
     */
    public static function cleanKeywords($keywords)
    {
        $keywords = trim($keywords);
        $keywords = htmlspecialchars($keywords);
        return $keywords;
    }

    /**
     * Escapes marker hashes and the pipe symbol so that they will not be
     * executed in templates.
     *
     * @param string $content Content potentially containing markers
     * @deprecated Only needed for old templating. Will be removed in 8.0
     * @return string Content with markers escaped
     */
    protected static function escapeMarkers($content)
    {
        GeneralUtility::logDeprecatedFunction();

        // escape marker hashes
        $content = str_replace('###', '&#35;&#35;&#35;', $content);
        // escape pipe character used for parameter separation
        $content = str_replace('|', '&#124;', $content);

        return $content;
    }

    // relevance, matching

    /**
     * Gets the raw, unescaped, unencoded keywords.
     *
     * USE WITH CAUTION!
     *
     * @return string raw keywords
     */
    public function getKeywordsRaw()
    {
        return $this->keywordsRaw;
    }

    /**
     * Sets the minimum match (mm) parameter
     *
     * @param mixed $minimumMatch Minimum match parameter as string or boolean FALSE to disable / reset the mm parameter
     * @see http://wiki.apache.org/solr/DisMaxRequestHandler#mm_.28Minimum_.27Should.27_Match.29
     */
    public function setMinimumMatch($minimumMatch)
    {
        $this->setQueryParameterWhenStringOrUnsetWhenEmpty('mm', $minimumMatch);
    }

    /**
     * Sets the boost function (bf) parameter
     *
     * @param mixed $boostFunction boost function parameter as string or boolean FALSE to disable / reset the bf parameter
     * @see http://wiki.apache.org/solr/DisMaxRequestHandler#bf_.28Boost_Functions.29
     */
    public function setBoostFunction($boostFunction)
    {
        $this->setQueryParameterWhenStringOrUnsetWhenEmpty('bf', $boostFunction);
    }

    // query fields
    // TODO move up to field list methods

    /**
     * Sets the boost query (bq) parameter
     *
     * @param mixed $boostQuery boost query parameter as string or array to set a boost query or boolean FALSE to disable / reset the bq parameter
     * @see http://wiki.apache.org/solr/DisMaxQParserPlugin#bq_.28Boost_Query.29
     */
    public function setBoostQuery($boostQuery)
    {
        if (is_array($boostQuery)) {
            $this->queryParameters['bq'] = $boostQuery;
            return;
        }
        $this->setQueryParameterWhenStringOrUnsetWhenEmpty('bq', $boostQuery);
    }

    /**
     * Gets a specific query parameter by its name.
     *
     * @param string $parameterName The parameter to return
     * @param mixed $defaultIfEmpty
     * @return mixed The parameter's value or $defaultIfEmpty if not set
     */
    public function getQueryParameter($parameterName, $defaultIfEmpty = null)
    {
        $parameters = $this->getQueryParameters();
        return isset($parameters[$parameterName]) ? $parameters[$parameterName] : $defaultIfEmpty;
    }

    /**
     * Builds an array of query parameters to use for the search query.
     *
     * @return array An array ready to use with query parameters
     */
    public function getQueryParameters()
    {
        $queryParameters = $this->getReturnFields()->build();
        $queryParameters = array_merge($queryParameters, $this->getFilters()->build());
        $queryParameters = array_merge($queryParameters, $this->queryParameters);
        $queryParameters = array_merge($queryParameters, $this->getQueryFields()->build());
        $queryParameters = array_merge($queryParameters, $this->getHighlighting()->build());
        $queryParameters = array_merge($queryParameters, $this->getFaceting()->build());
        $queryParameters = array_merge($queryParameters, $this->getGrouping()->build());

        return $queryParameters;
    }

    // general query parameters

    /**
     * Compiles the query fields into a string to be used in Solr's qf parameter.
     *
     * @deprecated Use getQueryFields()->toString() please. Will be removed in 8.0
     * @return string A string of query fields with their associated boosts
     */
    public function getQueryFieldsAsString()
    {
        GeneralUtility::logDeprecatedFunction();
        return $this->getQueryFields()->toString();
    }

    /**
     * Enables or disables highlighting of search terms in result teasers.
     *
     * @param Highlighting|bool $highlighting Enables highlighting when set to TRUE, deactivates highlighting when set to FALSE, defaults to TRUE.
     * @param int $fragmentSize Size, in characters, of fragments to consider for highlighting.
     * @see http://wiki.apache.org/solr/HighlightingParameters
     * @return void
     */
    public function setHighlighting($highlighting = true, $fragmentSize = 200)
    {
        if ($highlighting instanceof Highlighting) {
            $this->highlighting = $highlighting;
            return;
        }

        /**
         * @deprecated
         * @todo When starting with 8.0 we can add a typehint Highlighting to the highlighting argument and remove fragmentsize, to drop backwards compatibility.
         */
        GeneralUtility::deprecationLog('Usage of setHighlighting with boolean or fragmentSize is deprecated please use getHighlighting()->setIsEnabled() or getHighlighting()->setFragmentSize() please');
        $highlighting = (bool)$highlighting;
        $this->getHighlighting()->setIsEnabled($highlighting);
        $this->getHighlighting()->setFragmentSize($fragmentSize);
    }

    /**
     * @return Highlighting
     */
    public function getHighlighting()
    {
        return $this->highlighting;
    }

    // misc

    /**
     * Enables or disables spellchecking for the query.
     *
     * @param bool $spellchecking Enables spellchecking when set to TRUE, deactivates spellchecking when set to FALSE, defaults to TRUE.
     */
    public function setSpellchecking($spellchecking = true)
    {
        if ($spellchecking) {
            $this->queryParameters['spellcheck'] = 'true';
            $this->queryParameters['spellcheck.collate'] = 'true';
            $maxCollationTries = $this->solrConfiguration->getSearchSpellcheckingNumberOfSuggestionsToTry();
            $this->addQueryParameter('spellcheck.maxCollationTries', $maxCollationTries);
        } else {
            unset($this->queryParameters['spellcheck']);
            unset($this->queryParameters['spellcheck.collate']);
            unset($this->queryParameters['spellcheck.maxCollationTries']);
        }
    }

    /**
     * This method can be used to set a query parameter when the value is a string and not empty or unset it
     * in any other case. Extracted to avoid duplicate code.
     *
     * @param string $parameterName
     * @param mixed $value
     */
    private function setQueryParameterWhenStringOrUnsetWhenEmpty($parameterName, $value)
    {
        if (is_string($value) && !empty($value)) {
            $this->addQueryParameter($parameterName, $value);
        } else {
            unset($this->queryParameters[$parameterName]);
        }
    }

    /**
     * Adds any generic query parameter.
     *
     * @param string $parameterName Query parameter name
     * @param string $parameterValue Parameter value
     */
    public function addQueryParameter($parameterName, $parameterValue)
    {
        $this->queryParameters[$parameterName] = $parameterValue;
    }

    /**
     * Sets the sort parameter.
     *
     * $sorting must include a field name (or the pseudo-field score),
     * followed by a space,
     * followed by a sort direction (asc or desc).
     *
     * Multiple fallback sortings can be separated by comma,
     * ie: <field name> <direction>[,<field name> <direction>]...
     *
     * @param string|bool $sorting Either a comma-separated list of sort fields and directions or FALSE to reset sorting to the default behavior (sort by score / relevance)
     * @see http://wiki.apache.org/solr/CommonQueryParameters#sort
     */
    public function setSorting($sorting)
    {
        if ($sorting) {
            if (!is_string($sorting)) {
                throw new \InvalidArgumentException('Sorting needs to be a string!');
            }
            $sortParameter = $this->removeRelevanceSortField($sorting);
            $this->queryParameters['sort'] = $sortParameter;
        } else {
            unset($this->queryParameters['sort']);
        }
    }

    /**
     * Removes the relevance sort field if present in the sorting field definition.
     *
     * @param string $sorting
     * @return string
     */
    protected function removeRelevanceSortField($sorting)
    {
        $sortParameter = $sorting;
        list($sortField) = explode(' ', $sorting);
        if ($sortField == 'relevance') {
            $sortParameter = '';
            return $sortParameter;
        }

        return $sortParameter;
    }

    /**
     * Enables or disables the debug parameter for the query.
     *
     * @param bool $debugMode Enables debugging when set to TRUE, deactivates debugging when set to FALSE, defaults to TRUE.
     */
    public function setDebugMode($debugMode = true)
    {
        if ($debugMode) {
            $this->queryParameters['debugQuery'] = 'true';
            $this->queryParameters['echoParams'] = 'all';
        } else {
            unset($this->queryParameters['debugQuery']);
            unset($this->queryParameters['echoParams']);
        }
    }

    /**
     * Returns the link target page id.
     *
     * @return int
     */
    public function getLinkTargetPageId()
    {
        return $this->linkTargetPageId;
    }

    /**
     * Activates the collapsing on the configured field, if collapsing was enabled.
     *
     * @return bool
     */
    protected function initializeCollapsingFromConfiguration()
    {
        // check collapsing
        if ($this->solrConfiguration->getSearchVariants()) {
            $collapseField = $this->solrConfiguration->getSearchVariantsField();
            $this->setVariantField($collapseField);
            $this->setCollapsing(true);

            return true;
        }

        return false;
    }

    /**
     * @return void
     */
    protected function initializeFaceting()
    {
        $faceting = Faceting::fromTypoScriptConfiguration($this->solrConfiguration);
        $this->setFaceting($faceting);
    }

    /**
     * @return void
     */
    protected function initializeGrouping()
    {
        $grouping = Grouping::fromTypoScriptConfiguration($this->solrConfiguration);
        $this->setGrouping($grouping);
    }

    /**
     * @return void
     */
    protected function initializeFilters()
    {
        $filters = Filters::fromTypoScriptConfiguration($this->solrConfiguration);
        $this->setFilters($filters);
    }
}
