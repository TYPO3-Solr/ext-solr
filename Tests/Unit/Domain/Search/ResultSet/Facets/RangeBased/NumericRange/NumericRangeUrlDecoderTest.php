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
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for query parser range
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 * @author Ingo Renner <ingo@typo3.org>
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class NumericRangeUrlDecoderTest extends SetUpUnitTestCase
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
     */
    public static function rangeQueryParsingDataProvider(): Traversable
    {
        yield '50/100' => ['firstValue' => '50', 'secondValue' => '100', 'expectedResult' => '[50 TO 100]'];
        yield '-10/20' => ['firstValue' => '-10', 'secondValue' => '20', 'expectedResult' => '[-10 TO 20]'];
        yield '-10/-5' => ['firstValue' => '-10', 'secondValue' => '-5', 'expectedResult' => '[-10 TO -5]'];
    }

    /**
     * Test the filter decoding
     *
     *
     * @param string $firstValue
     * @param string $secondValue
     * @param string $expectedResult
     */
    #[DataProvider('rangeQueryParsingDataProvider')]
    #[Test]
    public function canParseRangeQuery(string $firstValue, string $secondValue, string $expectedResult)
    {
        $actual = $this->rangeParser->decode($firstValue . '-' . $secondValue);
        self::assertEquals($expectedResult, $actual);
    }

    /**
     * Test the handling of invalid parameters
     */
    #[Test]
    public function canHandleInvalidParameters()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->rangeParser->decode('invalid-value');
    }
}
