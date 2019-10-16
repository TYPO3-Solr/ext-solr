<?php
namespace ApacheSolrForTypo3\Solr\System\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrReadService;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use ApacheSolrForTypo3\Solr\Util;
use Solarium\Client;
use Solarium\Core\Client\Endpoint;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Solr Service Access
 *
 * @author Ingo Renner <ingo@typo3.org>
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
     * @var array
     */
    protected $clients = [];

    /**
     * Constructor
     *
     * @param Node $readNode,
     * @param Node $writeNode
     * @param TypoScriptConfiguration $configuration
     * @param SynonymParser $synonymParser
     * @param StopWordParser $stopWordParser
     * @param SchemaParser $schemaParser
     * @param SolrLogManager $logManager
     */
    public function __construct(
        Node $readNode,
        Node $writeNode,
        TypoScriptConfiguration $configuration = null,
        SynonymParser $synonymParser = null,
        StopWordParser $stopWordParser = null,
        SchemaParser $schemaParser = null,
        SolrLogManager $logManager = null
    ) {
        $this->nodes['read'] = $readNode;
        $this->nodes['write'] = $writeNode;
        $this->nodes['admin'] = $writeNode;
        $this->configuration = $configuration ?? Util::getSolrConfiguration();
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
        return GeneralUtility::makeInstance(SolrAdminService::class, $client, $this->configuration, $this->logger, $this->synonymParser, $this->stopWordParser, $this->schemaParser);
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
