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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Data;

use ApacheSolrForTypo3\Solr\System\Data\DateTime;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DateTimeTest extends UnitTest
{
    /**
     * @test
     */
    public function testCanWrapDateTimeAndConvertToString()
    {
        $proxy = new DateTime('2003-12-13T18:30:02Z', new \DateTimeZone('UTC'));
        self::assertSame('2003-12-13T18:30:02+0000', (string)$proxy);
    }

    /**
     * @test
     */
    public function testCanDispatchCallToUnderlyingDateTime()
    {
        $proxy = new DateTime('2003-12-13T18:30:02Z', new \DateTimeZone('UTC'));
        self::assertSame('2003-12-13T18:30:02+0000', $proxy->format(\DateTime::ISO8601));
    }
}
