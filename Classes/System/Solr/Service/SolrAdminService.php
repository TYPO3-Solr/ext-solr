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

use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SchemaParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\StopWordParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SynonymParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\VectorStoreParser;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\Schema\Schema;
use Solarium\Client;
use stdClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function simplexml_load_string;

/**
 * Class SolrAdminService
 */
class SolrAdminService extends AbstractSolrService
{
    public const PLUGINS_SERVLET = 'admin/plugins';
    public const LUKE_SERVLET = 'admin/luke';
    public const SYSTEM_SERVLET = 'admin/system';
    public const CORES_SERVLET = '../admin/cores';
    public const FILE_SERVLET = 'admin/file';
    public const SCHEMA_SERVLET = 'schema';
    public const SYNONYMS_SERVLET = 'schema/analysis/synonyms/';
    public const STOPWORDS_SERVLET = 'schema/analysis/stopwords/';
    public const VECTOR_STORAGE_SERVLET = 'schema/text-to-vector-model-store/';

    protected array $lukeData = [];

    protected ?ResponseAdapter $systemData = null;

    protected ?ResponseAdapter $pluginsData = null;

    protected ?string $solrconfigName = null;

    protected SchemaParser $schemaParser;

    protected ?Schema $schema = null;

    protected string $_synonymsUrl = '';

    protected string $_stopWordsUrl = '';

    protected string $_vectorStoreUrl = '';

    protected SynonymParser $synonymParser;

    protected StopWordParser $stopWordParser;

    protected VectorStoreParser $vectorStoreParser;

    public function __construct(
        Client $client,
        ?TypoScriptConfiguration $typoScriptConfiguration = null,
        ?SolrLogManager $logManager = null,
        ?SynonymParser $synonymParser = null,
        ?StopWordParser $stopWordParser = null,
        ?SchemaParser $schemaParser = null,
        ?VectorStoreParser $vectorStoreParser = null,
    ) {
        parent::__construct($client, $typoScriptConfiguration, $logManager);

        $this->synonymParser = $synonymParser ?? GeneralUtility::makeInstance(SynonymParser::class);
        $this->stopWordParser = $stopWordParser ?? GeneralUtility::makeInstance(StopWordParser::class);
        $this->schemaParser = $schemaParser ?? GeneralUtility::makeInstance(SchemaParser::class);
        $this->vectorStoreParser = $vectorStoreParser ?? GeneralUtility::makeInstance(VectorStoreParser::class);
    }

    /**
     * Call the /admin/system servlet and retrieve system information about Solr
     */
    public function system(): ResponseAdapter
    {
        return $this->_sendRawGet($this->_constructUrl(self::SYSTEM_SERVLET, ['wt' => 'json']));
    }

    /**
     * Gets information about the plugins installed in Solr
     *
     * @return ResponseAdapter|null A nested array of plugin data.
     */
    public function getPluginsInformation(): ?ResponseAdapter
    {
        if (count($this->pluginsData ?? []) === 0) {
            $url = $this->_constructUrl(self::PLUGINS_SERVLET, ['wt' => 'json']);
            $pluginsInformation = $this->_sendRawGet($url);

            /**
             * access a random property to trigger response parsing
             * @phpstan-ignore-next-line
             */
            $pluginsInformation->responseHeader;
            $this->pluginsData = $pluginsInformation;
        }

        return $this->pluginsData;
    }

    /**
     * get field meta data for the index
     *
     * @param int $numberOfTerms Number of top terms to fetch for each field
     */
    public function getFieldsMetaData(int $numberOfTerms = 0): stdClass
    {
        return $this->getLukeMetaData($numberOfTerms)->fields;
    }

    /**
     * Retrieves metadata about the index from the luke request handler
     *
     * @param int $numberOfTerms Number of top terms to fetch for each field
     * @return ResponseAdapter Index meta data
     */
    public function getLukeMetaData(int $numberOfTerms = 0): ResponseAdapter
    {
        if (!isset($this->lukeData[$numberOfTerms])) {
            $lukeUrl = $this->_constructUrl(
                self::LUKE_SERVLET,
                ['numTerms' => $numberOfTerms, 'wt' => 'json', 'fl' => '*']
            );

            $this->lukeData[$numberOfTerms] = $this->_sendRawGet($lukeUrl);
        }

        return $this->lukeData[$numberOfTerms];
    }

    /**
     * Gets information about the Solr server
     */
    public function getSystemInformation(): ResponseAdapter
    {
        if (empty($this->systemData)) {
            $systemInformation = $this->system();

            /**
             * access a random property to trigger response parsing
             * @phpstan-ignore-next-line
             */
            $systemInformation->responseHeader;
            $this->systemData = $systemInformation;
        }

        return $this->systemData;
    }

    /**
     * Gets the name of the solrconfig.xml file installed and in use on the Solr
     * server.
     *
     * @return string|null Name of the active solrconfig.xml
     */
    public function getSolrconfigName(): ?string
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
     */
    public function reloadCore(): ResponseAdapter
    {
        return $this->reloadCoreByName($this->getPrimaryEndpoint()->getCore());
    }

    /**
     * Reloads a core of the connection by a given core-name.
     */
    public function reloadCoreByName(string $coreName): ResponseAdapter
    {
        $coreAdminReloadUrl = $this->_constructUrl(self::CORES_SERVLET) . '?action=reload&core=' . $coreName;
        return $this->_sendRawGet($coreAdminReloadUrl);
    }

    /**
     * Get the configured schema for the current core.
     */
    public function getSchema(): Schema
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
     */
    public function getSynonyms(string $baseWord = ''): array
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
     */
    public function addSynonym(string $baseWord, array $synonyms): ResponseAdapter
    {
        $this->initializeSynonymsUrl();
        $json = $this->synonymParser->toJson($baseWord, $synonyms);
        return $this->_sendRawPost($this->_synonymsUrl, $json, 'application/json');
    }

    /**
     * Remove a synonym from the synonyms map
     */
    public function deleteSynonym(string $baseWord): ResponseAdapter
    {
        $this->initializeSynonymsUrl();
        return $this->_sendRawDelete($this->_synonymsUrl . '/' . rawurlencode(rawurlencode($baseWord)));
    }

    /**
     * Get currently configured stop words
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
     *
     * @throws InvalidArgumentException If $stopWords is empty
     */
    public function addStopWords(array|string $stopWords): ResponseAdapter
    {
        $this->initializeStopWordsUrl();
        $json = $this->stopWordParser->toJson($stopWords);
        return $this->_sendRawPost($this->_stopWordsUrl, $json, 'application/json');
    }

    /**
     * Deletes a words from the managed stop word list
     *
     * @throws InvalidArgumentException If $stopWords is empty
     */
    public function deleteStopWord(string $stopWordToDelete): ResponseAdapter
    {
        $this->initializeStopWordsUrl();
        if (empty($stopWordToDelete)) {
            throw new InvalidArgumentException('Must provide stop word.');
        }

        return $this->_sendRawDelete($this->_stopWordsUrl . '/' . rawurlencode(rawurlencode($stopWordToDelete)));
    }

    protected function initializeSynonymsUrl(): void
    {
        if (trim($this->_synonymsUrl ?? '') !== '') {
            return;
        }
        $this->_synonymsUrl = $this->_constructUrl(self::SYNONYMS_SERVLET) . $this->getSchema()->getManagedResourceId();
    }

    protected function initializeStopWordsUrl(): void
    {
        if (trim($this->_stopWordsUrl ?? '') !== '') {
            return;
        }

        $this->_stopWordsUrl = $this->_constructUrl(self::STOPWORDS_SERVLET) . $this->getSchema()->getManagedResourceId();
    }

    public function getVectorModelStore(): array
    {
        $this->initializeVectorStorageUrl();
        $response = $this->_sendRawGet($this->_vectorStoreUrl);
        return $this->vectorStoreParser->parseJson($response->getRawResponse());
    }

    protected function initializeVectorStorageUrl(): void
    {
        if (trim($this->_vectorStoreUrl ?? '') !== '') {
            return;
        }

        $this->_vectorStoreUrl = $this->_constructUrl(self::VECTOR_STORAGE_SERVLET) . $this->getSchema()->getManagedResourceId();
    }
}
