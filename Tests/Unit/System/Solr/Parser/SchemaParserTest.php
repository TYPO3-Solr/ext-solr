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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Solr\Parser;

use ApacheSolrForTypo3\Solr\System\Solr\Parser\SchemaParser;
use ApacheSolrForTypo3\Solr\System\Solr\Schema\Schema;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Testcase for the SchemaParser class.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SchemaParserTest extends SetUpUnitTestCase
{
    #[Test]
    public function canParseLanguage(): void
    {
        $parser = new SchemaParser();
        $schema = $parser->parseJson(self::getFixtureContentByName('schema.json'));
        self::assertSame('core_de', $schema->getManagedResourceId(), 'Could not parse id of managed resources from schema response.');
    }

    #[Test]
    public function canParseName(): void
    {
        $parser = new SchemaParser();
        $schema = $parser->parseJson(self::getFixtureContentByName('schema.json'));
        self::assertSame('tx_solr-6-0-0--20161122', $schema->getName(), 'Could not parser name from schema response');
    }

    #[Test]
    public function canReturnEmptySchemaWhenNoSchemaPropertyInResponse(): void
    {
        $parser = new SchemaParser();
        $schema = $parser->parseJson('{}');
        self::assertInstanceOf(Schema::class, $schema, 'Can not get schema object from empty response');
    }
}
