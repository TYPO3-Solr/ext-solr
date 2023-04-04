<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Util;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Util\ArrayAccessor;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase for the ArrayAccessor helper class.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class ArrayAccessorTest extends UnitTest
{
    /**
     * @test
     */
    public function canGet()
    {
        $data = ['foo' => ['bla' => 1]];
        $arrayAccessor = new ArrayAccessor($data);
        self::assertSame(1, $arrayAccessor->get('foo:bla'));

        $data = [];
        $data['one']['two']['three']['four'] = 'test';
        $arrayAccessor = new ArrayAccessor($data);
        self::assertSame('test', $arrayAccessor->get('one:two:three:four'));

        $emptyArray = [];
        $arrayAccessor = new ArrayAccessor($emptyArray);
        self::assertNull($arrayAccessor->get('one:two:three:four'));
    }

    /**
     * @test
     */
    public function canSetAndGet()
    {
        // can set and get a simple value
        $arrayAccessor = new ArrayAccessor();
        $arrayAccessor->set('foo', 'bar');
        self::assertSame('bar', $arrayAccessor->get('foo'));

        $arrayAccessor->set('one:two:three', 'test');
        self::assertSame('test', $arrayAccessor->get('one:two:three'));

        $arrayAccessor->set('one:two:three', ['four' => 'test2']);
        self::assertSame('test2', $arrayAccessor->get('one:two:three:four'));
    }

    /**
     * @test
     */
    public function canReset()
    {
        $data = ['one' => ['two' => ['a' => 111, 'b' => 222]]];
        // can set and get a simple value
        $arrayAccessor = new ArrayAccessor($data);
        self::assertSame(111, $arrayAccessor->get('one:two:a'));
        self::assertSame(222, $arrayAccessor->get('one:two:b'));

        $arrayAccessor->reset('one:two:a');

        self::assertNull($arrayAccessor->get('one:two:a'));
        self::assertSame(222, $arrayAccessor->get('one:two:b'));
    }

    /**
     * @test
     */
    public function resetIsRemovingEmptyNodes()
    {
        $data = ['one' => ['two' => ['a' => 111, 'b' => 222]]];
        // can set and get a simple value
        $arrayAccessor = new ArrayAccessor($data);
        self::assertSame(111, $arrayAccessor->get('one:two:a'));
        self::assertSame(222, $arrayAccessor->get('one:two:b'));

        $arrayAccessor->reset('one:two:a');

        self::assertNull($arrayAccessor->get('one:two:a'));
        self::assertSame(222, $arrayAccessor->get('one:two:b'));
        self::assertSame(['b' => 222], $arrayAccessor->get('one:two'));
    }

    /**
     * @test
     */
    public function resetIsRemovingSubNodesAndEmptyNodes()
    {
        $data = [
            'one' => [
                'two' => ['a' => 111, 'b' => 222],
                'three' => 333,
            ],
        ];
        // can set and get a simple value
        $arrayAccessor = new ArrayAccessor($data);
        self::assertSame(111, $arrayAccessor->get('one:two:a'));
        self::assertSame(222, $arrayAccessor->get('one:two:b'));

        $arrayAccessor->reset('one:two');

        self::assertNull($arrayAccessor->get('one:two:a'));
        self::assertNull($arrayAccessor->get('one:two:b'));
        self::assertNull($arrayAccessor->get('one:two'));

        self::assertSame(333, $arrayAccessor->get('one:three'));
    }
}
