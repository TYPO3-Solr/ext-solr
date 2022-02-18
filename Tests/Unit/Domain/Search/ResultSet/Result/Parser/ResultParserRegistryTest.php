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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\ResultParserRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Unit test case for the ResultParserRegistryTest.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ResultParserRegistryTest extends UnitTest
{

    /**
     * @var ResultParserRegistry
     */
    protected $registry;

    protected function setUp(): void
    {
        $this->registry = new ResultParserRegistry();
        parent::setUp();
    }

    /**
     * @test
     */
    public function canRegisterAndRetrieveParserWithAHigherPriority()
    {
        $fakeResultSet = $this->getDumbMock(SearchResultSet::class);
        $this->registry->registerParser(TestResultParser::class, 200);
        $retrievedParser = $this->registry->getParser($fakeResultSet);
        self::assertInstanceOf(TestResultParser::class, $retrievedParser, 'Did not retrieve register custom parser with higher priority');
    }

    /**
     * @test
     */
    public function hasParser()
    {
        $this->registry->registerParser(TestResultParser::class, 200);
        self::assertTrue($this->registry->hasParser(TestResultParser::class, 200), 'hasParser returned unexpected result for a parser that should exist');
        self::assertFalse($this->registry->hasParser('Fooo', 100), 'hasParser returned unexpected result for a parser that not should exist');
    }
}
