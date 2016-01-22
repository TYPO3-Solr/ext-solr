<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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
use ApacheSolrForTypo3\Solr\PingFailedException;

/**
 * Solr Service Access
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class SolrService extends \Apache_Solr_Service
{

    const LUKE_SERVLET = 'admin/luke';
    const SYSTEM_SERVLET = 'admin/system';
    const PLUGINS_SERVLET = 'admin/plugins';
    const CORES_SERVLET = 'admin/cores';
    const SCHEMA_SERVLET = 'schema';
    const SYNONYMS_SERVLET = 'schema/analysis/synonyms/';
    const STOPWORDS_SERVLET = 'schema/analysis/stopwords/';

    const SCHEME_HTTP = 'http';
    const SCHEME_HTTPS = 'https';

    /**
     * Server connection scheme. http or https.
     *
     * @var string
     */
    protected $_scheme = self::SCHEME_HTTP;

    /**
     * Constructed servlet URL for Luke
     *
     * @var string
     */
    protected $_lukeUrl;

    /**
     * Constructed servlet URL for plugin information
     *
     * @var string
     */
    protected $_pluginsUrl;

    protected $_coresUrl;

    protected $_extractUrl;

    protected $_synonymsUrl;

    protected $_stopWordsUrl;

    protected $_schemaUrl;

    protected $debug;

    /**
     * @var \Apache_Solr_Response
     */
    protected $responseCache = null;
    protected $hasSearched = false;

    protected $lukeData = array();
    protected $systemData = null;
    protected $pluginsData = null;

    protected $schemaName = null;
    protected $solrconfigName = null;

    /**
     * @var array
     */
    protected $configuration;


    /**
     * Constructor
     *
     * @param string $host Solr host
     * @param string $port Solr port
     * @param string $path Solr path
     * @param string $scheme Scheme, defaults to http, can be https
     */
    public function __construct(
        $host = '',
        $port = '8080',
        $path = '/solr/',
        $scheme = 'http'
    ) {
        $this->setScheme($scheme);
        $this->configuration = Util::getSolrConfiguration();

        parent::__construct($host, $port, $path);
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
     * Returns the current time in milliseconds.
     *
     * @return integer
     */
    protected function getMilliseconds()
    {
        return GeneralUtility::milliseconds();
    }

    /**
     * Performs a search.
     *
     * @param string $query query string / search term
     * @param integer $offset result offset for pagination
     * @param integer $limit number of results to retrieve
     * @param array $params additional HTTP GET parameters
     * @return \Apache_Solr_Response Solr response
     * @throws \RuntimeException if Solr returns a HTTP status code other than 200
     */
    public function search($query, $offset = 0, $limit = 10, $params = array())
    {
        $response = parent::search($query, $offset, $limit, $params);
        $this->hasSearched = true;

        $this->responseCache = $response;

        if ($response->getHttpStatus() != 200) {
            throw new \RuntimeException(
                'Invalid query. Solr returned an error: '
                . $response->getHttpStatus() . ' '
                . $response->getHttpStatusMessage(),
                1293109870
            );
        }

        return $response;
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
     * @param float|integer $timeout maximum time to wait for ping in seconds, -1 for unlimited (default is 2)
     * @return bool TRUE if Solr can be reached, FALSE if not
     */
    public function ping($timeout = 2)
    {
        $httpResponse = $this->performPingRequest($timeout);
        return ($httpResponse->getStatusCode() === 200);
    }

    /**
     * Call the /admin/ping servlet, can be used to get the runtime of a ping request.
     *
     * @param float|integer $timeout maximum time to wait for ping in seconds, -1 for unlimited (default is 2)
     * @return int runtime in milliseconds
     * @throws \ApacheSolrForTypo3\Solr\PingFailedException
     */
    public function getPingRoundTripRuntime($timeout = 2)
    {
        $start = $this->getMilliseconds();
        $httpResponse = $this->performPingRequest($timeout);
        $end = $this->getMilliseconds();

        if ($httpResponse->getStatusCode() !== 200) {
            $message = 'Solr ping failed with unexpected response code: ' . $httpResponse->getStatusCode();
            /** @var $exception \ApacheSolrForTypo3\Solr\PingFailedException */
            $exception = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\PingFailedException', $message);
            $exception->setHttpResponse($httpResponse);
            throw $exception;
        }

        return $end - $start;
    }

    /**
     * Performs a ping request and returns the result.
     *
     * @param int $timeout
     * @return \Apache_Solr_HttpTransport_Response
     */
    protected function performPingRequest($timeout = 2)
    {
        return $this->getHttpTransport()->performGetRequest($this->_pingUrl, $timeout);
    }

    /**
     * Performs a content and meta data extraction request.
     *
     * @param ExtractingQuery $query An extraction query
     * @return array An array containing the extracted content [0] and meta data [1]
     */
    public function extract(ExtractingQuery $query)
    {
        $headers = array(
            'Content-Type' => 'multipart/form-data; boundary=' . $query->getMultiPartPostDataBoundary()
        );

        try {
            $response = $this->requestServlet(
                self::EXTRACT_SERVLET,
                $query->getQueryParameters(),
                'POST',
                $headers,
                $query->getRawPostFileData()
            );
        } catch (\Exception $e) {
            GeneralUtility::devLog('Extracting text and meta data through Solr Cell over HTTP POST',
                'solr', 3, array(
                    'query' => (array)$query,
                    'parameters' => $query->getQueryParameters(),
                    'file' => $query->getFile(),
                    'headers' => $headers,
                    'query url' => self::EXTRACT_SERVLET,
                    'response' => $response,
                    'exception' => $e->getMessage()
                ));
        }

        return array(
            $response->extracted,
            (array)$response->extracted_metadata
        );
    }

    /**
     * Make a request to a servlet (a path) that's not a standard path.
     *
     * @param string $servlet Path to be added to the base Solr path.
     * @param array $parameters Optional, additional request parameters when constructing the URL.
     * @param string $method HTTP method to use, defaults to GET.
     * @param array $requestHeaders Key value pairs of header names and values. Should include 'Content-Type' for POST and PUT.
     * @param string $rawPost Must be an empty string unless method is POST or PUT.
     * @param float|boolean $timeout Read timeout in seconds, defaults to FALSE.
     * @return \Apache_Solr_Response Response object
     * @throws \Apache_Solr_HttpTransportException if returned HTTP status is other than 200
     */
    public function requestServlet(
        $servlet,
        $parameters = array(),
        $method = 'GET',
        $requestHeaders = array(),
        $rawPost = '',
        $timeout = false
    ) {
        $httpTransport = $this->getHttpTransport();
        $solrResponse = null;

        if ($method == 'GET' || $method == 'HEAD') {
            // Make sure we are not sending a request body.
            $rawPost = '';
        }

        // Add default parameters
        $parameters['wt'] = self::SOLR_WRITER;
        $parameters['json.nl'] = $this->_namedListTreatment;
        $url = $this->_constructUrl($servlet, $parameters);

        if ($method == self::METHOD_GET) {
            $httpResponse = $httpTransport->performGetRequest($url, $timeout);
        } elseif ($method == self::METHOD_POST) {
            // FIXME should respect all headers, not only Content-Type
            $httpResponse = $httpTransport->performPostRequest($url, $rawPost,
                $requestHeaders['Content-Type'], $timeout);
        }

        $solrResponse = new \Apache_Solr_Response($httpResponse,
            $this->_createDocuments, $this->_collapseSingleValueArrays);

        if ($solrResponse->getHttpStatus() != 200) {
            throw new \Apache_Solr_HttpTransportException($solrResponse);
        }

        return $solrResponse;
    }

    /**
     * Return a valid http URL given this server's scheme, host, port, and path
     * and a provided servlet name.
     *
     * @param string $servlet Servlet name
     * @param array $params Additional URL parameters to attach to the end of the URL
     * @return string Servlet URL
     */
    protected function _constructUrl($servlet, $params = array())
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
            throw new \UnexpectedValueException('Scheme parameter is empty',
                1380756390);
        } else {
            if (in_array($scheme,
                array(self::SCHEME_HTTP, self::SCHEME_HTTPS))) {
                $this->_scheme = $scheme;
            } else {
                throw new \UnexpectedValueException('Unsupported scheme parameter, scheme must be http or https',
                    1380756442);
            }
        }

        if ($this->_urlsInited) {
            $this->_initUrls();
        }
    }

    /**
     * get field meta data for the index
     *
     * @param integer $numberOfTerms Number of top terms to fetch for each field
     * @return array
     */
    public function getFieldsMetaData($numberOfTerms = 0)
    {
        return $this->getLukeMetaData($numberOfTerms)->fields;
    }

    /**
     * Retrieves meta data about the index from the luke request handler
     *
     * @param integer $numberOfTerms Number of top terms to fetch for each field
     * @return \Apache_Solr_Response Index meta data
     */
    public function getLukeMetaData($numberOfTerms = 0)
    {
        if (!isset($this->lukeData[$numberOfTerms])) {
            $lukeUrl = $this->_constructUrl(
                self::LUKE_SERVLET,
                array(
                    'numTerms' => $numberOfTerms,
                    'wt' => self::SOLR_WRITER,
                    'fl' => '*'
                )
            );

            $this->lukeData[$numberOfTerms] = $this->_sendRawGet($lukeUrl);
        }

        return $this->lukeData[$numberOfTerms];
    }

    /**
     * Central method for making a get operation against this Solr Server
     *
     * @param string $url
     * @param float|boolean $timeout Read timeout in seconds
     * @return \Apache_Solr_Response
     */
    protected function _sendRawGet($url, $timeout = false)
    {
        $logSeverity = 0; // info

        try {
            $response = parent::_sendRawGet($url, $timeout);
        } catch (\Apache_Solr_HttpTransportException $e) {
            $logSeverity = 3; // fatal error
            $response = $e->getResponse();
        }

        if ($this->configuration['logging.']['query.']['rawGet'] || $response->getHttpStatus() != 200) {
            $logData = array(
                'query url' => $url,
                'response' => (array)$response
            );

            if (!empty($e)) {
                $logData['exception'] = $e->__toString();
            } else {
                // trigger data parsing
                $response->response;
                $logData['response data'] = print_r($response, true);
            }

            GeneralUtility::devLog('Querying Solr using GET', 'solr',
                $logSeverity, $logData);
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
     * @return \Apache_Solr_Response Most recent response, or NULL if a search has not been executed yet.
     */
    public function getResponse()
    {
        return $this->responseCache;
    }

    /**
     * Enable/Disable debug mode
     *
     * @param boolean $debug TRUE to enable debug mode, FALSE to turn off, off by default
     */
    public function setDebug($debug)
    {
        $this->debug = (boolean)$debug;
    }

    /**
     * Gets information about the plugins installed in Solr
     *
     * @return array A nested array of plugin data.
     */
    public function getPluginsInformation()
    {
        if (empty($this->pluginsData)) {
            $pluginsInformation = $this->_sendRawGet($this->_pluginsUrl);

            // access a random property to trigger response parsing
            $pluginsInformation->responseHeader;
            $this->pluginsData = $pluginsInformation;
        }

        return $this->pluginsData;
    }

    /**
     * Gets the name of the schema.xml file installed and in use on the Solr
     * server.
     *
     * @return string Name of the active schema.xml
     */
    public function getSchemaName()
    {
        if (is_null($this->schemaName)) {
            $systemInformation = $this->getSystemInformation();
            $this->schemaName = $systemInformation->core->schema;
        }

        return $this->schemaName;
    }

    /**
     * Gets information about the Solr server
     *
     * @return array A nested array of system data.
     */
    public function getSystemInformation()
    {
        if (empty($this->systemData)) {
            $systemInformation = $this->system();

            // access a random property to trigger response parsing
            $systemInformation->responseHeader;
            $this->systemData = $systemInformation;
        }

        return $this->systemData;
    }

    /**
     * Gets the name of the solrconfig.xml file installed and in use on the Solr
     * server.
     *
     * @return string Name of the active solrconfig.xml
     */
    public function getSolrconfigName()
    {
        if (is_null($this->solrconfigName)) {
            $solrconfigXmlUrl = $this->_scheme . '://'
                . $this->_host . ':' . $this->_port
                . $this->_path . 'admin/file/?file=solrconfig.xml';

            $solrconfigXml = simplexml_load_file($solrconfigXmlUrl);
            if ($solrconfigXml === false) {
                throw new \InvalidArgumentException('No valid xml response from schema file: '.$solrconfigXmlUrl);
            }
            $this->solrconfigName = (string)$solrconfigXml->attributes()->name;
        }

        return $this->solrconfigName;
    }

    /**
     * Gets the Solr server's version number.
     *
     * @return string Solr version number
     */
    public function getSolrServerVersion()
    {
        $systemInformation = $this->getSystemInformation();

        // don't know why $systemInformation->lucene->solr-spec-version won't work
        $luceneInformation = (array)$systemInformation->lucene;
        return $luceneInformation['solr-spec-version'];
    }

    /**
     * Deletes all index documents of a certain type and does a commit
     * afterwards.
     *
     * @param string $type The type of documents to delete, usually a table name.
     * @param boolean $commit Will commit immediately after deleting the documents if set, defaults to TRUE
     */
    public function deleteByType($type, $commit = true)
    {
        $this->deleteByQuery('type:' . trim($type));

        if ($commit) {
            $this->commit(false, false, false);
        }
    }

    /**
     * Raw Delete Method. Takes a raw post body and sends it to the update service. Body should be
     * a complete and well formed "delete" xml document
     *
     * @param string $rawPost Expected to be utf-8 encoded xml document
     * @param float|integer $timeout Maximum expected duration of the delete operation on the server (otherwise, will throw a communication exception)
     * @return \Apache_Solr_Response
     */
    public function delete($rawPost, $timeout = 3600)
    {
        $response = $this->_sendRawPost($this->_updateUrl, $rawPost, $timeout);

        GeneralUtility::devLog(
            'Delete Query sent.',
            'solr',
            1,
            array(
                'query' => $rawPost,
                'query url' => $this->_updateUrl,
                'response' => (array)$response
            )
        );

        return $response;
    }

    /**
     * Central method for making a post operation against this Solr Server
     *
     * @param string $url
     * @param string $rawPost
     * @param float|boolean $timeout Read timeout in seconds
     * @param string $contentType
     * @return \Apache_Solr_Response
     */
    protected function _sendRawPost(
        $url,
        $rawPost,
        $timeout = false,
        $contentType = 'text/xml; charset=UTF-8'
    ) {
        $logSeverity = 0; // info

        try {
            $response = parent::_sendRawPost($url, $rawPost, $timeout,
                $contentType);
        } catch (\Apache_Solr_HttpTransportException $e) {
            $logSeverity = 3; // fatal error
            $response = $e->getResponse();
        }

        if ($this->configuration['logging.']['query.']['rawPost'] || $response->getHttpStatus() != 200) {
            $logData = array(
                'query url' => $url,
                'content' => $rawPost,
                'response' => (array)$response
            );

            if (!empty($e)) {
                $logData['exception'] = $e->__toString();
            }

            GeneralUtility::devLog('Querying Solr using POST', 'solr',
                $logSeverity, $logData);
        }

        return $response;
    }

    /**
     * Get currently configured synonyms
     *
     * @param string $baseWord If given a base word, retrieves the synonyms for that word only
     * @return array
     */
    public function getSynonyms($baseWord = '')
    {
        $synonymsUrl = $this->_synonymsUrl;
        if (!empty($baseWord)) {
            $synonymsUrl .= '/' . $baseWord;
        }

        $response = $this->_sendRawGet($synonymsUrl);
        $decodedResponse = json_decode($response->getRawResponse());

        $synonyms = array();
        if (!empty($baseWord)) {
            if (is_array($decodedResponse->{$baseWord})) {
                $synonyms = $decodedResponse->{$baseWord};
            }
        } else {
            if (isset($decodedResponse->synonymMappings->managedMap)) {
                $synonyms = (array)$decodedResponse->synonymMappings->managedMap;
            }
        }

        return $synonyms;
    }

    /**
     * Add list of synonyms for base word to managed synonyms map
     *
     * @param $baseWord
     * @param array $synonyms
     *
     * @return \Apache_Solr_Response
     *
     * @throws \Apache_Solr_InvalidArgumentException If $baseWord or $synonyms are empty
     */
    public function addSynonym($baseWord, array $synonyms)
    {
        if (empty($baseWord) || empty($synonyms)) {
            throw new \Apache_Solr_InvalidArgumentException('Must provide base word and synonyms.');
        }

        $rawPut = json_encode(array($baseWord => $synonyms));
        return $this->_sendRawPost($this->_synonymsUrl, $rawPut,
            $this->getHttpTransport()->getDefaultTimeout(), 'application/json');
    }

    /**
     * Remove a synonym from the synonyms map
     *
     * @param $baseWord
     * @return \Apache_Solr_Response
     * @throws \Apache_Solr_InvalidArgumentException
     */
    public function deleteSynonym($baseWord)
    {
        if (empty($baseWord)) {
            throw new \Apache_Solr_InvalidArgumentException('Must provide base word.');
        }

        return $this->_sendRawDelete($this->_synonymsUrl . '/' . $baseWord);
    }

    /**
     * Central method for making a HTTP DELETE operation against the Solr server
     *
     * @param $url
     * @param bool|float $timeout Read timeout in seconds
     * @return \Apache_Solr_Response
     */
    protected function _sendRawDelete($url, $timeout = false)
    {
        $logSeverity = 0; // info

        try {
            $httpTransport = $this->getHttpTransport();

            $httpResponse = $httpTransport->performDeleteRequest($url,
                $timeout);
            $solrResponse = new \Apache_Solr_Response($httpResponse,
                $this->_createDocuments, $this->_collapseSingleValueArrays);

            if ($solrResponse->getHttpStatus() != 200) {
                throw new \Apache_Solr_HttpTransportException($solrResponse);
            }
        } catch (\Apache_Solr_HttpTransportException $e) {
            $logSeverity = 3; // fatal error
            $solrResponse = $e->getResponse();
        }

        if ($this->configuration['logging.']['query.']['rawDelete'] || $solrResponse->getHttpStatus() != 200) {
            $logData = array(
                'query url' => $url,
                'response' => (array)$solrResponse
            );

            if (!empty($e)) {
                $logData['exception'] = $e->__toString();
            } else {
                // trigger data parsing
                $solrResponse->response;
                $logData['response data'] = print_r($solrResponse, true);
            }

            GeneralUtility::devLog('Querying Solr using GET', 'solr',
                $logSeverity, $logData);
        }

        return $solrResponse;
    }

    /**
     * Get currently configured stop words
     *
     * @return array
     */
    public function getStopWords()
    {
        $stopWords = array();

        $response = $this->_sendRawGet($this->_stopWordsUrl);
        $decodedResponse = json_decode($response->getRawResponse());

        if (isset($decodedResponse->wordSet->managedList)) {
            $stopWords = (array)$decodedResponse->wordSet->managedList;
        }

        return $stopWords;
    }

    /**
     * Adds stop words to the managed stop word list
     *
     * @param array|string $stopWords string for a single word, array for multiple words
     * @return \Apache_Solr_Response
     * @throws \Apache_Solr_InvalidArgumentException If $stopWords is empty
     */
    public function addStopWords($stopWords)
    {
        if (empty($stopWords)) {
            throw new \Apache_Solr_InvalidArgumentException('Must provide stop word.');
        }

        if (is_string($stopWords)) {
            $stopWords = array($stopWords);
        }

        $stopWords = array_values($stopWords);
        $rawPut = json_encode($stopWords);
        return $this->_sendRawPost($this->_stopWordsUrl, $rawPut,
            $this->getHttpTransport()->getDefaultTimeout(), 'application/json');
    }

    /**
     * Deletes a words from the managed stop word list
     *
     * @param string $stopWord stop word to delete
     * @return \Apache_Solr_Response
     * @throws \Apache_Solr_InvalidArgumentException If $stopWords is empty
     */
    public function deleteStopWord($stopWord)
    {
        if (empty($stopWord)) {
            throw new \Apache_Solr_InvalidArgumentException('Must provide stop word.');
        }

        return $this->_sendRawDelete($this->_stopWordsUrl . '/' . $stopWord);
    }

    /**
     * Reloads the current core
     *
     * @return \Apache_Solr_Response
     */
    public function reloadCore()
    {
        $coreName = array_pop(explode('/', trim($this->_path, '/')));
        $coreAdminReloadUrl = $this->_coresUrl . '?action=reload&core=' . $coreName;

        return $this->_sendRawGet($coreAdminReloadUrl);
    }

    /**
     * initializes various URLs, including the Luke URL
     *
     * @return void
     */
    protected function _initUrls()
    {
        parent::_initUrls();

        $this->_lukeUrl = $this->_constructUrl(
            self::LUKE_SERVLET,
            array(
                'numTerms' => '0',
                'wt' => self::SOLR_WRITER
            )
        );

        $this->_pluginsUrl = $this->_constructUrl(
            self::PLUGINS_SERVLET,
            array('wt' => self::SOLR_WRITER)
        );

        $pathElements = explode('/', trim($this->_path, '/'));
        $this->_coresUrl =
            $this->_scheme . '://' .
            $this->_host . ':' .
            $this->_port . '/' .
            $pathElements[0] . '/' .
            self::CORES_SERVLET;

        $this->_schemaUrl = $this->_constructUrl(self::SCHEMA_SERVLET);

        $managedLanguage = $this->getManagedLanguage();
        $this->_synonymsUrl = $this->_constructUrl(
                self::SYNONYMS_SERVLET
            ) . $managedLanguage;
        $this->_stopWordsUrl = $this->_constructUrl(
                self::STOPWORDS_SERVLET
            ) . $managedLanguage;
    }

    /**
     * Get the language map name for the text field.
     * This is necessary to select the correct synonym map.
     *
     * @return string
     */
    protected function getManagedLanguage()
    {
        $language = 'english';

        $schema = $this->getSchema();

        if (is_object($schema) && isset($schema->fieldTypes)) {
            foreach ($schema->fieldTypes as $fieldType) {
                if ($fieldType->name === 'text') {
                    foreach ($fieldType->queryAnalyzer->filters as $filter) {
                        if ($filter->class === 'solr.ManagedSynonymFilterFactory') {
                            $language = $filter->managed;
                        }
                    }
                }
            }
        }

        return $language;
    }

    /**
     * Get the configured schema for the current core
     *
     * @return \stdClass
     */
    protected function getSchema()
    {
        $response = $this->_sendRawGet($this->_schemaUrl);
        return json_decode($response->getRawResponse())->schema;
    }
}
