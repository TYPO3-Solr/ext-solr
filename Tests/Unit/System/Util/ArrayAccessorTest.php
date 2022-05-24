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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Util;

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
