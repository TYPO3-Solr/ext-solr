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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\DocumentEscapeService;
use ApacheSolrForTypo3\Solr\Search\FacetsModifier;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class to handle solr search requests
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Search
{

    /**
     * An instance of the Solr service
     *
     * @var SolrConnection
     */
    protected $solr = null;

    /**
     * The search query
     *
     * @var Query
     */
    protected $query = null;

    /**
     * The search response
     *
     * @var \Apache_Solr_Response
     */
    protected $response = null;

    /**
     * Flag for marking a search
     *
     * @var bool
     */
    protected $hasSearched = false;

    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    // TODO Override __clone to reset $response and $hasSearched

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager
     */
    protected $logger = null;

    /**
     * Constructor
     *
     * @param SolrConnection $solrConnection The Solr connection to use for searching
     */
    public function __construct(SolrConnection $solrConnection = null)
    {
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);

        $this->solr = $solrConnection;

        if (is_null($solrConnection)) {
            /** @var $connectionManager ConnectionManager */
            $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
            $this->solr = $connectionManager->getConnectionByPageId($GLOBALS['TSFE']->id, $GLOBALS['TSFE']->sys_language_uid);
        }

        $this->configuration = Util::getSolrConfiguration();
    }

    /**
     * Gets the Solr connection used by this search.
     *
     * @return SolrConnection Solr connection
     */
    public function getSolrConnection()
    {
        return $this->solr;
    }

    /**
     * Sets the Solr connection used by this search.
     *
     * Since ApacheSolrForTypo3\Solr\Search is a \TYPO3\CMS\Core\SingletonInterface, this is needed to
     * be able to switch between multiple cores/connections during
     * one request
     *
     * @param SolrConnection $solrConnection
     */
    public function setSolrConnection(SolrConnection $solrConnection)
    {
        $this->solr = $solrConnection;
    }

    /**
     * Executes a query against a Solr server.
     *
     * 1) Gets the query string
     * 2) Conducts the actual search
     * 3) Checks debug settings
     *
     * @param Query $query The query with keywords, filters, and so on.
     * @param int $offset Result offset for pagination.
     * @param int $limit Maximum number of results to return. If set to NULL, this value is taken from the query object.
     * @return \Apache_Solr_Response Solr response
     * @throws \Exception
     */
    public function search(Query $query, $offset = 0, $limit = 10)
    {
        $this->query = $query;

        if (empty($limit)) {
            $limit = $query->getResultsPerPage();
        }

        try {
            $response = $this->solr->getReadService()->search(
                $query->getQueryString(),
                $offset,
                $limit,
                $query->getQueryParameters()
            );

            if ($this->configuration->getLoggingQueryQueryString()) {
                $this->logger->log(
                    SolrLogManager::INFO,
                    'Querying Solr, getting result',
                    [
                        'query string' => $query->getQueryString(),
                        'query parameters' => $query->getQueryParameters(),
                        'response' => json_decode($response->getRawResponse(),
                            true)
                    ]
                );
            }
        } catch (SolrCommunicationException $e) {
            if ($this->configuration->getLoggingExceptions()) {
                $this->logger->log(
                    SolrLogManager::ERROR,
                    'Exception while querying Solr',
                    [
                        'exception' => $e->__toString(),
                        'query' => (array)$query,
                        'offset' => $offset,
                        'limit' => $limit
                    ]
                );
            }

            throw $e;
        }

        $this->response = $response;
        $this->hasSearched = true;

        return $this->response;
    }

    /**
     * Sends a ping to the solr server to see whether it is available.
     *
     * @param bool $useCache Set to true if the cache should be used.
     * @return bool Returns TRUE on successful ping.
     * @throws \Exception Throws an exception in case ping was not successful.
     */
    public function ping($useCache = true)
    {
        $solrAvailable = false;

        try {
            if (!$this->solr->getReadService()->ping(2, $useCache)) {
                throw new \Exception('Solr Server not responding.', 1237475791);
            }

            $solrAvailable = true;
        } catch (\Exception $e) {
            if ($this->configuration->getLoggingExceptions()) {
                $this->logger->log(
                    SolrLogManager::ERROR,
                    'Exception while trying to ping the solr server',
                    [
                        $e->__toString()
                    ]
                );
            }
        }

        return $solrAvailable;
    }

    /**
     * checks whether a search has been executed
     *
     * @return bool    TRUE if there was a search, FALSE otherwise (if the user just visited the search page f.e.)
     */
    public function hasSearched()
    {
        return $this->hasSearched;
    }

    /**
     * Gets the query object.
     *
     * @return Query Query
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Gets the Solr response
     *
     * @return \Apache_Solr_Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    public function getRawResponse()
    {
        return $this->response->getRawResponse();
    }

    public function getResponseHeader()
    {
        return $this->getResponse()->responseHeader;
    }

    public function getResponseBody()
    {
        return $this->getResponse()->response;
    }

    /**
     * Returns all results documents raw. Use with caution!
     *
     * @deprecated Since 8.0.0 will be removed in 9.0.0. Use $resultSet->getSearchResults() this will be initialized by the parser depending on the settings
     * @return \Apache_Solr_Document[]
     */
    public function getResultDocumentsRaw()
    {
        GeneralUtility::logDeprecatedFunction();
        return $this->getResponseBody()->docs;
    }

    /**
     * Returns all result documents but applies htmlspecialchars() on all fields retrieved
     * from solr except the configured fields in plugin.tx_solr.search.trustedFields
     *
     * @deprecated Since 8.0.0 will be removed in 9.0.0. Use DocumentEscapeService or
     * $resultSet->getSearchResults() this will be initialized by the parser depending on the settings.
     * @return \Apache_Solr_Document[]
     */
    public function getResultDocumentsEscaped()
    {
        GeneralUtility::logDeprecatedFunction();
        /** @var $escapeService DocumentEscapeService */
        $escapeService = GeneralUtility::makeInstance(DocumentEscapeService::class, $this->configuration);
        return $escapeService->applyHtmlSpecialCharsOnAllFields($this->getResponseBody()->docs);
    }

    /**
     * Gets the time Solr took to execute the query and return the result.
     *
     * @return int Query time in milliseconds
     */
    public function getQueryTime()
    {
        return $this->getResponseHeader()->QTime;
    }

    /**
     * Gets the number of results per page.
     *
     * @return int Number of results per page
     */
    public function getResultsPerPage()
    {
        return $this->getResponseHeader()->params->rows;
    }

    /**
     * Gets all facets with their fields, options, and counts.
     *
     * @deprecated Since 8.0.0 will be removed in 9.0.0. This method is deprecated. Use SearchResultSet::getFacets instead.
     * The parsing of facets count's is now done in the parser of the corresponding facet type
     * @see \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\
     *
     * @return array
     */
    public function getFacetCounts()
    {
        GeneralUtility::logDeprecatedFunction();
        static $facetCountsModified = false;
        static $facetCounts = null;

        $unmodifiedFacetCounts = $this->response->facet_counts;

        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyFacets'])) {
            if (!$facetCountsModified) {
                $facetCounts = $unmodifiedFacetCounts;

                foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyFacets'] as $classReference) {
                    $facetsModifier = GeneralUtility::makeInstance($classReference);

                    if ($facetsModifier instanceof FacetsModifier) {
                        $facetCounts = $facetsModifier->modifyFacets($facetCounts);
                        $facetCountsModified = true;
                    } else {
                        throw new \UnexpectedValueException(
                            get_class($facetsModifier) . ' must implement interface ' . FacetsModifier::class,
                            1310387526
                        );
                    }
                }
            }
        } else {
            $facetCounts = $unmodifiedFacetCounts;
        }

        return $facetCounts;
    }

    /**
     * @deprecated Since 8.0.0 will be removed in 9.0.0. This method is deprecated. Use SearchResultSet::getFacets instead.
     * The parsing of the "options" is now done in the facet parser of the OptionsFacets
     * @see \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacetParser
     *
     * @param $facetField
     * @return array|null
     */
    public function getFacetFieldOptions($facetField)
    {
        GeneralUtility::logDeprecatedFunction();
        $facetOptions = null;

        if (property_exists($this->getFacetCounts()->facet_fields,
            $facetField)) {
            $facetOptions = get_object_vars($this->getFacetCounts()->facet_fields->$facetField);
        }

        return $facetOptions;
    }

    /**
     * @deprecated Since 8.0.0 will be removed in 9.0.0. This method is deprecated. Use SearchResultSet::getFacets instead.
     * The parsing of the "query options" is now done in the facet parser of the QueryFacets
     * @see \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup\QueryGroupFacetParser
     *
     * @param string $facetField
     * @return array
     */
    public function getFacetQueryOptions($facetField)
    {
        GeneralUtility::logDeprecatedFunction();
        $options = [];

        $facetQueries = get_object_vars($this->getFacetCounts()->facet_queries);
        foreach ($facetQueries as $facetQuery => $numberOfResults) {
            // remove tags from the facet.query response, for facet.field
            // and facet.range Solr does that on its own automatically
            $facetQuery = preg_replace('/^\{!ex=[^\}]*\}(.*)/', '\\1',
                $facetQuery);

            if (GeneralUtility::isFirstPartOfStr($facetQuery, $facetField)) {
                $options[$facetQuery] = $numberOfResults;
            }
        }

        // filter out queries with no results
        $options = array_filter($options);

        return $options;
    }

    /**
     * @deprecated Since 8.0.0 will be removed in 9.0.0. This method is deprecated. Use SearchResultSet::getFacets instead.
     * The parsing of the range options is now done in the facet parser of the RangeFacets
     * @see \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\AbstractRangeFacetParser
     *
     * @param string $rangeFacetField
     * @return array
     */
    public function getFacetRangeOptions($rangeFacetField)
    {
        GeneralUtility::logDeprecatedFunction();
        return get_object_vars($this->getFacetCounts()->facet_ranges->$rangeFacetField);
    }

    public function getNumberOfResults()
    {
        return $this->response->response->numFound;
    }

    /**
     * Gets the result offset.
     *
     * @return int Result offset
     */
    public function getResultOffset()
    {
        return $this->response->response->start;
    }

    public function getMaximumResultScore()
    {
        return $this->response->response->maxScore;
    }

    public function getDebugResponse()
    {
        return $this->response->debug;
    }

    public function getHighlightedContent()
    {
        $highlightedContent = false;

        if ($this->response->highlighting) {
            $highlightedContent = $this->response->highlighting;
        }

        return $highlightedContent;
    }

    /**
     * @deprecated Since 8.0.0 will be removed in 9.0.0. This method is deprecated. Use SearchResultSet::getSpellcheckingSuggestions
     * and the domain model instead
     * @return array|bool
     */
    public function getSpellcheckingSuggestions()
    {
        GeneralUtility::logDeprecatedFunction();

        $spellcheckingSuggestions = false;

        $suggestions = (array)$this->response->spellcheck->suggestions;

        if (!empty($suggestions)) {
            $spellcheckingSuggestions = $suggestions;

            if (isset($this->response->spellcheck->collations)) {
                $collections = (array)$this->response->spellcheck->collations;
                $spellcheckingSuggestions['collation'] = $collections['collation'];
            }
        }

        return $spellcheckingSuggestions;
    }
}
