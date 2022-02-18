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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Session;

use ApacheSolrForTypo3\Solr\System\Session\FrontendUserSession;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Testcase for the SchemaParser class.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class FrontendSessionTest extends UnitTest
{

    /**
     * @var FrontendUserAuthentication
     */
    protected $feUserMock;

    /**
     * @var FrontendUserSession
     */
    protected $session;

    protected function setUp(): void
    {
        $this->feUserMock = $this->getDumbMock(FrontendUserAuthentication::class);
        $this->session = new FrontendUserSession($this->feUserMock);
        parent::setUp();
    }

    /**
     * @test
     */
    public function getEmptyArrayWhenNoLastSearchesInSession()
    {
        $lastSearches = $this->session->getLastSearches();
        self::assertSame([], $lastSearches, 'Expected to get an empty lastSearches array');
    }

    /**
     * @test
     */
    public function sessionDataWillBeRetrievedFromSessionForLastSearches()
    {
        $fakeSessionData = ['foo', 'bar'];
        $this->feUserMock->expects(self::once())->method('getKey')->with('ses', 'tx_solr_lastSearches')->willReturn($fakeSessionData);
        self::assertSame($fakeSessionData, $this->session->getLastSearches(), 'Session data from fe_user was not returned from session');
    }

    /**
     * @test
     */
    public function canSetLastSearchesInSession()
    {
        $lastSearches = ['TYPO3', 'solr'];
        $this->feUserMock->expects(self::once())->method('setKey')->with('ses', 'tx_solr_lastSearches', $lastSearches);
        $this->session->setLastSearches($lastSearches);
    }

    /**
     * @test
     */
    public function getHasPerPageReturnsFalseWhenNothingIsSet()
    {
        self::assertFalse($this->session->getHasPerPage(), 'Has per page should be false');
    }

    /**
     * @test
     */
    public function getPerPageReturnsZeroWhenNothingIsSet()
    {
        self::assertSame(0, $this->session->getPerPage(), 'Expected to get 0 when nothing was set');
    }

    /**
     * @test
     */
    public function getPerPageFromSessionData()
    {
        $fakeSessionData = 12;
        $this->feUserMock->expects(self::once())->method('getKey')->with('ses', 'tx_solr_resultsPerPage')->willReturn($fakeSessionData);
        self::assertSame(12, $this->session->getPerPage(), 'Could not get per page from session data');
    }

    /**
     * @test
     */
    public function canSetPerPageInSessionData()
    {
        $lastSearches = 45;
        $this->feUserMock->expects(self::once())->method('setKey')->with('ses', 'tx_solr_resultsPerPage', $lastSearches);
        $this->session->setPerPage($lastSearches);
    }
}
