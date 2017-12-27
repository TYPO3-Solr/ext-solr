<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Solr\Parser;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Solr\Parser\StopWordParser;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase for StopWordParser
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class StopWordParserTest extends UnitTest
{

    /**
     * @test
     */
    public function canParseStopWords()
    {
        $parser = new StopWordParser();
        $stopwords = $parser->parseJson($this->getFixtureContentByName('stopword.json'));
        $this->assertSame(['badword'], $stopwords, 'Could not parser stopwords from stopwords response');
    }
}