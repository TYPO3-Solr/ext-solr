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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Sorting;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting\Sorting;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit test case for the ObjectReconstitutionProcessor.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SortingTest extends SetUpUnitTestCase
{
    /**
     * @var Sorting
     */
    protected $sorting;

    /**
     * @var SearchResultSet
     */
    protected $resultSetMock;

    protected function setUp(): void
    {
        $this->resultSetMock = $this->createMock(SearchResultSet::class);

        $name = 'Price';
        $field = 'price_f';
        $direction = Sorting::DIRECTION_ASC;
        $label = 'the príce';
        $selected = false;
        $isResetOption = false;
        $this->sorting = new Sorting($this->resultSetMock, $name, $field, $direction, $label, $selected, $isResetOption);
        parent::setUp();
    }

    #[Test]
    public function canNotCreateWhenInvalidDirectionIsPassed()
    {
        $this->expectException(InvalidArgumentException::class);
        new Sorting($this->resultSetMock, 'Color', 'color_s', 'invalid direction', 'the color', false, false);
    }

    #[Test]
    public function canGetName()
    {
        self::assertSame('Price', $this->sorting->getName(), 'Could not get name from sorting');
    }

    #[Test]
    public function canGetLabel()
    {
        self::assertSame('the príce', $this->sorting->getLabel(), 'Could not get label from sorting');
    }

    #[Test]
    public function canGetField()
    {
        self::assertSame('price_f', $this->sorting->getField(), 'Could not get field from sorting');
    }

    #[Test]
    public function canGetDirection()
    {
        self::assertSame('asc', $this->sorting->getDirection(), 'Could not get direction');
    }

    #[Test]
    public function canGetOppositeDirection()
    {
        self::assertSame('desc', $this->sorting->getOppositeDirection(), 'Could not get opposite direction');

        $descSorting = new Sorting($this->resultSetMock, 'Color', 'color_s', Sorting::DIRECTION_DESC, 'the color', false, false);
        self::assertSame('asc', $descSorting->getOppositeDirection(), 'Could not get opposite direction');
    }

    #[Test]
    public function getGetIsAsDirection()
    {
        self::assertTrue($this->sorting->getIsAscDirection(), 'Sorting direction was not handled as ascending');
    }

    #[Test]
    public function getGetIsDescDirection()
    {
        self::assertFalse($this->sorting->getIsDescDirection(), 'Sorting should be indicated to not be descending');
    }

    #[Test]
    public function canGetIsResetOption()
    {
        self::assertFalse($this->sorting->getIsResetOption(), 'Sorting options should not be a reset option');
    }
}
