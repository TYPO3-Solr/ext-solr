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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper\EscapeService;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\BigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Faceting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Filters;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Grouping;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Highlighting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\PhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\QueryFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\ReturnFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\TrigramPhraseFields;
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
     * Holds the phrase fields with their associated boosts. The key represents
     * the field name, value represents the field's boost. These are the fields
     * for those Apache Solr should build phrase quieries and by phrase occurrences should be boosted.
     *
     * @var PhraseFields
     * @see https://lucene.apache.org/solr/guide/7_0/the-dismax-query-parser.html#pf-phrase-fields-parameter
     */
    protected $phraseFields;

    /**
     * Holds the bigram phrase fields with their associated boosts. The key represents
     * the field name, value represents the field's boost. These are the fields
     * for those Apache Solr should build the phrases from triplets and sentences.
     *
     * @var BigramPhraseFields
     * @see "pf2" https://lucene.apache.org/solr/guide/7_0/the-extended-dismax-query-parser.html#extended-dismax-parameters
     */
    protected $bigramPhraseFields;

    /**
     * Holds the trigram phrase fields with their associated boosts. The key represents
     * the field name, value represents the field's boost. These are the fields
     * for those Apache Solr should build the phrases from triplets and sentences.
     *
     * @var TrigramPhraseFields
     * @see "pf3" https://lucene.apache.org/solr/guide/7_0/the-extended-dismax-query-parser.html#extended-dismax-parameters
     */
    protected $trigramPhraseFields;

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

        // What fields to boost by phrase matching
        $phraseFields = PhraseFields::fromString((string)$this->solrConfiguration->getSearchQueryPhraseFields());
        $this->setPhraseFields($phraseFields);

        // For which fields to build bigram phrases and boost by phrase matching
        $bigramPhraseFields = BigramPhraseFields::fromString((string)$this->solrConfiguration->getSearchQueryBigramPhraseFields());
        $this->setBigramPhraseFields($bigramPhraseFields);

        // For which fields to build trigram phrases and boost by phrase matching
        $trigramPhraseFields = TrigramPhraseFields::fromString((string)$this->solrConfiguration->getSearchQueryTrigramPhraseFields());
        $this->setTrigramPhraseFields($trigramPhraseFields);

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
     * @param PhraseFields $phraseFields
     * @return void
     */
    public function setPhraseFields(PhraseFields $phraseFields)
    {
        $this->phraseFields = $phraseFields;
    }

    /**
     * @return PhraseFields
     */
    public function getPhraseFields()
    {
        return $this->phraseFields;
    }

    /**
     * @return BigramPhraseFields
     */
    public function getBigramPhraseFields()
    {
        return $this->bigramPhraseFields;
    }

    /**
     * @param BigramPhraseFields $bigramPhraseFields
     * @return void
     */
    public function setBigramPhraseFields(BigramPhraseFields $bigramPhraseFields)
    {
        $this->bigramPhraseFields = $bigramPhraseFields;
    }

    /**
     * @return TrigramPhraseFields
     */
    public function getTrigramPhraseFields()
    {
        return $this->trigramPhraseFields;
    }

    /**
     * @param TrigramPhraseFields $trigramPhraseFields
     * @return void
     */
    public function setTrigramPhraseFields(TrigramPhraseFields $trigramPhraseFields)
    {
        $this->trigramPhraseFields = $trigramPhraseFields;
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

    // grouping

    /**
     * Activates and deactivates grouping for the current query.
     *
     * @param Grouping $grouping TRUE to enable grouping, FALSE to disable grouping
     * @return void
     */
    public function setGrouping(Grouping $grouping)
    {
        $this->grouping = $grouping;
    }

    /**
     * @return Grouping
     */
    public function getGrouping()
    {
        return $this->grouping;
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

    // faceting

    /**
     * Activates and deactivates faceting for the current query.
     *
     * @param Faceting $faceting TRUE to enable faceting, FALSE to disable faceting
     * @return void
     */
    public function setFaceting(Faceting $faceting)
    {
        $this->faceting = $faceting;
    }

    /**
     * @return Faceting
     */
    public function getFaceting()
    {
        return $this->faceting;
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
     * @param string $alternativeQuery String alternative query or boolean FALSE to disable / reset the q.alt parameter.
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
     * Set the tie breaker (tie) parameter
     *
     * @param mixed $tieParameter tie breaker parameter as string or boolean FALSE to disable / reset the tie parameter
     * @return void
     */
    public function setTieParameter($tieParameter)
    {
        $this->setQueryParameterWhenStringOrUnsetWhenEmpty('tie', $tieParameter);
    }

    /**
     * Set the phrase slop (ps) parameter
     *
     * @param int $phraseSlop Phrase Slop parameter as int or null to unset this parameter
     * @return void
     */
    public function setPhraseSlopParameter(int $phraseSlop = null)
    {
        $this->setQueryParameterWhenIntOrUnsetWhenNull('ps', $phraseSlop);
    }

    /**
     * Set the Query Phrase Slop (qs) parameter
     *
     * @param int $queryPhraseSlop Query Phrase Slop parameter as int or null to unset this parameter
     * @return void
     */
    public function setQueryPhraseSlopParameter(int $queryPhraseSlop = null)
    {
        $this->setQueryParameterWhenIntOrUnsetWhenNull('qs', $queryPhraseSlop);
    }

    /**
     * Set the bigram phrase slop (ps2) parameter
     *
     * @param int $bigramPhraseSlop Bigram Phrase Slop parameter as int or null to unset this parameter
     * @return void
     */
    public function setBigramPhraseSlopParameter(int $bigramPhraseSlop = null)
    {
        $this->setQueryParameterWhenIntOrUnsetWhenNull('ps2', $bigramPhraseSlop);
    }

    /**
     * Set the trigram phrase slop (ps3) parameter
     *
     * @param int $trigramPhraseSlop Trigram Phrase Slop parameter as int or null to unset this parameter
     * @return void
     */
    public function setTrigramPhraseSlopParameter(int $trigramPhraseSlop = null)
    {
        $this->setQueryParameterWhenIntOrUnsetWhenNull('ps3', $trigramPhraseSlop);
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

        if ($this->solrConfiguration->getPhraseSearchIsEnabled()) {
            $queryParameters = array_merge($queryParameters, $this->getPhraseFields()->build());
        }
        if ($this->solrConfiguration->getBigramPhraseSearchIsEnabled()) {
            $queryParameters = array_merge($queryParameters, $this->getBigramPhraseFields()->build());
        }
        if ($this->solrConfiguration->getTrigramPhraseSearchIsEnabled()) {
            $queryParameters = array_merge($queryParameters, $this->getTrigramPhraseFields()->build());
        }

        $queryParameters = array_merge($queryParameters, $this->getHighlighting()->build());
        $queryParameters = array_merge($queryParameters, $this->getFaceting()->build());
        $queryParameters = array_merge($queryParameters, $this->getGrouping()->build());

        return $queryParameters;
    }

    // general query parameters

    /**
     * Enables or disables highlighting of search terms in result teasers.
     *
     * @param Highlighting $highlighting
     * @see http://wiki.apache.org/solr/HighlightingParameters
     * @return void
     */
    public function setHighlighting(Highlighting $highlighting)
    {
        $this->highlighting = $highlighting;
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
     * This method can be used to set a query parameter when the value is a int and not empty or unset it
     * in any other case. Extracted to avoid duplicate code.
     *
     * @param string $parameterName
     * @param int $value
     */
    private function setQueryParameterWhenIntOrUnsetWhenNull(string $parameterName, int $value = null)
    {
        if (null === $value) {
            unset($this->queryParameters[$parameterName]);
            return;
        }
        $this->addQueryParameter($parameterName, $value);
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
        if ($sortField === 'relevance') {
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
