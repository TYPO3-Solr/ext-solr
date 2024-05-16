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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ItemTest extends SetUpUnitTestCase
{
    #[Test]
    public function canGetErrors()
    {
        $metaData = ['errors' => 'error during index'];
        $record = [];
        $item = new Item($metaData, $record, null, $this->createMock(QueueItemRepository::class));

        $errors = $item->getErrors();
        self::assertSame('error during index', $errors, 'Can not get errors from queue item');
    }

    #[Test]
    public function canGetType()
    {
        $metaData = ['item_type' => 'pages'];
        $record = [];
        $item = new Item($metaData, $record, null, $this->createMock(QueueItemRepository::class));

        $type = $item->getType();
        self::assertSame('pages', $type, 'Can not get type from queue item');
    }

    public static function getStateDataProvider(): Traversable
    {
        yield 'pending item' => [['item_type' => 'pages', 'indexed' => 3, 'changed' => 4], Item::STATE_PENDING];
        yield 'indexed item' => [['item_type' => 'pages', 'indexed' => 5, 'changed' => 4], Item::STATE_INDEXED];
        yield 'blocked item' => [['item_type' => 'pages', 'indexed' => 5, 'changed' => 4, 'errors' => 'Something bad happened'], Item::STATE_BLOCKED];
    }

    #[DataProvider('getStateDataProvider')]
    #[Test]
    public function canGetState($metaData, $expectedState)
    {
        $item = new Item($metaData, [], null, $this->createMock(QueueItemRepository::class));
        self::assertSame($expectedState, $item->getState(), 'Can not get state from item as expected');
    }

    #[Test]
    public function testHasErrors()
    {
        $item = new Item([], [], null, $this->createMock(QueueItemRepository::class));
        self::assertFalse($item->getHasErrors(), 'Expected that item without any data has no errors');

        $item = new Item(['errors' => 'something is broken'], [], null, $this->createMock(QueueItemRepository::class));
        self::assertTrue($item->getHasErrors(), 'Item with errors was not indicated to have errors');
    }

    #[Test]
    public function testHasIndexingProperties()
    {
        $item = new Item([], [], null, $this->createMock(QueueItemRepository::class));
        self::assertFalse($item->hasIndexingProperties(), 'Expected that empty item should not have any indexing properties');

        $item = new Item(['has_indexing_properties' => true], [], null, $this->createMock(QueueItemRepository::class));
        self::assertTrue($item->hasIndexingProperties(), 'Item with proper meta data should have indexing properties');
    }
}
