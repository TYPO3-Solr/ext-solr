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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Task;

use ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Core\Environment;

/**
 * Testcase for IndexQueueWorkerTask
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class IndexQueueWorkerTaskTest extends UnitTest
{

    /**
     * @test
     */
    public function canGetWebRoot()
    {
        /** @var $indexQueuerWorker IndexQueueWorkerTask */
        $indexQueuerWorker = $this->getMockBuilder(IndexQueueWorkerTask::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute'])
            ->getMock();

        // by default the webroot should be Environment::getPublicPath()
        self::assertSame(Environment::getPublicPath() . '/', $indexQueuerWorker->getWebRoot(), 'Not using PATH_site as webroot');

        // can we overwrite it?
        $indexQueuerWorker->setForcedWebRoot('/var/www/foobar.de/subdir');
        self::assertSame('/var/www/foobar.de/subdir', $indexQueuerWorker->getWebRoot(), 'Can not force a webroot');

        // can we use a marker?
        $indexQueuerWorker->setForcedWebRoot('###PATH_site###../test/');
        self::assertSame(Environment::getPublicPath() . '/../test/', $indexQueuerWorker->getWebRoot(), 'Could not use a marker in forced webroot');
    }

    /**
     * @test
     */
    public function canGetErrorMessageInAdditionalInformationWhenSiteNotAvailable()
    {
        /** @var $indexQueuerWorker IndexQueueWorkerTask */
        $indexQueuerWorker = $this->getMockBuilder(IndexQueueWorkerTask::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSite'])
            ->getMock();

        $mesage = $indexQueuerWorker->getAdditionalInformation();
        $expectedMessage = 'Invalid site configuration for scheduler please re-create the task!';
        self::assertSame($expectedMessage, $mesage, 'Expect to get error message of non existing site');
    }
}
