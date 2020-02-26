<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\BigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\PhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\QueryFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Slops;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\TrigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Typo3PageIndexer;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Solarium\QueryType\Select\Query\Query;

/**
 * Test class to perform a search on a real solr server
 *
 * @author Timo Schmidt
 */
class SearchTest extends IntegrationTest
{

    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;


    public function setUp()
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->queryBuilder = new QueryBuilder(new TypoScriptConfiguration([]));
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * @test
     */
    public function canSearchForADocument()
    {
        $this->importDataSetFromFixture('can_search.xml');

        $GLOBALS['TT'] = $this->getMockBuilder(TimeTracker::class)->disableOriginalConstructor()->getMock();
        $fakeTSFE = $this->getConfiguredTSFE();
        $GLOBALS['TSFE'] = $fakeTSFE;

        /** @var $pageIndexer \ApacheSolrForTypo3\Solr\Typo3PageIndexer */
        $pageIndexer = GeneralUtility::makeInstance(Typo3PageIndexer::class, $fakeTSFE);
        $pageIndexer->indexPage();

        $this->waitToBeVisibleInSolr();

            /** @var $searchInstance \ApacheSolrForTypo3\Solr\Search */
        $searchInstance = GeneralUtility::makeInstance(Search::class);

        $query = $this->queryBuilder
            ->newSearchQuery('hello')
            ->useQueryFields(QueryFields::fromString('content^40.0, title^5.0, keywords^2.0, tagsH1^5.0, tagsH2H3^3.0, tagsH4H5H6^2.0, tagsInline^1.0, description^4.0, abstract^1.0, subtitle^1.0, navtitle^1.0, author^1.0'))
            ->getQuery();

        $searchResponse = $searchInstance->search($query);
        $rawResponse = $searchResponse->getRawResponse();
        $this->assertContains('"numFound":1', $rawResponse, 'Could not index document into solr');
        $this->assertContains('"title":"Hello Search Test"', $rawResponse, 'Could not index document into solr');
    }

    /**
     * @test
     */
    public function implicitPhraseSearchingBoostsDocsWithOccurringPhrase()
    {
        $this->fillIndexForPhraseSearchTests();

        /** @var $searchInstance \ApacheSolrForTypo3\Solr\Search */
        $searchInstance = GeneralUtility::makeInstance(Search::class);

        $query = $this->queryBuilder
            ->newSearchQuery('Hello World')
            ->getQuery();

        $searchResponse = $searchInstance->search($query);
        $parsedData = $searchResponse->getParsedData();
        // document with "Hello World for phrase serchin" is not on first place!
        // @extensionScannerIgnoreLine
        $this->assertGreaterThan(0, $parsedData->response->numFound, 'Could not index document into solr');
        // @extensionScannerIgnoreLine
        $this->assertNotEquals('Hello World for phrase serching', $parsedData->response->docs[0]->getTitle(), 'Unexpected score calculation. Expected Document shouldn\'t be at first place.');

        // Boost the document with query to make it first.
        $query = $this->queryBuilder->startFrom($query)->usePhraseFields(PhraseFields::fromString('title^10.0'))->getQuery();
        $searchResponse = $searchInstance->search($query);
        $parsedData = $searchResponse->getParsedData();

        // @extensionScannerIgnoreLine
        $this->assertGreaterThan(0, $parsedData->response->numFound, 'Could not index document into solr');
        // @extensionScannerIgnoreLine
        $this->assertSame('Hello World for phrase searching', $parsedData->response->docs[0]->getTitle(), 'Unexpected score calculation. Document');
    }

    /**
     * @test
     */
    public function implicitPhraseSearchSloppyPhraseBoostCanBeAdjustedByPhraseSlop()
    {
        $this->fillIndexForPhraseSearchTests();

        /** @var $searchInstance \ApacheSolrForTypo3\Solr\Search */
        $searchInstance = GeneralUtility::makeInstance(Search::class);

        $query = $this->queryBuilder
            ->newSearchQuery('Hello World')
            ->getQuery();


        // Boost the document with query to make it first.
        $query = $this->queryBuilder->startFrom($query)->usePhraseFields(PhraseFields::fromString('title^10.0'))->getQuery();
        // do following things

        // test different phrase slop values
        $parsedDatasByPhraseSlop = [];
        for ($i = 0; $i <= 2; $i++) {
            $slops = new Slops();
            $slops->setPhraseSlop($i);
            $query = $this->queryBuilder->startFrom($query)->useSlops($slops)->getQuery();

            $searchResponse = $searchInstance->search($query);
            $parsedDatasByPhraseSlop[$i] = $searchResponse->getParsedData();
        }

        /* by changing of phrase slop values the documents with sloppy phrases move to top.
          +------------------+---------------------------------|
          | ranking position | document UID @ phraseSlop value |
          +------------------+---------------------------------+
          |         0        |   14 @ 0 || 14 @ 1 || 14 @ 2    |
          +------------------+---------------------------------+
          |         1        |    1 @ 0 || 11 @ 1 || 11 @ 2    |
          +------------------+---------------------------------+
          |         2        |    2 @ 0 || 12 @ 1 || 12 @ 2    |
          +------------------+---------------------------------+
          |         3        |    3 @ 0 ||  1 @ 1 ||  1 @ 2    |
          +------------------+---------------------------------+
          |         4        |    4 @ 0 ||  2 @ 1 ||  3 @ 2    |
          +------------------+---------------------------------+
          |         5        |    5 @ 0 ||  3 @ 1 ||  4 @ 2    |
          +------------------+---------------------------------+
          |         6        |   11 @ 0 ||  4 @ 1 ||  5 @ 2    |
          +------------------+---------------------------------+
          |         7        |   12 @ 0 ||  5 @ 1 ||  2 @ 2    |
          +------------------+---------------------------------+
          |         8        |   13 @ 0 || 13 @ 1 || 13 @ 2    |
          +------------------+---------------------------------+
        */
        // Note positons beginning by 0 = first
        // first position is the same for all three slop values
        // @extensionScannerIgnoreLine
        $this->assertTrue($parsedDatasByPhraseSlop[0]->response->docs[0]->getUid() === $parsedDatasByPhraseSlop[1]->response->docs[0]->getUid()
            // @extensionScannerIgnoreLine
                && $parsedDatasByPhraseSlop[0]->response->docs[0]->getUid() === $parsedDatasByPhraseSlop[2]->response->docs[0]->getUid()
        , 'Phrase search does not work properly. Solr should position the document independent from slop value at first position.');
        // the slop value of 1 moves doc UID = 11 to the second position
        // @extensionScannerIgnoreLine
        $this->assertSame(11, $parsedDatasByPhraseSlop[1]->response->docs[1]->getUid(), 'Phrase slop setting does not work as expected.');
        // the slop value of 2 moves doc UID = 3 to the fifth position
        // @extensionScannerIgnoreLine
        $this->assertSame(3, $parsedDatasByPhraseSlop[2]->response->docs[4]->getUid(), 'Phrase slop setting does not work as expected. The Phrase Slop value of 2 has no influence on boosts.');
        // the slop value of 2 has an influence of positions up to 8
        // @extensionScannerIgnoreLine
        $this->assertSame(2, $parsedDatasByPhraseSlop[2]->response->docs[7]->getUid(), 'Phrase slop setting does not work as expected.');
    }

    /**
     * @test
     *
     * Bigram Phrase
     */
    public function implicitPhraseSearchSloppyPhraseBoostCanBeAdjustedByBigramPhraseSlop()
    {
        $this->fillIndexForPhraseSearchTests('phrase_search_bigram.xml');

        /** @var $searchInstance \ApacheSolrForTypo3\Solr\Search */
        $searchInstance = GeneralUtility::makeInstance(Search::class);

        $this->switchPhraseSearchFeature('bigramPhrase', 1);

        $query = $this->getSearchQueryForSolr();
        $this->queryBuilder->useQueryString('Bigram Phrase Search');


        // Boost the document with query to make it first.
        $this->queryBuilder->useBigramPhraseFields(BigramPhraseFields::fromString('title^100.0'));
        // do following things

        // test different phrase slop values
        $parsedDatasByPhraseSlop = [];
        for ($i = 0; $i <= 2; $i++) {
            $slops = new Slops();
            $slops->setBigramPhraseSlop($i);
            $this->queryBuilder->useSlops($slops);
            $searchResponse = $searchInstance->search($this->queryBuilder->getQuery());
            $parsedDatasByPhraseSlop[$i] = $searchResponse->getParsedData();
        }

        /* by changing of bigram phrase slop values the documents with sloppy phrases move to top.
          +------------------+---------------------------------|
          | ranking position | document UID @ phraseSlop value |
          +------------------+---------------------------------+
          |         0        |    1 @ 0 ||  1 @ 1 ||  1 @ 2    |
          +------------------+---------------------------------+
          |         1        |    2 @ 0 ||  2 @ 1 ||  2 @ 2    |
          +------------------+---------------------------------+
          |         2        |    3 @ 0 ||  3 @ 1 ||  3 @ 2    |
          +------------------+---------------------------------+
          |         3        |   14 @ 0 ||  4 @ 1 ||  4 @ 2    |
          +------------------+---------------------------------+
          |         4        |   15 @ 0 ||  5 @ 1 ||  5 @ 2    |
          +------------------+---------------------------------+
          |         5        |    4 @ 0 || 14 @ 1 ||  6 @ 2    |
          +------------------+---------------------------------+
          |         6        |    5 @ 0 || 15 @ 1 ||  7 @ 2    |
          +------------------+---------------------------------+
          |         7        |    6 @ 0 ||  6 @ 1 || 14 @ 2    |
          +------------------+---------------------------------+
          |         8        |    7 @ 0 ||  7 @ 1 || 15 @ 2    |
          +------------------+---------------------------------+
        */

        // Note positons beginning by 0 = first
        // first position is the same for all three slop values
        // @extensionScannerIgnoreLine
        $this->assertTrue($parsedDatasByPhraseSlop[0]->response->docs[0]->getUid() === $parsedDatasByPhraseSlop[1]->response->docs[0]->getUid()
            // @extensionScannerIgnoreLine
            && $parsedDatasByPhraseSlop[0]->response->docs[0]->getUid() === $parsedDatasByPhraseSlop[2]->response->docs[0]->getUid()
            , 'Bigram Phrase search does not work properly. Solr should position the documents independent from slop value at first position.');

        // slop = 1
        // the slop value of 1 moves doc UID = 4 to the fourth(key 3) position
        // @extensionScannerIgnoreLine
        $this->assertSame(4, $parsedDatasByPhraseSlop[1]->response->docs[3]->getUid(), 'Bigram phrase slop setting does not work as expected. It does not boost "sloppy phrase" docs for slop=1.');
        // the docuemnt on position 3 and 4 have same score
        $this->assertTrue(
        // @extensionScannerIgnoreLine
            $parsedDatasByPhraseSlop[1]->response->docs[3]->getScore() === $parsedDatasByPhraseSlop[1]->response->docs[4]->getScore(),
            'Bigram phrase slop setting does not work as expected. It does not boost all "sloppy phrase" docs for slop=1.'
        );

        // slop = 2
        // the slop value of 2 moves doc UID = 6 to the sixth(key 5) position
        // @extensionScannerIgnoreLine
        $this->assertSame(6, $parsedDatasByPhraseSlop[2]->response->docs[5]->getUid(), 'Trigram phrase slop setting does not work as expected. The Phrase Slop value of 2 has no influence on boosts.');
        // the docuemnt on position 5 and 6 have same score
        $this->assertTrue(
        // @extensionScannerIgnoreLine
            $parsedDatasByPhraseSlop[2]->response->docs[5]->getScore() === $parsedDatasByPhraseSlop[2]->response->docs[6]->getScore(),
            'Bigram phrase slop setting does not work as expected. It does not boost all "sloppy phrase" docs for slop=2.'
        );
    }

    /**
     * @test
     *
     * Trigram Phrase
     */
    public function implicitPhraseSearchSloppyPhraseBoostCanBeAdjustedByTrigramPhraseSlop()
    {
        $this->fillIndexForPhraseSearchTests('phrase_search_trigram.xml');

        /** @var $searchInstance \ApacheSolrForTypo3\Solr\Search */
        $searchInstance = GeneralUtility::makeInstance(Search::class);

        $this->switchPhraseSearchFeature('trigramPhrase', 1);

        $query = $this->getSearchQueryForSolr();
        $this->queryBuilder
            ->useQueryString('Awesome Trigram Phrase Search')
            // Boost the document with query to make it first.
            ->useTrigramPhraseFields(TrigramPhraseFields::fromString('title^100.0'));

        // do following things

        // test different phrase slop values
        $parsedDatasByPhraseSlop = [];
        for ($i = 0; $i <= 2; $i++) {
            $slops = new Slops();
            $slops->setTrigramPhraseSlop($i);
            $this->queryBuilder->useSlops($slops);
            $searchResponse = $searchInstance->search($this->queryBuilder->getQuery());
            $parsedDatasByPhraseSlop[$i] = $searchResponse->getParsedData();
        }

        /* by changing of trigram phrase slop values the documents with sloppy phrases become more score.
          +------------------+---------------------------------|
          | ranking position | document UID @ phraseSlop value |
          +------------------+---------------------------------+
          |         0        |    1 @ 0 ||  1 @ 1 ||  1 @ 2    |
          +------------------+---------------------------------+
          |         1        |    2 @ 0 ||  2 @ 1 ||  2 @ 2    |
          +------------------+---------------------------------+
          |         2        |    3 @ 0 ||  3 @ 1 ||  3 @ 2    |
          +------------------+---------------------------------+
          |         3        |    4 @ 0 ||  4 @ 1 ||  4 @ 2    | score boost here on slop = 1 || slop = 2
          +------------------+---------------------------------+
          |         4        |    6 @ 0 ||  5 @ 1 ||  5 @ 2    |
          +------------------+---------------------------------+
          |         5        |    5 @ 0 ||  6 @ 1 ||  6 @ 2    | score boost here on slop = 2
          +------------------+---------------------------------+
          |         6        |    7 @ 0 ||  7 @ 1 ||  7 @ 2    |
          +------------------+---------------------------------+
          |         7        |   15 @ 0 || 15 @ 1 || 15 @ 2    |
          +------------------+---------------------------------+
        */

        // Note positons beginning by 0 = first
        // first position is the same for all three slop values
        // @extensionScannerIgnoreLine
        $this->assertTrue($parsedDatasByPhraseSlop[0]->response->docs[0]->getUid() === $parsedDatasByPhraseSlop[1]->response->docs[0]->getUid()
            // @extensionScannerIgnoreLine
            && $parsedDatasByPhraseSlop[0]->response->docs[0]->getUid() === $parsedDatasByPhraseSlop[2]->response->docs[0]->getUid()
            , 'Trigram Phrase search does not work properly. Solr should position the documents independent from slop value at first position.');

        // slop = 1
        // the slop value of 1 moves doc UID = 4 to the fourth(key 3) position
        // @extensionScannerIgnoreLine
        $slop0ResponseDocs = $parsedDatasByPhraseSlop[0]->response->docs;
        // @extensionScannerIgnoreLine
        $slop1ResponseDocs = $parsedDatasByPhraseSlop[1]->response->docs;
        $this->assertTrue(
            $slop0ResponseDocs[3]->getUid() === $slop1ResponseDocs[3]->getUid()
            && $slop0ResponseDocs[3]->getScore() < $slop1ResponseDocs[3]->getScore(),
            'Trigram phrase slop value = 1 does not boost docs with "sloppy phrases"'
        );

        // @extensionScannerIgnoreLine
        $slop2ResponseDocs = $parsedDatasByPhraseSlop[2]->response->docs;
        $this->assertTrue(
            $slop1ResponseDocs[5]->getUid() === $slop2ResponseDocs[5]->getUid()
            && $slop0ResponseDocs[5]->getScore() < $slop1ResponseDocs[5]->getScore(),
            'Trigram phrase slop value = 2 does not boost docs with "sloppy phrases"'
        );
    }

    /**
     * @test
     */
    public function explicitPhraseSearchMatchesMorePrecise()
    {
        $this->fillIndexForPhraseSearchTests();

        /** @var $searchInstance \ApacheSolrForTypo3\Solr\Search */
        $searchInstance = GeneralUtility::makeInstance(Search::class);

        $query = $this->getSearchQueryForSolr();
        $this->queryBuilder->startFrom($query)->useQueryString('"Hello World"');
        $searchResponse = $searchInstance->search($this->queryBuilder->getQuery());
        $parsedData = $searchResponse->getParsedData();

        // document with "Hello World for phrase searching" is not on first place!
        // @extensionScannerIgnoreLine
        $this->assertSame(1, $parsedData->response->numFound, 'Could not index documents into solr to test boosts on explicit phrase searches.');
        // @extensionScannerIgnoreLine
        $this->assertSame('Hello World for phrase searching', $parsedData->response->docs[0]->getTitle(), 'Document containing "Hello World for phrase searching" should be found on explicit(surrounded with double quotes) phrase searching.');
    }

    /**
     * @test
     */
    public function explicitPhraseSearchPrecisionCanBeAdjustedByQuerySlop()
    {
        $this->fillIndexForPhraseSearchTests();

        /** @var $searchInstance \ApacheSolrForTypo3\Solr\Search */
        $searchInstance = GeneralUtility::makeInstance(Search::class);

        $query = $this->getSearchQueryForSolr();
        $this->queryBuilder->useQueryString('"Hello World"');

        $searchResponse = $searchInstance->search($query);
        $parsedData = $searchResponse->getParsedData();

        // document with "Hello World for phrase serchin" is not on first place!
        // @extensionScannerIgnoreLine
        $this->assertSame(1, $parsedData->response->numFound, 'Could not index document into solr');
        // @extensionScannerIgnoreLine
        $this->assertSame('Hello World for phrase searching', $parsedData->response->docs[0]->getTitle(), 'Document containing "Hello World for phrase serching" should be found');

        // simulate Lucenes "Hello World"~1
        $slops = new Slops();
        $slops->setQuerySlop(1);
        $query = $this->queryBuilder->useSlops($slops)->getQuery();
        $searchResponse = $searchInstance->search($query);
        $parsedData = $searchResponse->getParsedData();
        // @extensionScannerIgnoreLine
        $this->assertSame(3, $parsedData->response->numFound, 'Could not index document into solr');

        // simulate Lucenes "Hello World"~2
        $slops->setQuerySlop(2);
        $query = $this->queryBuilder->useSlops($slops)->getQuery();

        $searchResponse = $searchInstance->search($query);
        $parsedData = $searchResponse->getParsedData();
        // @extensionScannerIgnoreLine
        $this->assertSame(7, $parsedData->response->numFound, 'Found wrong number of decuments by explicit phrase search query.');
    }

    /**
     * @param string $fixture
     */
    protected function fillIndexForPhraseSearchTests(string $fixture = 'phrase_search.xml')
    {
        $this->importDataSetFromFixture($fixture);

        $GLOBALS['TT'] = $this->getMockBuilder(TimeTracker::class)->disableOriginalConstructor()->getMock();
        for ($i = 1; $i <= 15; $i++) {
            $fakeTSFE = $this->getConfiguredTSFE($i);
            $GLOBALS['TSFE'] = $fakeTSFE;

            /** @var $pageIndexer \ApacheSolrForTypo3\Solr\Typo3PageIndexer */
            $pageIndexer = GeneralUtility::makeInstance(Typo3PageIndexer::class, $fakeTSFE);
            $pageIndexer->indexPage();
        }
        $this->waitToBeVisibleInSolr();
    }

    /**
     * @return Query
     */
    protected function getSearchQueryForSolr() : Query
    {
        return $this->queryBuilder
            ->newSearchQuery('')
            ->useQueryFields(QueryFields::fromString('content^40.0, title^5.0, keywords^2.0, tagsH1^5.0, tagsH2H3^3.0, tagsH4H5H6^2.0, tagsInline^1.0, description^4.0, abstract^1.0, subtitle^1.0, navtitle^1.0, author^1.0'))
            ->getQuery();
    }

    /**
     * @param string $feature
     * @param int $state
     */
    protected function switchPhraseSearchFeature(string $feature, int $state) {
        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['query.'][$feature] = $state;

        /** @var $configurationManager ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);
    }
}
