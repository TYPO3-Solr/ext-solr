<?php
namespace ApacheSolrForTypo3\Solr\System\Solr;

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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\UnifiedConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SchemaParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\StopWordParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SynonymParser;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrReadService;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use ApacheSolrForTypo3\Solr\Util;
use Solarium\Client;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Solr Service Access
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @copyright (c) 2009-2021 Ingo Renner <ingo@typo3.org>
 */
class SolrConnection
{
    /**
     * @var SolrAdminService
     */
    protected $adminService;

    /**
     * @var SolrReadService
     */
    protected $readService;

    /**
     * @var SolrWriteService
     */
    protected $writeService;

    /**
     * @var UnifiedConfiguration
     */
    protected $unifiedConfiguration;

    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * @var SynonymParser
     */
    protected $synonymParser = null;

    /**
     * @var StopWordParser
     */
    protected $stopWordParser = null;

    /**
     * @var SchemaParser
     */
    protected $schemaParser = null;

    /**
     * @var Node[]
     */
    protected $nodes = [];

    /**
     * @var SolrLogManager
     */
    protected $logger = null;

    /**
     * @var Client[]
     */
    protected $clients = [];

    /**
     * Constructor
     *
     * @param Node $readNode,
     * @param Node $writeNode
     * @param UnifiedConfiguration $configuration
     * @param SynonymParser $synonymParser
     * @param StopWordParser $stopWordParser
     * @param SchemaParser $schemaParser
     * @param SolrLogManager $logManager
     */
    public function __construct(
        Node $readNode,
        Node $writeNode,
        UnifiedConfiguration $configuration = null,
        SynonymParser $synonymParser = null,
        StopWordParser $stopWordParser = null,
        SchemaParser $schemaParser = null,
        SolrLogManager $logManager = null
    ) {
        $this->nodes['read'] = $readNode;
        $this->nodes['write'] = $writeNode;
        $this->nodes['admin'] = $writeNode;
        $this->unifiedConfiguration = $configuration ?? Util::getUnifiedConfiguration();
        $this->configuration = $this->unifiedConfiguration->getConfigurationByClass(TypoScriptConfiguration::class);
        $this->synonymParser = $synonymParser;
        $this->stopWordParser = $stopWordParser;
        $this->schemaParser = $schemaParser;
        $this->logger = $logManager;
    }

    /**
     * @param string $key
     * @return Node
     */
    public function getNode($key)
    {
        return $this->nodes[$key];
    }

    /**
     * @return SolrAdminService
     */
    public function getAdminService()
    {
        if ($this->adminService === null) {
            $this->adminService = $this->buildAdminService();
        }

        return $this->adminService;
    }

    /**
     * @return SolrAdminService
     */
    protected function buildAdminService()
    {
        $endpointKey = 'admin';
        $client = $this->getClient($endpointKey);
        $this->initializeClient($client, $endpointKey);
        return GeneralUtility::makeInstance(
            SolrAdminService::class,
            $client,
            $this->configuration,
            $this->logger,
            $this->synonymParser,
            $this->stopWordParser,
            $this->schemaParser
        );
    }

    /**
     * @return SolrReadService
     */
    public function getReadService()
    {
        if ($this->readService === null) {
            $this->readService = $this->buildReadService();
        }

        return $this->readService;
    }

    /**
     * @return SolrReadService
     */
    protected function buildReadService()
    {
        $endpointKey = 'read';
        $client = $this->getClient($endpointKey);
        $this->initializeClient($client, $endpointKey);
        return GeneralUtility::makeInstance(SolrReadService::class, $client);
    }

    /**
     * @return SolrWriteService
     */
    public function getWriteService()
    {
        if ($this->writeService === null) {
            $this->writeService = $this->buildWriteService();
        }

        return $this->writeService;
    }

    /**
     * @return SolrWriteService
     */
    protected function buildWriteService()
    {
        $endpointKey = 'write';
        $client = $this->getClient($endpointKey);
        $this->initializeClient($client, $endpointKey);
        return GeneralUtility::makeInstance(SolrWriteService::class, $client);
    }

    /**
     * @param Client $client
     * @param string $endpointKey
     * @return Client
     */
    protected function initializeClient(Client $client, $endpointKey) {
        if (trim($this->getNode($endpointKey)->getUsername()) === '') {
            return $client;
        }

        $username = $this->getNode($endpointKey)->getUsername();
        $password = $this->getNode($endpointKey)->getPassword();
        $this->setAuthenticationOnAllEndpoints($client, $username, $password);

        return $client;
    }

    /**
     * @param Client $client
     * @param string $username
     * @param string $password
     */
    protected function setAuthenticationOnAllEndpoints(Client $client, $username, $password)
    {
        foreach ($client->getEndpoints() as $endpoint) {
            $endpoint->setAuthentication($username, $password);
        }
    }

    /**
     * @param string $endpointKey
     * @return Client
     */
    protected function getClient($endpointKey): Client
    {
        if($this->clients[$endpointKey]) {
            return $this->clients[$endpointKey];
        }

        $client = new Client(['adapter' => 'Solarium\Core\Client\Adapter\Guzzle']);
        $client->getPlugin('postbigrequest');
        $client->clearEndpoints();

        $newEndpointOptions = $this->getNode($endpointKey)->getSolariumClientOptions();
        $newEndpointOptions['key'] = $endpointKey;
        $client->createEndpoint($newEndpointOptions, true);

        $this->clients[$endpointKey] = $client;
        return $client;
    }

    /**
     * @param Client $client
     * @param string $endpointKey
     */
    public function setClient(Client $client, $endpointKey = 'read')
    {
        $this->clients[$endpointKey] = $client;
    }
}
