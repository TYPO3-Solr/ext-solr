<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\Statistics;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsWriterProcessor;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Unit test case for the StatisticsWriterProcessor.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class StatisticsWriterProcessorTest extends UnitTest
{

    /**
     * @test
     */
    public function canWritExpectedStatisticData()
    {
        $fakeTSFE = $this->getDumbMock(TypoScriptFrontendController::class);
        $fakeTime = 100;
        $fakeIP = '192.168.2.22';

            /** @var StatisticsWriterProcessor $processor */
        $processor = $this->getMockBuilder(StatisticsWriterProcessor::class)->setMethods(['getTSFE', 'getTime', 'getUserIp', 'saveStatisticDate'])->getMock();
        $processor->expects($this->once())->method('getTSFE')->will($this->returnValue($fakeTSFE));
        $processor->expects($this->once())->method('getUserIp')->will($this->returnValue($fakeIP));
        $processor->expects($this->once())->method('getTime')->will($this->returnValue($fakeTime));

        $typoScriptConfigurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $typoScriptConfigurationMock->expects($this->once())->method('getStatisticsAnonymizeIP')->will($this->returnValue(0));

        $searchRequestMock = $this->getDumbMock(SearchRequest::class);
        $searchRequestMock->expects($this->once())->method('getContextTypoScriptConfiguration')->will($this->returnValue($typoScriptConfigurationMock));

        $queryMock = $this->getDumbMock(Query::class);
        $queryMock->expects($this->once())->method('getKeywords')->will($this->returnValue('my search'));

        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $resultSetMock->expects($this->once())->method('getUsedQuery')->will($this->returnValue($queryMock));
        $resultSetMock->expects($this->once())->method('getUsedSearchRequest')->will($this->returnValue($searchRequestMock));

        $self = $this;
        $processor->expects($this->once())->method('saveStatisticDate')->will($this->returnCallback(function ($statisticData) use ($self) {
            $this->assertSame('my search', $statisticData['keywords'], 'Unexpected keywords given');
            $this->assertSame('192.168.2.22', $statisticData['ip'], 'Unexpected ip given');
        }));

        $processor->process($resultSetMock);
    }
}
