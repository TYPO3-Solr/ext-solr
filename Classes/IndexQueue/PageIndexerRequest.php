<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Index Queue Page Indexer request with details about which actions to perform.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class PageIndexerRequest
{

    /**
     * List of actions to perform during page rendering.
     *
     * @var array
     */
    protected $actions = array();

    /**
     * Parameters as sent from the Index Queue page indexer.
     *
     * @var array
     */
    protected $parameters = array();

    /**
     * Headers as sent from the Index Queue page indexer.
     *
     * @var array
     */
    protected $header = array();

    /**
     * Unique request ID.
     *
     * @var string
     */
    protected $requestId;

    /**
     * Username to use for basic auth protected URLs.
     *
     * @var string
     */
    protected $username = '';

    /**
     * Password to use for basic auth protected URLs.
     *
     * @var string
     */
    protected $password = '';

    /**
     * An Index Queue item related to this request.
     *
     * @var Item
     */
    protected $indexQueueItem = null;

    /**
     * Request timeout in seconds
     *
     * @var float
     */
    protected $timeout;

    /**
     * Constructor.
     *
     * @param string $header JSON encoded Index Queue page indexer parameters
     */
    public function __construct($header = null)
    {
        $this->requestId = uniqid();

        $this->timeout = (float)ini_get('default_socket_timeout');

        if (!is_null($header)) {
            $this->parameters = json_decode($header, true);
            $this->header = $header;

            $this->requestId = $this->parameters['requestId'];
            unset($this->parameters['requestId']);

            $actions = explode(',', $this->parameters['actions']);
            foreach ($actions as $action) {
                $this->addAction($action);
            }
            unset($this->parameters['actions']);
        }
    }

    /**
     * Adds an action to perform during page rendering.
     *
     * @param string $action Action name.
     */
    public function addAction($action)
    {
        $this->actions[] = $action;
    }

    /**
     * Executes the request.
     *
     * Uses headers to submit additional data and avoiding to have these
     * arguments integrated into the URL when created by RealURL.
     *
     * @param string $url The URL to request.
     * @return PageIndexerResponse Response
     */
    public function send($url)
    {
        $headers = $this->getHeaders();
        $response = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\PageIndexerResponse');

        $parsedURL = parse_url($url);
        if (!preg_match('/^https?/', $parsedURL['scheme'])) {
            throw new \RuntimeException(
                'Cannot send request headers for HTTPS protocol',
                1320319214
            );
        }

        $context = stream_context_create(array(
            'http' => array(
                'header' => implode(CRLF, $headers),
                'timeout' => $this->timeout
            )
        ));

        $rawResponse = file_get_contents($url, false, $context);

        // convert JSON response to response object properties
        $decodedResponse = $response->getResultsFromJson($rawResponse);

        if ($rawResponse === false || $decodedResponse === false) {
            GeneralUtility::devLog(
                'Failed to execute Page Indexer Request. Request ID: ' . $this->requestId,
                'solr',
                3,
                array(
                    'request ID' => $this->requestId,
                    'request url' => $url,
                    'request headers' => $headers,
                    'response headers' => $http_response_header,
                    // automagically defined by file_get_contents()
                    'raw response body' => $rawResponse,
                )
            );

            throw new \RuntimeException(
                'Failed to execute Page Indexer Request. See log for details. Request ID: ' . $this->requestId,
                1319116885
            );
        }

        if ($decodedResponse['requestId'] != $this->requestId) {
            throw new \RuntimeException(
                'Request ID mismatch. Request ID was ' . $this->requestId . ', received ' . $decodedResponse['requestId'] . '. Are requests cached?',
                1351260655
            );
        }

        $response->setRequestId($decodedResponse['requestId']);

        if (is_array($decodedResponse['actionResults'])) {
            foreach ($decodedResponse['actionResults'] as $action => $actionResult) {
                $response->addActionResult($action, $actionResult);
            }
        }

        return $response;
    }

    /**
     * Generates the headers to be send with the request.
     *
     * @return array Array of HTTP headers.
     */
    public function getHeaders()
    {
        $headers = $this->header;
        $headers[] = TYPO3_user_agent;
        $itemId = $this->indexQueueItem->getIndexQueueUid();
        $pageId = $this->indexQueueItem->getRecordPageId();

        $indexerRequestData = array(
            'requestId' => $this->requestId,
            'item' => $itemId,
            'page' => $pageId,
            'actions' => implode(',', $this->actions),
            'hash' => md5(
                $itemId . '|' .
                $pageId . '|' .
                $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']
            )
        );
        $indexerRequestData = array_merge($indexerRequestData,
            $this->parameters);

        $headers[] = 'X-Tx-Solr-Iq: ' . json_encode($indexerRequestData);

        if (!empty($this->username) && !empty($this->password)) {
            $headers[] = 'Authorization: Basic ' . base64_encode(
                    $this->username . ':' . $this->password
                );
        }

        return $headers;
    }

    /**
     * Adds an HTTP header to be send with the request.
     *
     * @param string $header HTTP header
     */
    public function addHeader($header)
    {
        $this->header[] = $header;
    }

    /**
     * Checks whether this is a legitimate request coming from the Index Queue
     * page indexer worker task.
     *
     * @return bool TRUE if it's a legitimate request, FALSE otherwise.
     */
    public function isAuthenticated()
    {
        $authenticated = false;

        if (!is_null($this->parameters)) {
            $calculatedHash = md5(
                $this->parameters['item'] . '|' .
                $this->parameters['page'] . '|' .
                $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']
            );

            if ($this->parameters['hash'] === $calculatedHash) {
                $authenticated = true;
            }
        }

        return $authenticated;
    }

    /**
     * Gets the list of actions to perform during page rendering.
     *
     * @return array List of actions
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * Gets the request's parameters.
     *
     * @return array Request parameters.
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Gets the request's unique ID.
     *
     * @return string Unique request ID.
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * Gets a specific parameter's value.
     *
     * @param string $parameterName The parameter to retrieve.
     * @return mixed NULL if a parameter was not set or it's value otherwise.
     */
    public function getParameter($parameterName)
    {
        $value = null;

        if (isset($this->parameters[$parameterName])) {
            $value = $this->parameters[$parameterName];
        }

        return $value;
    }

    /**
     * Sets a request's parameter and its value.
     *
     * @param string $parameter Parameter name
     * @param mixed $value Parameter value.
     */
    public function setParameter($parameter, $value)
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $this->parameters[$parameter] = $value;
    }

    /**
     * Sets username and password to be used for a basic auth request header.
     *
     * @param string $username username.
     * @param string $password password.
     */
    public function setAuthorizationCredentials($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Sets the Index Queue item this request is related to.
     *
     * @param Item $item Related Index Queue item.
     */
    public function setIndexQueueItem(Item $item)
    {
        $this->indexQueueItem = $item;
    }

    /**
     * Returns the request timeout in seconds
     *
     * @return float
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Sets the request timeout in seconds
     *
     * @param float $timeout Timeout seconds
     */
    public function setTimeout($timeout)
    {
        $this->timeout = (float)$timeout;
    }
}
