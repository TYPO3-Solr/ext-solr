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

namespace ApacheSolrForTypo3\Solr\Tests\Unit;

use ApacheSolrForTypo3\Solr\IndexQueue\AbstractIndexer;

/**
 * Testcase for AbstractIndexer
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class AbstractIndexerTest extends UnitTest
{

    /**
     * @test
     */
    public function testTypeIsNotAllowedOverride()
    {
        self::assertFalse(AbstractIndexer::isAllowedToOverrideField('type'), 'Type is allowed to override');
        self::assertTrue(AbstractIndexer::isAllowedToOverrideField('test_stringS'), 'New dynamic fields was not indicated to be overrideable');
    }
}
