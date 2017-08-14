<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets\OptionBased\Options;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Unit test for the OptionsFacet
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class OptionCollectionTest extends UnitTest
{
    /**
     * @test
     */
    public function canGetManualSortedCopy()
    {
        $searchResultSetMock = $this->getDumbMock(SearchResultSet::class);
        $facet = new OptionsFacet($searchResultSetMock, 'colors', 'colors_s');

        $red = new Option($facet, 'Rubin Red', 'red', 9);
        $blue = new Option($facet, 'Polar Blue', 'blue', 12);
        $yellow = new Option($facet, 'Lemon Yellow', 'yellow', 3);

        $facet->addOption($red);
        $facet->addOption($blue);
        $facet->addOption($yellow);

        $sortedOptions = $facet->getOptions()->getManualSortedCopy(['yellow', 'blue']);

        $this->assertSame($yellow, $sortedOptions->getByPosition(0), 'First sorted item was not yellow');
        $this->assertSame($blue, $sortedOptions->getByPosition(1), 'First sorted item was not blue');
        $this->assertSame($red, $sortedOptions->getByPosition(2), 'First sorted item was not blue');
    }
}
