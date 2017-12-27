<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\FrequentSearches;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt
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

use ApacheSolrForTypo3\Solr\Domain\Search\FrequentSearches\FrequentSearchesService;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Cache\Frontend\AbstractFrontend;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class FrequentSearchesServiceTest extends UnitTest
{
    /**
     * @var FrequentSearchesService
     */
    protected $frequentSearchesService;

    /**
     * @var TypoScriptFrontendController
     */
    protected $tsfeMock;

    /**
     * @var AbstractFrontend
     */
    protected $cacheMock;

    /**
     * @var TypoScriptConfiguration
     */
    protected $configurationMock;

    /**
     * @var StatisticsRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $statisticsRepositoryMock;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->tsfeMock = $this->getDumbMock(TypoScriptFrontendController::class);
        $this->statisticsRepositoryMock = $this->getDumbMock(StatisticsRepository::class );
        $this->cacheMock = $this->getDumbMock(AbstractFrontend::class);
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);

        $this->frequentSearchesService = new FrequentSearchesService(
            $this->configurationMock,
            $this->cacheMock,
            $this->tsfeMock,
            $this->statisticsRepositoryMock
        );
    }

    /**
     * @test
     */
    public function cachedResultIsUsedWhenIdentifierIsPresent()
    {
        $fakeConfiguration = [];
        $expectedCacheIdentifier = 'frequentSearchesTags_' . md5(serialize($fakeConfiguration));
        $this->configurationMock->expects($this->once())->method('getSearchFrequentSearchesConfiguration')->will($this->returnValue($fakeConfiguration));

        $this->fakeCacheResult($expectedCacheIdentifier, ['term a']);

        $frequentTerms = $this->frequentSearchesService->getFrequentSearchTerms();
        $this->assertSame('term a', $frequentTerms[0], 'Could not get frequent terms from service');
    }

    /**
     * @test
     */
    public function databaseResultIsUsedWhenNoCachedResultIsPresent()
    {
        $fakeConfiguration = [
            'select.' => [
                'checkRootPageId' => true,
                'checkLanguage' => true,
                'SELECT' => '',
                'FROM' => '',
                'ADD_WHERE' => '',
                'GROUP_BY' => '',
                'ORDER_BY' => ''
            ],
            'limit'
        ];

        $this->configurationMock->expects($this->once())->method('getSearchFrequentSearchesConfiguration')->will($this->returnValue($fakeConfiguration));

        $this->statisticsRepositoryMock->expects($this->once())->method('getFrequentSearchTermsFromStatisticsByFrequentSearchConfiguration')->will($this->returnValue([
            [
               'search_term' => 'my search',
                'hits' => 22
            ]
        ]));

            //we fake that we have no frequent searches in the cache and therefore expect that the database will be queried
        $this->fakeIdentifierNotInCache();
        $frequentTerms = $this->frequentSearchesService->getFrequentSearchTerms();

        $this->assertSame($frequentTerms, ['my search' => 22], 'Could not retrieve frequent search terms');
    }

    /**
     * @param string $identifier
     * @param array $value
     */
    public function fakeCacheResult($identifier, $value)
    {
        $this->cacheMock->expects($this->once())->method('has')->with($identifier)->will($this->returnValue(true));
        $this->cacheMock->expects($this->once())->method('get')->will($this->returnValue($value));
    }

    /**
     * @return void
     */
    public function fakeIdentifierNotInCache()
    {
        $this->cacheMock->expects($this->once())->method('has')->will($this->returnValue(false));
    }
}
