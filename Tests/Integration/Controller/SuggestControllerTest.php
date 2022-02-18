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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;

use ApacheSolrForTypo3\Solr\Controller\SuggestController;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageFieldMappingIndexer;
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
    protected function setUp(): void
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
     */
    public function canDoABasicSuggest()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $result = (string)($this->executeFrontendSubRequestForSuggestQueryString('Sweat', 'rand')->getBody());

        //we assume to get suggestions like Sweatshirt
        self::assertStringContainsString('suggestions":{"sweatshirts":2}', $result, 'Response did not contain sweatshirt suggestions');
    }

    /**
     * @test
     */
    public function canDoABasicSuggestWithoutCallback()
    {
        $this->importDataSetFromFixture('SearchAndSuggestControllerTest_indexing_data.xml');
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $result = (string)($this->executeFrontendSubRequestForSuggestQueryString('Sweat')->getBody());

        //we assume to get suggestions like Sweatshirt
        self::assertStringContainsString('suggestions":{"sweatshirts":2}', $result, 'Response did not contain sweatshirt suggestions');
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
                'expected' => 'suggestions":{"some/":1,"some/larg":1,"some/large/path":1}',
            ],
            [
                'prefix' => 'Some/Large',
                'expected' => 'suggestions":{"some/large/path":1}',
            ],
        ];
        foreach ($testCases as $testCase) {
            $this->expectSuggested($testCase['prefix'], $testCase['expected']);
        }
    }

    protected function expectSuggested(string $prefix, string $expected)
    {
        $result = (string)($this->executeFrontendSubRequestForSuggestQueryString($prefix, 'rand')->getBody());

        //we assume to get suggestions like some/large/path
        self::assertStringContainsString($expected, $result, 'Response did not contain expected suggestions: ' . $expected);
    }

    protected function executeFrontendSubRequestForSuggestQueryString(string $queryString, string $callback = null): Response
    {
        $request = $this->getPreparedRequest(1)
            ->withQueryParameter('type', '7384')
            ->withQueryParameter('tx_solr[queryString]', $queryString);

        if ($callback !== null) {
            $request = $request->withQueryParameter('tx_solr[callback]', $callback);
        }
        return $this->executeFrontendSubRequest($request);
    }
}
