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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

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
        self::assertSame('error during index', $errors, 'Can not get errors from queue item');
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
        self::assertSame('pages', $type, 'Can not get type from queue item');
    }

    /**
     * @return array
     */
    public function getStateDataProvider()
    {
        return [
            'pending item' => [['item_type' => 'pages', 'indexed' => 3, 'changed' => '4'], Item::STATE_PENDING],
            'indexed item' => [['item_type' => 'pages', 'indexed' => 5, 'changed' => '4'], Item::STATE_INDEXED],
            'blocked item' => [['item_type' => 'pages', 'indexed' => 5, 'changed' => '4', 'errors' => 'Something bad happened'], Item::STATE_BLOCKED],
        ];
    }

    /**
     * @dataProvider getStateDataProvider
     * @test
     */
    public function canGetState($metaData, $expectedState)
    {
        $item = new Item($metaData, []);
        self::assertSame($expectedState, $item->getState(), 'Can not get state from item as expected');
    }

    /**
     * @test
     */
    public function testHasErrors()
    {
        $item = new Item([], []);
        self::assertFalse($item->getHasErrors(), 'Expected that item without any data has no errors');

        $item = new Item(['errors' => 'something is broken'], []);
        self::assertTrue($item->getHasErrors(), 'Item with errors was not indicated to have errors');
    }

    /**
     * @test
     */
    public function testHasIndexingProperties()
    {
        $item = new Item([], []);
        self::assertFalse($item->hasIndexingProperties(), 'Expected that empty item should not have any indexing properties');

        $item = new Item(['has_indexing_properties' => true], []);
        self::assertTrue($item->hasIndexingProperties(), 'Item with proper meta data should have indexing properties');
    }
}
