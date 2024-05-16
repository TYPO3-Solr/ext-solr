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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Access;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;

/**
 * Testcase to verify the functionality of the Rootline
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RootlineTest extends SetUpUnitTestCase
{
    public static function rootLineDataProvider(): Traversable
    {
        yield 'simple' => ['rootLineString' => 'c:0', 'expectedGroups' => [0]];
        yield 'simpleOneGroup' => ['rootLineString' => 'c:1', 'expectedGroups' => [1]];
        yield 'mixed' => ['rootLineString' => '35:1/c:0', 'expectedGroups' => [0, 1]];
    }

    #[DataProvider('rootLineDataProvider')]
    #[Test]
    public function canParse(string $rootLineString, $expectedGroups)
    {
        $rootline = new Rootline($rootLineString);
        $groups = $rootline->getGroups();
        self::assertSame($expectedGroups, $groups);
    }
}
