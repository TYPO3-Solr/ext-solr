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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\RenderingInstructions;

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
        self::assertSame('17-11-15', $result, 'Could not format date with default format');
    }

    /**
     * @test
     */
    public function canPassCustomOutputFormat()
    {
        $processingInstruction = new FormatDate();
        $result = $processingInstruction->format('2015-11-17T17:16:10Z', ['outputFormat' => 'd.m.Y']);
        self::assertSame('17.11.2015', $result, 'Could not format date with default format');
    }

    /**
     * @test
     */
    public function canParseTimestampAsInputValue()
    {
        $processingInstruction = new FormatDate();
        $fiveDays = (60 * 60 * 24 * 5) - 1;
        $result = $processingInstruction->format((string)$fiveDays, ['inputFormat' => 'U', 'outputFormat' => 'd.m.Y']);
        self::assertSame('05.01.1970', $result, 'Could not format date from timestamp');
    }
}
