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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Result\Parser;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\DocumentEscapeService;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\ResultParserRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Unit test case for the ResultParserRegistryTest.
 */
class ResultParserRegistryTest extends SetUpUnitTestCase
{
    protected ResultParserRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ResultParserRegistry();
        $container = new Container();
        $container->set(
            SearchResultBuilder::class,
            $this->createMock(SearchResultBuilder::class)
        );
        $container->set(
            DocumentEscapeService::class,
            $this->createMock(DocumentEscapeService::class)
        );
        GeneralUtility::setContainer($container);
        parent::setUp();
    }

    #[Test]
    public function canRegisterAndRetrieveParserWithAHigherPriority(): void
    {
        $fakeResultSet = $this->createMock(SearchResultSet::class);
        $this->registry->registerParser(TestResultParser::class, 200);
        $retrievedParser = $this->registry->getParser($fakeResultSet);
        self::assertInstanceOf(TestResultParser::class, $retrievedParser, 'Did not retrieve register custom parser with higher priority');
    }

    #[Test]
    public function hasParser(): void
    {
        $this->registry->registerParser(TestResultParser::class, 200);
        self::assertTrue($this->registry->hasParser(TestResultParser::class, 200), 'hasParser returned unexpected result for a parser that should exist');
        self::assertFalse($this->registry->hasParser('Fooo', 100), 'hasParser returned unexpected result for a parser that not should exist');
    }
}
