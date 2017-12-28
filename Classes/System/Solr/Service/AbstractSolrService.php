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

use ApacheSolrForTypo3\Solr\PingFailedException;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractSolrService extends \Apache_Solr_Service {

    const SCHEME_HTTP = 'http';
    const SCHEME_HTTPS = 'https';

    /**
     * @var array
     */
    protected static $pingCache = [];

    /**
     * Server connection scheme. http or https.
     *
     * @var string
     */
    protected $_scheme = self::SCHEME_HTTP;

    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager
     */
    protected $logger = null;

    /**
     * SolrAdminService constructor.
     * @param string $host
     * @param string $port
     * @param string $path
     * @param string $scheme
     * @param TypoScriptConfiguration $typoScriptConfiguration
     * @param SolrLogManager $logManager
     */
    public function __construct($host = '', $port = '8983', $path = '/solr/', $scheme = 'http', $typoScriptConfiguration = null, $logManager = null)
    {
        $this->setScheme($scheme);
        parent::__construct($host, $port, $path);

        $this->configuration = is_null($typoScriptConfiguration) ? Util::getSolrConfiguration() : $typoScriptConfiguration;
        $this->logger = is_null($logManager) ? GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__) : $logManager;
        $this->initializeTimeoutFromConfiguration();
    }

    /**
     * Initializes the timeout from TypoScript when configuration is present.
     *
     * @return void
     */
    protected function initializeTimeoutFromConfiguration()
    {
        $timeout = $this->configuration->getSolrTimeout();
        if ($timeout > 0) {
            $this->getHttpTransport()->setDefaultTimeout($timeout);
        }
    }

    /**
     * Creates a string representation of the Solr connection. Specifically
     * will return the Solr URL.
     *
     * @return string The Solr URL.
     */
    public function __toString()
    {
        return $this->_scheme . '://' . $this->_host . ':' . $this->_port . $this->_path;
    }

    /**
     * Returns the set scheme
     *
     * @return string
     */
    public function getScheme()
    {
        return $this->_scheme;
    }

    /**
     * Set the scheme used. If empty will fallback to constants
     *
     * @param string $scheme Either http or https
     * @throws \UnexpectedValueException
     */
    public function setScheme($scheme)
    {
        // Use the provided scheme or use the default
        if (empty($scheme)) {
            throw new \UnexpectedValueException('Scheme parameter is empty', 1380756390);
        }

        $isHttpOrHttps = in_array($scheme, [self::SCHEME_HTTP, self::SCHEME_HTTPS]);
        if (!$isHttpOrHttps) {
            throw new \UnexpectedValueException('Unsupported scheme parameter, scheme must be http or https', 1380756442);
        }

        // we have a valid scheme
        $this->_scheme = $scheme;

        if ($this->_urlsInited) {
            $this->_initUrls();
        }
    }

    /**
     * Return a valid http URL given this server's scheme, host, port, and path
     * and a provided servlet name.
     *
     * @param string $servlet Servlet name
     * @param array $params Additional URL parameters to attach to the end of the URL
     * @return string Servlet URL
     */
    protected function _constructUrl($servlet, $params = [])
    {
        $url = parent::_constructUrl($servlet, $params);

        if (!GeneralUtility::isFirstPartOfStr($url, $this->_scheme)) {
            $parsedUrl = parse_url($url);

            // unfortunately can't use str_replace as it replace all
            // occurrences of $needle and can't be limited to replace only once
            $url = $this->_scheme . substr($url, strlen($parsedUrl['scheme']));
        }

        return $url;
    }

    /**
     * Central method for making a get operation against this Solr Server
     *
     * @param string $url
     * @param float|bool $timeout Read timeout in seconds
     * @return \Apache_Solr_Response
     */
    protected function _sendRawGet($url, $timeout = false)
    {
        $logSeverity = SolrLogManager::INFO;
        $exception = null;

        try {
            $response = parent::_sendRawGet($url, $timeout);
        } catch (\Apache_Solr_HttpTransportException $exception) {
            $logSeverity = SolrLogManager::ERROR;
            $response = $exception->getResponse();
        }

        if ($this->configuration->getLoggingQueryRawGet() || $response->getHttpStatus() != 200) {
            $this->writeLog($logSeverity, 'Querying Solr using GET', $url, $response, $exception);
        }

        return $response;
    }

    /**
     * Central method for making a HTTP DELETE operation against the Solr server
     *
     * @param string $url
     * @param bool|float $timeout Read timeout in seconds
     * @return \Apache_Solr_Response
     */
    protected function _sendRawDelete($url, $timeout = false)
    {
        $logSeverity = SolrLogManager::INFO;
        $exception = null;

        try {
            $httpTransport = $this->getHttpTransport();
            $httpResponse = $httpTransport->performDeleteRequest($url, $timeout);
            $solrResponse = new \Apache_Solr_Response($httpResponse, $this->_createDocuments, $this->_collapseSingleValueArrays);

            if ($solrResponse->getHttpStatus() != 200) {
                throw new \Apache_Solr_HttpTransportException($solrResponse);
            }
        } catch (\Apache_Solr_HttpTransportException $exception) {
            $logSeverity = SolrLogManager::ERROR;
            $solrResponse = $exception->getResponse();
        }

        if ($this->configuration->getLoggingQueryRawDelete() || $solrResponse->getHttpStatus() != 200) {
            $this->writeLog($logSeverity, 'Querying Solr using DELETE', $url, $solrResponse, $exception);
        }

        return $solrResponse;
    }

    /**
     * Central method for making a post operation against this Solr Server
     *
     * @param string $url
     * @param string $rawPost
     * @param float|bool $timeout Read timeout in seconds
     * @param string $contentType
     * @return \Apache_Solr_Response
     */
    protected function _sendRawPost($url, $rawPost, $timeout = false, $contentType = 'text/xml; charset=UTF-8')
    {
        $logSeverity = SolrLogManager::INFO;
        $exception = null;

        try {
            $response = parent::_sendRawPost($url, $rawPost, $timeout, $contentType);
        } catch (\Apache_Solr_HttpTransportException $exception) {
            $logSeverity = SolrLogManager::ERROR;
            $response = $exception->getResponse();
        }

        if ($this->configuration->getLoggingQueryRawPost() || $response->getHttpStatus() != 200) {
            $this->writeLog($logSeverity, 'Querying Solr using POST', $url, $response, $exception, $rawPost);
        }

        return $response;
    }


    /**
     * Build the log data and writes the message to the log
     *
     * @param integer $logSeverity
     * @param string $message
     * @param string $url
     * @param \Apache_Solr_Response $solrResponse
     * @param \Exception $exception
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
     * @param \Apache_Solr_Response $solrResponse
     * @param \Exception $e
     * @param string $url
     * @param string $contentSend
     * @return array
     */
    protected function buildLogDataFromResponse(\Apache_Solr_Response $solrResponse, \Exception $e = null, $url = '', $contentSend = '')
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
            $solrResponse->response;
            $logData['response data'] = print_r($solrResponse, true);
            return $logData;
        }
    }


    /**
     * Returns the core name from the configured path without the core name.
     *
     * @return string
     */
    public function getCoreBasePath()
    {
        $pathWithoutLeadingAndTrailingSlashes = trim(trim($this->_path), "/");
        $pathWithoutLastSegment = substr($pathWithoutLeadingAndTrailingSlashes, 0, strrpos($pathWithoutLeadingAndTrailingSlashes, "/"));
        return '/' . $pathWithoutLastSegment . '/';
    }

    /**
     * Returns the core name from the configured path.
     *
     * @return string
     */
    public function getCoreName()
    {
        $paths = explode('/', trim($this->_path, '/'));
        return (string)array_pop($paths);
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
     * @param int $timeout maximum time to wait for ping in seconds, -1 for unlimited (default is 2)
     * @param boolean $useCache indicates if the ping result should be cached in the instance or not
     * @return bool TRUE if Solr can be reached, FALSE if not
     */
    public function ping($timeout = 2, $useCache = true)
    {
        $httpResponse = $this->performPingRequest($timeout, $useCache);
        return ($httpResponse->getStatusCode() === 200);
    }

    /**
     * Call the /admin/ping servlet, can be used to get the runtime of a ping request.
     *
     * @param int $timeout maximum time to wait for ping in seconds, -1 for unlimited (default is 2)
     * @param boolean $useCache indicates if the ping result should be cached in the instance or not
     * @return double runtime in milliseconds
     * @throws \ApacheSolrForTypo3\Solr\PingFailedException
     */
    public function getPingRoundTripRuntime($timeout = 2, $useCache = true)
    {
        $start = $this->getMilliseconds();
        $httpResponse = $this->performPingRequest($timeout, $useCache);
        $end = $this->getMilliseconds();

        if ($httpResponse->getStatusCode() !== 200) {
            $message = 'Solr ping failed with unexpected response code: ' . $httpResponse->getStatusCode();
            /** @var $exception \ApacheSolrForTypo3\Solr\PingFailedException */
            $exception = GeneralUtility::makeInstance(PingFailedException::class, $message);
            $exception->setHttpResponse($httpResponse);
            throw $exception;
        }

        return $end - $start;
    }

    /**
     * Make a request to a servlet (a path) that's not a standard path.
     *
     * @param string $servlet Path to be added to the base Solr path.
     * @param array $parameters Optional, additional request parameters when constructing the URL.
     * @param string $method HTTP method to use, defaults to GET.
     * @param array $requestHeaders Key value pairs of header names and values. Should include 'Content-Type' for POST and PUT.
     * @param string $rawPost Must be an empty string unless method is POST or PUT.
     * @param float|bool $timeout Read timeout in seconds, defaults to FALSE.
     * @return \Apache_Solr_Response Response object
     * @throws \Apache_Solr_HttpTransportException if returned HTTP status is other than 200
     */
    public function requestServlet($servlet, $parameters = [], $method = 'GET', $requestHeaders = [], $rawPost = '', $timeout = false)
    {
        // Add default parameters
        $parameters['wt'] = self::SOLR_WRITER;
        $parameters['json.nl'] = $this->_namedListTreatment;
        $url = $this->_constructUrl($servlet, $parameters);

        $httpResponse = $this->getResponseFromTransport($url, $method, $requestHeaders, $rawPost, $timeout);
        $solrResponse = new \Apache_Solr_Response($httpResponse, $this->_createDocuments, $this->_collapseSingleValueArrays);
        if ($solrResponse->getHttpStatus() != 200) {
            throw new \Apache_Solr_HttpTransportException($solrResponse);
        }

        return $solrResponse;
    }

    /**
     * Decides which transport method to used, depending on the request method and retrieves the response.
     *
     * @param string $url
     * @param string $method
     * @param array $requestHeaders
     * @param string $rawPost
     * @param float|bool $timeout
     * @return \Apache_Solr_HttpTransport_Response
     */
    protected function getResponseFromTransport($url, $method, $requestHeaders, $rawPost, $timeout)
    {
        $httpTransport = $this->getHttpTransport();

        if ($method == self::METHOD_GET) {
            return $httpTransport->performGetRequest($url, $timeout);
        }
        if ($method == self::METHOD_POST) {
            // FIXME should respect all headers, not only Content-Type
            return $httpTransport->performPostRequest($url, $rawPost, $requestHeaders['Content-Type'], $timeout);
        }

        throw new \InvalidArgumentException('$method should be GET or POST');
    }

    /**
     * Performs a ping request and returns the result.
     *
     * @param int $timeout
     * @param boolean $useCache indicates if the ping result should be cached in the instance or not
     * @return \Apache_Solr_HttpTransport_Response
     */
    protected function performPingRequest($timeout = 2, $useCache = true)
    {
        $cacheKey = (string)($this);
        if ($useCache && isset(static::$pingCache[$cacheKey])) {
            return static::$pingCache[$cacheKey];
        }

        $pingResult = $this->getHttpTransport()->performGetRequest($this->_pingUrl, $timeout);

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
        return GeneralUtility::milliseconds();
    }
}