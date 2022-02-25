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

namespace ApacheSolrForTypo3\Solr;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Exception;
use stdClass;
use Throwable;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
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
     * @var SolrConnection|null
     */
    protected ?SolrConnection $solr;

    /**
     * The search query
     *
     * @var Query|null
     */
    protected ?Query $query;

    /**
     * The search response
     *
     * @var ResponseAdapter|null
     */
    protected ?ResponseAdapter $response = null;

    /**
     * @var TypoScriptConfiguration
     */
    protected TypoScriptConfiguration $configuration;

    // TODO Override __clone to reset $response and $hasSearched

    /**
     * @var SolrLogManager
     */
    protected SolrLogManager $logger;

    /**
     * Constructor
     *
     * @param SolrConnection|null $solrConnection The Solr connection to use for searching
     * @throws AspectNotFoundException
     * @throws DBALDriverException
     * @throws NoSolrConnectionFoundException
     */
    public function __construct(SolrConnection $solrConnection = null)
    {
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);

        $this->solr = $solrConnection;

        if (is_null($solrConnection)) {
            /** @var $connectionManager ConnectionManager */
            $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
            $this->solr = $connectionManager->getConnectionByPageId(($GLOBALS['TSFE']->id ?? 0), Util::getLanguageUid());
        }

        $this->configuration = Util::getSolrConfiguration();
    }

    /**
     * Gets the Solr connection used by this search.
     *
     * @return SolrConnection Solr connection
     */
    public function getSolrConnection(): ?SolrConnection
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
     * @param int|null $limit Maximum number of results to return. If set to NULL, this value is taken from the query object.
     * @return ResponseAdapter Solr response
     */
    public function search(Query $query, int $offset = 0, ?int $limit = null): ?ResponseAdapter
    {
        $this->query = $query;

        if (!empty($limit)) {
            $query->setRows($limit);
        }
        $query->setStart($offset);

        try {
            $response = $this->solr->getReadService()->search($query);
            if ($this->configuration->getLoggingQueryQueryString()) {
                $this->logger->log(
                    SolrLogManager::INFO,
                    'Querying Solr, getting result',
                    [
                        'query string' => $query->getQuery(),
                        'query parameters' => $query->getRequestBuilder()->build($query)->getParams(),
                        'response' => json_decode($response->getRawResponse(), true),
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
                        'limit' => $query->getRows(),
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
     */
    public function ping(bool $useCache = true): bool
    {
        $solrAvailable = false;

        try {
            if (!$this->solr->getReadService()->ping($useCache)) {
                throw new Exception('Solr Server not responding.', 1237475791);
            }

            $solrAvailable = true;
        } catch (Throwable $e) {
            if ($this->configuration->getLoggingExceptions()) {
                $this->logger->log(
                    SolrLogManager::ERROR,
                    'Exception while trying to ping the solr server',
                    [
                        $e->__toString(),
                    ]
                );
            }
        }

        return $solrAvailable;
    }

    /**
     * Gets the query object.
     *
     * @return Query|null
     */
    public function getQuery(): ?Query
    {
        return $this->query;
    }

    /**
     * Gets the Solr response
     *
     * @return ResponseAdapter|null
     */
    public function getResponse(): ?ResponseAdapter
    {
        return $this->response;
    }

    /**
     * @return string|null
     */
    public function getRawResponse(): ?string
    {
        return $this->response->getRawResponse();
    }

    /**
     * @return stdClass|null
     */
    public function getResponseHeader(): ?stdClass
    {
        return $this->getResponse()->responseHeader;
    }

    public function getResponseBody(): ?stdClass
    {
        // @extensionScannerIgnoreLine
        return $this->getResponse()->response;
    }

    /**
     * Gets the time Solr took to execute the query and return the result.
     *
     * @return int Query time in milliseconds
     */
    public function getQueryTime(): int
    {
        return $this->getResponseHeader()->QTime;
    }

    /**
     * Gets the number of results per page.
     *
     * @return int Number of results per page
     */
    public function getResultsPerPage(): int
    {
        return $this->getResponseHeader()->params->rows;
    }

    /**
     * Gets the result offset.
     *
     * @return int Result offset
     */
    public function getResultOffset(): int
    {
        // @extensionScannerIgnoreLine
        return $this->response->response->start;
    }

    public function getDebugResponse(): ?stdClass
    {
        // @extensionScannerIgnoreLine
        return $this->response->debug;
    }

    public function getHighlightedContent(): ?stdClass
    {
        $highlightedContent = new stdClass();

        if ($this->response->highlighting) {
            $highlightedContent = $this->response->highlighting;
        }

        return $highlightedContent;
    }
}
