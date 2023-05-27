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

namespace ApacheSolrForTypo3\Solr\System\Solr\Service;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrInternalServerErrorException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrUnavailableException;
use RuntimeException;
use Solarium\Exception\HttpException;

/**
 * Class SolrReadService
 */
class SolrReadService extends AbstractSolrService
{
    protected bool $hasSearched = false;

    protected ?ResponseAdapter $responseCache = null;

    /**
     * Performs a search.
     *
     * @return ResponseAdapter Solr response
     * @throws RuntimeException if Solr returns a HTTP status code other than 200
     */
    public function search(Query $query): ResponseAdapter
    {
        $request = $this->client->createRequest($query);
        $response = $this->executeRequest($request);

        if ($response->getHttpStatus() === 200) {
            $this->hasSearched = true;
            $this->responseCache = $response;
        } else {
            $this->handleErrorResponse($response);
        }
        return $response;
    }

    /**
     * Returns whether a search has been executed or not.
     *
     * @return bool TRUE if a search has been executed, FALSE otherwise
     */
    public function hasSearched(): bool
    {
        return $this->hasSearched;
    }

    /**
     * Gets the most recent response (if any)
     *
     * @return ResponseAdapter|null Most recent response, or NULL if a search has not been executed yet.
     */
    public function getResponse(): ?ResponseAdapter
    {
        return $this->responseCache;
    }

    /**
     * This method maps the failed solr requests to a meaningful exception.
     *
     * @throws SolrCommunicationException
     * @deprecated handleErrorResponses is deprecated and will be removed in v12, use handleErrorResponse() instead
     */
    protected function handleErrorResponses(HttpException $exception)
    {
        trigger_error(
            'handleErrorResponses() is deprecated and will be removed in v12, use handleErrorResponse() instead',
            E_USER_DEPRECATED
        );

        $solrResponse = new ResponseAdapter($exception->getBody(), $exception->getCode(), $exception->getStatusMessage());
        $this->handleErrorResponse($solrResponse);
    }

    /**
     * This method handles a failed Solr request and maps it to a meaningful exception.
     *
     * @throws SolrCommunicationException
     */
    protected function handleErrorResponse(ResponseAdapter $response): void
    {
        $status = $response->getHttpStatus();
        $message = $response->getHttpStatusMessage();

        if ($status === 0 || $status === 502) {
            $e = new SolrUnavailableException('Solr Server not available: ' . $message, 1505989391);
            $e->setSolrResponse($response);
            throw $e;
        }

        if ($status === 500) {
            $e = new SolrInternalServerErrorException('Internal Server error during search: ' . $message, 1505989897);
            $e->setSolrResponse($response);
            throw $e;
        }

        $e = new SolrCommunicationException('Invalid query. Solr returned an error: ' . $status . ' ' . $message, 1293109870);
        $e->setSolrResponse($response);

        throw $e;
    }
}
