<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use \TYPO3\CMS\Core\Tests\UnitTestCase as TYPO3UnitTest;

/**
 * Base class for all unit tests in the solr project
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
 */
abstract class SolrUnitTest extends TYPO3UnitTest {

    /**
     * Returns a mock class where every behaviour is mocked, just to full fill
     * the datatype and have the possibility to mock the behaviour.
     *
     * @param string $className
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getDumbMock($className) {
        return $this->getMock($className, array(), array(), '', FALSE);
    }

    /**
     * @param string $version
     */
    protected function skipInVersionBelow($version) {
        if (version_compare(TYPO3_branch, $version, '<')) {
            $this->markTestSkipped('This test requires at least version '.$version);
        }
    }
}