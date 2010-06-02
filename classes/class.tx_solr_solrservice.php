<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Ingo Renner <ingo.renner@dkd.de>
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

require_once($GLOBALS['PATH_solr'] . 'lib/SolrPhpClient/Apache/Solr/Service.php');


/**
 * Solr Service Access
 *
 * @author	Ingo Renner <ingo.renner@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_SolrService extends Apache_Solr_Service {

	const LUKE_SERVLET = 'admin/luke';
	const SYSTEM_SERVLET = 'admin/system';

	/**
	 * Constructed servlet URL for Luke
	 *
	 * @var string
	 */
	protected $_lukeUrl;

	/**
	 * Constructed servlet URL for system information
	 *
	 * @var string
	 */
	protected $_systemUrl;

	protected $debug;

	/**
	 * @var Apache_Solr_Response
	 */
	protected $responseCache = null;
	protected $hasSearched = false;

	protected $lukeData = array();
	protected $systemData = null;


	/**
	 * Constructor for class tx_solr_SolrService.
	 *
	 * @param	string	Solr host
	 * @param	string	Solr port
	 * @param	string	Solr path
	 */
	public function __construct($host = '', $port = '8080', $path = '/solr/') {

		if (empty($host)) {
			$solrConfiguration = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['solr.'];

			$host = $solrConfiguration['host'];
			$port = $solrConfiguration['port'];
			$path = $solrConfiguration['path'];
		}

		parent::__construct($host, $port, $path);
	}

	public function __destruct() {
			// TODO make this customizable as it might impact on performance when committing too often
			// disabled for now as we're using auto commit in solrconfig.xml
#		$this->commit();
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

		$this->_systemUrl  = $this->_constructUrl(
			self::SYSTEM_SERVLET,
			array('wt' => self::SOLR_WRITER)
		);
	}

	public function search($query, $offset = 0, $limit = 10, $params = array()) {
		$this->responseCache = parent::search($query, $offset, $limit, $params);
		$this->hasSearched = true;

		return $this->responseCache;
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
	 * Gets some information about the Solr server
	 *
	 * @return	array	A nested array of system data.
	 */
	public function getSystemInformation() {

		if (empty($this->systemData)) {
			$systemInformation = $this->_sendRawGet($this->_systemUrl);

				// access a random property to trigger response parsing
			$systemInformation->responseHeader;
			$this->systemData = $systemInformation;
		}

		return $this->systemData;
	}

	/**
	 * Gets the name of the schema installed and in use on the Solr server.
	 *
	 * @return	string	Name of the active schema
	 */
	public function getSchemaName() {
		$systemInformation = $this->getSystemInformation();

		return $systemInformation->core->schema;
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
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_solrservice.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_solrservice.php']);
}

?>