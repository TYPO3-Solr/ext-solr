<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Solr\Parser;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Solr\Parser\SchemaParser;
use ApacheSolrForTypo3\Solr\System\Solr\Schema\Schema;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase for the SchemaParser class.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SchemaParserTest extends UnitTest
{

    /**
     * @test
     */
    public function canParseLanguage()
    {
        $parser = new SchemaParser();
        $schema = $parser->parseJson($this->getFixtureContentByName('schema.json'));
        $this->assertSame('german', $schema->getLanguage(), 'Could not parser language from schema response');
    }

    /**
     * @test
     */
    public function canParseName()
    {
        $parser = new SchemaParser();
        $schema = $parser->parseJson($this->getFixtureContentByName('schema.json'));
        $this->assertSame('tx_solr-6-0-0--20161122', $schema->getName(), 'Could not parser name from schema response');
    }

    /**
     * @test
     */
    public function canReturnEmptySchemaWhenNoSchemaPropertyInResponse()
    {
        $parser = new SchemaParser();
        $schema = $parser->parseJson('{}');
        $this->assertInstanceOf(Schema::class, $schema, 'Can not get schema object from empty response');
    }

}