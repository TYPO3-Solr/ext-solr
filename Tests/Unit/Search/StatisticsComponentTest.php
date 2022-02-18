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

namespace ApacheSolrForTypo3\Solr\Tests\Unit;

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Search\StatisticsComponent;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * Testcase for StatisticsComponent
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class StatisticsComponentTest extends UnitTest
{
    /**
     * @test
     */
    public function canRegisterStatisticsComponents()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch']['statistics']  = null;
        self::assertEmpty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch']['statistics'], 'Expected that no statistic component was registered');

        $typoScriptConfiguration = new TypoScriptConfiguration([
            'plugin.' => [
                'tx_solr.' => [
                    'statistics' => 1,
                ],
            ],
        ]);

        $searchRequestMock = $this->getDumbMock(SearchRequest::class);
        $searchRequestMock->expects(self::once())->method('getContextTypoScriptConfiguration')->willReturn($typoScriptConfiguration);

        $statisticsComponent = new StatisticsComponent();
        $statisticsComponent->setSearchRequest($searchRequestMock);
        $statisticsComponent->initializeSearchComponent();
        self::assertNotEmpty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch']['statistics'], 'Expected that a statistic component was registered');
    }

    /**
     * @test
     */
    public function canRegisterCustomStatisticsComponents()
    {
        $className = 'MyVendor/Namespace/Statistics/StatisticsWriterProcessor::class';
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch']['statistics'] = $className;

        $typoScriptConfiguration = new TypoScriptConfiguration([
            'plugin.' => [
                'tx_solr.' => [
                    'statistics' => 1,
                ],
            ],
        ]);

        $searchRequestMock = $this->getDumbMock(SearchRequest::class);
        $searchRequestMock->expects(self::once())->method('getContextTypoScriptConfiguration')->willReturn($typoScriptConfiguration);

        $statisticsComponent = new StatisticsComponent();
        $statisticsComponent->setSearchRequest($searchRequestMock);
        $statisticsComponent->initializeSearchComponent();
        self::assertEquals($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch']['statistics'], $className);
    }
}
