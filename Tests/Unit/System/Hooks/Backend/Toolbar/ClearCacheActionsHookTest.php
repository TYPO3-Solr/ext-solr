<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Hooks\Backend\Toolbar;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Hooks\Backend\Toolbar\ClearCacheActionsHook;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Backend\Routing\UriBuilder;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ClearCacheActionsHookTest extends UnitTest
{

    /**
     * @var BackendUserAuthentication
     */
    protected $backendUserMock;

    /**
     * @var UriBuilder
     */
    protected $uriBuilderMock;

    /**
     * @var ClearCacheActionsHook
     */
    protected $hook;

    public function setUp(): void
    {
        $this->backendUserMock = $this->getDumbMock(BackendUserAuthentication::class);
        $this->uriBuilderMock = $this->getDumbMock(UriBuilder::class);
        $this->hook = new ClearCacheActionsHook($this->uriBuilderMock, $this->backendUserMock);
    }

    /**
     * @test
     */
    public function entryIsNotBuildForNormalUser()
    {
        $this->backendUserMock->expects($this->once())->method('isAdmin')->willReturn(false);

        $cacheActions = [];
        $optionValues = [];
        $this->hook->manipulateCacheActions($cacheActions, $optionValues);

        $this->assertEmpty($cacheActions);
        $this->assertEmpty($optionValues);
    }

    /**
     * @test
     */
    public function entryIsBuildWhenAdminIsLoggedIn()
    {
        $this->backendUserMock->expects($this->once())->method('isAdmin')->willReturn(true);
        $this->uriBuilderMock->expects($this->once())->method('buildUriFromRoute')->willReturn('myuri');

        $cacheActions = [];
        $optionValues = [];
        $this->hook->manipulateCacheActions($cacheActions, $optionValues);

        $this->assertSame($cacheActions[0]['href'], 'myuri', 'Cache actions where not extended');
    }
}