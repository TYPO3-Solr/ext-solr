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

use ApacheSolrForTypo3\Solr\System\Solr\Parser\SynonymParser;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Testcase for StopWordParser
 */
class SynonymParserTest extends SetUpUnitTestCase
{
    #[Test]
    public function canParseSynonyms(): void
    {
        $parser = new SynonymParser();
        $synonyms = $parser->parseJson('foo', self::getFixtureContentByName('synonym.json'));
        self::assertSame(['bar'], $synonyms, 'Could not parser synonyms from synonyms response');
    }
}
