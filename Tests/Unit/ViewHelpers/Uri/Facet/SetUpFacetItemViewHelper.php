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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelpers\Uri\Facet;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class SetUpFacetItemViewHelper extends SetUpUnitTestCase
{
    protected function getTestColorFacet(): OptionsFacet
    {
        $searchRequest = new SearchRequest();

        $searchResultSetMock = $this->createMock(SearchResultSet::class);
        $searchResultSetMock
            ->expects(self::any())
            ->method('getUsedSearchRequest')
            ->willReturn($searchRequest);

        $facet = new OptionsFacet($searchResultSetMock, 'Color', 'color');
        $facet->addOption(
            new Option($facet, 'Red', 'red', 4)
        );

        return $facet;
    }
}
