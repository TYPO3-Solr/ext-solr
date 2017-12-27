<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Query\FilterEncoder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange\DateRangeUrlDecoder;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for query parser range
 *
 * @author Markus Goldbach
 */
class DateRangeUrlEncoderTest extends UnitTest
{

    /**
     * @var DateRangeUrlDecoder
     */
    protected $rangeParser;

    public function setUp()
    {
        $this->rangeParser = GeneralUtility::makeInstance(DateRangeUrlDecoder::class);
    }

    /**
     * @test
     */
    public function canParseDateRangeQuery()
    {
        $expected = '[2010-01-01T00:00:00Z TO 2010-01-31T23:59:59Z]';
        $actual = $this->rangeParser->decode('201001010000-201001312359');
        $this->assertEquals($expected, $actual);
    }
}
