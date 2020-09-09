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

    /**
     * @return void
     */
    public function setUp()
    {
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->sessionMock = $this->getDumbMock(FrontendUserSession::class);
        $this->searchRequestBuilder = new SearchRequestBuilder($this->configurationMock, $this->sessionMock);
    }

    /**
     * @test
     */
    public function testPageIsResettedWhenValidResultsPerPageValueWasPassed()
    {
        $this->configurationMock->expects($this->once())->method('getSearchResultsPerPageSwitchOptionsAsArray')
            ->will($this->returnValue([10, 25]));
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
        $this->configurationMock->expects($this->once())->method('getSearchResultsPerPageSwitchOptionsAsArray')
            ->will($this->returnValue([10, 25]));
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
