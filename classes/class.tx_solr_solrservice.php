<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Ingo Renner <ingo.renner@dkd.de>
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


/**
 * Solr Service Access
 *
 * @author	Ingo Renner <ingo.renner@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_SolrService extends Apache_Solr_Service {

	const LUKE_SERVLET = 'admin/luke';
	const SYSTEM_SERVLET = 'admin/system';
	const PLUGINS_SERVLET = 'admin/plugins';

	/**
	 * Server connection scheme. http or https.
	 *
	 * @var string
	 */
	protected $_scheme = 'http';

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

	protected $_extractUrl;

	protected $debug;

	/**
	 * @var Apache_Solr_Response
	 */
	protected $responseCache = NULL;
	protected $hasSearched   = FALSE;

	protected $lukeData       = array();
	protected $systemData     = NULL;
	protected $pluginsData    = NULL;
	protected $solrconfigName = NULL;


	/**
	 * Constructor for class tx_solr_SolrService.
	 *
	 * @param	string	Solr host
	 * @param	string	Solr port
	 * @param	string	Solr path
	 * @param	string	Scheme, defaults to http, can be https
	 */
	public function __construct($host = '', $port = '8080', $path = '/solr/', $scheme = 'http') {
		$this->setScheme($scheme);

		parent::__construct($host, $port, $path);
	}

	/**
	 * initializes various URLs, including the Luke URL
	 *
	 * @return void
	 */
	protected function _initUrls() {
		parent::_initUrls();

		$this->_lukeUrl = $this->_constructUrl(
			self::LUKE_SERVLET,
			array(
				'numTerms' => '0',
				'wt' => self::SOLR_WRITER
			)
		);

		$this->_pluginsUrl  = $this->_constructUrl(
			self::PLUGINS_SERVLET,
			array('wt' => self::SOLR_WRITER)
		);
	}

	/**
	 * Return a valid http URL given this server's scheme, host, port, and path
	 * and a provided servlet name.
	 *
	 * @param string $servlet
	 * @return string
	 */
	protected function _constructUrl($servlet, $params = array()) {
		$url = parent::_constructUrl($servlet, $params);

		if (!t3lib_div::isFirstPartOfStr($url, $this->_scheme)) {
			$parsedUrl = parse_url($url);

				// unfortunately can't use str_replace as it replace all
				// occurances of $needle and can't be limited to replace only once
			$url = $this->_scheme . substr($url, strlen($parsedUrl['scheme']));
		}

		return $url;
	}

	/**
	 * Make a request to a servlet (a path) that's not a standard path.
	 *
	 * @param	string	Path to be added to the base Solr path.
	 * @param	array	Optional, additional request parameters when constructing the URL.
	 * @param	string	HTTP method to use, defaults to GET.
	 * @param	string	Key value pairs of header names and values. Should include 'Content-Type' for POST and PUT.
	 * @param	string	Must be an empty string unless method is POST or PUT.
	 * @param	float	Read timeout in seconds, defaults to FALSE.
	 * @return	Apache_Solr_Response	Response object
	 */
	public function requestServlet($servlet, $parameters = array(), $method = 'GET', $requestHeaders = array(), $rawPost = '', $timeout = FALSE) {
		$httpTransport = $this->getHttpTransport();
		$solrResponse  = NULL;

		if ($method == 'GET' || $method == 'HEAD') {
				// Make sure we are not sending a request body.
			$rawPost = '';
		}

			// Add default paramseters
		$parameters['wt'] = self::SOLR_WRITER;
		$parameters['json.nl'] = $this->_namedListTreatment;
		$url = $this->_constructUrl($servlet, $parameters);

		if ($method == self::METHOD_GET) {
			$httpResponse = $httpTransport->performGetRequest($url, $timeout);
		} elseif ($method == self::METHOD_POST) {
				// FIXME should respect all headers, not only Content-Type
			$httpResponse = $httpTransport->performPostRequest($url, $rawPost, $requestHeaders['Content-Type'], $timeout);
		}

		$solrResponse = new Apache_Solr_Response($httpResponse, $this->_createDocuments, $this->_collapseSingleValueArrays);

		if ($solrResponse->getHttpStatus() != 200) {
			throw new Apache_Solr_HttpTransportException($solrResponse);
		}

		return $solrResponse;
	}

	/**
	 * Performs a search.
	 *
	 * @param	string	query string / search term
	 * @param	integer	result offset for pagination
	 * @param	integer	number of results to retrieve
	 * @param	array	additional HTTP GET parameters
	 * @return	Apache_Solr_Response	Solr response
	 */
	public function search($query, $offset = 0, $limit = 10, $params = array()) {
		$response = parent::search($query, $offset, $limit, $params);
		$this->hasSearched = TRUE;

		$this->responseCache = $response;

		if ($response->getHttpStatus() != 200) {
			throw new RuntimeException(
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
	 * server is able to be made.
	 *
	 * Simply overrides the SolrPhpClient implementation, changing ping from a
	 * HEAD to a GET request, see http://forge.typo3.org/issues/44167
	 *
	 * @param float $timeout maximum time to wait for ping in seconds, -1 for unlimited (default is 2)
	 * @return float Actual time taken to ping the server, FALSE if timeout or HTTP error status occurs
	 */
	public function ping($timeout = 2) {
		$start = microtime(TRUE);

		$httpTransport = $this->getHttpTransport();

		$httpResponse = $httpTransport->performGetRequest($this->_pingUrl, $timeout);
		$solrResponse = new Apache_Solr_Response($httpResponse, $this->_createDocuments, $this->_collapseSingleValueArrays);

		if ($solrResponse->getHttpStatus() == 200) {
			return microtime(TRUE) - $start;
		} else {
			return FALSE;
		}
	}

	/**
	 * Performs a content and meta data extraction request.
	 *
	 * @param	tx_solr_ExtractingQuery	An extraction query
	 * @return	array	An array containing the extracted content [0] and meta data [1]
	 */
	public function extract(tx_solr_ExtractingQuery $query) {
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
		} catch (Exception $e) {
			t3lib_div::devLog('Extracting text and meta data through Solr Cell over HTTP POST', 'solr', 3, array(
				'query'      => (array) $query,
				'parameters' => $query->getQueryParameters(),
				'file'       => $query->getFile(),
				'headers'    => $headers,
				'query url'  => self::EXTRACT_SERVLET,
				'response'   => $response,
				'exception'  => $e->getMessage()
			));
		}

		return array($response->extracted, (array) $response->extracted_metadata);
	}

	/**
	 * Central method for making a get operation against this Solr Server
	 *
	 * @param	string	$url
	 * @param	float	$timeout Read timeout in seconds
	 * @return	Apache_Solr_Response
	 */
	protected function _sendRawGet($url, $timeout = FALSE) {
		$logSeverity = 0; // info

		try {
			$response = parent::_sendRawGet($url, $timeout);
		} catch (Apache_Solr_HttpTransportException $e) {
			$logSeverity = 3; // fatal error
			$response    = $e->getResponse();
		}

		if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['query.']['rawGet'] || $response->getHttpStatus() != 200) {
			$logData = array(
				'query url' => $url,
				'response'  => (array) $response
			);

			if (!empty($e)) {
				$logData['exception'] = $e->__toString();
			} else {
					// trigger data parsing
				$response->response;
				$logData['response data'] = print_r($response, TRUE);
			}

			t3lib_div::devLog('Querying Solr using GET', 'solr', $logSeverity, $logData);
		}

		return $response;
	}

	/**
	 * Central method for making a post operation against this Solr Server
	 *
	 * @param	string	$url
	 * @param	string	$rawPost
	 * @param	float	$timeout Read timeout in seconds
	 * @param	string	$contentType
	 * @return	Apache_Solr_Response
	 */
	protected function _sendRawPost($url, $rawPost, $timeout = FALSE, $contentType = 'text/xml; charset=UTF-8') {
		$logSeverity = 0; // info

		try {
			$response = parent::_sendRawPost($url, $rawPost, $timeout, $contentType);
		} catch (Apache_Solr_HttpTransportException $e) {
			$logSeverity = 3; // fatal error
			$response    = $e->getResponse();
		}

		if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['query.']['rawPost'] || $response->getHttpStatus() != 200) {
			$logData = array(
				'query url' => $url,
				'content'   => $rawPost,
				'response'  => (array) $response
			);

			if (!empty($e)) {
				$logData['exception'] = $e->__toString();
			}

			t3lib_div::devLog('Querying Solr using POST', 'solr', $logSeverity, $logData);
		}

		return $response;
	}

	/**
	 * Returns the set scheme
	 *
	 * @return string
	 */
	public function getScheme() {
		return $this->_scheme;
	}

	/**
	 * Set the scheme used. If empty will fallback to constants
	 *
	 * @param	string	$scheme
	 */
	public function setScheme($scheme) {
			// Use the provided scheme or use the default
		if (empty($scheme)) {
			throw new Exception('Scheme parameter is empty');
		} else {
			if (in_array($scheme, array('http', 'https'))) {
				$this->_scheme = $scheme;
			} else {
				throw new Exception('Unsupported scheme parameter');
			}
		}

		if ($this->_urlsInited) {
			$this->_initUrls();
		}
	}

	/**
	 * Retrieves meta data about the index from the luke request handler
	 *
	 * @param	integer	Number of top terms to fetch for each field
	 * @return	array	An array of index meta data
	 */
	public function getLukeMetaData($numberOfTerms = 0) {
		if (!isset($this->luke[$numberOfTerms])) {
			$lukeUrl = $this->_constructUrl(
				self::LUKE_SERVLET,
				array(
					'numTerms' => $numberOfTerms,
					'wt'       => self::SOLR_WRITER
				)
			);

			$this->lukeData[$numberOfTerms] = $this->_sendRawGet($lukeUrl);
		}

		return $this->lukeData[$numberOfTerms];
	}

	/**
	 * get field meta data for the index
	 *
	 * @param	integer	Number of top terms to fetch for each field
	 * @return	array
	 */
	public function getFieldsMetaData($numberOfTerms = 0) {
		return $this->getLukeMetaData($numberOfTerms)->fields;
	}

	public function hasSearched() {
		return $this->hasSearched;
	}

	public function getResponse() {
		return $this->responseCache;
	}

	public function setDebug($debug) {
		$this->debug = (boolean) $debug;
	}

	/**
	 * Gets information about the Solr server
	 *
	 * @return	array	A nested array of system data.
	 */
	public function getSystemInformation() {

		if (empty($this->systemData)) {
			$systemInformation = $this->system();

				// access a random property to trigger response parsing
			$systemInformation->responseHeader;
			$this->systemData = $systemInformation;
		}

		return $this->systemData;
	}

	/**
	 * Gets information about the plugins installed in Solr
	 *
	 * @return	array	A nested array of plugin data.
	 */
	public function getPluginsInformation() {

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
	 * @return	string	Name of the active schema.xml
	 */
	public function getSchemaName() {
		$systemInformation = $this->getSystemInformation();

		return $systemInformation->core->schema;
	}

	/**
	 * Gets the name of the solrconfig.xml file installed and in use on the Solr
	 * server.
	 *
	 * @return	string	Name of the active solrconfig.xml
	 */
	public function getSolrconfigName() {
		if (is_null($this->solrconfigName)) {
			$solrconfigXmlUrl = $this->_scheme . '://'
			. $this->_host . ':' . $this->_port
			. $this->_path . 'admin/file/?file=solrconfig.xml';

			$solrconfigXml = simplexml_load_file($solrconfigXmlUrl);
			$this->solrconfigName = (string) $solrconfigXml->attributes()->name;
		}

		return $this->solrconfigName;
	}

	/**
	 * Gets the Solr server's version number.
	 *
	 * @return	string	Solr version number
	 */
	public function getSolrServerVersion() {
		$systemInformation = $this->getSystemInformation();

			// don't know why $systemInformation->lucene->solr-spec-version won't work
		$luceneInformation = (array) $systemInformation->lucene;
		return $luceneInformation['solr-spec-version'];
	}

	/**
	 * Deletes all index documents of a certain type and does a commit
	 * afterwards.
	 *
	 * @param	string	The type of documents to delete, usually a table name.
	 * @param	boolean	Will commit imidiately after deleting the documents if set, defautls to TRUE
	 */
	public function deleteByType($type, $commit = TRUE) {
		$this->deleteByQuery('type:' . trim($type));

		if ($commit) {
			$this->commit(FALSE, FALSE, FALSE);
		}
	}

	/**
	 * Raw Delete Method. Takes a raw post body and sends it to the update service. Body should be
	 * a complete and well formed "delete" xml document
	 *
	 * @param string $rawPost Expected to be utf-8 encoded xml document
	 * @param float $timeout Maximum expected duration of the delete operation on the server (otherwise, will throw a communication exception)
	 * @return Apache_Solr_Response
	 *
	 * @throws Apache_Solr_HttpTransportException If an error occurs during the service call
	 */
	public function delete($rawPost, $timeout = 3600) {
		$response = $this->_sendRawPost($this->_updateUrl, $rawPost, $timeout);

		t3lib_div::devLog(
			'Delete Query sent.',
			'solr',
			1,
			array(
				'query'     => $rawPost,
				'query url' => $this->_updateUrl,
				'response'  => (array) $response
			)
		);

		return $response;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_solrservice.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_solrservice.php']);
}

?>