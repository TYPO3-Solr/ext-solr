<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue;

use ApacheSolrForTypo3\Solr\Tests\Integration\Task\IndexQueueDependencyFaker;

/**
 * This function is used to overwrite uniqid in the IndexQueue context to provide a fake request id.
 * We use this since this is a integration test and the unique id could not be injected from outside.
 *
 * @return string
 */
function uniqid()
{
    return IndexQueueDependencyFaker::getRequestId();
}

/**
 * This function fakes the file_get_contents calls to provide a faked frontend indexing response.
 *
 * @param string $url
 * @param bool $flags
 * @param resource $context
 *
 * @return string
 */
function file_get_contents($url, $flags, $context)
{
    return IndexQueueDependencyFaker::getHttpContent($url, $flags,
        $context);
}

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;

/**
 * TestCase to check if we can indexer from a index queue worker task into a solr server
 *
 * @author Timo Schmidt
 */
class IndexQueueWorkerTest extends IntegrationTest
{
    /**
     * @var Queue
     */
    protected $indexQueue;

    /**
     * @var array
     */
    protected $coreExtensionsToLoad = [
        'extensionmanager',
        'scheduler'
    ];

    public function setUp()
    {
        parent::setUp();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
    }

    /**
     * @test
     */
    public function canTriggerFrontendIndexingAndMarkQueueEntryAsProcessed()
    {
        $this->importDataSetFromFixture('can_trigger_frontend_calls_for_page_index.xml');

        // we expect that the index queue is empty before we start
        $this->assertFalse($this->indexQueue->containsIndexedItem('pages', 1));

        /** @var $beUser  \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $beUser;

        /** @var $languageService  \TYPO3\CMS\Lang\LanguageService */
        $languageService = GeneralUtility::makeInstance(LanguageService::class);
        $GLOBALS['LANG'] = $languageService;

        $charsetConverter = GeneralUtility::makeInstance(CharsetConverter::class);
        $GLOBALS['LANG']->csConvObj = $charsetConverter;

        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        /** @var $indexQueueQueueWorkerTask \ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask */
        $indexQueueQueueWorkerTask = GeneralUtility::makeInstance(IndexQueueWorkerTask::class);
        $indexQueueQueueWorkerTask->setDocumentsToIndexLimit(1);
        $indexQueueQueueWorkerTask->setRootPageId($site->getRootPageId());

        $progressBefore = $indexQueueQueueWorkerTask->getProgress();
        $indexQueueQueueWorkerTask->execute();
        $progressAfter = $indexQueueQueueWorkerTask->getProgress();

        // we expect that the index queue is empty before we start
        $this->assertTrue($this->indexQueue->containsIndexedItem('pages', 1));
        $this->assertEquals(0.0, $progressBefore, 'Wrong progress before');
        $this->assertEquals(100.0, $progressAfter, 'Wrong progress after');
    }

    /**
     * @test
     */
    public function canGetAdditionalInformationFromTask()
    {
        $this->importDataSetFromFixture('can_trigger_frontend_calls_for_page_index.xml');
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        /** @var $indexQueueQueueWorkerTask \ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask */
        $indexQueueQueueWorkerTask = GeneralUtility::makeInstance(IndexQueueWorkerTask::class);
        $indexQueueQueueWorkerTask->setDocumentsToIndexLimit(1);
        $indexQueueQueueWorkerTask->setRootPageId($site->getRootPageId());

        $additionalInformation = $indexQueueQueueWorkerTask->getAdditionalInformation();

        $this->assertContains('Root Page ID: 1', $additionalInformation);
        $this->assertContains('Site: page for testing', $additionalInformation);
    }
}
