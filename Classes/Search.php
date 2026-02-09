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
use ApacheSolrForTypo3\Solr\System\Logging\DebugWriter;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use Doctrine\DBAL\Exception as DBALException;
use stdClass;
use Throwable;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class to handle solr search requests
 */
class Search
{
    /**
     * An instance of the Solr service
     */
    protected ?SolrConnection $solr;

    /**
     * The search query
     */
    protected ?Query $query = null;

    /**
     * The search response
     */
    protected ?ResponseAdapter $response = null;

    protected TypoScriptConfiguration $configuration;

    protected SolrLogManager $logger;

    /**
     * Search constructor
     *
     * @throws DBALException
     * @throws NoSolrConnectionFoundException
     */
    public function __construct(?SolrConnection $solrConnection = null)
    {
        $this->logger = new SolrLogManager(__CLASS__, GeneralUtility::makeInstance(DebugWriter::class));

        $this->solr = $solrConnection;

        if (is_null($solrConnection)) {
            $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
            $language = $GLOBALS['TYPO3_REQUEST']->getAttribute('language');
            $pageArguments = $GLOBALS['TYPO3_REQUEST']->getAttribute('routing');
            $pageId = ($pageArguments instanceof PageArguments) ? $pageArguments->getPageId() : 0;
            $this->solr = $connectionManager->getConnectionByPageId($pageId, $language?->getLanguageId() ?? 0);
        }

        $this->configuration = Util::getSolrConfiguration();
    }

    /**
     * Gets the Solr connection used by this search.
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
     */
    public function setSolrConnection(SolrConnection $solrConnection): void
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
     * @return ResponseAdapter|null Solr response
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
                $this->logger->info(
                    'Querying Solr, getting result',
                    [
                        'query string' => $query->getQuery(),
                        'query parameters' => $query->getRequestBuilder()->build($query)->getParams(),
                        'response' => json_decode($response->getRawResponse(), true),
                    ],
                );
            }
        } catch (SolrCommunicationException $e) {
            if ($this->configuration->getLoggingExceptions()) {
                $this->logger->error(
                    'Exception while querying Solr',
                    [
                        'exception' => $e->__toString(),
                        'query' => (array)$query,
                        'offset' => $offset,
                        'limit' => $query->getRows(),
                    ],
                );
            }

            throw $e;
        }

        $this->response = $response;

        return $this->response;
    }

    /**
     * Sends a ping to the solr server to see whether it is available.
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
                $this->logger->error(
                    'Exception while trying to ping the solr server',
                    [
                        $e->__toString(),
                    ],
                );
            }
        }

        return $solrAvailable;
    }

    /**
     * Gets the query object.
     */
    public function getQuery(): ?Query
    {
        return $this->query;
    }

    /**
     * Gets the Solr response
     */
    public function getResponse(): ?ResponseAdapter
    {
        return $this->response;
    }

    /**
     * Returns raw response if available.
     */
    public function getRawResponse(): ?string
    {
        return $this->response->getRawResponse();
    }

    /**
     * Returns response header if available.
     */
    public function getResponseHeader(): ?stdClass
    {
        return $this->getResponse()->responseHeader;
    }

    /**
     * Returns response body if available.
     */
    public function getResponseBody(): ?stdClass
    {
        // @extensionScannerIgnoreLine
        return $this->getResponse()->response;
    }

    /**
     * Gets the time in milliseconds Solr took to execute the query and return the result.
     */
    public function getQueryTime(): int
    {
        return $this->getResponseHeader()->QTime;
    }

    /**
     * Gets the number of results per page.
     */
    public function getResultsPerPage(): int
    {
        return $this->getResponseHeader()->params->rows;
    }

    /**
     * Gets the result offset.
     */
    public function getResultOffset(): int
    {
        // @extensionScannerIgnoreLine
        return (int)$this->response->response->start;
    }

    /**
     * Returns the debug response if available.
     */
    public function getDebugResponse(): ?stdClass
    {
        // @extensionScannerIgnoreLine
        return $this->response->debug;
    }

    /**
     * Returns highlighted content if available.
     */
    public function getHighlightedContent(): ?stdClass
    {
        $highlightedContent = new stdClass();

        if ($this->response->highlighting) {
            $highlightedContent = $this->response->highlighting;
        }

        return $highlightedContent;
    }
}
