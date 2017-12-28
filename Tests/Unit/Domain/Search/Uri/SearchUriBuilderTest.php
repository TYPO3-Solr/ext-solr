<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\Uri;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
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
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\Uri\SearchUriBuilder;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

/**
 * Unit test case for the ObjectReconstitutionProcessor.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SearchUriBuilderTest extends UnitTest
{

    /**
     * @var SearchUriBuilder
     */
    protected $searchUrlBuilder;

    /**
     * @var UriBuilder
     */
    protected $extBaseUriBuilderMock;

    /**
     * @return void
     */
    protected function setUp()
    {
        $this->extBaseUriBuilderMock = $this->getDumbMock(UriBuilder::class);
        $this->searchUrlBuilder = new SearchUriBuilder();
        $this->searchUrlBuilder->injectUriBuilder($this->extBaseUriBuilderMock);
    }

    /**
     * @test
     */
    public function addFacetLinkIsCalledWithSubstitutedArguments()
    {
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetLinkUrlParametersAsArray')->will($this->returnValue([]));

        $expectedArguments = ['tx_solr' => ['filter' => ['###tx_solr:filter:0###']]];
        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)->will($this->returnValue($this->extBaseUriBuilderMock));

        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);
        $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'foo', 'bar');
    }

    /**
     * @test
     */
    public function addFacetLinkWillAddAdditionalConfiguredArguments()
    {
        $expectedArguments = ['tx_solr' => ['filter' => ['###tx_solr:filter:0###']], '###tx_solr:0###'];
        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)->will($this->returnValue($this->extBaseUriBuilderMock));

        $result = 'filter='.urlencode('###tx_solr:filter:0###').'&'.urlencode('###tx_solr:0###');
        $this->extBaseUriBuilderMock->expects($this->once())->method('build')->with()->will($this->returnValue($result));

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));

        $configurationMock->expects($this->once())->method('getSearchFacetingFacetLinkUrlParametersAsArray')->will($this->returnValue(['foo=bar']));
        $previousRequest =  new SearchRequest([], 1, 0, $configurationMock);
        $result = $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'option', 'value');

        $this->assertEquals($result, 'filter=option%3Avalue&foo%3Dbar');
    }

    /**
     * @test
     */
    public function setArgumentsIsOnlyCalledOnceEvenWhenMultipleFacetsGetRendered()
    {
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetLinkUrlParametersAsArray')->will($this->returnValue([]));

        $expectedArguments = ['tx_solr' => ['filter' => ['###tx_solr:filter:0###']]];
        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('build')->will($this->returnValue(urlencode('tx_solr[filter][0]=###tx_solr:filter:0###')));

        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);
        $previousRequest->removeAllFacets();
        $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'color', 'green');

        $previousRequest->removeAllFacets();
        $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'color', 'blue');

        $previousRequest->removeAllFacets();
        $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'color', 'red');
    }


    /**
     * @test
     */
    public function targetPageUidIsPassedWhenSortingIsAdded()
    {
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getSearchTargetPage')->will($this->returnValue(4711));
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));

        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);

        $expectedArguments = ['tx_solr' => ['sort' => '###tx_solr:sort###']];

            // we expect that the page uid from the configruation will be used to build the url with the uri builder
        $this->extBaseUriBuilderMock->expects($this->once())->method('setTargetPageUid')->with(4711)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('build')->will($this->returnValue('tx_solr[sort]='.urlencode('###tx_solr:sort###')));
        $result = $this->searchUrlBuilder->getSetSortingUri($previousRequest, 'title', 'desc');
        $this->assertEquals('tx_solr[sort]=title+desc', $result);
    }

    /**
     * @test
     */
    public function canGetRemoveFacetOptionUri()
    {
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));

        $previousRequest =  new SearchRequest([
                    'tx_solr' => [
                        'filter' => [
                            'type:pages'
                        ]
                    ]
                ],
                0,
                0,
                $configurationMock);

        // we expect that the filters are empty after remove
        $expectedArguments = ['tx_solr' => ['filter' => []]];
        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->searchUrlBuilder->getRemoveFacetValueUri($previousRequest, 'type', 'pages');
    }

    /**
     * @test
     */
    public function canGetRemoveFacetUri()
    {
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));

        $previousRequest =  new SearchRequest([
                    'tx_solr' => [
                        'filter' => [
                            'type:pages',
                            'type:tt_news',
                        ]
                    ]
                ],
                0,
                0,
                $configurationMock);

        // we expect that the filters are empty after remove
        //@todo we need to refactor the request in ext:solr to cleanup empty arguments completely to assert  $expectedArguments = []
        $expectedArguments = ['tx_solr' => ['filter' => []]];
        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->searchUrlBuilder->getRemoveFacetUri($previousRequest, 'type');
    }
}
