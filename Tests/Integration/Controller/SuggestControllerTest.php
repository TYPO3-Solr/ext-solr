<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;

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

use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageFieldMappingIndexer;
use ApacheSolrForTypo3\Solr\Controller\SuggestController;
use TYPO3\CMS\Core\Http\Response;

/**
 * Integration testcase to test for {@link SuggestController}
 *
 * @author Timo Hund
 * @copyright (c) 2018 Timo Hund <timo.hund@dkd.de>
 * @group frontend
 */
class SuggestControllerTest extends AbstractFrontendControllerTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            @import \'EXT:solr/Configuration/TypoScript/Examples/Suggest/setup.typoscript\'
            '
        );
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'][PageFieldMappingIndexer::class] = PageFieldMappingIndexer::class;
    }

    /**
     * @test
     *
     * https://solr-ddev-site.ddev.site/content-examples/form-elements/search?type=7384&tx_solr[callback]=jQuery311041938492718528986_1642765952279&tx_solr%5BqueryString]=i&_=1642765952284
     */
    public function canDoABasicSuggest()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $result = (string)($this->executeFrontendSubRequestForSuggestQueryString('Sweat')->getBody());

        //we assume to get suggestions like Sweatshirt
        $this->assertStringContainsString('suggestions":{"sweatshirts":2}', $result, 'Response did not contain sweatshirt suggestions');
    }

    /**
     * @test
     */
    public function canSuggestWithUriSpecialChars()
    {
        $this->importDataSetFromFixture('can_suggest_with_uri_special_chars.xml');

        $this->addTypoScriptToTemplateRecord(
            1,
            '
			plugin.tx_solr.suggest.suggestField = title
            '
        );

        $this->indexPages([1, 2, 3, 4, 5]);

        // @todo: add more variants
        // @TODO: Check why does solr return some/larg instead of some/large
        $testCases = [
            [
                'prefix' => 'Some/',
                'expected' => 'suggestions":{"some/":1,"some/larg":1,"some/large/path":1}'
            ],
            [
                'prefix' => 'Some/Large',
                'expected' => 'suggestions":{"some/large/path":1}'
            ],
        ];
        foreach ($testCases as $testCase) {
            $this->expectSuggested($testCase['prefix'], $testCase['expected']);
        }
    }

    protected function expectSuggested(string $prefix, string $expected)
    {
        $result = (string)($this->executeFrontendSubRequestForSuggestQueryString($prefix)->getBody());

        //we assume to get suggestions like some/large/path
        $this->assertStringContainsString($expected, $result, 'Response did not contain expected suggestions: ' . $expected);
    }

    protected function executeFrontendSubRequestForSuggestQueryString(string $queryString): Response
    {
        return $this->executeFrontendSubRequest(
            $this->getPreparedRequest(1)
                ->withQueryParameter('type', '7384')
                ->withQueryParameter('tx_solr[queryString]', $queryString)
                ->withQueryParameter('tx_solr[callback]', 'rand')
        );
    }
}
