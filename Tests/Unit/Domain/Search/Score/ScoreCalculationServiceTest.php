<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Score;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2016 Timo Schmidt
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Search\Score\ScoreCalculationService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;

/**
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class ScoreCalculationServiceTest extends UnitTest
{

    /**
     * @var ScoreCalculationService
     */
    protected $scoreCalculationService;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->scoreCalculationService = new ScoreCalculationService();
    }

    /**
     * @test
     */
    public function canGetRenderedScoreAnalysis()
    {
        $fakeDebugData = $this->getFixtureContent('fakeSolrDebugData.txt');
        $fakeQueryFields = 'content^40.0, title^5.0, keywords^2.0, tagsH1^5.0, tagsH2H3^3.0, tagsH4H5H6^2.0, tagsInline^1.0';

        $scoreAnalysis = $this->scoreCalculationService->getRenderedScores($fakeDebugData, $fakeQueryFields);

        $this->assertContains('<td>+       0.1781365</td>', $scoreAnalysis);
        $this->assertContains('<td>content</td>', $scoreAnalysis);
        $this->assertContains('<td>40.0</td></tr>', $scoreAnalysis);

        $this->assertContains('<td>+       0.38260993</td>', $scoreAnalysis);
        $this->assertContains('<td>tagsH2H3</td>', $scoreAnalysis);
        $this->assertContains('<td>3.0</td></tr>', $scoreAnalysis);
    }
}
