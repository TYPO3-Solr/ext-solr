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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\UpdateHandler;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract testcase for the update handlers
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
abstract class AbstractUpdateHandlerTest extends UnitTest
{
    /**
     * @var ConfigurationAwareRecordService|MockObject
     */
    protected $recordServiceMock;

    /**
     * @var FrontendEnvironment|MockObject
     */
    protected $frontendEnvironmentMock;

    /**
     * @var TypoScriptConfiguration|MockObject
     */
    protected $typoScriptConfigurationMock;

    /**
     * @var TCAService|MockObject
     */
    protected $tcaServiceMock;

    /**
     * @var Queue|MockObject
     */
    protected $indexQueueMock;

    /**
     * @var QueryGenerator|MockObject
     */
    protected $queryGeneratorMock;

    /**
     * @var PagesRepository|MockObject
     */
    protected $pagesRepositoryMock;

    protected function setUp(): void
    {
        $this->recordServiceMock = $this->createMock(ConfigurationAwareRecordService::class);
        $this->frontendEnvironmentMock = $this->createMock(FrontendEnvironment::class);
        $this->tcaServiceMock = $this->createMock(TCAService::class);
        $this->indexQueueMock = $this->createMock(Queue::class);
        $this->pagesRepositoryMock = $this->createMock(PagesRepository::class);

        $this->typoScriptConfigurationMock = $this->createMock(TypoScriptConfiguration::class);
        $this->frontendEnvironmentMock
            ->expects(self::any())
            ->method('getSolrConfigurationFromPageId')
            ->willReturn($this->typoScriptConfigurationMock);

        $this->queryGeneratorMock = $this->createMock(QueryGenerator::class);

        GeneralUtility::addInstance(QueryGenerator::class, $this->queryGeneratorMock);
        parent::setUp();
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        unset($GLOBALS['TCA']);
        parent::tearDown();
    }
}
