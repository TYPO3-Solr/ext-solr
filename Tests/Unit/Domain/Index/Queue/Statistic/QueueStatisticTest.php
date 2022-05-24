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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Queue\Statistic;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\Statistic\QueueStatistic;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class QueueStatisticTest extends UnitTest
{
    /**
     * @test
     */
    public function canGetFailedPercentage()
    {
        /** @var $statistic QueueStatistic */
        $statistic = GeneralUtility::makeInstance(QueueStatistic::class);
        $statistic->setFailedCount(2);
        $statistic->setSuccessCount(1);
        $statistic->setPendingCount(1);

        self::assertSame(50.0, $statistic->getFailedPercentage(), 'Can not calculate failed percentage');
        self::assertSame(25.0, $statistic->getSuccessPercentage(), 'Can not calculate success percentage');
        self::assertSame(25.0, $statistic->getPendingPercentage(), 'Can not calculate pending percentage');
    }

    /**
     * @test
     */
    public function canGetZeroPercentagesWhenEmpty()
    {
        /** @var $statistic QueueStatistic */
        $statistic = GeneralUtility::makeInstance(QueueStatistic::class);
        self::assertSame(0.0, $statistic->getFailedPercentage(), 'Can not zero percent for empty');
    }
}
