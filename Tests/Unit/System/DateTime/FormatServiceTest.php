<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\DateTime;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2017 Thomas Hohn <tho@systime.dk>
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

use ApacheSolrForTypo3\Solr\System\DateTime\FormatService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase for FormatService
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class FormatServiceTest extends UnitTest
{
    /**
     * @var FormatService
     */
    protected $formatService;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->formatService = new FormatService();
    }

    /**
     * @test
     */
    public function canFormatLegalDate()
    {
        $this->assertSame('2017-02-16', $this->formatService->format('2017-02-16'));
    }

    /**
     * @test
     */
    public function canFormatIllegalDate()
    {
        $this->assertSame('20170216', $this->formatService->format('20170216'));
    }

    /**
     * @test
     */
    public function canFormatLegalDateOtherInputFormat()
    {
        $this->assertSame('16-02-17', $this->formatService->format('02-16-2017', 'm-d-Y'));
    }

    /**
     * @test
     */
    public function canFormatIllegalDateOtherInputFormat()
    {
        $this->assertSame('02162017', $this->formatService->format('02162017', 'm-d-Y'));
    }

    /**
     * @test
     */
    public function canTimestampToIsoLegalDate()
    {
        $this->assertSame('2017-02-16T20:13:57Z', $this->formatService->TimestampToIso(1487272437));
    }

    /**
     * @test
     */
    public function canTimestampToIsoIllegalDate()
    {
        $this->assertEquals('1970-01-01T00:59:59Z', $this->formatService->TimestampToIso(-1));
    }

    /**
     * @test
     */
    public function canTimestampToIsoNull()
    {
        $this->assertEquals('1970-01-01T01:00:00Z', $this->formatService->TimestampToIso(null));
    }

    /**
     * @test
     */
    public function canIsoToTimestampLegalDate()
    {
        $this->assertEquals(1487272437, $this->formatService->IsoToTimestamp('2017-02-16T20:13:57Z'));
    }

    /**
     * @test
     */
    public function canIsoToTimestampIllegalDate()
    {
        $this->assertEquals(0, $this->formatService->IsoToTimestamp('02-16-2017T20:13:57Z'));
    }

    /**
     * @test
     */
    public function canIsoToTimestampEpoch()
    {
        $this->assertEquals(0, $this->formatService->IsoToTimestamp('1970-01-01T00:00:00'));
    }

    /**
     * @test
     */
    public function canTimestampToUtcIsoLegalDate()
    {
        $this->assertEquals('2017-02-16T19:13:57Z', $this->formatService->timestampToUtcIso(1487272437));
    }

    /**
     * @test
     */
    public function canTimestampToUtcIsoIllegalDate()
    {
        $this->assertEquals('1969-12-31T23:59:59Z', $this->formatService->timestampToUtcIso(-1));
    }

    /**
     * @test
     */
    public function canTimestampToUtcIsoNull()
    {
        $this->assertEquals('1970-01-01T00:00:00Z', $this->formatService->timestampToUtcIso(null));
    }

    /**
     * @test
     */
    public function canUtcIsoToTimestampLegalDate()
    {
        $this->assertEquals(1487276037, $this->formatService->utcIsoToTimestamp('2017-02-16T20:13:57Z'));
    }

    /**
     * @test
     */
    public function canUtcIsoToTimestampIllegalDate()
    {
        $this->assertEquals(0, $this->formatService->utcIsoToTimestamp('02-16-2017T20:13:57Z'));
    }

    /**
     * @test
     */
    public function canUtcIsoToTimestampEpoch()
    {
        $this->assertEquals(0, $this->formatService->utcIsoToTimestamp('1970-01-01T00:00:00'));
    }
}
