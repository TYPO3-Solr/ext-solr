<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\RenderingInstructions;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2016 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RenderingInstructions\FormatDate;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;


/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class FormatDateTest extends UnitTest
{

    /**
     * @test
     */
    public function canFormatUsingDefaultFormat()
    {
        $processingInstruction = new FormatDate();
        $result = $processingInstruction->format('2015-11-17T17:16:10Z', []);
        $this->assertSame('17-11-15', $result, 'Could not format date with default format');
    }

    /**
     * @test
     */
    public function canPassCustomOutputFormat()
    {
        $processingInstruction = new FormatDate();
        $result = $processingInstruction->format('2015-11-17T17:16:10Z', ['outputFormat' => 'd.m.Y']);
        $this->assertSame('17.11.2015', $result, 'Could not format date with default format');
    }

    /**
     * @test
     */
    public function canParseTimestampAsInputValue()
    {
        $processingInstruction = new FormatDate();
        $fiveDays = (60 * 60 * 24 * 5) - 1;
        $result = $processingInstruction->format((string) $fiveDays, ['inputFormat' => 'U', 'outputFormat' => 'd.m.Y']);
        $this->assertSame('05.01.1970', $result, 'Could not format date from timestamp');
    }
}