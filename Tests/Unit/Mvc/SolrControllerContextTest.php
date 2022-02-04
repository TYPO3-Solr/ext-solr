<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Mvc\ControllerContext;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SolrControllerContextTest extends UnitTest
{
    /**
     * @var SolrControllerContext
     */
    protected $controllerContext;

    protected function setUp(): void
    {
        $this->controllerContext = new SolrControllerContext();
        parent::setUp();
    }

    /**
     * @test
     */
    public function canSetTypoScriptConfiguration()
    {
        /** @var TypoScriptConfiguration $typoScriptConfigurationMock */
        $typoScriptConfigurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->controllerContext->setTypoScriptConfiguration($typoScriptConfigurationMock);
        self::assertSame($this->controllerContext->getTypoScriptConfiguration(), $typoScriptConfigurationMock, 'Can not get and set TypoScriptConfiguration');
    }

    /**
     * @test
     */
    public function canSetSearchResultSet()
    {
        $searchResultSetMock = $this->getDumbMock(SearchResultSet::class);
        $this->controllerContext->setSearchResultSet($searchResultSetMock);
        self::assertSame($this->controllerContext->getSearchResultSet(), $searchResultSetMock, 'Can not get and set SearchResultSet');
    }
}
