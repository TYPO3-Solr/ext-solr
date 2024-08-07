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
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Testcase for the SchemaParser class.
 */
class FrontendUserSessionTest extends SetUpUnitTestCase
{
    protected FrontendUserAuthentication|MockObject $feUserMock;
    protected FrontendUserSession $session;

    protected function setUp(): void
    {
        $this->feUserMock = $this->createMock(FrontendUserAuthentication::class);
        $this->session = new FrontendUserSession($this->feUserMock);
        parent::setUp();
    }

    #[Test]
    public function getEmptyArrayWhenNoLastSearchesInSession(): void
    {
        $lastSearches = $this->session->getLastSearches();
        self::assertSame([], $lastSearches, 'Expected to get an empty lastSearches array');
    }

    #[Test]
    public function sessionDataWillBeRetrievedFromSessionForLastSearches(): void
    {
        $fakeSessionData = ['foo', 'bar'];
        $this->feUserMock->expects(self::once())->method('getKey')->with('ses', 'tx_solr_lastSearches')->willReturn($fakeSessionData);
        self::assertSame($fakeSessionData, $this->session->getLastSearches(), 'Session data from fe_user was not returned from session');
    }

    #[Test]
    public function canSetLastSearchesInSession(): void
    {
        $lastSearches = ['TYPO3', 'solr'];
        $this->feUserMock->expects(self::once())->method('setKey')->with('ses', 'tx_solr_lastSearches', $lastSearches);
        $this->session->setLastSearches($lastSearches);
    }

    #[Test]
    public function getHasPerPageReturnsFalseWhenNothingIsSet(): void
    {
        self::assertFalse($this->session->getHasPerPage(), 'Has per page should be false');
    }

    #[Test]
    public function getPerPageReturnsZeroWhenNothingIsSet(): void
    {
        self::assertSame(0, $this->session->getPerPage(), 'Expected to get 0 when nothing was set');
    }

    #[Test]
    public function getPerPageFromSessionData(): void
    {
        $fakeSessionData = 12;
        $this->feUserMock->expects(self::once())->method('getKey')->with('ses', 'tx_solr_resultsPerPage')->willReturn($fakeSessionData);
        self::assertSame(12, $this->session->getPerPage(), 'Could not get per page from session data');
    }

    #[Test]
    public function canSetPerPageInSessionData(): void
    {
        $lastSearches = 45;
        $this->feUserMock->expects(self::once())->method('setKey')->with('ses', 'tx_solr_resultsPerPage', $lastSearches);
        $this->session->setPerPage($lastSearches);
    }
}
