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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup\QueryGroupFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Unit test for the QueryGroupFacet
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Frans Saris <frans@beech.it>
 */
class QueryGroupFacetTest extends SetUpUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    #[Test]
    public function canGetTitleFromOptionsFacet(): void
    {
        $resultSetMock = $this->createMock(SearchResultSet::class);
        $optionsFacet = new QueryGroupFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle');
        self::assertSame('myTitle', $optionsFacet->getLabel(), 'Could not get title from queryGroup facet');
    }

    #[Test]
    public function canAddOptionsToFacet(): void
    {
        $resultSetMock = $this->createMock(SearchResultSet::class);
        $queryGroupFacet = new QueryGroupFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle');
        $option = new Option($queryGroupFacet);

        // before adding there should not be any facet present
        // @extensionScannerIgnoreLine
        self::assertEquals(0, $queryGroupFacet->getOptions()->getCount());
        $queryGroupFacet->addOption($option);

        // now we should have 1 option present
        // @extensionScannerIgnoreLine
        self::assertEquals(1, $queryGroupFacet->getOptions()->getCount());
    }

    #[Test]
    public function getDefaultPartialName(): void
    {
        $resultSetMock = $this->createMock(SearchResultSet::class);
        $queryGroupFacet = new QueryGroupFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle');

        self::assertEquals('Options', $queryGroupFacet->getPartialName());
    }

    #[Test]
    public function getCustomPartialName(): void
    {
        $resultSetMock = $this->createMock(SearchResultSet::class);
        $queryGroupFacet = new QueryGroupFacet($resultSetMock, 'myFacet', 'myFacetFieldName', 'myTitle', ['partialName' => 'MyPartial']);

        self::assertEquals('MyPartial', $queryGroupFacet->getPartialName());
    }
}
