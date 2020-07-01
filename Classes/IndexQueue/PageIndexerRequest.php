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

use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ClientException;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Index Queue Page Indexer request with details about which actions to perform.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class PageIndexerRequest
{

    const SOLR_INDEX_HEADER = 'X-Tx-Solr-Iq';

    /**
     * List of actions to perform during page rendering.
     *
     * @var array
     */
    protected $actions = [];

    /**
     * Parameters as sent from the Index Queue page indexer.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * Headers as sent from the Index Queue page indexer.
     *
     * @var array
     */
    protected $header = [];

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
     * @var \ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager
     */
    protected $logger = null;

    /**
     * @var ExtensionConfiguration
     */
    protected $extensionConfiguration;

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * PageIndexerRequest constructor.
     *
     * @param string $jsonEncodedParameters json encoded header
     * @param SolrLogManager|null $solrLogManager
     * @param ExtensionConfiguration|null $extensionConfiguration
     * @param RequestFactory|null $requestFactory
     */
    public function __construct($jsonEncodedParameters = null, SolrLogManager $solrLogManager = null, ExtensionConfiguration $extensionConfiguration = null, RequestFactory $requestFactory = null)
    {
        $this->requestId = uniqid();
        $this->timeout = (float)ini_get('default_socket_timeout');

        $this->logger = $solrLogManager ?? GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
        $this->extensionConfiguration = $extensionConfiguration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->requestFactory = $requestFactory ?? GeneralUtility::makeInstance(RequestFactory::class);

        if (is_null($jsonEncodedParameters)) {
            return;
        }

        $this->parameters = (array)json_decode($jsonEncodedParameters, true);
        $this->requestId = $this->parameters['requestId'];
        unset($this->parameters['requestId']);

        $actions = explode(',', $this->parameters['actions']);
        foreach ($actions as $action) {
            $this->addAction($action);
        }
        unset($this->parameters['actions']);
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
        /** @var $response PageIndexerResponse */
        $response = GeneralUtility::makeInstance(PageIndexerResponse::class);
        $decodedResponse = $this->getUrlAndDecodeResponse($url, $response);

        if ($decodedResponse['requestId'] != $this->requestId) {
            throw new \RuntimeException(
                'Request ID mismatch. Request ID was ' . $this->requestId . ', received ' . $decodedResponse['requestId'] . '. Are requests cached?',
                1351260655
            );
        }

        $response->setRequestId($decodedResponse['requestId']);

        if (!is_array($decodedResponse['actionResults'])) {
            // nothing to parse
            return $response;
        }

        foreach ($decodedResponse['actionResults'] as $action => $actionResult) {
            $response->addActionResult($action, $actionResult);
        }

        return $response;
    }

    /**
     * This method is used to retrieve an url from the frontend and decode the response.
     *
     * @param string $url
     * @param PageIndexerResponse $response
     * @return mixed
     */
    protected function getUrlAndDecodeResponse($url, PageIndexerResponse $response)
    {
        $headers = $this->getHeaders();
        $rawResponse = $this->getUrl($url, $headers, $this->timeout);
        // convert JSON response to response object properties
        $decodedResponse = $response->getResultsFromJson($rawResponse->getBody()->getContents());

        if ($rawResponse === false || $decodedResponse === false) {
            $this->logger->log(
                SolrLogManager::ERROR,
                'Failed to execute Page Indexer Request. Request ID: ' . $this->requestId,
                [
                    'request ID' => $this->requestId,
                    'request url' => $url,
                    'request headers' => $headers,
                    'response headers' => $rawResponse->getHeaders(),
                    'raw response body' => $rawResponse->getBody()->getContents()
                ]
            );

            throw new \RuntimeException('Failed to execute Page Indexer Request. See log for details. Request ID: ' . $this->requestId, 1319116885);
        }
        return $decodedResponse;
    }

    /**
     * Generates the headers to be send with the request.
     *
     * @return string[] Array of HTTP headers.
     */
    public function getHeaders()
    {
        $headers = $this->header;
        $headers[] = 'User-Agent: ' . $this->getUserAgent();
        $itemId = $this->indexQueueItem->getIndexQueueUid();
        $pageId = $this->indexQueueItem->getRecordUid();

        $indexerRequestData = [
            'requestId' => $this->requestId,
            'item' => $itemId,
            'page' => $pageId,
            'actions' => implode(',', $this->actions),
            'hash' => md5(
                $itemId . '|' .
                $pageId . '|' .
                $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']
            )
        ];

        $indexerRequestData = array_merge($indexerRequestData, $this->parameters);
        $headers[] = self::SOLR_INDEX_HEADER . ': ' . json_encode($indexerRequestData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

        return $headers;
    }

    /**
     * @return string
     */
    protected function getUserAgent()
    {
        return $GLOBALS['TYPO3_CONF_VARS']['HTTP']['headers']['User-Agent'] ?? 'TYPO3';
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

        if (is_null($this->parameters)) {
            return $authenticated;
        }

        $calculatedHash = md5(
            $this->parameters['item'] . '|' .
            $this->parameters['page'] . '|' .
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']
        );

        if ($this->parameters['hash'] === $calculatedHash) {
            $authenticated = true;
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
        return isset($this->parameters[$parameterName]) ? $this->parameters[$parameterName] : null;
    }

    /**
     * Sets a request's parameter and its value.
     *
     * @param string $parameter Parameter name
     * @param string $value Parameter value.
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

    /**
     * Fetches a page by sending the configured headers.
     *
     * @param string $url
     * @param string[] $headers
     * @param float $timeout
     * @return ResponseInterface
     * @throws \Exception
     */
    protected function getUrl($url, $headers, $timeout): ResponseInterface
    {
        try {
            $options = $this->buildGuzzleOptions($headers, $timeout);
            $response = $this->requestFactory->request($url, 'GET', $options);
        } catch (ClientException $e) {
            $response = $e->getResponse();
        } catch (ServerException $e) {
            $response = $e->getResponse();
        }

        return $response;
    }

    /**
     * Build the options array for the guzzle client.
     *
     * @param array $headers
     * @param float $timeout
     * @return array
     */
    protected function buildGuzzleOptions($headers, $timeout)
    {
        $finalHeaders = [];

        foreach ($headers as $header) {
            list($name, $value) = explode(':', $header, 2);
            $finalHeaders[$name] = trim($value);
        }

        $options = ['headers' => $finalHeaders, 'timeout' => $timeout];
        if (!empty($this->username) && !empty($this->password)) {
            $options['auth'] = [$this->username, $this->password];
        }

        if ($this->extensionConfiguration->getIsSelfSignedCertificatesEnabled()) {
            $options['verify'] = false;
        }

        return $options;
    }
}
