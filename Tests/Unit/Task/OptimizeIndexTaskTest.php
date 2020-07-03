<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Task;

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

use ApacheSolrForTypo3\Solr\Task\OptimizeIndexTask;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase for OptimizeIndexTask
 *
 * @author Jens Jacobsen <typo3@jens-jacobsen.de>
 */
class OptimizeIndexTaskTest extends UnitTest
{
    /**
     * @test
     */
    public function canGetErrorMessageInAdditionalInformationWhenSiteNotAvailable()
    {
            /** @var $indexQueuerWorker OptimizeIndexTask */
        $indexQueuerWorker = $this->getMockBuilder(OptimizeIndexTask::class)
            ->disableOriginalConstructor()
            ->setMethods(['getSite'])
            ->getMock();

        $message = $indexQueuerWorker->getAdditionalInformation();
        $expectedMessage = 'Invalid site configuration for scheduler please re-create the task!';
        $this->assertSame($expectedMessage, $message, 'Expect to get error message of non existing site');
    }
}
