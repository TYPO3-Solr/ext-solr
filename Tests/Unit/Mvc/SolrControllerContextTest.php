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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Mvc\ControllerContext;

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
