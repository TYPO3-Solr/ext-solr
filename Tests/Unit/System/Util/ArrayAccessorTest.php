<?php
namespace ApacheSolrForTypo3\Solr\Test\System\Util;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\System\Util\ArrayAccessor;

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
        $data = array('foo' => array('bla' => 1));
        $arrayAccessor = new ArrayAccessor($data);
        $this->assertSame(1, $arrayAccessor->get('foo:bla'));

        $data = array();
        $data['one']['two']['three']['four'] = 'test';
        $arrayAccessor = new ArrayAccessor($data);
        $this->assertSame('test', $arrayAccessor->get('one:two:three:four'));

        $emptyArray = array();
        $arrayAccessor = new ArrayAccessor($emptyArray);
        $this->assertSame(null, $arrayAccessor->get('one:two:three:four'));
    }

    /**
     * @test
     */
    public function canSetAndGet()
    {
        // can set and get a simple value
        $arrayAccessor = new ArrayAccessor();
        $arrayAccessor->set('foo', 'bar');
        $this->assertSame('bar', $arrayAccessor->get('foo'));

        $arrayAccessor->set('one:two:three', 'test');
        $this->assertSame('test', $arrayAccessor->get('one:two:three'));

        $arrayAccessor->set('one:two:three', array('four' => 'test2'));
        $this->assertSame('test2', $arrayAccessor->get('one:two:three:four'));
    }
}
