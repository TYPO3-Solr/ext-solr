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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Query;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\SuggestQuery;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Tests the ApacheSolrForTypo3\Solr\SuggestQuery class
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SuggestQueryTest extends UnitTest
{
    /**
     * @test
     */
    public function testSuggestQueryDoesNotUseFieldCollapsing()
    {
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['variants'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['variants.'] = [
            'variantField' => 'myField',
        ];

        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $queryBuilder = new QueryBuilder($fakeConfiguration);
        $suggestQuery = $queryBuilder->newSuggestQuery('type')->getQuery();

        self::assertNull($suggestQuery->getFilterQuery('fieldCollapsing'), 'Collapsing should never be active for a suggest query, even when active');
    }

    /**
     * @test
     */
    public function testSuggestQueryUsesFilterList()
    {
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $suggestQuery = new SuggestQuery('typ', $fakeConfiguration);

        $queryBuilder = new QueryBuilder($fakeConfiguration);
        $queryBuilder->startFrom($suggestQuery)->useFilter('+type:pages');
        $queryParameters = $suggestQuery->getRequestBuilder()->build($suggestQuery)->getParams();
        self::assertSame('+type:pages', $queryParameters['fq'], 'Filter was not added to the suggest query parameters');
    }
}
