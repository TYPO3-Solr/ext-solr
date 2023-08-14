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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\FrequentSearches;

use ApacheSolrForTypo3\Solr\Domain\Search\FrequentSearches\FrequentSearchesService;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Cache\Frontend\AbstractFrontend;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class FrequentSearchesServiceTest extends SetUpUnitTestCase
{
    protected FrequentSearchesService|MockObject $frequentSearchesService;
    protected TypoScriptFrontendController|MockObject $tsfeMock;
    protected AbstractFrontend|MockObject $cacheMock;
    protected TypoScriptConfiguration|MockObject $configurationMock;
    protected StatisticsRepository|MockObject $statisticsRepositoryMock;

    protected function setUp(): void
    {
        $this->tsfeMock = $this->createMock(TypoScriptFrontendController::class);
        $this->tsfeMock->tmpl = $this->createMock(TemplateService::class);
        $this->tsfeMock->tmpl->rootLine = [
            0 => [
                'uid' => 4711,
            ],
        ];
        $this->statisticsRepositoryMock = $this->createMock(StatisticsRepository::class);
        $this->cacheMock = $this->createMock(AbstractFrontend::class);
        $this->configurationMock = $this->createMock(TypoScriptConfiguration::class);

        $this->frequentSearchesService = new class ($this->configurationMock, $this->cacheMock, $this->tsfeMock, $this->statisticsRepositoryMock) extends FrequentSearchesService {
            //            protected function getCacheIdentifier(array $frequentSearchConfiguration) : string {
            //                $identifier = 'frequentSearchesTags';
            //                if (isset($frequentSearchConfiguration['select.']['checkRootPageId']) && $frequentSearchConfiguration['select.']['checkRootPageId']) {
            //                    $identifier .= '_RP' . 4710;
            //                }
            //                if (isset($frequentSearchConfiguration['select.']['checkLanguage']) && $frequentSearchConfiguration['select.']['checkLanguage']) {
            //                    $identifier .= '_L' . 0;
            //                }
            //
            //                $identifier .= '_' . md5(serialize($frequentSearchConfiguration));
            //                return $identifier;
            //            }
        };
        parent::setUp();
    }

    /**
     * @test
     */
    public function cachedResultIsUsedWhenIdentifierIsPresent(): void
    {
        $fakeConfiguration = [];
        $expectedCacheIdentifier = 'frequentSearchesTags_' . md5(serialize($fakeConfiguration));
        $this->configurationMock->expects(self::once())->method('getSearchFrequentSearchesConfiguration')->willReturn($fakeConfiguration);

        $this->fakeCacheResult($expectedCacheIdentifier, ['term a']);

        $frequentTerms = $this->frequentSearchesService->getFrequentSearchTerms();
        self::assertSame('term a', $frequentTerms[0], 'Could not get frequent terms from service');
    }

    /**
     * @test
     */
    public function databaseResultIsUsedWhenNoCachedResultIsPresent(): void
    {
        $fakeConfiguration = [
            'select.' => [
                'checkRootPageId' => true,
                'checkLanguage' => true,
                'SELECT' => '',
                'FROM' => '',
                'ADD_WHERE' => '',
                'GROUP_BY' => '',
                'ORDER_BY' => '',
            ],
            'limit',
        ];

        $this->configurationMock->expects(self::once())->method('getSearchFrequentSearchesConfiguration')->willReturn($fakeConfiguration);

        $this->statisticsRepositoryMock->expects(self::once())->method('getFrequentSearchTermsFromStatisticsByFrequentSearchConfiguration')->willReturn([
            [
               'search_term' => 'my search',
                'hits' => 22,
            ],
        ]);

        //we fake that we have no frequent searches in the cache and therefore expect that the database will be queried
        $this->fakeIdentifierNotInCache();
        $frequentTerms = $this->frequentSearchesService->getFrequentSearchTerms();

        self::assertSame($frequentTerms, ['my search' => 22], 'Could not retrieve frequent search terms');
    }

    public function fakeCacheResult(string $identifier, array $value): void
    {
        $this->cacheMock->expects(self::once())->method('has')->with($identifier)->willReturn(true);
        $this->cacheMock->expects(self::once())->method('get')->willReturn($value);
    }

    public function fakeIdentifierNotInCache(): void
    {
        $this->cacheMock->expects(self::once())->method('has')->willReturn(false);
    }
}
