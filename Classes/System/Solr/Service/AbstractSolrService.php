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
use Solarium\Client;
use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\Request;
use Solarium\Core\Query\QueryInterface;
use Solarium\Exception\HttpException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractSolrService
{

    /**
     * @var array
     */
    protected static $pingCache = [];

    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager
     */
    protected $logger = null;

    /**
     * @var Client
     */
    protected $client = null;

    /**
     * SolrReadService constructor.
     */
    public function __construct(Client $client, $typoScriptConfiguration = null, $logManager = null)
    {
        $this->client = $client;
        $this->configuration = $typoScriptConfiguration ?? Util::getSolrConfiguration();
        $this->logger = $logManager ?? GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
    }

    /**
     * Returns the path to the core solr path + core path.
     *
     * @return string
     */
    public function getCorePath()
    {
        $endpoint = $this->getPrimaryEndpoint();
        return is_null($endpoint) ? '' : $endpoint->getPath() .'/'. $endpoint->getCore();
    }

    /**
     * Returns the Solarium client
     *
     * @return ?Client
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * Return a valid http URL given this server's host, port and path and a provided servlet name
     *
     * @param string $servlet
     * @param array $params
     * @return string
     */
    protected function _constructUrl($servlet, $params = [])
    {
        $queryString = count($params) ? '?' . http_build_query($params, '', '&') : '';
        return $this->__toString() . $servlet . $queryString;
    }

    /**
     * Creates a string representation of the Solr connection. Specifically
     * will return the Solr URL.
     *
     * @return string The Solr URL.
     * @TODO: Add support for API version 2
     */
    public function __toString()
    {
        $endpoint = $this->getPrimaryEndpoint();
        if (!$endpoint instanceof Endpoint) {
            return '';
        }

        try {
            return $endpoint->getCoreBaseUri();
        } catch (\Exception $exception) {
        }
        return  $endpoint->getScheme(). '://' . $endpoint->getHost() . ':' . $endpoint->getPort() . $endpoint->getPath() . '/' . $endpoint->getCore() . '/';
    }

    /**
     * @return Endpoint|null
     */
    public function getPrimaryEndpoint()
    {
        $clientEndpoints = $this->client->getEndpoints();
        return is_array($clientEndpoints) ? reset($clientEndpoints) : null;
    }

    /**
     * Central method for making a get operation against this Solr Server
     *
     * @param string $url
     * @return ResponseAdapter
     */
    protected function _sendRawGet($url)
    {
        return $this->_sendRawRequest($url, Request::METHOD_GET);
    }

    /**
     * Central method for making a HTTP DELETE operation against the Solr server
     *
     * @param string $url
     * @return ResponseAdapter
     */
    protected function _sendRawDelete($url)
    {
        return $this->_sendRawRequest($url, Request::METHOD_DELETE);
    }

    /**
     * Central method for making a post operation against this Solr Server
     *
     * @param string $url
     * @param string $rawPost
     * @param string $contentType
     * @return ResponseAdapter
     */
    protected function _sendRawPost($url, $rawPost, $contentType = 'text/xml; charset=UTF-8')
    {
        $initializeRequest = function(Request $request) use ($rawPost, $contentType) {
            $request->setRawData($rawPost);
            $request->addHeader('Content-Type: ' . $contentType);
            return $request;
        };

        return $this->_sendRawRequest($url, Request::METHOD_POST, $rawPost, $initializeRequest);
    }

    /**
     * Method that performs an http request with the solarium client.
     *
     * @param string $url
     * @param string $method
     * @param string $body
     * @param ?\Closure $initializeRequest
     * @return ResponseAdapter
     */
    protected function _sendRawRequest(
        string $url,
        $method = Request::METHOD_GET,
        $body = '',
        \Closure $initializeRequest = null
    ) {
        $logSeverity = SolrLogManager::INFO;
        $exception = null;
        $url = $this->reviseUrl($url);
        try {
            $request = $this->buildSolariumRequestFromUrl($url, $method);
            if($initializeRequest !== null) {
                $request = $initializeRequest($request);
            }
            $response = $this->executeRequest($request);
        } catch (HttpException $exception) {
            $logSeverity = SolrLogManager::ERROR;
            $response = new ResponseAdapter($exception->getBody(), $exception->getCode(), $exception->getMessage());
        }

        if ($this->configuration->getLoggingQueryRawPost() || $response->getHttpStatus() != 200) {
            $message = 'Querying Solr using '.$method;
            $this->writeLog($logSeverity, $message, $url, $response, $exception, $body);
        }

        return $response;
    }

    /**
     * Revise url
     * - Resolve relative paths
     *
     * @param string $url
     * @return string
     */
    protected function reviseUrl(string $url): string
    {
        /* @var Uri $uri */
        $uri = GeneralUtility::makeInstance(Uri::class, $url);

        if ((string)$uri->getPath() === '') {
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
     *
     * @param integer $logSeverity
     * @param string $message
     * @param string $url
     * @param ResponseAdapter $solrResponse
     * @param ?\Exception $exception
     * @param string $contentSend
     */
    protected function writeLog($logSeverity, $message, $url, $solrResponse, $exception = null, $contentSend = '')
    {
        $logData = $this->buildLogDataFromResponse($solrResponse, $exception, $url, $contentSend);
        $this->logger->log($logSeverity, $message, $logData);
    }

    /**
     * Parses the solr information to build data for the logger.
     *
     * @param ResponseAdapter $solrResponse
     * @param ?\Exception $e
     * @param string $url
     * @param string $contentSend
     * @return array
     */
    protected function buildLogDataFromResponse(ResponseAdapter $solrResponse, \Exception $e = null, $url = '', $contentSend = '')
    {
        $logData = ['query url' => $url, 'response' => (array)$solrResponse];

        if ($contentSend !== '') {
            $logData['content'] = $contentSend;
        }

        if (!empty($e)) {
            $logData['exception'] = $e->__toString();
            return $logData;
        } else {
            // trigger data parsing
            // @extensionScannerIgnoreLine
            $solrResponse->response;
            $logData['response data'] = print_r($solrResponse, true);
            return $logData;
        }
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
     * @param boolean $useCache indicates if the ping result should be cached in the instance or not
     * @return bool TRUE if Solr can be reached, FALSE if not
     */
    public function ping($useCache = true)
    {
        try {
            $httpResponse = $this->performPingRequest($useCache);
        } catch (HttpException $exception) {
            return false;
        }

        return ($httpResponse->getHttpStatus() === 200);
    }

    /**
     * Call the /admin/ping servlet, can be used to get the runtime of a ping request.
     *
     * @param boolean $useCache indicates if the ping result should be cached in the instance or not
     * @return double runtime in milliseconds
     * @throws \ApacheSolrForTypo3\Solr\PingFailedException
     */
    public function getPingRoundTripRuntime($useCache = true)
    {
        try {
            $start = $this->getMilliseconds();
            $httpResponse = $this->performPingRequest($useCache);
            $end = $this->getMilliseconds();
        } catch (HttpException $e) {
            $message = 'Solr ping failed with unexpected response code: ' . $e->getCode();
            /** @var $exception \ApacheSolrForTypo3\Solr\PingFailedException */
            $exception = GeneralUtility::makeInstance(PingFailedException::class, /** @scrutinizer ignore-type */ $message);
            throw $exception;
        }

        if ($httpResponse->getHttpStatus() !== 200) {
            $message = 'Solr ping failed with unexpected response code: ' . $httpResponse->getHttpStatus();
            /** @var $exception \ApacheSolrForTypo3\Solr\PingFailedException */
            $exception = GeneralUtility::makeInstance(PingFailedException::class, /** @scrutinizer ignore-type */ $message);
            throw $exception;
        }

        return $end - $start;
    }

    /**
     * Performs a ping request and returns the result.
     *
     * @param boolean $useCache indicates if the ping result should be cached in the instance or not
     * @return ResponseAdapter
     */
    protected function performPingRequest($useCache = true)
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
     *
     * @return double
     */
    protected function getMilliseconds()
    {
        return round(microtime(true) * 1000);
    }

    /**
     * @param QueryInterface $query
     * @return ResponseAdapter
     */
    protected function createAndExecuteRequest(QueryInterface $query): ResponseAdapter
    {
        $request = $this->client->createRequest($query);
        return $this->executeRequest($request);
    }

    /**
     * @param $request
     * @return ResponseAdapter
     */
    protected function executeRequest($request): ResponseAdapter
    {
        $result = $this->client->executeRequest($request);
        return new ResponseAdapter($result->getBody(), $result->getStatusCode(), $result->getStatusMessage());
    }

    /**
     * Build the request for Solarium.
     *
     * Important: The endpoint already contains the API information.
     * The internal Solarium will append the information including the core if set.
     *
     * @param string $url
     * @param string $httpMethod
     * @return Request
     */
    protected function buildSolariumRequestFromUrl(string $url, string $httpMethod = Request::METHOD_GET): Request
    {
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
     *
     * @param string $basePath
     * @param string $targetPath
     * @return string
     */
    protected function buildRelativePath(string $basePath, string $targetPath): string
    {
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
