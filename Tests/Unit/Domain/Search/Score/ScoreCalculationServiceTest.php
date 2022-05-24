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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Score;

use ApacheSolrForTypo3\Solr\Domain\Search\Score\ScoreCalculationService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class ScoreCalculationServiceTest extends UnitTest
{

    /**
     * @var ScoreCalculationService
     */
    protected ScoreCalculationService $scoreCalculationService;

    protected function setUp(): void
    {
        $this->scoreCalculationService = new ScoreCalculationService();
        parent::setUp();
    }

    /**
     * @test
     */
    public function canGetRenderedScoreAnalysis()
    {
        $fakeDebugData = $this->getFixtureContentByName('fakeSolrDebugData.txt');
        $fakeQueryFields = 'content^40.0, title^5.0, keywords^2.0, tagsH1^5.0, tagsH2H3^3.0, tagsH4H5H6^2.0, tagsInline^1.0, description^4.0, abstract^1.0, subtitle^1.0, navtitle^1.0, author^1.0';

        $scoreAnalysis = $this->scoreCalculationService->getRenderedScores($fakeDebugData, $fakeQueryFields);

        self::assertStringContainsString('<td>+     98.444336</td', $scoreAnalysis);
        self::assertStringContainsString('<td>content</td>', $scoreAnalysis);
        self::assertStringContainsString('<td>40.0</td></tr>', $scoreAnalysis);

        self::assertStringContainsString('<td>+     6.2762194</td>', $scoreAnalysis);
        self::assertStringContainsString('<td>tagsH2H3</td>', $scoreAnalysis);
        self::assertStringContainsString('<td>3.0</td></tr>', $scoreAnalysis);
    }
}
