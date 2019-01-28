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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\Group;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\Uri\SearchUriBuilder;
use ApacheSolrForTypo3\Solr\Util;
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

        // @todo This check can be dropped with TYPO3 8 LTS support is dropped
        if (Util::getIsTYPO3VersionBelow9()) {
            $expectedArguments = ['tx_solr' => ['filter' => ['###tx_solr:filter:0###']]];
            $cHashIsUsed = false;
        } else {
            $expectedArguments = ['tx_solr' => ['filter' => ['foo:bar']]];
            $cHashIsUsed = true;
        }

        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with($cHashIsUsed)->will($this->returnValue($this->extBaseUriBuilderMock));

        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);
        $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'foo', 'bar');
    }

    /**
     * @test
     */
    public function addFacetLinkWillAddAdditionalConfiguredArguments()
    {
        // @todo This check can be dropped with TYPO3 8 LTS support is dropped
        if (Util::getIsTYPO3VersionBelow9()) {
            $expectedArguments = ['tx_solr' => ['filter' => ['###tx_solr:filter:0###']], '###tx_solr:0###'];
            $linkBuilderResult = 'filter='.urlencode('###tx_solr:filter:0###').'&'.urlencode('###tx_solr:0###');
            $cHashIsUsed = false;
        } else {
            $expectedArguments = ['tx_solr' => ['filter' => ['option:value']], 'foo=bar'];
            $linkBuilderResult = 'filter='.urlencode('option:value').'&'.urlencode('foo=bar');
            $cHashIsUsed = true;
        }

        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with($cHashIsUsed)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('build')->with()->will($this->returnValue($linkBuilderResult));

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));

        $configurationMock->expects($this->once())->method('getSearchFacetingFacetLinkUrlParametersAsArray')->will($this->returnValue(['foo=bar']));
        $previousRequest =  new SearchRequest([], 1, 0, $configurationMock);
        $linkBuilderResult = $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'option', 'value');

        $this->assertEquals($linkBuilderResult, 'filter=option%3Avalue&foo%3Dbar');
    }

    /**
     * @test
     */
    public function setArgumentsIsOnlyCalledOnceEvenWhenMultipleFacetsGetRendered()
    {
        // @todo This test can be dropped when TYPO3 8 support is dropped
        if (!Util::getIsTYPO3VersionBelow9()) {
            $this->markTestSkipped('This scenario is only relevant for TYPO3 8 when the typolink cache is active');
        }

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
    public function setArgumentsIsCalledForEveryArgument()
    {
        // @todo This check can be dropped with TYPO3 8 LTS support is dropped, the test can be kept for 9 LTS
        if (Util::getIsTYPO3VersionBelow9()) {
            $this->markTestSkipped('This scenario is only relevant for TYPO3 8 when the typolink cache is active');
        }

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetLinkUrlParametersAsArray')->will($this->returnValue([]));

        $this->extBaseUriBuilderMock->expects($this->exactly(3))->method('setArguments')
            ->withConsecutive(
                [['tx_solr' => ['filter' => ['color:green']]]],
                [['tx_solr' => ['filter' => ['color:blue']]]],
                [['tx_solr' => ['filter' => ['color:red']]]]
            )->will($this->returnValue($this->extBaseUriBuilderMock));

        $this->extBaseUriBuilderMock->expects($this->exactly(3))->method('setUseCacheHash')->with(true)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->exactly(3))->method('build')->will(
            $this->onConsecutiveCalls(
                urlencode('tx_solr[filter][0]=color:green'),
                urlencode('tx_solr[filter][0]=color:blue'),
                urlencode('tx_solr[filter][0]=color:red')
            )
        );

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
        // @todo This check can be dropped with TYPO3 8 LTS support is dropped
        if (Util::getIsTYPO3VersionBelow9()) {
            $expectedArguments = ['tx_solr' => ['sort' => '###tx_solr:sort###']];
            $linkBuilderResult = 'tx_solr[sort]='.urlencode('###tx_solr:sort###');
            $cHashIsUsed = false;
        } else {
            $expectedArguments = ['tx_solr' => ['sort' => 'title desc']];
            $linkBuilderResult = 'tx_solr[sort]='.urlencode('title desc');
            $cHashIsUsed = true;
        }

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getSearchTargetPage')->will($this->returnValue(4711));
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));

        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);

            // we expect that the page uid from the configruation will be used to build the url with the uri builder
        $this->extBaseUriBuilderMock->expects($this->once())->method('setTargetPageUid')->with(4711)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with($cHashIsUsed)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('build')->will($this->returnValue($linkBuilderResult));
        $result = $this->searchUrlBuilder->getSetSortingUri($previousRequest, 'title', 'desc');
        $this->assertEquals('tx_solr[sort]=title+desc', $result);
    }

    /**
     * @test
     */
    public function canGetRemoveFacetOptionUri()
    {
        // @todo This check can be dropped with TYPO3 8 LTS support is dropped
        if (Util::getIsTYPO3VersionBelow9()) {
            $cHashIsUsed = false;
        } else {
            $cHashIsUsed = true;
        }

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
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with($cHashIsUsed)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->searchUrlBuilder->getRemoveFacetValueUri($previousRequest, 'type', 'pages');
    }

    /**
     * @test
     */
    public function canGetRemoveFacetUri()
    {
        // @todo This check can be dropped with TYPO3 8 LTS support is dropped
        if (Util::getIsTYPO3VersionBelow9()) {
            $cHashIsUsed = false;
        } else {
            $cHashIsUsed = true;
        }

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
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with($cHashIsUsed)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->searchUrlBuilder->getRemoveFacetUri($previousRequest, 'type');
    }

    /**
     * When a page for a group was set, this should be resetted when a facet is selected.
     *
     * @test
     */
    public function addFacetUriRemovesPreviousGroupPage()
    {
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));

        $previousRequest =  new SearchRequest([
                'tx_solr' => [
                    'groupPage' => [
                        'typeGroup' => [
                            'pages' => 4
                        ]
                    ]
                ]
            ],
            0, 0, $configurationMock);

        $this->extBaseUriBuilderMock->expects($this->any())->method('setArguments')->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->any())->method('setUseCacheHash')->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->any())->method('build')->will($this->returnValue('tx_solr[filter][0]=type:pages'));

        $uri = $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'type', 'pages');
        $this->assertSame('tx_solr[filter][0]=type:pages', urldecode($uri), 'Unexpected uri generated');
    }

    /**
     * @test
     */
    public function canSetGroupPageForQueryGroup()
    {
        // @todo This check can be dropped with TYPO3 8 LTS support is dropped
        if (Util::getIsTYPO3VersionBelow9()) {
            $cHashIsUsed = false;
            $expectedArguments = [
                'tx_solr' => [
                    'groupPage' => [
                        'smallPidRange' => [
                            'pid0to5' => '###tx_solr:groupPage:smallPidRange:pid0to5###'
                        ]
                    ]
                ]
            ];
            $linkBuilderResult = 'tx_solr[groupPage][smallPidRange][pid0to5]='.urlencode('###tx_solr:groupPage:smallPidRange:pid0to5###');
        } else {
            $cHashIsUsed = true;
            $expectedArguments = [
                'tx_solr' => [
                    'groupPage' => [
                        'smallPidRange' => [
                            'pid0to5' => '5'
                        ]
                    ]
                ]
            ];
            $linkBuilderResult = 'tx_solr[groupPage][smallPidRange][pid0to5]='.urlencode('5');
        }

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));
        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);

        $group = new Group('smallPidRange', 5);
        $groupItem = new GroupItem($group, 'pid:[0 to 5]', 12, 0, 32);


        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with($cHashIsUsed)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('build')->will($this->returnValue($linkBuilderResult));
        $uri = $this->searchUrlBuilder->getResultGroupItemPageUri($previousRequest, $groupItem, 5);
        $this->assertContains('tx_solr[groupPage][smallPidRange][pid0to5]=5', $uri, 'Uri did not contain link segment for query group');
    }
}
