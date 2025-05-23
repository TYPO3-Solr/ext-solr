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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Faceting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\UrlFacetContainer;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Util\ArrayAccessor;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class SetUpFacetParser extends SetUpUnitTestCase
{
    protected function initializeSearchResultSetFromFakeResponse(string $fixtureFile, array $facetConfiguration, array $activeFilters = []): SearchResultSet
    {
        $fakeResponseJson = self::getFixtureContentByName($fixtureFile);

        $facetingMock = $this->getMockBuilder(Faceting::class)->onlyMethods(['getSorting'])->disableOriginalConstructor()->getMock();
        $facetingMock->expects(self::any())->method('getSorting')->willReturn('');
        $usedQueryMock = $this->getMockBuilder(Query::class)->onlyMethods([])->disableOriginalConstructor()->getMock();

        $searchRequestMock = $this->getMockBuilder(SearchRequest::class)
            ->onlyMethods(['getActiveFacetNames', 'getContextTypoScriptConfiguration', 'getActiveFacets', 'getActiveFacetValuesByName'])
            ->getMock();

        $fakeResponse = new ResponseAdapter($fakeResponseJson, 200);

        $searchResultSet = new SearchResultSet();
        $searchResultSet->setUsedQuery($usedQueryMock);
        $searchResultSet->setUsedSearchRequest($searchRequestMock);
        $searchResultSet->setResponse($fakeResponse);

        $activeUrlFacets = new UrlFacetContainer(
            new ArrayAccessor([ 'tx_solr' => ['filter' => $activeFilters] ]),
        );
        $configuration = [];
        $configuration['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = $facetConfiguration;
        $typoScriptConfiguration = new TypoScriptConfiguration($configuration);
        $searchRequestMock->expects(self::any())
            ->method('getContextTypoScriptConfiguration')
            ->willReturn($typoScriptConfiguration);

        // Replace calls with own data bag
        $searchRequestMock->expects(self::any())
            ->method('getActiveFacetNames')
            ->willReturnCallback(function () use ($activeUrlFacets) {
                return $activeUrlFacets->getActiveFacetNames();
            });
        $searchRequestMock->expects(self::any())
            ->method('getActiveFacets')
            ->willReturnCallback(function () use ($activeUrlFacets) {
                return $activeUrlFacets->getActiveFacets();
            });
        $searchRequestMock->expects(self::any())
            ->method('getActiveFacetValuesByName')
            ->willReturnCallback(function (string $facetName) use ($activeUrlFacets) {
                return $activeUrlFacets->getActiveFacetValuesByName($facetName);
            });

        return $searchResultSet;
    }

    /**
     * @param string $className
     * @return AbstractFacetParser
     */
    protected function getInitializedParser($className)
    {
        return GeneralUtility::makeInstance($className);
    }
}
