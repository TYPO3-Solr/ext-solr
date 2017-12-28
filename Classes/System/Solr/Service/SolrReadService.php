<?php
namespace ApacheSolrForTypo3\Solr\System\Solr\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrInternalServerErrorException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrUnavailableException;

/**
 * Class SolrReadService
 * @package ApacheSolrForTypo3\System\Solr\Service
 */
class SolrReadService extends AbstractSolrService
{

    /**
     * @var bool
     */
    protected $hasSearched = false;

    /**
     * @var \Apache_Solr_Response
     */
    protected $responseCache = null;


    /**
     * @var string
     */
    protected $_extractUrl;

    /**
     * Performs a search.
     *
     * @param string $query query string / search term
     * @param int $offset result offset for pagination
     * @param int $limit number of results to retrieve
     * @param array $params additional HTTP GET parameters
     * @param string $method The HTTP method (Apache_Solr_Service::METHOD_GET or Apache_Solr_Service::METHOD::POST)
     * @return \Apache_Solr_Response Solr response
     * @throws \RuntimeException if Solr returns a HTTP status code other than 200
     */
    public function search($query, $offset = 0, $limit = 10, $params = [], $method = self::METHOD_GET)
    {
        $response = parent::search($query, $offset, $limit, $params, $method);
        $this->hasSearched = true;

        $this->responseCache = $response;

        $status = $response->getHttpStatus();
        $isValidResponse = $status === 200;
        if ($isValidResponse) {
            return $response;
        }

        $this->handleErrorResponses($response);
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
     * @return \Apache_Solr_Response Most recent response, or NULL if a search has not been executed yet.
     */
    public function getResponse()
    {
        return $this->responseCache;
    }

    /**
     * This method maps the failed solr requests to a meaningful exception.
     *
     * @param \Apache_Solr_Response $response
     * @throws SolrCommunicationException
     * @return \Apache_Solr_Response
     */
    protected function handleErrorResponses(\Apache_Solr_Response $response)
    {
        $status = $response->getHttpStatus();
        $message = $response->getHttpStatusMessage();

        if ($status === 0) {
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