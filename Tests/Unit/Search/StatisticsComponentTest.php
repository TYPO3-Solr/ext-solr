<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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
        $this->assertEmpty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch']['statistics'], 'Expected that no statistic component was registered');


        $typoScriptConfiguration = new TypoScriptConfiguration([
            'plugin.' => [
                'tx_solr.' => [
                    'statistics' => 1
                ]
            ]
        ]);

        $searchRequestMock = $this->getDumbMock(SearchRequest::class);
        $searchRequestMock->expects($this->once())->method('getContextTypoScriptConfiguration')->willReturn($typoScriptConfiguration);

        $statisticsComponent = new StatisticsComponent();
        $statisticsComponent->setSearchRequest($searchRequestMock);
        $statisticsComponent->initializeSearchComponent();
        $this->assertNotEmpty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch']['statistics'], 'Expected that a statistic component was registered');
    }

}