<?php
namespace ApacheSolrForTypo3\Solr\Test\ViewHelpers\Uri\Facet;

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

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractFacetItemViewHelperTest extends UnitTest
{
    /**
     * @return OptionsFacet
     */
    protected function getTestColorFacet()
    {
        $searchRequest = new SearchRequest();
        $searchResultSetMock = $this->getDumbMock(SearchResultSet::class);
        $searchResultSetMock->expects($this->any())->method('getUsedSearchRequest')->will($this->returnValue($searchRequest));

        $facet = new OptionsFacet($searchResultSetMock, 'Color', 'color');
        $option = new Option($facet, 'Red', 'red', 4);
        $facet->addOption($option);

        return $facet;
    }
}
