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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\DateTime;

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

    protected function setUp(): void
    {
        $this->formatService = new FormatService();
        parent::setUp();
    }

    /**
     * @test
     */
    public function canFormatLegalDate()
    {
        self::assertSame('2017-02-16', $this->formatService->format('2017-02-16'));
    }

    /**
     * @test
     */
    public function canFormatIllegalDate()
    {
        self::assertSame('20170216', $this->formatService->format('20170216'));
    }

    /**
     * @test
     */
    public function canFormatLegalDateOtherInputFormat()
    {
        self::assertSame('16-02-17', $this->formatService->format('02-16-2017', 'm-d-Y'));
    }

    /**
     * @test
     */
    public function canFormatIllegalDateOtherInputFormat()
    {
        self::assertSame('02162017', $this->formatService->format('02162017', 'm-d-Y'));
    }

    /**
     * @test
     */
    public function canTimestampToIsoLegalDate()
    {
        self::assertSame('2017-02-16T20:13:57Z', $this->formatService->TimestampToIso(1487272437));
    }

    /**
     * @test
     */
    public function canTimestampToIsoIllegalDate()
    {
        self::assertEquals('1970-01-01T00:59:59Z', $this->formatService->TimestampToIso(-1));
    }

    /**
     * @test
     */
    public function canTimestampToIsoNull()
    {
        self::assertEquals('1970-01-01T01:00:00Z', $this->formatService->TimestampToIso(null));
    }

    /**
     * @test
     */
    public function canIsoToTimestampLegalDate()
    {
        self::assertEquals(1487272437, $this->formatService->IsoToTimestamp('2017-02-16T20:13:57Z'));
    }

    /**
     * @test
     */
    public function canIsoToTimestampIllegalDate()
    {
        self::assertEquals(0, $this->formatService->IsoToTimestamp('02-16-2017T20:13:57Z'));
    }

    /**
     * @test
     */
    public function canIsoToTimestampEpoch()
    {
        self::assertEquals(0, $this->formatService->IsoToTimestamp('1970-01-01T00:00:00'));
    }

    /**
     * @test
     */
    public function canTimestampToUtcIsoLegalDate()
    {
        self::assertEquals('2017-02-16T19:13:57Z', $this->formatService->timestampToUtcIso(1487272437));
    }

    /**
     * @test
     */
    public function canTimestampToUtcIsoIllegalDate()
    {
        self::assertEquals('1969-12-31T23:59:59Z', $this->formatService->timestampToUtcIso(-1));
    }

    /**
     * @test
     */
    public function canTimestampToUtcIsoNull()
    {
        self::assertEquals('1970-01-01T00:00:00Z', $this->formatService->timestampToUtcIso(null));
    }

    /**
     * @test
     */
    public function canUtcIsoToTimestampLegalDate()
    {
        self::assertEquals(1487276037, $this->formatService->utcIsoToTimestamp('2017-02-16T20:13:57Z'));
    }

    /**
     * @test
     */
    public function canUtcIsoToTimestampIllegalDate()
    {
        self::assertEquals(0, $this->formatService->utcIsoToTimestamp('02-16-2017T20:13:57Z'));
    }

    /**
     * @test
     */
    public function canUtcIsoToTimestampEpoch()
    {
        self::assertEquals(0, $this->formatService->utcIsoToTimestamp('1970-01-01T00:00:00'));
    }
}
