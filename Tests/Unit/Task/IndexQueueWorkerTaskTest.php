<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
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
            ->setMethods(['execute'])
            ->getMock();

            // by default the webroot should be PATH_site
        $this->assertSame(Environment::getPublicPath() . '/', $indexQueuerWorker->getWebRoot(), 'Not using PATH_site as webroot');

            // can we overwrite it?
        $indexQueuerWorker->setForcedWebRoot('/var/www/foobar.de/subdir');
        $this->assertSame('/var/www/foobar.de/subdir', $indexQueuerWorker->getWebRoot(), 'Can not force a webroot');

            // can we use a marker?
        $indexQueuerWorker->setForcedWebRoot('###PATH_site###../test/');
        $this->assertSame(Environment::getPublicPath() . '/../test/', $indexQueuerWorker->getWebRoot(), 'Could not use a marker in forced webroot');
    }

    /**
     * @test
     */
    public function canGetErrorMessageInAdditionalInformationWhenSiteNotAvailable()
    {
            /** @var $indexQueuerWorker IndexQueueWorkerTask */
        $indexQueuerWorker = $this->getMockBuilder(IndexQueueWorkerTask::class)
            ->disableOriginalConstructor()
            ->setMethods(['getSite'])
            ->getMock();

        $mesage = $indexQueuerWorker->getAdditionalInformation();
        $expectedMessage = 'Invalid site configuration for scheduler please re-create the task!';
        $this->assertSame($expectedMessage, $mesage, 'Expect to get error message of non existing site');
    }
}
