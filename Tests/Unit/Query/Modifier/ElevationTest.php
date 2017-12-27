<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Query\Modifier;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetQueryBuilderRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetUrlDecoderRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Query\Modifier\Faceting;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Tests the ApacheSolrForTypo3\Solr\Query\Modifier\Elevation class
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ElevationTest extends UnitTest
{

    /**
     * @test
     */
    public function canModifiyQuery()
    {
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getSearchElevation')->willReturn(false);
        $configurationMock->expects($this->once())->method('getSearchElevationForceElevation')->willReturn(false);
        $configurationMock->expects($this->once())->method('getSearchElevationMarkElevatedResults')->willReturn(false);

        $requestMock = $this->getDumbMock(SearchRequest::class);
        $requestMock->expects($this->once())->method('getContextTypoScriptConfiguration')->willReturn($configurationMock);

        $query = $this->getDumbMock(Query::class);
        $query->expects($this->once())->method('setQueryElevation')->with(false, false, false);

        $modifier = new Query\Modifier\Elevation();
        $modifier->setSearchRequest($requestMock);
        $modifier->modifyQuery($query);
    }
}
