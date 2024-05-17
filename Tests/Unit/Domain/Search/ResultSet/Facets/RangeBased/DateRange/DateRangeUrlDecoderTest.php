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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange\DateRangeUrlDecoder;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for query parser range
 */
class DateRangeUrlDecoderTest extends SetUpUnitTestCase
{
    /**
     * @var DateRangeUrlDecoder
     */
    protected $rangeParser;

    protected function setUp(): void
    {
        $this->rangeParser = GeneralUtility::makeInstance(DateRangeUrlDecoder::class);
        parent::setUp();
    }

    #[Test]
    public function canParseDateRangeQuery(): void
    {
        $expected = '[2010-01-01T00:00:00Z TO 2010-01-31T23:59:59Z]';
        $actual = $this->rangeParser->decode('201001010000-201001312359');
        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canParseMinOpenDateRangeQuery(): void
    {
        $expected = '[* TO 2010-01-31T23:59:59Z]';
        $actual = $this->rangeParser->decode('-201001312359');
        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canParseMaxOpenDateRangeQuery(): void
    {
        $expected = '[2010-01-01T00:00:00Z TO *]';
        $actual = $this->rangeParser->decode('201001010000-');
        self::assertEquals($expected, $actual);
    }
}
