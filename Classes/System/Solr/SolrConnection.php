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

namespace ApacheSolrForTypo3\Solr\System\Solr;

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SchemaParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\StopWordParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SynonymParser;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrReadService;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use ApacheSolrForTypo3\Solr\Util;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Solarium\Client;
use Solarium\Core\Client\Adapter\Psr18Adapter;
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
     * @var ClientInterface[]
     */
    protected $clients = [];

    /**
     * @var ClientInterface
     */
    protected $psr7Client;

    /**
     * @var RequestFactoryInterface
     */
    protected $requestFactory;

    /**
     * @var StreamFactoryInterface
     */
    protected $streamFactory;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * Constructor
     *
     * @param Node $readNode
     * @param Node $writeNode
     * @param ?TypoScriptConfiguration $configuration
     * @param ?SynonymParser $synonymParser
     * @param ?StopWordParser $stopWordParser
     * @param ?SchemaParser $schemaParser
     * @param ?SolrLogManager $logManager
     * @param ?ClientInterface $psr7Client
     * @param ?RequestFactoryInterface $requestFactory
     * @param ?StreamFactoryInterface $streamFactory
     * @param ?EventDispatcherInterface $eventDispatcher
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        Node $readNode,
        Node $writeNode,
        TypoScriptConfiguration $configuration = null,
        SynonymParser $synonymParser = null,
        StopWordParser $stopWordParser = null,
        SchemaParser $schemaParser = null,
        SolrLogManager $logManager = null,
        ClientInterface $psr7Client = null,
        RequestFactoryInterface $requestFactory = null,
        StreamFactoryInterface $streamFactory = null,
        EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->nodes['read'] = $readNode;
        $this->nodes['write'] = $writeNode;
        $this->nodes['admin'] = $writeNode;
        $this->configuration = $configuration ?? Util::getSolrConfiguration();
        $this->synonymParser = $synonymParser;
        $this->stopWordParser = $stopWordParser;
        $this->schemaParser = $schemaParser;
        $this->logger = $logManager;
        $this->psr7Client = $psr7Client ?? GeneralUtility::getContainer()->get(ClientInterface::class);
        $this->requestFactory = $requestFactory ?? GeneralUtility::getContainer()->get(RequestFactoryInterface::class);
        $this->streamFactory = $streamFactory ?? GeneralUtility::getContainer()->get(StreamFactoryInterface::class);
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::getContainer()->get(EventDispatcherInterface::class);
    }

    /**
     * @param string $key
     * @return Node
     */
    public function getNode(string $key): Node
    {
        return $this->nodes[$key];
    }

    /**
     * @return SolrAdminService
     */
    public function getAdminService(): SolrAdminService
    {
        if ($this->adminService === null) {
            $this->adminService = $this->buildAdminService();
        }

        return $this->adminService;
    }

    /**
     * @return SolrAdminService
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    protected function buildAdminService(): SolrAdminService
    {
        $endpointKey = 'admin';
        $client = $this->getClient($endpointKey);
        $this->initializeClient($client, $endpointKey);
        return GeneralUtility::makeInstance(SolrAdminService::class, $client, $this->configuration, $this->logger, $this->synonymParser, $this->stopWordParser, $this->schemaParser);
    }

    /**
     * @return SolrReadService
     */
    public function getReadService(): SolrReadService
    {
        if ($this->readService === null) {
            $this->readService = $this->buildReadService();
        }

        return $this->readService;
    }

    /**
     * @return SolrReadService
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    protected function buildReadService(): SolrReadService
    {
        $endpointKey = 'read';
        $client = $this->getClient($endpointKey);
        $this->initializeClient($client, $endpointKey);
        return GeneralUtility::makeInstance(SolrReadService::class, $client);
    }

    /**
     * @return SolrWriteService
     */
    public function getWriteService(): SolrWriteService
    {
        if ($this->writeService === null) {
            $this->writeService = $this->buildWriteService();
        }

        return $this->writeService;
    }

    /**
     * @return SolrWriteService
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    protected function buildWriteService(): SolrWriteService
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
    protected function initializeClient(Client $client, string $endpointKey): Client
    {
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
    protected function setAuthenticationOnAllEndpoints(Client $client, string $username, string $password)
    {
        foreach ($client->getEndpoints() as $endpoint) {
            $endpoint->setAuthentication($username, $password);
        }
    }

    /**
     * @param string $endpointKey
     * @return Client
     */
    protected function getClient(string $endpointKey): Client
    {
        if (isset($this->clients[$endpointKey])) {
            return $this->clients[$endpointKey];
        }

        $adapter = new Psr18Adapter($this->psr7Client, $this->requestFactory, $this->streamFactory);

        $client = new Client($adapter, $this->eventDispatcher);
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
     * @param ?string $endpointKey
     */
    public function setClient(Client $client, ?string $endpointKey = 'read')
    {
        $this->clients[$endpointKey] = $client;
    }
}
