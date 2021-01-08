<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search;

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

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequestBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\UnifiedConfiguration;
use ApacheSolrForTypo3\Solr\System\Session\FrontendUserSession;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 * @copyright (c) 2017-2021 Timo Hund <timo.hund@dkd.de
 */
class SearchRequestBuilderTest extends UnitTest
{
    /**
     * @var FrontendUserSession
     */
    protected $sessionMock;

    /**
     * @var UnifiedConfiguration
     */
    protected $unifiedConfiguration;

    /**
     * @var TypoScriptConfiguration
     */
    protected $configurationMock;

    /**
     * @var SearchRequestBuilder
     */
    protected $searchRequestBuilder;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->unifiedConfiguration = new UnifiedConfiguration(1, 0);
        $this->unifiedConfiguration->mergeConfigurationByObject(new TypoScriptConfiguration([]));
        $this->sessionMock = $this->getDumbMock(FrontendUserSession::class);
        $this->searchRequestBuilder = new SearchRequestBuilder(
            $this->unifiedConfiguration,
            $this->sessionMock
        );
    }

    /**
     * @test
     */
    public function testPageIsResettedWhenValidResultsPerPageValueWasPassed()
    {
        $this->unifiedConfiguration->replaceConfigurationByObject(
            new TypoScriptConfiguration(
                [
                    'plugin.' => [
                        'tx_solr.' => [
                            'search.' => [
                                'results.' => [
                                    'resultsPerPageSwitchOptions' => '10,25'
                                ]
                            ]
                        ]
                    ]
                ]
            )
        );
        $this->assertPerPageInSessionWillBeChanged();

        $requestArguments = ['q' => 'test', 'page' => 5, 'resultsPerPage' => 25];
        $request = $this->searchRequestBuilder->buildForSearch($requestArguments, 0, 0);
        $this->assertSame($request->getPage(), null, 'Page was not resetted.');
    }

    /**
     * @test
     */
    public function testPerPageValueIsNotSetInSession()
    {
        $this->unifiedConfiguration->replaceConfigurationByObject(
            new TypoScriptConfiguration(
                [
                    'plugin.' => [
                        'tx_solr.' => [
                            'search.' => [
                                'results.' => [
                                    'resultsPerPageSwitchOptions' => '10,25'
                                ]
                            ]
                        ]
                    ]
                ]
            )
        );
        $this->assertPerPageInSessionWillNotBeChanged();

        $requestArguments = ['q' => 'test', 'page' => 3];
        $this->searchRequestBuilder->buildForSearch($requestArguments, 0, 0);
    }

    /**
     * @return void
     */
    private function assertPerPageInSessionWillBeChanged()
    {
        $this->sessionMock->expects($this->once())->method('setPerPage');
    }

    /**
     * @return void
     */
    private function assertPerPageInSessionWillNotBeChanged()
    {
        $this->sessionMock->expects($this->never())->method('setPerPage');
    }
}
