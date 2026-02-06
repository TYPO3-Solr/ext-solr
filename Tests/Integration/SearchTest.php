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

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\BigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\PhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\QueryFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Slops;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\TrigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Test class to perform a search on a real solr server
 */
class SearchTest extends IntegrationTestBase
{
    protected QueryBuilder $queryBuilder;

    protected Search $searchInstance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->queryBuilder = new QueryBuilder(new TypoScriptConfiguration([]));

        $this->getConfiguredRequest();
        $this->searchInstance = GeneralUtility::makeInstance(Search::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
    }

    /**
     * @throws DBALException
     * @throws InvalidArgumentException
     * @throws SiteNotFoundException
     */
    #[Test]
    public function canSearchForADocument(): void
    {
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Search/can_search.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');

        $this->indexPages([2]);

        $query = $this->queryBuilder
            ->newSearchQuery('hello')
            ->useQueryFields(QueryFields::fromString('content^40.0, title^5.0, keywords^2.0, tagsH1^5.0, tagsH2H3^3.0, tagsH4H5H6^2.0, tagsInline^1.0, description^4.0, abstract^1.0, subtitle^1.0, navtitle^1.0, author^1.0'))
            ->getQuery();

        $searchResponse = $this->searchInstance->search($query);
        $rawResponse = $searchResponse->getRawResponse();
        self::assertStringContainsString('"numFound":1', $rawResponse, 'Could not index document into solr');
        self::assertStringContainsString('"title":"Hello Search Test"', $rawResponse, 'Could not index document into solr');
    }

    /**
     * @throws SiteNotFoundException
     * @throws InvalidArgumentException
     * @throws DBALException
     */
    #[Test]
    public function canHighlightTerms(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Search/phrase_search.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexPages(range(2, 16));

        // fragmentSize 50 => fastVector
        $typoScriptConfiguration = new TypoScriptConfiguration([
            'plugin.' => [
                'tx_solr.' => [
                    'search.' => [
                        'query.' => ['queryFields' => 'content,title'],
                        'results.' => [
                            'resultsHighlighting' => 1,
                            'resultsHighlighting.' => [
                                'fragmentSize' => 50,
                                'wrap' => '<mark>|</mark>',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $queryBuilder = new QueryBuilder($typoScriptConfiguration);
        $query = $queryBuilder->buildSearchQuery('enterprise');
        $parsedData = $this->searchInstance->search($query)->getParsedData();
        $highlightString = current((array)$parsedData->highlighting)?->title[0];

        // fragmentSize 20 => fastVector
        $typoScriptConfiguration->mergeSolrConfiguration([
            'search.' => [
                'results.' => [
                    'resultsHighlighting.' => [
                        'fragmentSize' => 20,
                    ],
                ],
            ],
        ]);
        $query = $queryBuilder->buildSearchQuery('enterprise');
        $parsedData = $this->searchInstance->search($query)->getParsedData();
        $highlightString2 = current((array)$parsedData->highlighting)?->title[0];

        // fragmentSize 10 => original
        $typoScriptConfiguration->mergeSolrConfiguration([
            'search.' => [
                'results.' => [
                    'resultsHighlighting.' => [
                        'fragmentSize' => 10,
                    ],
                ],
            ],
        ]);
        $query = $queryBuilder->buildSearchQuery('enterprise');
        $parsedData = $this->searchInstance->search($query)->getParsedData();
        $highlightString3 = current((array)$parsedData->highlighting)?->title[0];

        self::assertStringContainsString('<mark>', $highlightString);
        self::assertStringContainsString('<mark>', $highlightString2);
        self::assertStringContainsString('<mark>', $highlightString3);
        self::assertTrue((strlen($highlightString) > strlen($highlightString2)));
        self::assertTrue((strlen($highlightString2) > strlen($highlightString3)));
    }

    /**
     * @throws SiteNotFoundException
     * @throws InvalidArgumentException
     * @throws DBALException
     */
    #[Test]
    public function implicitPhraseSearchingBoostsDocsWithOccurringPhrase(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Search/phrase_search.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexPages(range(2, 16));

        $query = $this->queryBuilder
            ->newSearchQuery('Hello World')
            ->getQuery();

        $searchResponse = $this->searchInstance->search($query);
        $parsedData = $searchResponse->getParsedData();
        // document with "Hello World for phrase searching" is not on first place!
        // @extensionScannerIgnoreLine
        self::assertGreaterThan(0, $parsedData->response->numFound, 'Could not index document into solr');
        // @extensionScannerIgnoreLine
        self::assertNotEquals('Hello World for phrase searching', $parsedData->response->docs[0]->getTitle(), 'Unexpected score calculation. Expected Document shouldn\'t be at first place.');

        // Boost the document with query to make it first.
        $query = $this->queryBuilder->startFrom($query)->usePhraseFields(PhraseFields::fromString('title^10.0'))->getQuery();
        $searchResponse = $this->searchInstance->search($query);
        $parsedData = $searchResponse->getParsedData();

        // @extensionScannerIgnoreLine
        self::assertGreaterThan(0, $parsedData->response->numFound, 'Could not index document into solr');
        // @extensionScannerIgnoreLine
        self::assertSame('Hello World for phrase searching', $parsedData->response->docs[0]->getTitle(), 'Unexpected score calculation. Document');
    }

    /**
     * @throws SiteNotFoundException
     * @throws InvalidArgumentException
     * @throws DBALException
     */
    #[Test]
    public function implicitPhraseSearchSloppyPhraseBoostCanBeAdjustedByPhraseSlop(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Search/phrase_search.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexPages(range(2, 16));

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

            $searchResponse = $this->searchInstance->search($query);
            $parsedDatasByPhraseSlop[$i] = $searchResponse->getParsedData();
        }

        /* by changing of phrase slop values, the documents with sloppy phrases move to top.
          +------------------+---------------------------------|
          | ranking position | document UID @ phraseSlop value |
          +------------------+---------------------------------+
          |         0        |   15 @ 0 || 15 @ 1 || 15 @ 2    |
          +------------------+---------------------------------+
          |         1        |    2 @ 0 || 12 @ 1 || 12 @ 2    |
          +------------------+---------------------------------+
          |         2        |    3 @ 0 || 13 @ 1 || 13 @ 2    |
          +------------------+---------------------------------+
          |         3        |    4 @ 0 ||  2 @ 1 ||  2 @ 2    |
          +------------------+---------------------------------+
          |         4        |    5 @ 0 ||  3 @ 1 ||  4 @ 2    |
          +------------------+---------------------------------+
          |         5        |    6 @ 0 ||  4 @ 1 ||  5 @ 2    |
          +------------------+---------------------------------+
          |         6        |   12 @ 0 ||  5 @ 1 ||  6 @ 2    |
          +------------------+---------------------------------+
          |         7        |   13 @ 0 ||  6 @ 1 ||  3 @ 2    |
          +------------------+---------------------------------+
          |         8        |   14 @ 0 || 14 @ 1 || 14 @ 2    |
          +------------------+---------------------------------+
        */
        // Note positions beginning by 0 = first.
        // The first position is the same for all three slop values
        // @extensionScannerIgnoreLine
        self::assertTrue($parsedDatasByPhraseSlop[0]->response->docs[0]->getUid() === $parsedDatasByPhraseSlop[1]->response->docs[0]->getUid()
            // @extensionScannerIgnoreLine
                && $parsedDatasByPhraseSlop[0]->response->docs[0]->getUid() === $parsedDatasByPhraseSlop[2]->response->docs[0]->getUid(), 'Phrase search does not work properly. Solr should position the document independent from slop value at first position.');
        // the slop value of 1 moves doc UID = 12 to the second(key 1) position
        // @extensionScannerIgnoreLine
        self::assertSame(12, $parsedDatasByPhraseSlop[1]->response->docs[1]->getUid(), 'Phrase slop setting does not work as expected. The PID is not 12');
        // the slop value of 2 moves doc UID = 4 to the fifth(key 4) position
        // @extensionScannerIgnoreLine
        self::assertSame(4, $parsedDatasByPhraseSlop[2]->response->docs[4]->getUid(), 'Phrase slop setting does not work as expected. The Phrase Slop value of 2 has no influence on boosts.');
        // the slop value of 2 has an influence of positions up to 8(key 7)
        // @extensionScannerIgnoreLine
        self::assertSame(3, $parsedDatasByPhraseSlop[2]->response->docs[7]->getUid(), 'Phrase slop setting does not work as expected.');
    }

    /**
     * @throws SiteNotFoundException
     * @throws InvalidArgumentException
     * @throws DBALException
     */
    #[Test]
    public function implicitPhraseSearchSloppyPhraseBoostCanBeAdjustedByBigramPhraseSlop(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Search/phrase_search_bigram.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexPages(range(2, 16));

        $this->switchPhraseSearchFeature('bigramPhrase', 1);

        $this->getSearchQueryForSolr();
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
            $searchResponse = $this->searchInstance->search($this->queryBuilder->getQuery());
            $parsedDatasByPhraseSlop[$i] = $searchResponse->getParsedData();
        }

        /* by changing of bigram phrase slop values the documents with sloppy phrases move to top.
          +------------------+---------------------------------|
          | ranking position | document UID @ phraseSlop value |
          +------------------+---------------------------------+
          |         0        |    2 @ 0 ||  2 @ 1 ||  2 @ 2    |
          +------------------+---------------------------------+
          |         1        |    3 @ 0 ||  3 @ 1 ||  3 @ 2    |
          +------------------+---------------------------------+
          |         2        |    4 @ 0 ||  4 @ 1 ||  4 @ 2    |
          +------------------+---------------------------------+
          |         3        |   15 @ 0 ||  5 @ 1 ||  5 @ 2    |
          +------------------+---------------------------------+
          |         4        |   16 @ 0 ||  6 @ 1 ||  6 @ 2    |
          +------------------+---------------------------------+
          |         5        |    5 @ 0 || 15 @ 1 ||  7 @ 2    |
          +------------------+---------------------------------+
          |         6        |    6 @ 0 || 16 @ 1 ||  8 @ 2    |
          +------------------+---------------------------------+
          |         7        |    7 @ 0 ||  7 @ 1 || 15 @ 2    |
          +------------------+---------------------------------+
          |         8        |    8 @ 0 ||  8 @ 1 || 16 @ 2    |
          +------------------+---------------------------------+
        */

        // Note positions beginning by 0 = first.
        // The first position is the same for all three slop values
        // @extensionScannerIgnoreLine
        self::assertTrue($parsedDatasByPhraseSlop[0]->response->docs[0]->getUid() === $parsedDatasByPhraseSlop[1]->response->docs[0]->getUid()
            // @extensionScannerIgnoreLine
            && $parsedDatasByPhraseSlop[0]->response->docs[0]->getUid() === $parsedDatasByPhraseSlop[2]->response->docs[0]->getUid(), 'Bigram Phrase search does not work properly. Solr should position the documents independent from slop value at first position.');

        // slop = 1
        // the slop value of 1 moves doc UID = 5 to the fourth(key 3) position
        // @extensionScannerIgnoreLine
        self::assertSame(5, $parsedDatasByPhraseSlop[1]->response->docs[3]->getUid(), 'Bigram phrase slop setting does not work as expected. It does not boost "sloppy phrase" docs for slop=1.');
        // the document on position 3 and 4 have same score
        self::assertTrue(
            // @extensionScannerIgnoreLine
            $parsedDatasByPhraseSlop[1]->response->docs[3]->getScore() === $parsedDatasByPhraseSlop[1]->response->docs[4]->getScore(),
            'Bigram phrase slop setting does not work as expected. It does not boost all "sloppy phrase" docs for slop=1.',
        );

        // slop = 2
        // the slop value of 2 moves doc UID = 7 to the sixth(key 5) position
        // @extensionScannerIgnoreLine
        self::assertSame(7, $parsedDatasByPhraseSlop[2]->response->docs[5]->getUid(), 'Trigram phrase slop setting does not work as expected. The Phrase Slop value of 2 has no influence on boosts.');
        // the document on position 5 and 6 have same score
        self::assertTrue(
            // @extensionScannerIgnoreLine
            $parsedDatasByPhraseSlop[2]->response->docs[5]->getScore() === $parsedDatasByPhraseSlop[2]->response->docs[6]->getScore(),
            'Bigram phrase slop setting does not work as expected. It does not boost all "sloppy phrase" docs for slop=2.',
        );
    }

    /**
     * @throws SiteNotFoundException
     * @throws InvalidArgumentException
     * @throws DBALException
     */
    #[Test]
    public function implicitPhraseSearchSloppyPhraseBoostCanBeAdjustedByTrigramPhraseSlop(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Search/phrase_search_trigram.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexPages(range(2, 16));

        $this->switchPhraseSearchFeature('trigramPhrase', 1);

        $this->getSearchQueryForSolr();
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
            $searchResponse = $this->searchInstance->search($this->queryBuilder->getQuery());
            $parsedDatasByPhraseSlop[$i] = $searchResponse->getParsedData();
        }

        /* by changing of trigram phrase slop values the documents with sloppy phrases become more score.
          +------------------+---------------------------------|
          | ranking position | document UID @ phraseSlop value |
          +------------------+---------------------------------+
          |         0        |    2 @ 0 ||  2 @ 1 ||  2 @ 2    |
          +------------------+---------------------------------+
          |         1        |    3 @ 0 ||  3 @ 1 ||  3 @ 2    |
          +------------------+---------------------------------+
          |         2        |    4 @ 0 ||  4 @ 1 ||  4 @ 2    |
          +------------------+---------------------------------+
          |         3        |    5 @ 0 ||  5 @ 1 ||  5 @ 2    | score boost here on slop = 1 || slop = 2
          +------------------+---------------------------------+
          |         4        |    7 @ 0 ||  6 @ 1 ||  6 @ 2    |
          +------------------+---------------------------------+
          |         5        |    6 @ 0 ||  7 @ 1 ||  7 @ 2    | score boost here on slop = 2
          +------------------+---------------------------------+
          |         6        |    8 @ 0 ||  8 @ 1 ||  8 @ 2    |
          +------------------+---------------------------------+
          |         7        |   16 @ 0 || 16 @ 1 || 16 @ 2    |
          +------------------+---------------------------------+
        */

        // Note positions beginning by 0 = first.
        // The first position is the same for all three slop values
        // @extensionScannerIgnoreLine
        self::assertTrue($parsedDatasByPhraseSlop[0]->response->docs[0]->getUid() === $parsedDatasByPhraseSlop[1]->response->docs[0]->getUid()
            // @extensionScannerIgnoreLine
            && $parsedDatasByPhraseSlop[0]->response->docs[0]->getUid() === $parsedDatasByPhraseSlop[2]->response->docs[0]->getUid(), 'Trigram Phrase search does not work properly. Solr should position the documents independent from slop value at first position.');

        // slop = 1
        // the slop value of 1 moves doc UID = 5 to the fourth(key 3) position
        // @extensionScannerIgnoreLine
        $slop0ResponseDocs = $parsedDatasByPhraseSlop[0]->response->docs;
        // @extensionScannerIgnoreLine
        $slop1ResponseDocs = $parsedDatasByPhraseSlop[1]->response->docs;
        self::assertTrue(
            $slop0ResponseDocs[3]->getUid() === $slop1ResponseDocs[3]->getUid()
            && $slop0ResponseDocs[3]->getScore() < $slop1ResponseDocs[3]->getScore(),
            'Trigram phrase slop value = 1 does not boost docs with "sloppy phrases"',
        );

        // @extensionScannerIgnoreLine
        $slop2ResponseDocs = $parsedDatasByPhraseSlop[2]->response->docs;
        self::assertTrue(
            $slop1ResponseDocs[5]->getUid() === $slop2ResponseDocs[5]->getUid()
            && $slop0ResponseDocs[5]->getScore() < $slop1ResponseDocs[5]->getScore(),
            'Trigram phrase slop value = 2 does not boost docs with "sloppy phrases"',
        );
    }

    /**
     * @throws SiteNotFoundException
     * @throws InvalidArgumentException
     * @throws DBALException
     */
    #[Test]
    public function explicitPhraseSearchMatchesMorePrecise(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Search/phrase_search.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexPages(range(2, 16));

        $query = $this->getSearchQueryForSolr();
        $this->queryBuilder->startFrom($query)->useQueryString('"Hello World"');
        $searchResponse = $this->searchInstance->search($this->queryBuilder->getQuery());
        $parsedData = $searchResponse->getParsedData();

        // document with "Hello World for phrase searching" is not on first place!
        // @extensionScannerIgnoreLine
        self::assertSame(1, $parsedData->response->numFound, 'Could not index documents into solr to test boosts on explicit phrase searches.');
        // @extensionScannerIgnoreLine
        self::assertSame('Hello World for phrase searching', $parsedData->response->docs[0]->getTitle(), 'Document containing "Hello World for phrase searching" should be found on explicit(surrounded with double quotes) phrase searching.');
    }

    /**
     * @throws SiteNotFoundException
     * @throws InvalidArgumentException
     * @throws DBALException
     */
    #[Test]
    public function explicitPhraseSearchPrecisionCanBeAdjustedByQuerySlop(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Search/phrase_search.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexPages(range(2, 16));

        $query = $this->getSearchQueryForSolr();
        $this->queryBuilder->useQueryString('"Hello World"');

        $searchResponse = $this->searchInstance->search($query);
        $parsedData = $searchResponse->getParsedData();

        // document with "Hello World for phrase searching" is not on first place!
        // @extensionScannerIgnoreLine
        self::assertSame(1, $parsedData->response->numFound, 'Could not index document into solr');
        // @extensionScannerIgnoreLine
        self::assertSame('Hello World for phrase searching', $parsedData->response->docs[0]->getTitle(), 'Document containing "Hello World for phrase searching" should be found');

        // simulate Lucenes "Hello World"~1
        $slops = new Slops();
        $slops->setQuerySlop(1);
        $query = $this->queryBuilder->useSlops($slops)->getQuery();
        $searchResponse = $this->searchInstance->search($query);
        $parsedData = $searchResponse->getParsedData();
        // @extensionScannerIgnoreLine
        self::assertSame(3, $parsedData->response->numFound, 'Could not index document into solr');

        // simulate Lucenes "Hello World"~2
        $slops->setQuerySlop(2);
        $query = $this->queryBuilder->useSlops($slops)->getQuery();

        $searchResponse = $this->searchInstance->search($query);
        $parsedData = $searchResponse->getParsedData();
        // @extensionScannerIgnoreLine
        self::assertSame(7, $parsedData->response->numFound, 'Found wrong number of documents by explicit phrase search query.');
    }

    protected function getSearchQueryForSolr(): Query
    {
        return $this->queryBuilder
            ->newSearchQuery('')
            ->useQueryFields(QueryFields::fromString('content^40.0, title^5.0, keywords^2.0, tagsH1^5.0, tagsH2H3^3.0, tagsH4H5H6^2.0, tagsInline^1.0, description^4.0, abstract^1.0, subtitle^1.0, navtitle^1.0, author^1.0'))
            ->getQuery();
    }

    protected function switchPhraseSearchFeature(string $feature, int $state): void
    {
        $overwriteConfiguration = [];
        $overwriteConfiguration['search.']['query.'][$feature] = $state;

        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($overwriteConfiguration);
    }

    protected function getConfiguredRequest(int $id = 1): ServerRequest
    {
        $bootstrapper = GeneralUtility::makeInstance(TSFETestBootstrapper::class);
        return $bootstrapper->bootstrap($id);
    }
}
