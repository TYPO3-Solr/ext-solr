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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\RangeBased\NumericRange;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange\NumericRangeUrlDecoder;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use InvalidArgumentException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for query parser range
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 * @author Ingo Renner <ingo@typo3.org>
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class NumericRangeUrlEncoderTest extends UnitTest
{
    /**
     * Parser to build Solr range queries
     *
     * @var NumericRangeUrlDecoder
     */
    protected $rangeParser;

    protected function setUp(): void
    {
        $this->rangeParser = GeneralUtility::makeInstance(NumericRangeUrlDecoder::class);
        parent::setUp();
    }

    /**
     * Provides data for filter decoding tests
     *
     * @return array
     */
    public function rangeQueryParsingDataProvider(): array
    {
        return [
            ['firstValue' => '50', 'secondValue' => '100', 'expected' => '[50 TO 100]'],
            ['firstValue' => '-10', 'secondValue' => '20', 'expected' => '[-10 TO 20]'],
            ['firstValue' => '-10', 'secondValue' => '-5', 'expected' => '[-10 TO -5]'],
        ];
    }

    /**
     * Test the filter decoding
     *
     * @dataProvider rangeQueryParsingDataProvider
     * @test
     *
     * @param string $firstValue
     * @param string $secondValue
     * @param string $expectedResult
     */
    public function canParseRangeQuery(string $firstValue, string $secondValue, string $expectedResult)
    {
        $actual = $this->rangeParser->decode($firstValue . '-' . $secondValue);
        self::assertEquals($expectedResult, $actual);
    }

    /**
     * Test the handling of invalid parameters
     *
     * @test
     */
    public function canHandleInvalidParameters()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->rangeParser->decode('invalid-value');
    }
}
