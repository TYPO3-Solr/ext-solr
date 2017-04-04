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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\FieldProcessor\PageUidToHierarchy;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A Solr search query
 *
 * @author Ingo Renner <ingo@typo3.org>
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
     * @var array
     */
    protected $filters = [];

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
     * @var array
     * @see http://wiki.apache.org/solr/DisMaxQParserPlugin#qf_.28Query_Fields.29
     */
    protected $queryFields = [];

    /**
     * List of fields that will be returned in the result documents.
     *
     * used in Solr's fl parameter
     *
     * @var array
     * @see http://wiki.apache.org/solr/CommonQueryParameters#fl
     */
    protected $fieldList = [];

    /**
     * @var array
     */
    protected $filterFields;

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
     * Query constructor.
     * @param string $keywords
     * @param TypoScriptConfiguration $solrConfiguration
     * @param SiteHashService|null $siteHashService
     */
    public function __construct($keywords, $solrConfiguration = null, SiteHashService $siteHashService = null)
    {
        $keywords = (string)$keywords;

        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
        $this->solrConfiguration = is_null($solrConfiguration) ? Util::getSolrConfiguration() : $solrConfiguration;
        $this->siteHashService = is_null($siteHashService) ? GeneralUtility::makeInstance(SiteHashService::class) : $siteHashService;

        $this->setKeywords($keywords);
        $this->sorting = '';

        // What fields to search
        $queryFields = $this->solrConfiguration->getSearchQueryQueryFields();
        if ($queryFields != '') {
            $this->setQueryFieldsFromString($queryFields);
        }

        // What fields to return from Solr
        $this->fieldList = $this->solrConfiguration->getSearchQueryReturnFieldsAsArray(['*', 'score']);
        $this->linkTargetPageId = $this->solrConfiguration->getSearchTargetPage();

        $this->initializeQuery();

        $this->id = ++self::$idCount;
    }

    /**
     * @return void
     */
    protected function initializeQuery()
    {
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
     * @param string $queryFields A string defining which fields to query and their associated boosts
     * @return void
     */
    public function setQueryFieldsFromString($queryFields)
    {
        $fields = GeneralUtility::trimExplode(',', $queryFields, true);

        foreach ($fields as $field) {
            $fieldNameAndBoost = explode('^', $field);

            $boost = 1.0;
            if (isset($fieldNameAndBoost[1])) {
                $boost = floatval($fieldNameAndBoost[1]);
            }

            $this->setQueryField($fieldNameAndBoost[0], $boost);
        }
    }

    /**
     * Sets a query field and its boost. If the field does not exist yet, it
     * gets added. Boost is optional, if left out a default boost of 1.0 is
     * applied.
     *
     * @param string $fieldName The field's name
     * @param float $boost Optional field boost, defaults to 1.0
     * @return void
     */
    public function setQueryField($fieldName, $boost = 1.0)
    {
        $this->queryFields[$fieldName] = (float)$boost;
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
     * @return string The escaped/quoted string
     */
    public function escape($string)
    {
        // when we have a numeric string only, nothing needs to be done
        if (is_numeric($string)) {
            return $string;
        }

        // when no whitespaces are in the query we can also just escape the special characters
        if (preg_match('/\W/', $string) != 1) {
            return $this->escapeSpecialCharacters($string);
        }

        // when there are no quotes inside the query string we can also just escape the whole string
        $hasQuotes = strrpos($string, '"') !== false;
        if (!$hasQuotes) {
            return $this->escapeSpecialCharacters($string);
        }

        $result = $this->tokenizeByQuotesAndEscapeDependingOnContext($string);

        return $result;
    }

    /**
     * This method is used to escape the content in the query string surrounded by quotes
     * different then when it is not in a quoted context.
     *
     * @param string $string
     * @return string
     */
    protected function tokenizeByQuotesAndEscapeDependingOnContext($string)
    {
        $result = '';
        $quotesCount = substr_count($string, '"');
        $isEvenAmountOfQuotes = $quotesCount % 2 === 0;

        // go over all quote segments and apply escapePhrase inside a quoted
        // context and escapeSpecialCharacters outside the quoted context.
        $segments = explode('"', $string);
        $segmentsIndex = 0;
        foreach ($segments as $segment) {
            $isInQuote = $segmentsIndex % 2 !== 0;
            $isLastQuote = $segmentsIndex === $quotesCount;

            if ($isLastQuote && !$isEvenAmountOfQuotes) {
                $result .= '\"';
            }

            if ($isInQuote && !$isLastQuote) {
                $result .= $this->escapePhrase($segment);
            } else {
                $result .= $this->escapeSpecialCharacters($segment);
            }

            $segmentsIndex++;
        }

        return $result;
    }

    // pagination

    /**
     * Escapes a value meant to be contained in a phrase with characters with
     * special meanings in Lucene query syntax.
     *
     * @param string $value Unescaped - "dirty" - string
     * @return string Escaped - "clean" - string
     */
    protected function escapePhrase($value)
    {
        $pattern = '/("|\\\)/';
        $replace = '\\\$1';

        return '"' . preg_replace($pattern, $replace, $value) . '"';
    }

    /**
     * Escapes characters with special meanings in Lucene query syntax.
     *
     * @param string $value Unescaped - "dirty" - string
     * @return string Escaped - "clean" - string
     */
    protected function escapeSpecialCharacters($value)
    {
        // list taken from http://lucene.apache.org/core/4_4_0/queryparser/org/apache/lucene/queryparser/classic/package-summary.html#package_description
        // which mentions: + - && || ! ( ) { } [ ] ^ " ~ * ? : \ /
        // of which we escape: ( ) { } [ ] ^ " ~ : \ /
        // and explicitly don't escape: + - && || ! * ?
        $pattern = '/(\\(|\\)|\\{|\\}|\\[|\\]|\\^|"|~|\:|\\\\|\\/)/';
        $replace = '\\\$1';

        return preg_replace($pattern, $replace, $value);
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
    public function setQueryElevation(
        $elevation = true,
        $forceElevation = true,
        $markElevatedResults = true
    ) {
        if ($elevation) {
            $this->queryParameters['enableElevation'] = 'true';
            $this->setForceElevation($forceElevation);
            if ($markElevatedResults) {
                $this->addReturnField('isElevated:[elevated]');
            }
        } else {
            $this->queryParameters['enableElevation'] = 'false';
            unset($this->queryParameters['forceElevation']);
            $this->removeReturnField('isElevated:[elevated]');
            $this->removeReturnField('[elevated]'); // fallback
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
        return array_key_exists('collapsing', $this->filters);
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
            $this->filters['collapsing'] = '{!collapse field=' . $this->variantField . '}';
            if ($this->solrConfiguration->getSearchVariantsExpand()) {
                $this->queryParameters['expand'] = 'true';
                $this->queryParameters['expand.rows'] = $this->solrConfiguration->getSearchVariantsLimit();
            }
        } else {
            unset($this->filters['collapsing']);
            unset($this->queryParameters['expand']);
            unset($this->queryParameters['expand.rows']);
        }
    }

    // grouping

    /**
     * Adds a field to the list of fields to return. Also checks whether * is
     * set for the fields, if so it's removed from the field list.
     *
     * @param string $fieldName Name of a field to return in the result documents
     */
    public function addReturnField($fieldName)
    {
        if (strpos($fieldName, '[') === false
            && strpos($fieldName, ']') === false
            && in_array('*', $this->fieldList)
        ) {
            $this->fieldList = array_diff($this->fieldList, ['*']);
        }

        $this->fieldList[] = $fieldName;
    }

    /**
     * Removes a field from the list of fields to return (fl parameter).
     *
     * @param string $fieldName Field to remove from the list of fields to return
     */
    public function removeReturnField($fieldName)
    {
        $key = array_search($fieldName, $this->fieldList);

        if ($key !== false) {
            unset($this->fieldList[$key]);
        }
    }

    /**
     * Activates and deactivates grouping for the current query.
     *
     * @param bool $grouping TRUE to enable grouping, FALSE to disable grouping
     * @return void
     */
    public function setGrouping($grouping = true)
    {
        if ($grouping) {
            $this->queryParameters['group'] = 'true';
            $this->queryParameters['group.format'] = 'grouped';
            $this->queryParameters['group.ngroups'] = 'true';
        } else {
            foreach ($this->queryParameters as $key => $value) {
                // remove all group.* settings
                if (GeneralUtility::isFirstPartOfStr($key, 'group')) {
                    unset($this->queryParameters[$key]);
                }
            }
        }
    }

    /**
     * Sets the number of groups to return per group field or group query
     *
     * Internally uses the rows parameter.
     *
     * @param int $numberOfGroups Number of groups per group.field or group.query
     */
    public function setNumberOfGroups($numberOfGroups)
    {
        $this->setResultsPerPage($numberOfGroups);
    }

    /**
     * Gets the number of groups to return per group field or group query
     *
     * Internally uses the rows parameter.
     *
     * @return int Number of groups per group.field or group.query
     */
    public function getNumberOfGroups()
    {
        return $this->getResultsPerPage();
    }

    /**
     * Returns the number of results that should be shown per page
     *
     * @return int number of results to show per page
     */
    public function getResultsPerPage()
    {
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
     * @param string $fieldName Name of a field for grouping
     */
    public function addGroupField($fieldName)
    {
        if (!isset($this->queryParameters['group.field'])) {
            $this->queryParameters['group.field'] = [];
        }

        $this->queryParameters['group.field'][] = $fieldName;
    }

    /**
     * Gets the fields set for grouping.
     *
     * @return array An array of fields set for grouping.
     */
    public function getGroupFields()
    {
        return (array)$this->getQueryParameter('group.field', []);
    }

    /**
     * Adds sorting configuration for grouping.
     *
     * @param string $sorting value of sorting configuration
     */
    public function addGroupSorting($sorting)
    {
        if (!isset($this->queryParameters['group.sort'])) {
            $this->queryParameters['group.sort'] = [];
        }
        $this->queryParameters['group.sort'][] = $sorting;
    }

    /**
     * Gets the sorting set for grouping.
     *
     * @return array An array of sorting configurations for grouping.
     */
    public function getGroupSortings()
    {
        return (array)$this->getQueryParameter('group.sort', []);
    }

    // faceting

    /**
     * Adds a query that should be used for grouping.
     *
     * @param string $query Lucene query for grouping
     */
    public function addGroupQuery($query)
    {
        if (!isset($this->queryParameters['group.query'])) {
            $this->queryParameters['group.query'] = [];
        }

        $this->queryParameters['group.query'][] = $query;
    }

    /**
     * Gets the queries set for grouping.
     *
     * @return array An array of queries set for grouping.
     */
    public function getGroupQueries()
    {
        return (array)$this->getQueryParameter('group.query', []);
    }

    /**
     * Sets the maximum number of results to be returned per group.
     *
     * @param int $numberOfResults Maximum number of results per group to return
     */
    public function setNumberOfResultsPerGroup($numberOfResults)
    {
        $numberOfResults = max(intval($numberOfResults), 0);

        $this->queryParameters['group.limit'] = $numberOfResults;
    }

    // filter

    /**
     * Gets the maximum number of results to be returned per group.
     *
     * @return int Maximum number of results per group to return
     */
    public function getNumberOfResultsPerGroup()
    {
        // default if nothing else set is 1, @see http://wiki.apache.org/solr/FieldCollapsing
        $numberOfResultsPerGroup = 1;

        if (!empty($this->queryParameters['group.limit'])) {
            $numberOfResultsPerGroup = $this->queryParameters['group.limit'];
        }

        return $numberOfResultsPerGroup;
    }

    /**
     * Activates and deactivates faceting for the current query.
     *
     * @param bool $faceting TRUE to enable faceting, FALSE to disable faceting
     * @return void
     */
    public function setFaceting($faceting = true)
    {
        if ($faceting) {
            $this->queryParameters['facet'] = 'true';
            $this->queryParameters['facet.mincount'] = $this->solrConfiguration->getSearchFacetingMinimumCount();
            $this->queryParameters['facet.limit'] = $this->solrConfiguration->getSearchFacetingFacetLimit();

            $this->applyConfiguredFacetSorting();
        } else {
            $this->removeFacetingParametersFromQuery();
        }
    }

    /**
     * Removes all facet.* or f.*.facet.* parameters from the query.
     *
     * @return void
     */
    protected function removeFacetingParametersFromQuery()
    {
        foreach ($this->queryParameters as $key => $value) {
            // remove all facet.* settings
            if (GeneralUtility::isFirstPartOfStr($key, 'facet')) {
                unset($this->queryParameters[$key]);
            }

            // remove all f.*.facet.* settings (overrides for individual fields)
            if (GeneralUtility::isFirstPartOfStr($key, 'f.') && strpos($key, '.facet.') !== false) {
                unset($this->queryParameters[$key]);
            }
        }
    }

    /**
     * Reads the facet sorting configuration and applies it to the queryParameters.
     *
     * @return void
     */
    protected function applyConfiguredFacetSorting()
    {
        $sorting = $this->solrConfiguration->getSearchFacetingSortBy();
        if (!GeneralUtility::inList('count,index,alpha,lex,1,0,true,false', $sorting)) {
            // when the sorting is not in the list of valid values we do not apply it.
            return;
        }

        // alpha and lex alias for index
        if ($sorting == 'alpha' || $sorting == 'lex') {
            $sorting = 'index';
        }

        $this->queryParameters['facet.sort'] = $sorting;
    }

    /**
     * Sets facet fields for a query.
     *
     * @param array $facetFields Array of field names
     */
    public function setFacetFields(array $facetFields)
    {
        $this->queryParameters['facet.field'] = [];

        foreach ($facetFields as $facetField) {
            $this->addFacetField($facetField);
        }
    }

    /**
     * Adds a single facet field.
     *
     * @param string $facetField field name
     */
    public function addFacetField($facetField)
    {
        $this->queryParameters['facet.field'][] = $facetField;
    }

    /**
     * Removes a filter on a field
     *
     * @param string $filterFieldName The field name the filter should be removed for
     * @return void
     */
    public function removeFilter($filterFieldName)
    {
        foreach ($this->filters as $key => $filterString) {
            if (GeneralUtility::isFirstPartOfStr($filterString,
                $filterFieldName . ':')
            ) {
                unset($this->filters[$key]);
            }
        }
    }

    /**
     * Removes a filter based on key of filter array
     *
     * @param string $key array key
     */
    public function removeFilterByKey($key)
    {
        unset($this->filters[$key]);
    }

    /**
     * Removes a filter by the filter value. The value has the following format:
     *
     * "fieldname:value"
     *
     * @param string $filterString The filter to remove, in the form of field:value
     */
    public function removeFilterByValue($filterString)
    {
        $key = array_search($filterString, $this->filters);
        if ($key === false) {
            // value not found, nothing to do
            return;
        }
        unset($this->filters[$key]);
    }

    /**
     * Gets all currently applied filters.
     *
     * @return array Array of filters
     */
    public function getFilters()
    {
        return $this->filters;
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

        foreach ($this->filters as $key => $filter) {
            if (GeneralUtility::isFirstPartOfStr($filter, '{!typo3access}')) {
                unset($this->filters[$key]);
            }
        }

        $this->addFilter($accessFilter);
    }

    /**
     * Adds a filter parameter.
     *
     * @param string $filterString The filter to add, in the form of field:value
     * @return void
     */
    public function addFilter($filterString)
    {
        // TODO refactor to split filter field and filter value, @see Drupal
        if ($this->solrConfiguration->getLoggingQueryFilters()) {
            $this->logger->log(
                SolrLogManager::INFO,
                'Adding filter',
                [
                    $filterString
                ]
            );
        }

        $this->filters[] = $filterString;
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

        $this->addFilter(implode(' OR ', $filters));
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

        $this->addFilter(implode(' OR ', $filters));
    }

    /**
     * Gets the list of fields a query will return.
     *
     * @return array Array of field names the query will return
     */
    public function getFieldList()
    {
        return $this->fieldList;
    }

    /**
     * Sets the fields to return by a query.
     *
     * @param array|string $fieldList an array or comma-separated list of field names
     * @throws \UnexpectedValueException on parameters other than comma-separated lists and arrays
     */
    public function setFieldList($fieldList = ['*', 'score'])
    {
        if (is_string($fieldList)) {
            $fieldList = GeneralUtility::trimExplode(',', $fieldList);
        }

        if (!is_array($fieldList) || empty($fieldList)) {
            throw new \UnexpectedValueException(
                'Field list must be a comma-separated list or array and must not be empty.',
                1310740308
            );
        }

        $this->fieldList = $fieldList;
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
        if ($queryType) {
            $this->queryParameters['qt'] = $queryType;
        } else {
            unset($this->queryParameters['qt']);
        }
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
        if ($alternativeQuery) {
            $this->queryParameters['q.alt'] = $alternativeQuery;
        } else {
            unset($this->queryParameters['q.alt']);
        }
    }

    // keywords

    /**
     * Set the query to omit the response header
     *
     * @param bool $omitHeader TRUE (default) to omit response headers, FALSE to re-enable
     */
    public function setOmitHeader($omitHeader = true)
    {
        if ($omitHeader) {
            $this->queryParameters['omitHeader'] = 'true';
        } else {
            unset($this->queryParameters['omitHeader']);
        }
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
        $this->keywords = $this->escape($keywords);
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
        $keywords = GeneralUtility::removeXSS($keywords);
        $keywords = htmlentities($keywords, ENT_QUOTES,
            $GLOBALS['TSFE']->metaCharset);

        // escape triple hashes as they are used in the template engine
        // TODO remove after switching to fluid templates
        $keywords = Template::escapeMarkers($keywords);

        return $keywords;
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
        if (is_string($minimumMatch) && !empty($minimumMatch)) {
            $this->queryParameters['mm'] = $minimumMatch;
        } else {
            unset($this->queryParameters['mm']);
        }
    }

    /**
     * Sets the boost function (bf) parameter
     *
     * @param mixed $boostFunction boost function parameter as string or boolean FALSE to disable / reset the bf parameter
     * @see http://wiki.apache.org/solr/DisMaxRequestHandler#bf_.28Boost_Functions.29
     */
    public function setBoostFunction($boostFunction)
    {
        if (is_string($boostFunction) && !empty($boostFunction)) {
            $this->queryParameters['bf'] = $boostFunction;
        } else {
            unset($this->queryParameters['bf']);
        }
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
        if ((is_string($boostQuery) || is_array($boostQuery)) && !empty($boostQuery)) {
            $this->queryParameters['bq'] = $boostQuery;
        } else {
            unset($this->queryParameters['bq']);
        }
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
        $queryParameters = array_merge(
            [
                'fl' => implode(',', $this->fieldList),
                'fq' => array_values($this->filters)
            ],
            $this->queryParameters
        );

        $queryFieldString = $this->getQueryFieldsAsString();
        if (!empty($queryFieldString)) {
            $queryParameters['qf'] = $queryFieldString;
        }

        return $queryParameters;
    }

    // general query parameters

    /**
     * Compiles the query fields into a string to be used in Solr's qf parameter.
     *
     * @return string A string of query fields with their associated boosts
     */
    public function getQueryFieldsAsString()
    {
        $queryFieldString = '';

        foreach ($this->queryFields as $fieldName => $fieldBoost) {
            $queryFieldString .= $fieldName;

            if ($fieldBoost != 1.0) {
                $queryFieldString .= '^' . number_format($fieldBoost, 1, '.', '');
            }

            $queryFieldString .= ' ';
        }

        return trim($queryFieldString);
    }

    /**
     * Enables or disables highlighting of search terms in result teasers.
     *
     * @param bool $highlighting Enables highlighting when set to TRUE, deactivates highlighting when set to FALSE, defaults to TRUE.
     * @param int $fragmentSize Size, in characters, of fragments to consider for highlighting.
     * @see http://wiki.apache.org/solr/HighlightingParameters
     * @return void
     */
    public function setHighlighting($highlighting = true, $fragmentSize = 200)
    {
        if ($highlighting) {
            $this->queryParameters['hl'] = 'true';
            $this->queryParameters['hl.fragsize'] = (int)$fragmentSize;

            $highlightingFields = $this->solrConfiguration->getSearchResultsHighlightingFields();
            if ($highlightingFields != '') {
                $this->queryParameters['hl.fl'] = $highlightingFields;
            }

            // the fast vector highlighter can only be used, when the fragmentSize is
            // higher then 17 otherwise solr throws an exception
            $useFastVectorHighlighter = ($fragmentSize >= 18);
            $wrap = explode('|', $this->solrConfiguration->getSearchResultsHighlightingWrap());

            if ($useFastVectorHighlighter) {
                $this->queryParameters['hl.useFastVectorHighlighter'] = 'true';
                $this->queryParameters['hl.tag.pre'] = $wrap[0];
                $this->queryParameters['hl.tag.post'] = $wrap[1];
            }

            if (isset($wrap[0]) && isset($wrap[1])) {
                $this->queryParameters['hl.simple.pre'] = $wrap[0];
                $this->queryParameters['hl.simple.post'] = $wrap[1];
            }
        } else {
            // remove all hl.* settings
            foreach ($this->queryParameters as $key => $value) {
                if (GeneralUtility::isFirstPartOfStr($key, 'hl')) {
                    unset($this->queryParameters[$key]);
                }
            }
        }
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
}
