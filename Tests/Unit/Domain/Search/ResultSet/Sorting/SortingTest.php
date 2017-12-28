<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting\Sorting;

/**
 * Unit test case for the ObjectReconstitutionProcessor.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SortingTest extends UnitTest
{
    /**
     * @var Sorting
     */
    protected $sorting;

    /**
     * @var SearchResultSet
     */
    protected $resultSetMock;

    /**
     * @test
     */
    public function setUp()
    {
        $this->resultSetMock = $this->getDumbMock(SearchResultSet::class);

        $name = 'Price';
        $field = 'price_f';
        $direction = Sorting::DIRECTION_ASC;
        $label = 'the príce';
        $selected = false;
        $isResetOption = false;
        $this->sorting = new Sorting($this->resultSetMock, $name, $field, $direction, $label, $selected, $isResetOption);
    }

    /**
     * @test
     */
    public function canNotCreateWhenInvalidDirectionIsPassed()
    {
        $this->expectException(\InvalidArgumentException::class);
        new Sorting($this->resultSetMock, 'Color', 'color_s', 'invalid direction', 'the color', false, false);
    }

    /**
     * @test
     */
    public function canGetName()
    {
        $this->assertSame('Price', $this->sorting->getName(), 'Could not get name from sorting');
    }

    /**
     * @test
     */
    public function canGetLabel()
    {
        $this->assertSame('the príce', $this->sorting->getLabel(), 'Could not get label from sorting');
    }

    /**
     * @test
     */
    public function canGetField()
    {
        $this->assertSame('price_f', $this->sorting->getField(), 'Could not get field from sorting');
    }

    /**
     * @test
     */
    public function canGetDirection()
    {
        $this->assertSame('asc', $this->sorting->getDirection(), 'Could not get direction');
    }

    /**
     * @test
     */
    public function canGetOppositeDirection()
    {
        $this->assertSame('desc', $this->sorting->getOppositeDirection(), 'Could not get opposite direction');

        $descSorting = new Sorting($this->resultSetMock, 'Color', 'color_s', Sorting::DIRECTION_DESC, 'the color', false, false);
        $this->assertSame('asc', $descSorting->getOppositeDirection(), 'Could not get opposite direction');
    }

    /**
     * @test
     */
    public function getGetIsAsDirection()
    {
        $this->assertTrue($this->sorting->getIsAscDirection(), 'Sorting direction was not handled as ascending');
    }

    /**
     * @test
     */
    public function getGetIsDescDirection()
    {
        $this->assertFalse($this->sorting->getIsDescDirection(), 'Sorting should be indicated to not be descending');
    }

    /**
     * @test
     */
    public function canGetIsResetOption()
    {
        $this->assertFalse($this->sorting->getIsResetOption(), 'Sorting options should not be a reset option');
    }
}
