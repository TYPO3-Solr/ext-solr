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
        $this->path = $path;
        $this->scheme = $scheme;
        $this->username = $username;
        $this->password = $password;
        $this->configuration = $typoScriptConfiguration;
        $this->synonymParser = $synonymParser;
        $this->stopWordParser = $stopWordParser;
        $this->schemaParser = $schemaParser;
        $this->logger = $logManager;
    }

    /**
     * @return SolrAdminService
     */
    public function getAdminService()
    {
        if ($this->adminService === null) {
            $this->adminService = $this->buildAdminService();
        }

        return $this->initializeService($this->adminService);
    }

    /**
     * @return SolrAdminService
     */
    protected function buildAdminService()
    {
        return GeneralUtility::makeInstance(SolrAdminService::class, $this->host, $this->port, $this->path, $this->scheme, $this->configuration, $this->logger, $this->synonymParser, $this->stopWordParser, $this->schemaParser);
    }

    /**
     * @return SolrReadService
     */
    public function getReadService()
    {
        if ($this->readService === null) {
            $this->readService = $this->buildReadService();
        }

        return $this->initializeService($this->readService);
    }

    /**
     * @return SolrReadService
     */
    protected function buildReadService()
    {
        return GeneralUtility::makeInstance(SolrReadService::class, $this->host, $this->port, $this->path, $this->scheme, $this->configuration, $this->logger);
    }

    /**
     * @return SolrWriteService
     */
    public function getWriteService()
    {
        if ($this->writeService === null) {
            $this->writeService = $this->buildWriteService();
        }

        return $this->initializeService($this->writeService);
    }

    /**
     * @return SolrWriteService
     */
    protected function buildWriteService()
    {
        return GeneralUtility::makeInstance(SolrWriteService::class, $this->host, $this->port, $this->path, $this->scheme, $this->configuration, $this->logger);
    }

    /**
     * @param AbstractSolrService $service
     * @return AbstractSolrService
     */
    protected function initializeService(AbstractSolrService $service) {
        if (trim($this->username) !== '') {
            $service->setAuthenticationCredentials($this->username, $this->password);
        }

        return $service;
    }
}
