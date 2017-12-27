<?php
namespace ApacheSolrForTypo3\Solr\Test\Mvc\ControllerContext;

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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SolrControllerContextTest extends UnitTest
{
    /**
     * @var SolrControllerContext
     */
    protected $controllerContext = null;

    public function setUp()
    {
        $this->controllerContext = new SolrControllerContext();
    }

    /**
     * @test
     */
    public function canSetTypoScriptConfiguration()
    {
        /** @var TypoScriptConfiguration $typoScriptConfigurationMock */
        $typoScriptConfigurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->controllerContext->setTypoScriptConfiguration($typoScriptConfigurationMock);
        $this->assertSame($this->controllerContext->getTypoScriptConfiguration(), $typoScriptConfigurationMock, 'Can not get and set TypoScriptConfiguration');
    }

    /**
     * @test
     */
    public function canSetSearchResultSet()
    {
        $searchResultSetMock = $this->getDumbMock(SearchResultSet::class);
        $this->controllerContext->setSearchResultSet($searchResultSetMock);
        $this->assertSame($this->controllerContext->getSearchResultSet(), $searchResultSetMock, 'Can not get and set SearchResultSet');
    }
}
