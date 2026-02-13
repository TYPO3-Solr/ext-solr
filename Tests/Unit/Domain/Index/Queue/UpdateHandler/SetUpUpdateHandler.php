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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Queue\UpdateHandler;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract testcase for the update handlers
 */
abstract class SetUpUpdateHandler extends SetUpUnitTestCase
{
    protected ConfigurationAwareRecordService|MockObject $recordServiceMock;
    protected FrontendEnvironment|MockObject $frontendEnvironmentMock;
    protected TypoScriptConfiguration|MockObject $typoScriptConfigurationMock;
    protected TCAService|MockObject $tcaServiceMock;
    protected Queue|MockObject $indexQueueMock;
    protected PagesRepository|MockObject $pagesRepositoryMock;
    protected SolrLogManager|MockObject $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recordServiceMock = $this->createMock(ConfigurationAwareRecordService::class);
        $this->frontendEnvironmentMock = $this->createMock(FrontendEnvironment::class);
        $this->tcaServiceMock = $this->createMock(TCAService::class);
        $this->indexQueueMock = $this->createMock(Queue::class);
        $this->pagesRepositoryMock = $this->createMock(PagesRepository::class);
        GeneralUtility::addInstance(PagesRepository::class, $this->pagesRepositoryMock);
        $this->loggerMock = $this->createMock(SolrLogManager::class);

        $this->typoScriptConfigurationMock = $this->createMock(TypoScriptConfiguration::class);
        $this->frontendEnvironmentMock
            ->expects(self::any())
            ->method('getSolrConfigurationFromPageId')
            ->willReturn($this->typoScriptConfigurationMock);

        // Note: GarbageHandler mock is NOT pre-registered here because some tests need
        // specific expectations on it. Tests that call code using GarbageHandler should
        // register their own mock via GeneralUtility::addInstance(GarbageHandler::class, $mock)
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        unset($GLOBALS['TCA']);
        parent::tearDown();
    }
}
