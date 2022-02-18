<?php

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
use Solarium\Exception\HttpException;

/**
 * Class SolrReadService
 */
class SolrReadService extends AbstractSolrService
{

    /**
     * @var bool
     */
    protected $hasSearched = false;

    /**
     * @var ResponseAdapter
     */
    protected $responseCache = null;

    /**
     * Performs a search.
     *
     * @param Query $query
     * @return ResponseAdapter Solr response
     * @throws \RuntimeException if Solr returns a HTTP status code other than 200
     */
    public function search($query)
    {
        try {
            $request = $this->client->createRequest($query);
            $response = $this->executeRequest($request);
            $this->hasSearched = true;
            $this->responseCache = $response;
        } catch (HttpException $e) {
            $this->handleErrorResponses($e);
        }
        return $response;
    }

    /**
     * Returns whether a search has been executed or not.
     *
     * @return bool TRUE if a search has been executed, FALSE otherwise
     */
    public function hasSearched()
    {
        return $this->hasSearched;
    }

    /**
     * Gets the most recent response (if any)
     *
     * @return ResponseAdapter Most recent response, or NULL if a search has not been executed yet.
     */
    public function getResponse()
    {
        return $this->responseCache;
    }

    /**
     * This method maps the failed solr requests to a meaningful exception.
     *
     * @param HttpException $exception
     * @throws SolrCommunicationException
     * @return HttpException
     */
    protected function handleErrorResponses(HttpException $exception)
    {
        $status = $exception->getCode();
        $message = $exception->getStatusMessage();
        $solrRespone = new ResponseAdapter($exception->getBody());

        if ($status === 0 || $status === 502) {
            $e = new SolrUnavailableException('Solr Server not available: ' . $message, 1505989391);
            $e->setSolrResponse($solrRespone);
            throw $e;
        }

        if ($status === 500) {
            $e = new SolrInternalServerErrorException('Internal Server error during search: ' . $message, 1505989897);
            $e->setSolrResponse($solrRespone);
            throw $e;
        }

        $e = new SolrCommunicationException('Invalid query. Solr returned an error: ' . $status . ' ' . $message, 1293109870);
        $e->setSolrResponse($solrRespone);

        throw $e;
    }
}
