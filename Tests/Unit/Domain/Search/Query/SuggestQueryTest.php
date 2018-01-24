<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Query;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2016 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper\EscapeService;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\SuggestQuery;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
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
        $this->markTestSkipped('todo');
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['variants'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['variants.'] = [
            'variantField' => 'myField'
        ];

        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $suggestQuery = new SuggestQuery('typ', $fakeConfiguration);
        $this->assertFalse($suggestQuery->getFieldCollapsing()->getIsEnabled(), 'Collapsing should never be active for a suggest query, even when active');
    }

    /**
     * @test
     */
    public function testSuggestQueryUsesFilterList()
    {
        $this->markTestSkipped('todo');
        $fakeConfiguration = new TypoScriptConfiguration([]);
        $suggestQuery = new SuggestQuery('typ', $fakeConfiguration);
        $suggestQuery->getFilters()->add('+type:pages');
        $queryParameters = $suggestQuery->getQueryParameters();
        $this->assertSame('+type:pages', $queryParameters['fq'][0], 'Filter was not added to the suggest query parameters');
    }
}
