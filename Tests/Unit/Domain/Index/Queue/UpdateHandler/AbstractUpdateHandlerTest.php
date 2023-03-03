<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2022 Markus Friedrich <markus.friedrich@dkd.de>
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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Queue\UpdateHandler;

use PHPUnit\Framework\MockObject\MockObject;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

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

    protected function setUp(): void
    {
        $this->recordServiceMock = $this->createMock(ConfigurationAwareRecordService::class);
        $this->frontendEnvironmentMock = $this->createMock(FrontendEnvironment::class);
        $this->tcaServiceMock = $this->createMock(TCAService::class);
        $this->indexQueueMock = $this->createMock(Queue::class);

        $this->typoScriptConfigurationMock = $this->createMock(TypoScriptConfiguration::class);
        $this->frontendEnvironmentMock
            ->expects($this->any())
            ->method('getSolrConfigurationFromPageId')
            ->willReturn($this->typoScriptConfigurationMock);

        $this->queryGeneratorMock = $this->createMock(QueryGenerator::class);

        GeneralUtility::addInstance(QueryGenerator::class, $this->queryGeneratorMock);
    }

    public function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        unset($GLOBALS['TCA']);
    }
}
