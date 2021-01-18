<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Timo Hund
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\MountPagesUpdater;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Testcase for the RecordMonitor class.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RecordMonitorTest extends UnitTest
{

    /**
     * @var RecordMonitor
     */
    protected $recordMonitor;

    /**
     * @var Queue
     */
    protected $queueMock;

    /**
     * @var MountPagesUpdater
     */
    protected $mountPageUpdaterMock;

    /**
     * @var TCAService
     */
    protected $tcaServiceMock;

    /**
     * @var RootPageResolver
     */
    protected $rootPageResolverMock;

    /**
     * @var PagesRepository
     */
    protected $pageRepositoryMock;

    /**
     * @var SolrLogManager
     */
    protected $logManagerMock;

    /**
     * @var ConfigurationAwareRecordService
     */
    protected $recordServiceMock;

    public function setUp()
    {
        $this->queueMock = $this->getDumbMock(Queue::class);
        $this->mountPageUpdaterMock = $this->getDumbMock(MountPagesUpdater::class);
        $this->tcaServiceMock = $this->getDumbMock(TCAService::class);
        $this->rootPageResolverMock = $this->getDumbMock(RootPageResolver::class);
        $this->pageRepositoryMock = $this->getDumbMock(PagesRepository::class);
        $this->logManagerMock = $this->getDumbMock(SolrLogManager::class);
        $this->recordServiceMock = $this->getDumbMock(ConfigurationAwareRecordService::class);

        $this->recordMonitor = $this->getMockBuilder(RecordMonitor::class)
            ->setMethods(
                [
                     'isDraftRecord',
                     'getSolrConfigurationFromPageId',
                     'removeFromIndexAndQueueWhenItemInQueue',
                     'getRecordPageId'
                ]
            )->setConstructorArgs([
                $this->queueMock,
                $this->mountPageUpdaterMock,
                $this->tcaServiceMock,
                $this->rootPageResolverMock,
                $this->pageRepositoryMock,
                $this->logManagerMock,
                $this->recordServiceMock
            ])
            ->getMock();
    }

    /**
     * @test
     */
    public function processCmdmap_postProcessUpdatesQueueItemForVersionSwapOfEnabledPage()
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->recordMonitor->expects($this->once())->method('getSolrConfigurationFromPageId')->with(4711)->willReturn($configurationMock);
        $this->recordMonitor->expects($this->never())->method('removeFromIndexAndQueueWhenItemInQueue');

        $this->recordServiceMock->expects($this->once())->method('getRecord')->willReturn(['uid' => 4711, 'pid' => 999]);
        $this->tcaServiceMock->expects($this->once())->method('isEnabledRecord')->willReturn(true);


        $this->queueMock->expects($this->once())->method('updateItem')->with('pages', 4711);
        $this->recordMonitor->processCmdmap_postProcess('version', 'pages', 4711, ['action' => 'swap'], $dataHandlerMock);
    }

    /**
     * @test
     */
    public function processCmdmap_postProcessUpdatesQueueItemForVersionSwapOfEnabledRecord()
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);
        $dataHandlerMock->expects($this->once())->method('getPID')->willReturn(999);

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getIndexQueueIsMonitoredTable')->willReturn(true);

        $this->recordMonitor->expects($this->once())->method('getSolrConfigurationFromPageId')->with(999)->willReturn($configurationMock);
        $this->recordMonitor->expects($this->never())->method('removeFromIndexAndQueueWhenItemInQueue');

        $this->recordServiceMock->expects($this->once())->method('getRecord')->willReturn(['uid' => 888, 'pid' => 999]);

        $this->tcaServiceMock->expects($this->once())->method('getTranslationOriginalUidIfTranslated')->willReturn(888);
        $this->tcaServiceMock->expects($this->once())->method('isEnabledRecord')->willReturn(true);


        $this->queueMock->expects($this->once())->method('updateItem')->with('tx_foo_bar', 888);
        $this->recordMonitor->processCmdmap_postProcess('version', 'tx_foo_bar', 888, ['action' => 'swap'], $dataHandlerMock);
    }

    /**
     * @test
     */
    public function processCmdmap_postProcessRemovesQueueItemForVersionSwapOfDisabledPage()
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->recordMonitor->expects($this->once())->method('getSolrConfigurationFromPageId')->with(4711)->willReturn($configurationMock);

        $this->recordServiceMock->expects($this->once())->method('getRecord')->willReturn(['uid' => 4711, 'pid' => 999]);
        $this->tcaServiceMock->expects($this->once())->method('isEnabledRecord')->willReturn(false);

        $this->queueMock->expects($this->never())->method('updateItem');
        $this->recordMonitor->expects($this->once())->method('removeFromIndexAndQueueWhenItemInQueue')->with('pages', 4711);

        $this->recordMonitor->processCmdmap_postProcess('version', 'pages', 4711, ['action' => 'swap'], $dataHandlerMock);
    }

    /**
     * @test
     */
    public function processCmdmap_postProcessRemovesQueueItemForVersionSwapOfDisabledRecord()
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);
        $dataHandlerMock->expects($this->once())->method('getPID')->willReturn(999);

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getIndexQueueIsMonitoredTable')->willReturn(true);

        $this->recordMonitor->expects($this->once())->method('getSolrConfigurationFromPageId')->with(999)->willReturn($configurationMock);

        $this->recordServiceMock->expects($this->once())->method('getRecord')->willReturn(['uid' => 888, 'pid' => 999]);

        $this->tcaServiceMock->expects($this->once())->method('isEnabledRecord')->willReturn(false);

        $this->queueMock->expects($this->never())->method('updateItem');
        $this->recordMonitor->expects($this->once())->method('removeFromIndexAndQueueWhenItemInQueue')->with('tx_foo_bar', 888);
        $this->recordMonitor->processCmdmap_postProcess('version', 'tx_foo_bar', 888, ['action' => 'swap'], $dataHandlerMock);
    }

    /**
     * @test
     */
    public function processCmdmap_postProcessUpdatesQueueItemForMoveOfEnabledPage()
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->recordMonitor->expects($this->once())->method('getSolrConfigurationFromPageId')->with(4711)->willReturn($configurationMock);
        $this->recordMonitor->expects($this->never())->method('removeFromIndexAndQueueWhenItemInQueue');

        $this->recordServiceMock->expects($this->once())->method('getRecord')->willReturn(['uid' => 4711, 'pid' => 999]);
        $this->tcaServiceMock->expects($this->once())->method('isEnabledRecord')->willReturn(true);

        $this->queueMock->expects($this->once())->method('updateItem')->with('pages', 4711);
        $this->recordMonitor->processCmdmap_postProcess('move', 'pages', 4711, [], $dataHandlerMock);
    }

    /**
     * @test
     */
    public function processCmdmap_postProcessRemovesQueueItemForMoveOfDisabledPage()
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->recordMonitor->expects($this->once())->method('getSolrConfigurationFromPageId')->with(4711)->willReturn($configurationMock);

        $this->recordServiceMock->expects($this->once())->method('getRecord')->willReturn(['uid' => 4711, 'pid' => 999]);
        $this->tcaServiceMock->expects($this->once())->method('isEnabledRecord')->willReturn(false);

        $this->queueMock->expects($this->never())->method('updateItem');
        $this->recordMonitor->expects($this->once())->method('removeFromIndexAndQueueWhenItemInQueue')->with('pages', 4711);

        $this->recordMonitor->processCmdmap_postProcess('move', 'pages', 4711, [], $dataHandlerMock);
    }

    /**
     * @test
     * For more infos, please refer https://github.com/TYPO3-Solr/ext-solr/pull/2836
     */
    public function processDatamap_afterDatabaseOperationsUsesAlreadyResolvedNextAutoIncrementValueForNewStatus()
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);
        $this->rootPageResolverMock->expects($this->once())->method('getAlternativeSiteRootPagesIds')->willReturn([]);

        $this->recordMonitor->expects($this->once())
            ->method('getRecordPageId')
            ->with('new', 'tt_content', 4711, 4711, ['pid' => 1], $dataHandlerMock);
        $this->recordMonitor->processDatamap_afterDatabaseOperations('new', 'tt_content', 4711, ['pid' => 1], $dataHandlerMock);
    }

}
