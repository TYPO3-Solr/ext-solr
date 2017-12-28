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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SchemaParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\StopWordParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SynonymParser;
use ApacheSolrForTypo3\Solr\System\Solr\Schema\Schema;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SolrAdminService
 * @package ApacheSolrForTypo3\System\Solr\Service
 */
class SolrAdminService extends AbstractSolrService
{
    const PLUGINS_SERVLET = 'admin/plugins';
    const LUKE_SERVLET = 'admin/luke';
    const SYSTEM_SERVLET = 'admin/system';
    const CORES_SERVLET = 'admin/cores';
    const FILE_SERVLET = 'admin/file';
    const SCHEMA_SERVLET = 'schema';
    const SYNONYMS_SERVLET = 'schema/analysis/synonyms/';
    const STOPWORDS_SERVLET = 'schema/analysis/stopwords/';

    /**
     * Constructed servlet URL for plugin information
     *
     * @var string
     */
    protected $_pluginsUrl;

    /**
     * Constructed servlet URL for Luke
     *
     * @var string
     */
    protected $_lukeUrl;

    /**
     * @var array
     */
    protected $lukeData = [];

    /**
     * @var string
     */
    protected $_coresUrl;


    protected $systemData = null;

    protected $pluginsData = null;

    /**
     * @var string|null
     */
    protected $solrconfigName;

    /**
     * @var string
     */
    protected $_schemaUrl;

    /**
     * @var SchemaParser
     */
    protected $schemaParser = null;

    /**
     * @var Schema
     */
    protected $schema;

    /**
     * @var string
     */
    protected $_synonymsUrl;

    /**
     * @var string
     */
    protected $_stopWordsUrl;

    /**
     * @var SynonymParser
     */
    protected $synonymParser = null;

    /**
     * @var StopWordParser
     */
    protected $stopWordParser = null;

    /**
     * Constructor
     *
     * @param string $host Solr host
     * @param string $port Solr port
     * @param string $path Solr path
     * @param string $scheme Scheme, defaults to http, can be https
     * @param TypoScriptConfiguration $typoScriptConfiguration
     * @param SynonymParser $synonymParser
     * @param StopWordParser $stopWordParser
     * @param SchemaParser $schemaParser
     * @param SolrLogManager $logManager
     */
    public function __construct(
        $host = '',
        $port = '8983',
        $path = '/solr/',
        $scheme = 'http',
        TypoScriptConfiguration $typoScriptConfiguration = null,
        SolrLogManager $logManager = null,
        SynonymParser $synonymParser = null,
        StopWordParser $stopWordParser = null,
        SchemaParser $schemaParser = null
    )
    {
        parent::__construct($host, $port, $path, $scheme, $typoScriptConfiguration);

        $this->synonymParser = is_null($synonymParser) ? GeneralUtility::makeInstance(SynonymParser::class) : $synonymParser;
        $this->stopWordParser = is_null($stopWordParser) ? GeneralUtility::makeInstance(StopWordParser::class) : $stopWordParser;
        $this->schemaParser = is_null($schemaParser) ? GeneralUtility::makeInstance(SchemaParser::class) : $schemaParser;
    }

    protected function _initUrls()
    {
        parent::_initUrls();
        $this->_pluginsUrl = $this->_constructUrl(
            self::PLUGINS_SERVLET,
            ['wt' => self::SOLR_WRITER]
        );
        $this->_lukeUrl = $this->_constructUrl(
            self::LUKE_SERVLET,
            [
                'numTerms' => '0',
                'wt' => self::SOLR_WRITER
            ]
        );

        $this->_coresUrl =
            $this->_scheme . '://' .
            $this->_host . ':' .
            $this->_port .
            $this->getCoreBasePath() .
            self::CORES_SERVLET;

        $this->_schemaUrl = $this->_constructUrl(self::SCHEMA_SERVLET);
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
     * get field meta data for the index
     *
     * @param int $numberOfTerms Number of top terms to fetch for each field
     * @return array
     */
    public function getFieldsMetaData($numberOfTerms = 0)
    {
        return $this->getLukeMetaData($numberOfTerms)->fields;
    }

    /**
     * Retrieves meta data about the index from the luke request handler
     *
     * @param int $numberOfTerms Number of top terms to fetch for each field
     * @return \Apache_Solr_Response Index meta data
     */
    public function getLukeMetaData($numberOfTerms = 0)
    {
        if (!isset($this->lukeData[$numberOfTerms])) {
            $lukeUrl = $this->_constructUrl(
                self::LUKE_SERVLET, ['numTerms' => $numberOfTerms, 'wt' => self::SOLR_WRITER, 'fl' => '*']
            );

            $this->lukeData[$numberOfTerms] = $this->_sendRawGet($lukeUrl);
        }

        return $this->lukeData[$numberOfTerms];
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
            $solrconfigXmlUrl = $this->_constructUrl(self::FILE_SERVLET, ['file' => 'solrconfig.xml']);
            $response = $this->_sendRawGet($solrconfigXmlUrl);
            $solrconfigXml = simplexml_load_string($response->getRawResponse());
            if ($solrconfigXml === false) {
                throw new \InvalidArgumentException('No valid xml response from schema file: ' . $solrconfigXmlUrl);
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
     * Reloads the current core
     *
     * @return \Apache_Solr_Response
     */
    public function reloadCore()
    {
        return $this->reloadCoreByName($this->getCoreName());
    }

    /**
     * Reloads a core of the connection by a given corename.
     *
     * @param string $coreName
     * @return \Apache_Solr_Response
     */
    public function reloadCoreByName($coreName)
    {
        $coreAdminReloadUrl = $this->_coresUrl . '?action=reload&core=' . $coreName;
        return $this->_sendRawGet($coreAdminReloadUrl);
    }

    /**
     * Get the configured schema for the current core.
     *
     * @return Schema
     */
    public function getSchema()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }
        $response = $this->_sendRawGet($this->_schemaUrl);
        $this->schema = $this->schemaParser->parseJson($response->getRawResponse());
        return $this->schema;
    }

    /**
     * Get currently configured synonyms
     *
     * @param string $baseWord If given a base word, retrieves the synonyms for that word only
     * @return array
     */
    public function getSynonyms($baseWord = '')
    {
        $this->initializeSynonymsUrl();
        $synonymsUrl = $this->_synonymsUrl;
        if (!empty($baseWord)) {
            $synonymsUrl .= '/' . $baseWord;
        }

        $response = $this->_sendRawGet($synonymsUrl);
        return $this->synonymParser->parseJson($baseWord, $response->getRawResponse());
    }

    /**
     * Add list of synonyms for base word to managed synonyms map
     *
     * @param string $baseWord
     * @param array $synonyms
     *
     * @return \Apache_Solr_Response
     *
     * @throws \Apache_Solr_InvalidArgumentException If $baseWord or $synonyms are empty
     */
    public function addSynonym($baseWord, array $synonyms)
    {
        $this->initializeSynonymsUrl();
        $json = $this->synonymParser->toJson($baseWord, $synonyms);
        return $this->_sendRawPost($this->_synonymsUrl, $json,
            $this->getHttpTransport()->getDefaultTimeout(), 'application/json');
    }

    /**
     * Remove a synonym from the synonyms map
     *
     * @param string $baseWord
     * @return \Apache_Solr_Response
     * @throws \Apache_Solr_InvalidArgumentException
     */
    public function deleteSynonym($baseWord)
    {
        $this->initializeSynonymsUrl();
        if (empty($baseWord)) {
            throw new \Apache_Solr_InvalidArgumentException('Must provide base word.');
        }

        return $this->_sendRawDelete($this->_synonymsUrl . '/' . urlencode($baseWord));
    }


    /**
     * Get currently configured stop words
     *
     * @return array
     */
    public function getStopWords()
    {
        $this->initializeStopWordsUrl();
        $response = $this->_sendRawGet($this->_stopWordsUrl);
        return $this->stopWordParser->parseJson($response->getRawResponse());
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
        $this->initializeStopWordsUrl();
        $json = $this->stopWordParser->toJson($stopWords);
        return $this->_sendRawPost($this->_stopWordsUrl, $json,
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
        $this->initializeStopWordsUrl();
        if (empty($stopWord)) {
            throw new \Apache_Solr_InvalidArgumentException('Must provide stop word.');
        }

        return $this->_sendRawDelete($this->_stopWordsUrl . '/' . urlencode($stopWord));
    }

    /**
     * @return void
     */
    protected function initializeSynonymsUrl()
    {
        if (trim($this->_synonymsUrl) !== '') {
            return;
        }
        $this->_synonymsUrl = $this->_constructUrl(self::SYNONYMS_SERVLET) . $this->getSchema()->getLanguage();
    }

    /**
     * @return void
     */
    protected function initializeStopWordsUrl()
    {
        if (trim($this->_stopWordsUrl) !== '') {
            return;
        }

        $this->_stopWordsUrl = $this->_constructUrl(self::STOPWORDS_SERVLET) . $this->getSchema()->getLanguage();
    }
}
