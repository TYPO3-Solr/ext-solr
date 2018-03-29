<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017
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

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ItemTest extends UnitTest
{

    /**
     * @test
     */
    public function canGetErrors()
    {
        $metaData = ['errors' => 'error during index'];
        $record = [];
        $item = new Item($metaData, $record);

        $errors = $item->getErrors();
        $this->assertSame('error during index', $errors, 'Can not get errors from queue item');
    }

    /**
     * @test
     */
    public function canGetType()
    {
        $metaData = ['item_type' => 'pages'];
        $record = [];
        $item = new Item($metaData, $record);

        $type = $item->getType();
        $this->assertSame('pages', $type, 'Can not get type from queue item');
    }

    /**
     * @return array
     */
    public function getStateDataProvider()
    {
        return [
            'pending item' => [['item_type' => 'pages', 'indexed' => 3, 'changed' => '4'], Item::STATE_PENDING],
            'indexed item' => [['item_type' => 'pages', 'indexed' => 5, 'changed' => '4'], Item::STATE_INDEXED],
            'blocked item' => [['item_type' => 'pages', 'indexed' => 5, 'changed' => '4', 'errors' => 'Something bad happened'], Item::STATE_BLOCKED]
        ];
    }

    /**
     * @dataProvider getStateDataProvider
     * @test
     */
    public function canGetState($metaData, $expectedState)
    {
        $item = new Item($metaData, []);
        $this->assertSame($expectedState, $item->getState(), 'Can not get state from item as expected');
    }

    /**
     * @test
     */
    public function testHasErrors()
    {
        $item = new Item([], []);
        $this->assertFalse($item->getHasErrors(), 'Expected that item without any data has no errors');

        $item = new Item(['errors' => 'something is broken'], []);
        $this->assertTrue($item->getHasErrors(), 'Item with errors was not indicated to have errors');
    }

    /**
     * @test
     */
    public function testHasIndexingProperties()
    {
        $item = new Item([], []);
        $this->assertFalse($item->hasIndexingProperties(), 'Expected that empty item should not have any indexing properties');

        $item = new Item(['has_indexing_properties' => true], []);
        $this->assertTrue($item->hasIndexingProperties(), 'Item with proper meta data should have indexing properties');
    }
}