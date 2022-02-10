<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange\DateRange;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange\DateRangeFacet;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use DateTime;

/**
 * Class DateRangeTest
 */
class DateRangeTest extends UnitTest
{
    /**
     * @test
     */
    public function canHandleHalfOpenDateRanges()
    {
        $dateTime = new DateTime('2021-07-20 16:04:21.000000');
        $dateRangeOpenStart = new DateRange(
            $this->getDumbMock(DateRangeFacet::class),
            null,
            $dateTime,
            null,
            null,
            null,
            null,
            [],
            null
        );
        $dateRangeOpenEnd = new DateRange(
            $this->getDumbMock(DateRangeFacet::class),
            $dateTime,
            null,
            null,
            null,
            null,
            null,
            [],
            null
        );

        try {
            $dateRangeCollectionKeyOpenStart = $dateRangeOpenStart->getCollectionKey();
            $dateRangeCollectionKeyOpenEnd = $dateRangeOpenEnd->getCollectionKey();
        } catch (\Error $error) {
            self::fail(
                'Can\'t handle half open date ranges. Please see: https://github.com/TYPO3-Solr/ext-solr/issues/2942 and error: ' . PHP_EOL .
                $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine()
            );
        }
        self::assertEquals('-202107200000', $dateRangeCollectionKeyOpenStart);
        self::assertEquals('202107200000-', $dateRangeCollectionKeyOpenEnd);
    }
}
