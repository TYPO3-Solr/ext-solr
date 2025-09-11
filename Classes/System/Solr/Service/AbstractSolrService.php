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

use ApacheSolrForTypo3\Solr\PingFailedException;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\Util;
use Closure;
use Psr\Log\LogLevel;
use Solarium\Client;
use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\Request;
use Solarium\Core\Query\QueryInterface;
use Solarium\Exception\HttpException;
use Throwable;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractSolrService
{
    protected static array $pingCache = [];

    protected TypoScriptConfiguration $configuration;

    protected SolrLogManager $logger;

    protected Client $client;

    public function __construct(Client $client, $typoScriptConfiguration = null, $logManager = null)
    {
        $this->client = $client;
        $this->configuration = $typoScriptConfiguration ?? Util::getSolrConfiguration();
        $this->logger = $logManager ?? GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
    }

    /**
     * Returns the path to the core solr path + core path.
     */
    public function getCorePath(): string
    {
        $endpoint = $this->getPrimaryEndpoint();
        return $endpoint->getPath() . '/' . $endpoint->getCore();
    }

    /**
     * Returns the Solarium client
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Return a valid http URL given this server's host, port and path and a provided servlet name
     */
    protected function _constructUrl(string $servlet, array $params = []): string
    {
        $queryString = count($params) ? '?' . http_build_query($params) : '';
        return $this->__toString() . $servlet . $queryString;
    }

    /**
     * Creates a string representation of the Solr connection. Specifically will return the Solr URL.
     * @TODO: Add support for API version 2
     */
    public function __toString()
    {
        $endpoint = $this->getPrimaryEndpoint();
        try {
            return $endpoint->getCoreBaseUri();
        } catch (Throwable) {
        }
        return  $endpoint->getScheme() . '://' . $endpoint->getHost() . ':' . $endpoint->getPort() . $endpoint->getPath() . '/' . $endpoint->getCore() . '/';
    }

    public function getPrimaryEndpoint(): Endpoint
    {
        return $this->client->getEndpoint();
    }

    /**
     * Central method for making a get operation against this Solr Server
     */
    protected function _sendRawGet(string $url): ResponseAdapter
    {
        return $this->_sendRawRequest($url);
    }

    /**
     * Central method for making an HTTP DELETE operation against the Solr server
     */
    protected function _sendRawDelete(string $url): ResponseAdapter
    {
        return $this->_sendRawRequest($url, Request::METHOD_DELETE);
    }

    /**
     * Central method for making a post operation against this Solr Server
     */
    protected function _sendRawPost(
        string $url,
        string $rawPost,
        string $contentType = 'text/xml; charset=UTF-8',
    ): ResponseAdapter {
        $initializeRequest = function (Request $request) use ($rawPost, $contentType) {
            $request->setRawData($rawPost);
            $request->addHeader('Content-Type: ' . $contentType);
            return $request;
        };

        return $this->_sendRawRequest($url, Request::METHOD_POST, $rawPost, $initializeRequest);
    }

    /**
     * Method that performs an HTTP request with the solarium client.
     */
    protected function _sendRawRequest(
        string $url,
        string $method = Request::METHOD_GET,
        string $body = '',
        ?Closure $initializeRequest = null,
    ): ResponseAdapter {
        $logSeverity = LogLevel::INFO;
        $exception = null;
        $url = $this->reviseUrl($url);
        try {
            $request = $this->buildSolariumRequestFromUrl($url, $method);
            if ($initializeRequest !== null) {
                $request = $initializeRequest($request);
            }
            $response = $this->executeRequest($request);
        } catch (HttpException $exception) {
            $logSeverity = LogLevel::ERROR;
            $response = new ResponseAdapter($exception->getBody(), $exception->getCode(), $exception->getMessage());
        }

        if ($this->configuration->getLoggingQueryRawPost() || $response->getHttpStatus() != 200) {
            $message = 'Querying Solr using ' . $method;
            $this->writeLog($logSeverity, $message, $url, $response, $exception, $body);
        }

        return $response;
    }

    /**
     * Revise url
     * - Resolve relative paths
     */
    protected function reviseUrl(string $url): string
    {
        /** @var Uri $uri */
        $uri = GeneralUtility::makeInstance(Uri::class, $url);

        if ($uri->getPath() === '') {
            return $url;
        }

        $path = trim($uri->getPath(), '/');
        $pathsCurrent = explode('/', $path);
        $pathNew = [];
        foreach ($pathsCurrent as $pathCurrent) {
            if ($pathCurrent === '..') {
                array_pop($pathNew);
                continue;
            }
            if ($pathCurrent === '.') {
                continue;
            }
            $pathNew[] = $pathCurrent;
        }

        $uri = $uri->withPath(implode('/', $pathNew));
        return (string)$uri;
    }

    /**
     * Build the log data and writes the message to the log
     */
    protected function writeLog(
        string $logSeverity,
        string $message,
        string $url,
        ?ResponseAdapter $solrResponse,
        ?Throwable $exception = null,
        string $contentSend = '',
    ): void {
        $logData = $this->buildLogDataFromResponse($solrResponse, $exception, $url, $contentSend);
        $this->logger->log($logSeverity, $message, $logData);
    }

    /**
     * Parses the solr information to build data for the logger.
     */
    protected function buildLogDataFromResponse(
        ResponseAdapter $solrResponse,
        ?Throwable $e = null,
        string $url = '',
        string $contentSend = '',
    ): array {
        $logData = ['query url' => $url, 'response' => (array)$solrResponse];

        if ($contentSend !== '') {
            $logData['content'] = $contentSend;
        }

        if ($e !== null) {
            $logData['exception'] = $e->__toString();
            return $logData;
        }
        // trigger data parsing
        /** @noinspection PhpExpressionResultUnusedInspection */
        /** @phpstan-ignore-next-line */
        $solrResponse->response;
        $logData['response data'] = print_r($solrResponse, true);
        return $logData;
    }

    /**
     * Call the /admin/ping servlet, can be used to quickly tell if a connection to the
     * server is available.
     *
     * Simply overrides the SolrPhpClient implementation, changing ping from a
     * HEAD to a GET request, see http://forge.typo3.org/issues/44167
     *
     * Also does not report the time, see https://forge.typo3.org/issues/64551
     *
     * @param bool $useCache indicates if the ping result should be cached in the instance or not
     *
     * @return bool TRUE if Solr can be reached, FALSE if not
     */
    public function ping(bool $useCache = true): bool
    {
        try {
            $httpResponse = $this->performPingRequest($useCache);
        } catch (HttpException) {
            return false;
        }

        return $httpResponse->getHttpStatus() === 200;
    }

    /**
     * Call the /admin/ping servlet, can be used to get the runtime of a ping request.
     *
     * @param bool $useCache indicates if the ping result should be cached in the instance or not
     *
     * @return float runtime in milliseconds
     *
     * @throws PingFailedException
     */
    public function getPingRoundTripRuntime(bool $useCache = true): float
    {
        try {
            $start = $this->getMilliseconds();
            $httpResponse = $this->performPingRequest($useCache);
            $end = $this->getMilliseconds();
        } catch (HttpException $e) {
            throw new PingFailedException(
                'Solr ping failed with unexpected response code: ' . $e->getCode(),
                1645716101
            );
        }

        if ($httpResponse->getHttpStatus() !== 200) {
            throw new PingFailedException(
                'Solr ping failed with unexpected response code: ' . $httpResponse->getHttpStatus(),
                1645716102
            );
        }

        return $end - $start;
    }

    /**
     * Performs a ping request and returns the result.
     *
     * @param bool $useCache indicates if the ping result should be cached in the instance or not
     */
    protected function performPingRequest(bool $useCache = true): ResponseAdapter
    {
        $cacheKey = (string)($this);
        if ($useCache && isset(static::$pingCache[$cacheKey])) {
            return static::$pingCache[$cacheKey];
        }

        $pingQuery = $this->client->createPing();
        $pingResult = $this->createAndExecuteRequest($pingQuery);

        if ($useCache) {
            static::$pingCache[$cacheKey] = $pingResult;
        }

        return $pingResult;
    }

    /**
     * Returns the current time in milliseconds.
     */
    protected function getMilliseconds(): float
    {
        return round(microtime(true) * 1000);
    }

    protected function createRequest(QueryInterface $query): Request
    {
        return $this->client->createRequest($query);
    }

    /**
     * Creates and executes the request and returns the response
     */
    protected function createAndExecuteRequest(QueryInterface $query): ResponseAdapter
    {
        $request = $this->createRequest($query);
        return $this->executeRequest($request);
    }

    /**
     * Executes given request and returns the response
     */
    protected function executeRequest(Request $request): ResponseAdapter
    {
        try {
            $result = $this->client->executeRequest($request);
        } catch (HttpException $e) {
            return new ResponseAdapter($e->getMessage(), $e->getCode(), $e->getStatusMessage());
        }

        return new ResponseAdapter($result->getBody(), $result->getStatusCode(), $result->getStatusMessage());
    }

    /**
     * Build the request for Solarium.
     *
     * Important: The endpoint already contains the API information.
     * The internal Solarium will append the information including the core if set.
     */
    protected function buildSolariumRequestFromUrl(
        string $url,
        string $httpMethod = Request::METHOD_GET,
    ): Request {
        $params = [];
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $params);
        $request = new Request();
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $endpoint = $this->getPrimaryEndpoint();
        $api = $request->getApi() === Request::API_V1 ? 'solr' : 'api';
        $coreBasePath = $endpoint->getPath() . '/' . $api . '/' . $endpoint->getCore() . '/';

        $handler = $this->buildRelativePath($coreBasePath, $path);
        $request->setMethod($httpMethod);
        $request->setParams($params);
        $request->setHandler($handler);
        return $request;
    }

    /**
     * Build a relative path from base path to target path.
     * Required since Solarium contains the core information
     */
    protected function buildRelativePath(
        string $basePath,
        string $targetPath,
    ): string {
        $basePath = trim($basePath, '/');
        $targetPath = trim($targetPath, '/');
        $baseElements = explode('/', $basePath);
        $targetElements = explode('/', $targetPath);
        $targetSegment = array_pop($targetElements);
        foreach ($baseElements as $i => $segment) {
            if (isset($targetElements[$i]) && $segment === $targetElements[$i]) {
                unset($baseElements[$i], $targetElements[$i]);
            } else {
                break;
            }
        }
        $targetElements[] = $targetSegment;
        return str_repeat('../', count($baseElements)) . implode('/', $targetElements);
    }
}
