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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
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
    protected array $actions = [];

    /**
     * Parameters as sent from the Index Queue page indexer.
     *
     * @var array
     */
    protected array $parameters = [];

    /**
     * Headers as sent from the Index Queue page indexer.
     *
     * @var array
     */
    protected array $header = [];

    /**
     * Unique request ID.
     *
     * @var string|null
     */
    protected ?string $requestId = null;

    /**
     * Username to use for basic auth protected URLs.
     *
     * @var string
     */
    protected string $username = '';

    /**
     * Password to use for basic auth protected URLs.
     *
     * @var string
     */
    protected string $password = '';

    /**
     * An Index Queue item related to this request.
     *
     * @var Item|null
     */
    protected ?Item $indexQueueItem = null;

    /**
     * Request timeout in seconds
     *
     * @var float
     */
    protected float $timeout;

    /**
     * @var SolrLogManager
     */
    protected SolrLogManager $logger;

    /**
     * @var ExtensionConfiguration
     */
    protected ExtensionConfiguration $extensionConfiguration;

    /**
     * @var RequestFactory
     */
    protected RequestFactory $requestFactory;

    /**
     * PageIndexerRequest constructor.
     *
     * @param string|null $jsonEncodedParameters json encoded header
     * @param SolrLogManager|null $solrLogManager
     * @param ExtensionConfiguration|null $extensionConfiguration
     * @param RequestFactory|null $requestFactory
     */
    public function __construct(
        string $jsonEncodedParameters = null,
        SolrLogManager $solrLogManager = null,
        ExtensionConfiguration $extensionConfiguration = null,
        RequestFactory $requestFactory = null
    ) {
        $this->requestId = uniqid();
        $this->timeout = (float)ini_get('default_socket_timeout');

        $this->logger = $solrLogManager ?? GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
        $this->extensionConfiguration = $extensionConfiguration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->requestFactory = $requestFactory ?? GeneralUtility::makeInstance(RequestFactory::class);

        if (is_null($jsonEncodedParameters)) {
            return;
        }

        $this->parameters = (array)json_decode($jsonEncodedParameters, true);
        $this->requestId = $this->parameters['requestId'] ?? null;
        unset($this->parameters['requestId']);

        $actions = explode(',', $this->parameters['actions'] ?? '');
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
    public function addAction(string $action)
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
     * @throws Exception
     */
    public function send(string $url): PageIndexerResponse
    {
        /** @var $response PageIndexerResponse */
        $response = GeneralUtility::makeInstance(PageIndexerResponse::class);
        $decodedResponse = $this->getUrlAndDecodeResponse($url, $response);

        if ($decodedResponse['requestId'] != $this->requestId) {
            throw new RuntimeException(
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
     * @return array|bool
     * @throws Exception
     */
    protected function getUrlAndDecodeResponse(string $url, PageIndexerResponse $response)
    {
        $headers = $this->getHeaders();
        $rawResponse = $this->getUrl($url, $headers, $this->timeout);
        // convert JSON response to response object properties
        $decodedResponse = $response->getResultsFromJson($rawResponse->getBody()->getContents());

        if ($decodedResponse === false) {
            $this->logger->log(
                SolrLogManager::ERROR,
                'Failed to execute Page Indexer Request. Request ID: ' . $this->requestId,
                [
                    'request ID' => $this->requestId,
                    'request url' => $url,
                    'request headers' => $headers,
                    'response headers' => $rawResponse->getHeaders(),
                    'raw response body' => $rawResponse->getBody()->getContents(),
                ]
            );

            throw new RuntimeException('Failed to execute Page Indexer Request. See log for details. Request ID: ' . $this->requestId, 1319116885);
        }
        return $decodedResponse;
    }

    /**
     * Generates the headers to be sent with the request.
     *
     * @return string[] Array of HTTP headers.
     */
    public function getHeaders(): array
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
            ),
        ];

        $indexerRequestData = array_merge($indexerRequestData, $this->parameters);
        $headers[] = self::SOLR_INDEX_HEADER . ': ' . json_encode($indexerRequestData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

        return $headers;
    }

    /**
     * @return string
     */
    protected function getUserAgent(): string
    {
        return $GLOBALS['TYPO3_CONF_VARS']['HTTP']['headers']['User-Agent'] ?? 'TYPO3';
    }

    /**
     * Adds an HTTP header to be sent with the request.
     *
     * @param string $header HTTP header
     */
    public function addHeader(string $header)
    {
        $this->header[] = $header;
    }

    /**
     * Checks whether this is a legitimate request coming from the Index Queue
     * page indexer worker task.
     *
     * @return bool TRUE if it's a legitimate request, FALSE otherwise.
     */
    public function isAuthenticated(): bool
    {
        $authenticated = false;

        if (empty($this->parameters)) {
            return false;
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
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * Gets the request's parameters.
     *
     * @return array Request parameters.
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Gets the request's unique ID.
     *
     * @return string|null Unique request ID.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Gets a specific parameter's value.
     *
     * @param string $parameterName The parameter to retrieve.
     * @return mixed|null NULL if a parameter was not set, or it's value otherwise.
     */
    public function getParameter(string $parameterName)
    {
        return $this->parameters[$parameterName] ?? null;
    }

    /**
     * Sets a request's parameter and its value.
     *
     * @param string $parameter Parameter name
     * @param mixed $value Parameter value.
     */
    public function setParameter(string $parameter, $value)
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
    public function setAuthorizationCredentials(string $username, string $password)
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
    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * Sets the request timeout in seconds
     *
     * @param float $timeout Timeout seconds
     */
    public function setTimeout(float $timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * Fetches a page by sending the configured headers.
     *
     * @param string $url
     * @param string[] $headers
     * @param float $timeout
     * @return ResponseInterface
     * @throws Exception
     */
    protected function getUrl(string $url, array $headers, float $timeout): ResponseInterface
    {
        try {
            $options = $this->buildGuzzleOptions($headers, $timeout);
            $response = $this->requestFactory->request($url, 'GET', $options);
        } catch (ClientException|ServerException $e) {
            $response = $e->getResponse();
        }
        return $response;
    }

    /**
     * Build the options array for the guzzle-client.
     *
     * @param array $headers
     * @param float $timeout
     * @return array
     */
    protected function buildGuzzleOptions(array $headers, float $timeout): array
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
