<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Query;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Tests the ApacheSolrForTypo3\Solr\Query class
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class QueryTest extends UnitTest
{

    /**
     * @test
     */
    public function noFiltersAreSetAfterInitialization()
    {
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query',
            'test');
        $filters = $query->getFilters();

        $this->assertTrue(
            empty($filters),
            'Query already contains filters after intialization.'
        );
    }

    /**
     * @test
     */
    public function addsCorrectAccessFilterForAnonymousUser()
    {
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query',
            'test');
        $query->setUserAccessGroups(array(-1, 0));
        $filters = $query->getFilters();

        $this->assertContains(
            '{!typo3access}-1,0',
            $filters,
            'Access filter not found in [' . implode('], [', $filters) . ']'
        );
    }

    /**
     * @test
     */
    public function grantsAccessToGroupZeroIfNoGroupsProvided()
    {
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query',
            'test');
        $query->setUserAccessGroups(array());
        $filters = $query->getFilters();

        $this->assertContains(
            '{!typo3access}0',
            $filters,
            'Access filter not found in [' . implode('], [', $filters) . ']'
        );
    }

    /**
     * @test
     */
    public function grantsAccessToGroupZeroIfZeroNotProvided()
    {
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query',
            'test');
        $query->setUserAccessGroups(array(5));
        $filters = $query->getFilters();

        $this->assertContains(
            '{!typo3access}0,5',
            $filters,
            'Access filter not found in [' . implode('], [', $filters) . ']'
        );
    }

    /**
     * @test
     */
    public function filtersDuplicateAccessGroups()
    {
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query',
            'test');
        $query->setUserAccessGroups(array(1, 1));
        $filters = $query->getFilters();

        $this->assertContains(
            '{!typo3access}0,1',
            $filters,
            'Access filter not found in [' . implode('], [', $filters) . ']'
        );
    }

    /**
     * @test
     */
    public function allowsOnlyOneAccessFilter()
    {
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query',
            'test');
        $query->setUserAccessGroups(array(1));
        $query->setUserAccessGroups(array(2));
        $filters = $query->getFilters();

        $this->assertSame(
            count($filters),
            1,
            'Too many filters in [' . implode('], [', $filters) . ']'
        );
    }

    // TODO if user is in group -2 (logged in), disallow access to group -1


    // grouping


    /**
     * @test
     */
    public function groupingIsNotActiveAfterInitialization()
    {
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query',
            'test');

        $queryParameters = $query->getQueryParameters();
        foreach ($queryParameters as $queryParameter => $value) {
            $this->assertTrue(
                !GeneralUtility::isFirstPartOfStr($queryParameter, 'group'),
                'Query already contains grouping parameter "' . $queryParameter . '"'
            );
        }
    }

    /**
     * @test
     */
    public function settingGroupingTrueActivatesGrouping()
    {
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query',
            'test');

        $query->setGrouping(true);

        $queryParameters = $query->getQueryParameters();
        $this->assertArrayHasKey('group', $queryParameters);
        $this->assertEquals('true', $queryParameters['group']);

        $this->assertArrayHasKey('group.format', $queryParameters);
        $this->assertEquals('grouped', $queryParameters['group.format']);

        $this->assertArrayHasKey('group.ngroups', $queryParameters);
        $this->assertEquals('true', $queryParameters['group.ngroups']);

        return $query;
    }

    /**
     * @test
     * @depends settingGroupingTrueActivatesGrouping
     */
    public function settingGroupingFalseDeactivatesGrouping(Query $query)
    {
        $query->setGrouping(false);

        $queryParameters = $query->getQueryParameters();

        foreach ($queryParameters as $queryParameter => $value) {
            $this->assertTrue(
                !GeneralUtility::isFirstPartOfStr($queryParameter, 'group'),
                'Query contains grouping parameter "' . $queryParameter . '"'
            );
        }
    }

    /**
     * @test
     */
    public function canEnableHighlighting()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test');
        $query->setHighlighting(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame("true", $queryParameters["hl"], 'Enable highlighting did not set the "hl" query parameter');
        $this->assertSame(200, $queryParameters["hl.fragsize"], 'hl.fragsize was not set to the default value of 200');
    }

    /**
     * @test
     */
    public function canSetHighlightingFieldList()
    {
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['results.']['resultsHighlighting.']['highlightFields'] = 'title';
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);
        $query->setHighlighting(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame("true", $queryParameters["hl"], 'Enable highlighting did not set the "hl" query parameter');
        $this->assertSame("title", $queryParameters["hl.fl"], 'Can set highlighting field list');
    }

    /**
     * @test
     */
    public function canPassCustomWrapForHighlighting()
    {
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['results.']['resultsHighlighting.']['wrap'] = '[A]|[B]';
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);
        $query->setHighlighting(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame("[A]", $queryParameters["hl.tag.pre"], 'Can set highlighting hl.tag.pre');
        $this->assertSame("[B]", $queryParameters["hl.tag.post"], 'Can set highlighting hl.tag.post');
        $this->assertSame("[A]", $queryParameters["hl.simple.pre"], 'Can set highlighting hl.tag.pre');
        $this->assertSame("[B]", $queryParameters["hl.simple.post"], 'Can set highlighting hl.tag.post');
    }

    /**
     * @test
     */
    public function simplePreAndPostIsUsedWhenFastVectorHighlighterCouldNotBeUsed()
    {
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['results.']['resultsHighlighting.']['wrap'] = '[A]|[B]';
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);
        
        // fragSize 10 is to small for FastVectorHighlighter
        $query->setHighlighting(true, 17);
        $queryParameters = $query->getQueryParameters();
        $this->assertSame("[A]", $queryParameters["hl.simple.pre"], 'Can set highlighting field list');
        $this->assertSame("[B]", $queryParameters["hl.simple.post"], 'Can set highlighting field list');
        $this->assertEmpty($queryParameters["hl.tag.pre"], 'When the highlighting fragment size is to small hl.tag.pre should not be used because FastVectoreHighlighter will not be used');
        $this->assertEmpty($queryParameters["hl.tag.post"], 'When the highlighting fragment size is to small hl.tag.post should not be used because FastVectoreHighlighter will not be used');
    }

    /**
     * @test
     */
    public function canUseFastVectorHighlighting()
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);
        $query->setHighlighting(true, 200);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame("true", $queryParameters["hl"], 'Enable highlighting did not set the "hl" query parameter');
        $this->assertSame("true", $queryParameters["hl.useFastVectorHighlighter"], 'Enable highlighting did not set the "hl.useFastVectorHighlighter" query parameter');
    }

    /**
     * @test
     */
    public function fastVectorHighlighterIsDisabledWhenFragSizeIsLessThen18()
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);
        $query->setHighlighting(true, 0);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame("true", $queryParameters["hl"], 'Enable highlighting did not set the "hl" query parameter');
        $this->assertNull($queryParameters["hl.useFastVectorHighlighter"], 'FastVectorHighlighter was disabled but still requested');
    }


    /**
     * @test
     */
    public function canGetQueryFieldsAsStringWhenPassedFromConfiguration()
    {
        $input = 'content^10, title^5';
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['queryFields'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);

        $output = $query->getQueryFieldsAsString();
        $expectedOutput = 'content^10.0 title^5.0';

        $this->assertSame($output, $expectedOutput, 'Passed and retrieved query fields are not the same');
    }

    /**
     * @test
     */
    public function canReturnEmptyStringAsQueryFieldStringWhenNothingWasPassed()
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);

        $output = $query->getQueryFieldsAsString();
        $expectedOutput = '';

        $this->assertSame($output, $expectedOutput, 'Unexpected output from getQueryFieldsAsString when no configuration was passed');
    }

    /**
     * @test
     */
    public function canReturnFieldListWhenConfigurationWithReturnFieldsWasPassed()
    {
        $input = 'abstract, price';
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['query.']['returnFields'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);

        $output = $query->getFieldList();
        $expectedOutput = array('abstract', 'price');
        $this->assertSame($output, $expectedOutput, 'Did not parse returnsFields as expected');
    }

    /**
     * @test
     */
    public function canReturnDefaultFieldListWhenNoConfigurationWasPassed()
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);

        $output = $query->getFieldList();
        $expectedOutput = array('*', 'score');
        $this->assertSame($output, $expectedOutput, 'Did not parse returnsFields as expected');
    }

    /**
     * @test
     */
    public function canSetTargetPageFromConfiguration()
    {
        $input = 4711;
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['targetPage'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);
        $this->assertEquals($input, $query->getLinkTargetPageId());
    }

    /**
     * @test
     */
    public function canFallbackToTSFEIdWhenNoTargetPageConfigured()
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray, 8000);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);
        $this->assertEquals(8000, $query->getLinkTargetPageId());
    }

    /**
     * @test
     */
    public function canEnableFaceting()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test');
        $query->setFaceting(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame("true", $queryParameters["facet"], 'Enable faceting did not set the "facet" query parameter');
    }

    /**
     * @test
     */
    public function canUseFacetMinCountFromConfiguration()
    {
        $input = 10;
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['minimumCount'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);
        $query->setFaceting(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame(10, $queryParameters["facet.mincount"], 'Can not use facet.minimumCount from configuration');
    }

    /**
     * @test
     */
    public function canUseFacetSortByFromConfiguration()
    {
        $input = 'alpha';
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['sortBy'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);
        $query->setFaceting(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame('index', $queryParameters["facet.sort"], 'Can not use facet.sort from configuration');
    }

    /**
     * @test
     */
    public function canSetSpellChecking()
    {
        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test');
        $query->setSpellchecking(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame("true", $queryParameters["spellcheck"], 'Enable spellchecking did not set the "spellcheck" query parameter');
    }

    /**
     * @test
     */
    public function canTestNumberOfSuggestionsToTryFromConfiguration()
    {
        $input = 9;
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['spellchecking.']['numberOfSuggestionsToTry'] = $input;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);
        $query->setSpellchecking(true);
        $queryParameters = $query->getQueryParameters();

        $this->assertSame($input, $queryParameters['spellcheck.maxCollationTries'], 'Could not set spellcheck.maxCollationTries as expected');
    }

    /**
     * @test
     */
    public function canWriteALogForAFilterWhenLoggingIsEnabled()
    {
        $fakeConfigurationArray = array();
        $fakeConfigurationArray['plugin.']['tx_solr.']['logging.']['query.']['filters'] = true;
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = $this->getMock('ApacheSolrForTypo3\Solr\Query', array('writeDevLog'), array('test', $fakeConfiguration));

        $query->expects($this->once())->method('writeDevLog');
        $query->addFilter('foo');
    }

    /**
     * @return array
     */
    public function escapeQueryDataProvider()
    {
        return array(
            'empty' => array('input' => '', 'expectedOutput' => ''),
            'simple' => array('input' => 'foo', 'expectedOutput' => 'foo'),
            'single quoted word' => array('input' => '"world"', 'expectedOutput' => '"world"'),
            'simple quoted phrase' => array('input' => '"hello world"', 'expectedOutput' => '"hello world"'),
            'simple quoted phrase with ~' => array('input' => '"hello world~"', 'expectedOutput' => '"hello world~"'),
            'simple phrase with ~' => array('input' => 'hello world~', 'expectedOutput' => 'hello world\~'),
            'single quote' =>  array('input' => '20" monitor', 'expectedOutput' => '20\" monitor'),
            'rounded brackets many words' => array('input' => 'hello (world)', 'expectedOutput' => 'hello \(world\)'),
            'rounded brackets one word' => array('input' => '(world)', 'expectedOutput' => '\(world\)'),
            'plus character is kept' => array('input' => 'foo +bar -world', 'expectedOutput' => 'foo +bar -world'),
            '&& character is kept' => array('input' => 'hello && world', 'expectedOutput' => 'hello && world'),
            '! character is kept' => array('input' => 'hello !world', 'expectedOutput' => 'hello !world'),
            '* character is kept' => array('input' => 'hello *world', 'expectedOutput' => 'hello *world'),
            '? character is kept' => array('input' => 'hello ?world', 'expectedOutput' => 'hello ?world'),
            'ö character is kept' => array('input' => 'schöner tag', 'expectedOutput' => 'schöner tag'),
            'numeric is kept' => array('input' => 42, 'expectedOutput' => 42),
            'combined quoted phrase' => array('input' => '"hello world" or planet', 'expectedOutput' => '"hello world" or planet'),
            'two combined quoted phrases' => array('input' => '"hello world" or "hello planet"', 'expectedOutput' => '"hello world" or "hello planet"'),
            'combined quoted phrase mixed with escape character' => array('input' => '"hello world" or (planet)', 'expectedOutput' => '"hello world" or \(planet\)')
        );
    }

    /**
     * @dataProvider escapeQueryDataProvider
     * @test
     */
    public function canEscapeAsExpected($input, $expectedOutput)
    {
        $fakeConfigurationArray = array();
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test', $fakeConfiguration);

        $output = $query->escape($input);
        $this->assertSame($expectedOutput, $output, 'Query was not escaped as expected');
    }
}
