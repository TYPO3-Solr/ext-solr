<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Result\Parser;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\ResultParserRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Result\Parser\TestResultParser;
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

    /**
     * @var TypoScriptConfiguration
     */
    protected $configurationMock;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->registry = new ResultParserRegistry($this->configurationMock);
    }

    /**
     * @test
     */
    public function canRegisterAndRetrieveParserWithAHigherPriority()
    {
        $fakeResultSet = $this->getDumbMock(SearchResultSet::class);
        $this->registry->registerParser(TestResultParser::class, 200);
        $retrievedParser = $this->registry->getParser($fakeResultSet);
        $this->assertInstanceOf(TestResultParser::class, $retrievedParser, 'Did not retrieve register custom parser with higher priority');
    }

    /**
     * @test
     */
    public function hasParser()
    {
        $this->registry->registerParser(TestResultParser::class, 200);
        $this->assertTrue($this->registry->hasParser(TestResultParser::class, 200), 'hasParser returned unexpected result for a parser that should exist');
        $this->assertFalse($this->registry->hasParser('Fooo', 100), 'hasParser returned unexpected result for a parser that not should exist');
    }
}