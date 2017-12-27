<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Environment;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2016 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Environment\CliEnvironment;
use ApacheSolrForTypo3\Solr\System\Environment\WebRootAllReadyDefinedException;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to test the functionality of the CliEnvironment. Needs to be an integration test because
 * the constants stay defined in the unit test.
 * @author Timo Hund <timo.hund@dkd.de>
 */
class CliEnvironmentTest extends IntegrationTest
{

    /**
     * @test
     */
    public function canInitialize()
    {
        $this->assertFalse(defined('TYPO3_PATH_WEB'));

        $cliEnvironment = new CliEnvironment();
        $cliEnvironment->initialize('/var/www');

        $this->assertTrue(defined('TYPO3_PATH_WEB'));
        $this->assertEquals('/var/www', TYPO3_PATH_WEB);

        $cliEnvironment->restore();
    }

    /**
     * @test
     */
    public function canNotInitializeTwiceWithTwoInstances()
    {
        $this->expectException(WebRootAllReadyDefinedException::class);
        $this->assertFalse(defined('TYPO3_PATH_WEB'));

        $cliEnvironment = new CliEnvironment();
        $cliEnvironment->initialize('/var/www');

        $cliEnvironment2 = new CliEnvironment();
        $cliEnvironment2->initialize('/var/otherwww');
    }

    /**
     * @test
     */
    public function canInitializeTwiceWhenUsedAsSingleton()
    {
        $cliEnvironment = GeneralUtility::makeInstance(CliEnvironment::class);

        // the result should be true because the constant should have been set
        $firstInit = $cliEnvironment->initialize('/var/www');

        // the second init should return false because an initialization was allready done before
        $secondInit = $cliEnvironment->initialize('/var/www2');

        $this->assertTrue($firstInit);
        $this->assertFalse($secondInit);
    }
}
