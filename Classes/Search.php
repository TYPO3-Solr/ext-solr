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

use ApacheSolrForTypo3\Solr\Query\Modifier\Modifier;
use ApacheSolrForTypo3\Solr\Search\FacetsModifier;
use ApacheSolrForTypo3\Solr\Search\ResponseModifier;
use ApacheSolrForTypo3\Solr\Search\SearchAware;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class to handle solr search requests
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Search implements SingletonInterface
{

    /**
     * An instance of the Solr service
     *
     * @var SolrService
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
     * @var boolean
     */
    protected $hasSearched = false;


    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;


    // TODO Override __clone to reset $response and $hasSearched

    /**
     * Constructor
     *
     * @param SolrService $solrConnection The Solr connection to use for searching
     */
    public function __construct(SolrService $solrConnection = null)
    {
        $this->solr = $solrConnection;

        if (is_null($solrConnection)) {
            $this->solr = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager')->getConnectionByPageId(
                $GLOBALS['TSFE']->id,
                $GLOBALS['TSFE']->sys_language_uid
            );
        }

        $this->configuration = Util::getSolrConfiguration();
    }

    /**
     * Gets the Solr connection used by this search.
     *
     * @return SolrService Solr connection
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
     */
    public function setSolrConnection(SolrService $solrConnection)
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
     * @param integer $offset Result offset for pagination.
     * @param integer $limit Maximum number of results to return. If set to NULL, this value is taken from the query object.
     * @return \Apache_Solr_Response Solr response
     */
    public function search(Query $query, $offset = 0, $limit = 10)
    {
        $query = $this->modifyQuery($query);
        $this->query = $query;

        if (empty($limit)) {
            $limit = $query->getResultsPerPage();
        }

        try {
            $response = $this->solr->search(
                $query->getQueryString(),
                $offset,
                $limit,
                $query->getQueryParameters()
            );

            if ($this->configuration->getLoggingQueryQueryString()) {
                GeneralUtility::devLog('Querying Solr, getting result', 'solr',
                    0, array(
                        'query string' => $query->getQueryString(),
                        'query parameters' => $query->getQueryParameters(),
                        'response' => json_decode($response->getRawResponse(),
                            true)
                    ));
            }
        } catch (\RuntimeException $e) {
            $response = $this->solr->getResponse();

            if ($this->configuration->getLoggingExceptions()) {
                GeneralUtility::devLog('Exception while querying Solr', 'solr',
                    3, array(
                        'exception' => $e->__toString(),
                        'query' => (array)$query,
                        'offset' => $offset,
                        'limit' => $limit
                    ));
            }
        }

        $response = $this->modifyResponse($response);
        $this->response = $response;
        $this->hasSearched = true;

        return $this->response;
    }

    /**
     * Allows to modify a query before eventually handing it over to Solr.
     *
     * @param Query $query The current query before it's being handed over to Solr.
     * @return Query The modified query that is actually going to be given to Solr.
     */
    protected function modifyQuery(Query $query)
    {
        // hook to modify the search query
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery'] as $classReference) {
                $queryModifier = GeneralUtility::getUserObj($classReference);

                if ($queryModifier instanceof Modifier) {
                    if ($queryModifier instanceof SearchAware) {
                        $queryModifier->setSearch($this);
                    }

                    $query = $queryModifier->modifyQuery($query);
                } else {
                    throw new \UnexpectedValueException(
                        get_class($queryModifier) . ' must implement interface ApacheSolrForTypo3\Solr\Query\Modifier\QueryModifier',
                        1310387414
                    );
                }
            }
        }

        return $query;
    }

    /**
     * Allows to modify a response returned from Solr before returning it to
     * the rest of the extension.
     *
     * @param \Apache_Solr_Response $response The response as returned by Solr
     * @return \Apache_Solr_Response The modified response that is actually going to be returned to the extension.
     * @throws \UnexpectedValueException if a response modifier does not implement interface ApacheSolrForTypo3\Solr\Search\ResponseModifier
     */
    protected function modifyResponse(\Apache_Solr_Response $response)
    {
        // hook to modify the search response
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchResponse'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchResponse'] as $classReference) {
                $responseModifier = GeneralUtility::getUserObj($classReference);

                if ($responseModifier instanceof ResponseModifier) {
                    if ($responseModifier instanceof SearchAware) {
                        $responseModifier->setSearch($this);
                    }

                    $response = $responseModifier->modifyResponse($response);
                } else {
                    throw new \UnexpectedValueException(
                        get_class($responseModifier) . ' must implement interface ApacheSolrForTypo3\Solr\Search\ResponseModifier',
                        1343147211
                    );
                }
            }

            // add modification indicator
            $response->response->isModified = true;
        }

        return $response;
    }

    /**
     * Sends a ping to the solr server to see whether it is available.
     *
     * @return boolean Returns TRUE on successful ping.
     * @throws \Exception Throws an exception in case ping was not successful.
     */
    public function ping()
    {
        $solrAvailable = false;

        try {
            if (!$this->solr->ping()) {
                throw new \Exception('Solr Server not responding.', 1237475791);
            }

            $solrAvailable = true;
        } catch (\Exception $e) {
            if ($this->configuration->getLoggingExceptions()) {
                GeneralUtility::devLog('exception while trying to ping the solr server',
                    'solr', 3, array(
                        $e->__toString()
                    ));
            }
        }

        return $solrAvailable;
    }

    /**
     * checks whether a search has been executed
     *
     * @return boolean    TRUE if there was a search, FALSE otherwise (if the user just visited the search page f.e.)
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
     * @return \Apache_Solr_Document[]
     */
    public function getResultDocumentsRaw()
    {
        return $this->getResponseBody()->docs;
    }

    /**
     * Returns all result documents but applies htmlspecialchars() on all fields retrieved
     * from solr except the configured fields in plugin.tx_solr.search.trustedFields
     *
     * @return \Apache_Solr_Document[]
     */
    public function getResultDocumentsEscaped()
    {
        return $this->applyHtmlSpecialCharsOnAllFields($this->getResponseBody()->docs);
    }

    /**
     * This method is used to apply htmlspecialchars on all document fields that
     * are not configured to be secure. Secure mean that we know where the content is comming from.
     *
     * @param array $documents
     * @return \Apache_Solr_Document[]
     */
    protected function applyHtmlSpecialCharsOnAllFields(array $documents)
    {
        $trustedSolrFields = $this->configuration->getSearchTrustedFieldsArray();

        foreach ($documents as $key => $document) {
            $fieldNames = $document->getFieldNames();

            foreach ($fieldNames as $fieldName) {
                if (in_array($fieldName, $trustedSolrFields)) {
                    // we skip this field, since it was marked as secure
                    continue;
                }

                $document->{$fieldName} = $this->applyHtmlSpecialCharsOnSingleFieldValue($document->{$fieldName});
            }

            $documents[$key] = $document;
        }

        return $documents;
    }

    /**
     * Applies htmlspecialchars on all items of an array of a single value.
     *
     * @param $fieldValue
     * @return array|string
     */
    protected function applyHtmlSpecialCharsOnSingleFieldValue($fieldValue)
    {
        if (is_array($fieldValue)) {
            foreach ($fieldValue as $key => $fieldValueItem) {
                $fieldValue[$key] = htmlspecialchars($fieldValueItem, null, null, false);
            }
        } else {
            $fieldValue = htmlspecialchars($fieldValue, null, null, false);
        }

        return $fieldValue;
    }

    /**
     * Gets the time Solr took to execute the query and return the result.
     *
     * @return integer Query time in milliseconds
     */
    public function getQueryTime()
    {
        return $this->getResponseHeader()->QTime;
    }

    /**
     * Gets the number of results per page.
     *
     * @return integer Number of results per page
     */
    public function getResultsPerPage()
    {
        return $this->getResponseHeader()->params->rows;
    }

    /**
     * Gets all facets with their fields, options, and counts.
     *
     * @return array
     */
    public function getFacetCounts()
    {
        static $facetCountsModified = false;
        static $facetCounts = null;

        $unmodifiedFacetCounts = $this->response->facet_counts;

        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyFacets'])) {
            if (!$facetCountsModified) {
                $facetCounts = $unmodifiedFacetCounts;

                foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyFacets'] as $classReference) {
                    $facetsModifier = GeneralUtility::getUserObj($classReference);

                    if ($facetsModifier instanceof FacetsModifier) {
                        $facetCounts = $facetsModifier->modifyFacets($facetCounts);
                        $facetCountsModified = true;
                    } else {
                        throw new \UnexpectedValueException(
                            get_class($facetsModifier) . ' must implement interface ApacheSolrForTypo3\Solr\Facet\FacetsModifier',
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

    public function getFacetFieldOptions($facetField)
    {
        $facetOptions = null;

        if (property_exists($this->getFacetCounts()->facet_fields,
            $facetField)) {
            $facetOptions = get_object_vars($this->getFacetCounts()->facet_fields->$facetField);
        }

        return $facetOptions;
    }

    public function getFacetQueryOptions($facetField)
    {
        $options = array();

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

    public function getFacetRangeOptions($rangeFacetField)
    {
        return get_object_vars($this->getFacetCounts()->facet_ranges->$rangeFacetField);
    }

    public function getNumberOfResults()
    {
        return $this->response->response->numFound;
    }

    /**
     * Gets the result offset.
     *
     * @return integer Result offset
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

    public function getSpellcheckingSuggestions()
    {
        $spellcheckingSuggestions = false;

        $suggestions = (array)$this->response->spellcheck->suggestions;

        if (!empty($suggestions)) {
            $spellcheckingSuggestions = $suggestions;

            if(isset($this->response->spellcheck->collations)) {
                $collactions = (array) $this->response->spellcheck->collations;
                $spellcheckingSuggestions['collation'] = $collactions['collation'];
            }
        }


        return $spellcheckingSuggestions;
    }
}
