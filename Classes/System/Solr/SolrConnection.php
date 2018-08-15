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
use ApacheSolrForTypo3\Solr\System\Solr\Service\AbstractSolrService;
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
     * @var string
     */
    protected $host = '';

    /**
     * @var string
     */
    protected $port = '8983';

    /**
     * @var string
     */
    protected $path = '/solr/';

    /**
     * @var string
     */
    protected $core = '';

    /**
     * @var string
     */
    protected $scheme = 'http';

    /**
     * @var string
     */
    protected $username = '';

    /**
     * @var string
     */
    protected $password = '';

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
     * @param string $host Solr host
     * @param string $port Solr port
     * @param string $path Solr path
     * @param string $scheme Scheme, defaults to http, can be https
     * @param string $username the username that should be used to authenticate
     * @param string $password the password that should be used to authenticate
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
        $username = '',
        $password = '',
        TypoScriptConfiguration $typoScriptConfiguration = null,
        SynonymParser $synonymParser = null,
        StopWordParser $stopWordParser = null,
        SchemaParser $schemaParser = null,
        SolrLogManager $logManager = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->path = $this->getCoreBasePath($path);
        $this->core = $this->getCoreName($path);

        $this->scheme = $scheme;
        $this->username = $username;
        $this->password = $password;
        $this->configuration = $typoScriptConfiguration ?? Util::getSolrConfiguration();
        $this->synonymParser = $synonymParser;
        $this->stopWordParser = $stopWordParser;
        $this->schemaParser = $schemaParser;
        $this->logger = $logManager;
    }

    /**
     * Returns the core name from the configured path without the core name.
     *
     * @return string
     */
    protected function getCoreBasePath($path)
    {
        $pathWithoutLeadingAndTrailingSlashes = trim(trim($path), "/");
        $pathWithoutLastSegment = substr($pathWithoutLeadingAndTrailingSlashes, 0, strrpos($pathWithoutLeadingAndTrailingSlashes, "/"));
        return '/' . $pathWithoutLastSegment . '/';
    }

    /**
     * Returns the core name from the configured path.
     *
     * @return string
     */
    protected function getCoreName($path)
    {
        $paths = explode('/', trim($path, '/'));
        return (string)array_pop($paths);
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
        $client = $this->getClient('admin');
        $this->initializeClient($client);
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
        $client = $this->getClient('read');
        $this->initializeClient($client);
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
        $this->initializeClient($client);
        return GeneralUtility::makeInstance(SolrWriteService::class, $client);
    }

    /**
     * @param Client $client
     * @return Client
     */
    protected function initializeClient(Client $client) {
        if (trim($this->username) === '') {
            return $client;
        }

        $this->setAuthenticationOnAllEndpoints($client);

        return $client;
    }

    /**
     * @param Client $client
     */
    protected function setAuthenticationOnAllEndpoints(Client $client)
    {
        foreach ($client->getEndpoints() as $endpoint) {
            $endpoint->setAuthentication($this->username, $this->password);
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
        return $this->scheme . '://' . $this->host . ':' . $this->port . $this->path . $this->core . '/';
    }

    /**
     * @param string $key
     * @param int $timeout
     * @return array
     */
    protected function getSolrClientOptions($key = 'read', $timeout = 5):array
    {
        return ['host' => $this->host, 'port' => $this->port, 'scheme' => $this->scheme, 'path' => $this->path, 'core' => $this->core, 'key' => $key, 'timeout' => $timeout];
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

        $client->clearEndpoints();

        $this->checkIfRequiredPropertyIsSet($this->scheme, 'scheme');
        $this->checkIfRequiredPropertyIsSet($this->host, 'host');
        $this->checkIfRequiredPropertyIsSet($this->port, 'port');
        $this->checkIfRequiredPropertyIsSet($this->path, 'path');
        $this->checkIfRequiredPropertyIsSet($this->core, 'core');

        $newEndpointOptions = $this->getSolrClientOptions($endpointKey, $this->configuration->getSolrTimeout());
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

    /**
     * @param mixed $property
     * @param string $name
     * @throws |UnexpectedValueException
     */
    protected function checkIfRequiredPropertyIsSet($property, $name)
    {
        if (empty($property)) {
            throw new \UnexpectedValueException('Required solr connection property ' . $name. ' is missing.');
        }
    }

}
