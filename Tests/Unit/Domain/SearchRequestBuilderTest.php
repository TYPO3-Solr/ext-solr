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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search;

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
