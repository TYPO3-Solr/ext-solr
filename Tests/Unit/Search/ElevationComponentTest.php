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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Search;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Event\Search\AfterSearchQueryHasBeenPreparedEvent;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Search\ElevationComponent;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests the ApacheSolrForTypo3\Solr\Query\Modifier\Elevation class
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ElevationComponentTest extends SetUpUnitTestCase
{
    #[Test]
    public function canModifyQuery(): void
    {
        $query = $this->createMock(Query::class);

        $queryBuilderMock = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([null, null, $this->createMock(SiteHashService::class)])
            ->disableProxyingToOriginalMethods()
            ->getMock();
        $queryBuilderMock->expects(self::once())->method('startFrom')->willReturn($queryBuilderMock);
        $queryBuilderMock->expects(self::once())->method('useElevationFromTypoScript')->willReturn($queryBuilderMock);
        $queryBuilderMock->expects(self::once())->method('getQuery')->willReturn($query);

        $modifier = new ElevationComponent($queryBuilderMock);
        $modifier->__invoke(
            new AfterSearchQueryHasBeenPreparedEvent($query, $this->createMock(SearchRequest::class), $this->createMock(Search::class), $this->createMock(TypoScriptConfiguration::class))
        );
    }
}
