<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search;

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
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequestBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Session\FrontendUserSession;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SearchRequestBuilderTest extends UnitTest
{

    /**
     * @var FrontendUserSession
     */
    protected $sessionMock;

    /**
     * @var TypoScriptConfiguration
     */
    protected $configurationMock;

    /**
     * @var SearchRequestBuilder
     */
    protected $searchRequestBuilder;

    protected function setUp(): void
    {
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->sessionMock = $this->getDumbMock(FrontendUserSession::class);
        $this->searchRequestBuilder = new SearchRequestBuilder($this->configurationMock, $this->sessionMock);
        parent::setUp();
    }

    /**
     * @test
     */
    public function testPageIsResettedWhenValidResultsPerPageValueWasPassed()
    {
        $this->configurationMock->expects(self::once())->method('getSearchResultsPerPageSwitchOptionsAsArray')
            ->willReturn([10, 25]);
        $this->configurationMock->expects(self::any())->method('getSearchPluginNamespace')
            ->willReturn(SearchRequest::DEFAULT_PLUGIN_NAMESPACE);
        $this->assertPerPageInSessionWillBeChanged();

        $requestArguments = [
            'q' => 'test',
            'resultsPerPage' => 25,
            'page' => 5, // pagination page
        ];
        $request = $this->searchRequestBuilder->buildForSearch($requestArguments, 0, 0);
        self::assertSame($request->getPage(), null, 'Page was not resetted.');
    }

    /**
     * @test
     */
    public function testPerPageValueIsNotSetInSession()
    {
        $this->configurationMock->expects(self::once())->method('getSearchResultsPerPageSwitchOptionsAsArray')
            ->willReturn([10, 25]);
        $this->assertPerPageInSessionWillNotBeChanged();

        $requestArguments = ['q' => 'test', 'page' => 3];
        $this->searchRequestBuilder->buildForSearch($requestArguments, 0, 0);
    }

    private function assertPerPageInSessionWillBeChanged()
    {
        $this->sessionMock->expects(self::once())->method('setPerPage');
    }

    private function assertPerPageInSessionWillNotBeChanged()
    {
        $this->sessionMock->expects(self::never())->method('setPerPage');
    }
}
