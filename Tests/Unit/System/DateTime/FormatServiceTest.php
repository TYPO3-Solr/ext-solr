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
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Testcase for FormatService
 */
class FormatServiceTest extends SetUpUnitTestCase
{
    protected FormatService $formatService;

    protected function setUp(): void
    {
        $this->formatService = new FormatService();
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] = 'Y-m-d';
    }

    #[Test]
    public function canFormatLegalDate(): void
    {
        self::assertSame('2017-02-16', $this->formatService->format('2017-02-16'));
    }

    #[Test]
    public function canFormatIllegalDate(): void
    {
        self::assertSame('20170216', $this->formatService->format('20170216'));
    }

    #[Test]
    public function canFormatLegalDateOtherInputFormat(): void
    {
        self::assertSame('2017-02-16', $this->formatService->format('02-16-2017', 'm-d-Y'));
    }

    #[Test]
    public function canFormatIllegalDateOtherInputFormat(): void
    {
        self::assertSame('02162017', $this->formatService->format('02162017', 'm-d-Y'));
    }

    #[Test]
    public function canTimestampToIsoLegalDate(): void
    {
        self::assertSame('2017-02-16T20:13:57Z', $this->formatService->TimestampToIso(1487272437));
    }

    #[Test]
    public function canTimestampToIsoIllegalDate(): void
    {
        self::assertEquals('1970-01-01T00:59:59Z', $this->formatService->TimestampToIso(-1));
    }

    #[Test]
    public function canTimestampToIsoNull(): void
    {
        self::assertEquals('1970-01-01T01:00:00Z', $this->formatService->TimestampToIso(null));
    }

    #[Test]
    public function canIsoToTimestampLegalDate(): void
    {
        self::assertEquals(1487272437, $this->formatService->IsoToTimestamp('2017-02-16T20:13:57Z'));
    }

    #[Test]
    public function canIsoToTimestampIllegalDate(): void
    {
        self::assertEquals(0, $this->formatService->IsoToTimestamp('02-16-2017T20:13:57Z'));
    }

    #[Test]
    public function canIsoToTimestampEpoch(): void
    {
        self::assertEquals(0, $this->formatService->IsoToTimestamp('1970-01-01T00:00:00'));
    }

    #[Test]
    public function canTimestampToUtcIsoLegalDate(): void
    {
        self::assertEquals('2017-02-16T19:13:57Z', $this->formatService->timestampToUtcIso(1487272437));
    }

    #[Test]
    public function canTimestampToUtcIsoIllegalDate(): void
    {
        self::assertEquals('1969-12-31T23:59:59Z', $this->formatService->timestampToUtcIso(-1));
    }

    #[Test]
    public function canTimestampToUtcIsoNull(): void
    {
        self::assertEquals('1970-01-01T00:00:00Z', $this->formatService->timestampToUtcIso(null));
    }

    #[Test]
    public function canUtcIsoToTimestampLegalDate(): void
    {
        self::assertEquals(1487276037, $this->formatService->utcIsoToTimestamp('2017-02-16T20:13:57Z'));
    }

    #[Test]
    public function canUtcIsoToTimestampIllegalDate(): void
    {
        self::assertEquals(0, $this->formatService->utcIsoToTimestamp('02-16-2017T20:13:57Z'));
    }

    #[Test]
    public function canUtcIsoToTimestampEpoch(): void
    {
        self::assertEquals(0, $this->formatService->utcIsoToTimestamp('1970-01-01T00:00:00'));
    }
}
