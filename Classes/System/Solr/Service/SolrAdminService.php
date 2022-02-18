<?php

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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SchemaParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\StopWordParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SynonymParser;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\Schema\Schema;
use InvalidArgumentException;
use Solarium\Client;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function simplexml_load_string;

/**
 * Class SolrAdminService
 */
class SolrAdminService extends AbstractSolrService
{
    const PLUGINS_SERVLET = 'admin/plugins';
    const LUKE_SERVLET = 'admin/luke';
    const SYSTEM_SERVLET = 'admin/system';
    const CORES_SERVLET = '../admin/cores';
    const FILE_SERVLET = 'admin/file';
    const SCHEMA_SERVLET = 'schema';
    const SYNONYMS_SERVLET = 'schema/analysis/synonyms/';
    const STOPWORDS_SERVLET = 'schema/analysis/stopwords/';

    /**
     * @var array
     */
    protected $lukeData = [];

    protected $systemData = null;

    protected $pluginsData = [];

    /**
     * @var string|null
     */
    protected $solrconfigName;

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
     * @param Client $client
     * @param TypoScriptConfiguration|null $typoScriptConfiguration
     * @param SolrLogManager|null $logManager
     * @param SynonymParser|null $synonymParser
     * @param StopWordParser|null $stopWordParser
     * @param SchemaParser|null $schemaParser
     */
    public function __construct(
        Client $client,
        TypoScriptConfiguration $typoScriptConfiguration = null,
        SolrLogManager $logManager = null,
        SynonymParser $synonymParser = null,
        StopWordParser $stopWordParser = null,
        SchemaParser $schemaParser = null
    )
    {
        parent::__construct($client, $typoScriptConfiguration);

        $this->synonymParser = $synonymParser ?? GeneralUtility::makeInstance(SynonymParser::class);
        $this->stopWordParser = $stopWordParser ?? GeneralUtility::makeInstance(StopWordParser::class);
        $this->schemaParser = $schemaParser ?? GeneralUtility::makeInstance(SchemaParser::class);
    }

    /**
     * Call the /admin/system servlet and retrieve system information about Solr
     *
     * @return ResponseAdapter
     */
    public function system()
    {
        return $this->_sendRawGet($this->_constructUrl(self::SYSTEM_SERVLET, ['wt' => 'json']));
    }

    /**
     * Gets information about the plugins installed in Solr
     *
     * @return array A nested array of plugin data.
     */
    public function getPluginsInformation()
    {
        if (count($this->pluginsData) == 0) {
            $url = $this->_constructUrl(self::PLUGINS_SERVLET, ['wt' => 'json']);
            $pluginsInformation = $this->_sendRawGet($url);

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
     * @return \stdClass
     */
    public function getFieldsMetaData($numberOfTerms = 0)
    {
        return $this->getLukeMetaData($numberOfTerms)->fields;
    }

    /**
     * Retrieves meta data about the index from the luke request handler
     *
     * @param int $numberOfTerms Number of top terms to fetch for each field
     * @return ResponseAdapter Index meta data
     */
    public function getLukeMetaData($numberOfTerms = 0)
    {
        if (!isset($this->lukeData[$numberOfTerms])) {
            $lukeUrl = $this->_constructUrl(
                self::LUKE_SERVLET, ['numTerms' => $numberOfTerms, 'wt' => 'json', 'fl' => '*']
            );

            $this->lukeData[$numberOfTerms] = $this->_sendRawGet($lukeUrl);
        }

        return $this->lukeData[$numberOfTerms];
    }

    /**
     * Gets information about the Solr server
     *
     * @return ResponseAdapter
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
                throw new InvalidArgumentException('No valid xml response from schema file: ' . $solrconfigXmlUrl);
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
    public function getSolrServerVersion(): string
    {
        $systemInformation = $this->getSystemInformation();
        // don't know why $systemInformation->lucene->solr-spec-version won't work
        $luceneInformation = (array)$systemInformation->lucene;
        return $luceneInformation['solr-spec-version'] ?? '';
    }

    /**
     * Reloads the current core
     *
     * @return ResponseAdapter
     */
    public function reloadCore()
    {
        return $this->reloadCoreByName($this->getPrimaryEndpoint()->getCore());
    }

    /**
     * Reloads a core of the connection by a given corename.
     *
     * @param string $coreName
     * @return ResponseAdapter
     */
    public function reloadCoreByName($coreName)
    {
        $coreAdminReloadUrl = $this->_constructUrl(self::CORES_SERVLET) . '?action=reload&core=' . $coreName;
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
        $response = $this->_sendRawGet($this->_constructUrl(self::SCHEMA_SERVLET));

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
            $synonymsUrl .= '/' . rawurlencode(rawurlencode($baseWord));
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
     * @return ResponseAdapter
     */
    public function addSynonym(string $baseWord, array $synonyms): ResponseAdapter
    {
        $this->initializeSynonymsUrl();
        $json = $this->synonymParser->toJson($baseWord, $synonyms);
        return $this->_sendRawPost($this->_synonymsUrl, $json, 'application/json');
    }

    /**
     * Remove a synonym from the synonyms map
     *
     * @param string $baseWord
     * @return ResponseAdapter
     */
    public function deleteSynonym(string $baseWord): ResponseAdapter
    {
        $this->initializeSynonymsUrl();
        return $this->_sendRawDelete($this->_synonymsUrl . '/' . rawurlencode(rawurlencode($baseWord)));
    }

    /**
     * Get currently configured stop words
     *
     * @return array
     */
    public function getStopWords(): array
    {
        $this->initializeStopWordsUrl();
        $response = $this->_sendRawGet($this->_stopWordsUrl);
        return $this->stopWordParser->parseJson($response->getRawResponse());
    }

    /**
     * Adds stop words to the managed stop word list
     *
     * @param array|string $stopWords string for a single word, array for multiple words
     * @return ResponseAdapter
     * @throws InvalidArgumentException If $stopWords is empty
     */
    public function addStopWords($stopWords): ResponseAdapter
    {
        $this->initializeStopWordsUrl();
        $json = $this->stopWordParser->toJson($stopWords);
        return $this->_sendRawPost($this->_stopWordsUrl, $json, 'application/json');
    }

    /**
     * Deletes a words from the managed stop word list
     *
     * @param string $stopWord stop word to delete
     * @return ResponseAdapter
     * @throws InvalidArgumentException If $stopWords is empty
     */
    public function deleteStopWord($stopWord)
    {
        $this->initializeStopWordsUrl();
        if (empty($stopWord)) {
            throw new InvalidArgumentException('Must provide stop word.');
        }

        return $this->_sendRawDelete($this->_stopWordsUrl . '/' . rawurlencode(rawurlencode($stopWord)));
    }

    /**
     * @return void
     */
    protected function initializeSynonymsUrl()
    {
        if (trim($this->_synonymsUrl ?? '') !== '') {
            return;
        }
        $this->_synonymsUrl = $this->_constructUrl(self::SYNONYMS_SERVLET) . $this->getSchema()->getManagedResourceId();
    }

    /**
     * @return void
     */
    protected function initializeStopWordsUrl()
    {
        if (trim($this->_stopWordsUrl ?? '') !== '') {
            return;
        }

        $this->_stopWordsUrl = $this->_constructUrl(self::STOPWORDS_SERVLET) . $this->getSchema()->getManagedResourceId();
    }
}
