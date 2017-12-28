<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Access;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase to verify the functionality of the Rootline
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RootlineTest extends UnitTest
{

    /**
     * @return array
     */
    public function rootLineDataProvider()
    {
        return [
            'simple' => ['string' => 'c:0', 'expectedGroups' => [0]],
            'simpleOneGroup' => ['string' => 'c:1', 'expectedGroups' => [1]],
            'mixed' => ['string' => '35:1/c:0', 'expectedGroups' => [0,1]]
        ];
    }

    /**
     * @test
     * @dataProvider rootLineDataProvider
     */
    public function canParser($rootLineString, $expectedGroups)
    {
        $rootline = new Rootline($rootLineString);
        $groups = $rootline->getGroups();
        $this->assertSame($expectedGroups, $groups);
    }
}
