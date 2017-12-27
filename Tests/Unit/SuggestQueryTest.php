<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit;

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
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\SuggestQuery;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;

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
            'variantField' => 'myField'
        ];

        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $siteHashServiceMock = $this->getDumbMock(SiteHashService::class);
        $escapeServiceMock = $this->getDumbMock(EscapeService::class);
        $solrLogManagerMock = $this->getDumbMock(SolrLogManager::class);

        $suggestQuery = new SuggestQuery('typ', $fakeConfiguration, $siteHashServiceMock, $escapeServiceMock, $solrLogManagerMock);

        $this->assertFalse($suggestQuery->getIsCollapsing(), 'Collapsing should never be active for a suggest query, even when active');
    }
}
