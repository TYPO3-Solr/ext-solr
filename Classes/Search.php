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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
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
     * @var ResponseAdapter
     */
    protected $response = null;

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
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);

        $this->solr = $solrConnection;

        if (is_null($solrConnection)) {
            /** @var $connectionManager ConnectionManager */
            $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
            $this->solr = $connectionManager->getConnectionByPageId($GLOBALS['TSFE']->id, Util::getLanguageUid());
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
     * @return ResponseAdapter Solr response
     * @throws \Exception
     */
    public function search(Query $query, $offset = 0, $limit = 10)
    {
        $this->query = $query;

        if (!empty($limit)) {
            $query->setRows($limit);
        }
        $query->setStart($offset);

        try {
            $response = $this->solr->getReadService()->search($query);
            if ($this->configuration->getLoggingQueryQueryString()) {
                $this->logger->log(SolrLogManager::INFO,
                    'Querying Solr, getting result',
                    [
                        'query string' => $query->getQuery(),
                        'query parameters' => $query->getRequestBuilder()->build($query)->getParams(),
                        'response' => json_decode($response->getRawResponse(), true)
                    ]
                );
            }
        }  catch (SolrCommunicationException $e) {
            if ($this->configuration->getLoggingExceptions()) {
                $this->logger->log(
                    SolrLogManager::ERROR,
                    'Exception while querying Solr',
                    [
                        'exception' => $e->__toString(),
                        'query' => (array)$query,
                        'offset' => $offset,
                        'limit' => $query->getRows()
                    ]
                );
            }

            throw $e;
        }

        $this->response = $response;

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
            if (!$this->solr->getReadService()->ping($useCache)) {
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
     * Gets the query object.
     *
     * @return Query
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Gets the Solr response
     *
     * @return ResponseAdapter
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
        // @extensionScannerIgnoreLine
        return $this->getResponse()->response;
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
     * Gets the result offset.
     *
     * @return int Result offset
     */
    public function getResultOffset()
    {
        // @extensionScannerIgnoreLine
        return $this->response->response->start;
    }

    public function getDebugResponse()
    {
        // @extensionScannerIgnoreLine
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
}
