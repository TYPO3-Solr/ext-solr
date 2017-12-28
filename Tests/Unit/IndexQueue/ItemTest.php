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
 * @authot Timo Hund <timo.hund@dkd.de>
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
}