<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Session;

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

    public function setUp()
    {
        parent::setUp();
        $this->feUserMock = $this->getDumbMock(FrontendUserAuthentication::class);
        $this->session = new FrontendUserSession($this->feUserMock);
    }

    /**
     * @test
     */
    public function getEmptyArrayWhenNoLastSearchesInSession()
    {
        $lastSearches = $this->session->getLastSearches();
        $this->assertSame([], $lastSearches, 'Expected to get an empty lastSearches array');
    }

    /**
     * @test
     */
    public function sessionDataWillBeRetrievedFromSessionForLastSearches()
    {
        $fakeSessionData = ['foo', 'bar'];
        $this->feUserMock->expects($this->once())->method('getKey')->with('ses', 'tx_solr_lastSearches')->will($this->returnValue($fakeSessionData));
        $this->assertSame($fakeSessionData, $this->session->getLastSearches(), 'Session data from fe_user was not returned from session');
    }

    /**
     * @test
     */
    public function canSetLastSearchesInSession()
    {
        $lastSearches = ['TYPO3', 'solr'];
        $this->feUserMock->expects($this->once())->method('setKey')->with('ses', 'tx_solr_lastSearches', $lastSearches);
        $this->session->setLastSearches($lastSearches);
    }

    /**
     * @test
     */
    public function getHasPerPageReturnsFalseWhenNothingIsSet()
    {
        $this->assertFalse($this->session->getHasPerPage(), 'Has per page should be false');
    }

    /**
     * @test
     */
    public function getPerPageReturnsZeroWhenNothingIsSet()
    {
        $this->assertSame(0, $this->session->getPerPage(), 'Expected to get 0 when nothing was set');
    }

    /**
     * @test
     */
    public function getPerPageFromSessionData()
    {
        $fakeSessionData = 12;
        $this->feUserMock->expects($this->once())->method('getKey')->with('ses', 'tx_solr_resultsPerPage')->will($this->returnValue($fakeSessionData));
        $this->assertSame(12, $this->session->getPerPage(), 'Could not get per page from session data');
    }

    /**
     * @test
     */
    public function canSetPerPageInSessionData()
    {
        $lastSearches = 45;
        $this->feUserMock->expects($this->once())->method('setKey')->with('ses', 'tx_solr_resultsPerPage', $lastSearches);
        $this->session->setPerPage($lastSearches);
    }

}