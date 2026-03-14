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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Environment;

use ApacheSolrForTypo3\Solr\System\Environment\CliEnvironment;
use ApacheSolrForTypo3\Solr\System\Environment\WebRootAllReadyDefinedException;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to test the functionality of the CliEnvironment. Needs to be an integration test because
 * the constants stay defined in the unit test.
 */
class CliEnvironmentTest extends IntegrationTestBase
{
    protected bool $initializeDatabase = false;

    #[Test]
    public function canInitialize(): void
    {
        $cliEnvironment = new CliEnvironment();
        self::assertNull($cliEnvironment->getWebRoot());

        $cliEnvironment->initialize('/var/www');

        self::assertEquals('/var/www', $cliEnvironment->getWebRoot());

        $cliEnvironment->restore();
        self::assertNull($cliEnvironment->getWebRoot());
    }

    #[Test]
    public function canNotInitializeTwiceWithTwoInstances(): void
    {
        $cliEnvironment = new CliEnvironment();
        $cliEnvironment->initialize('/var/www');

        try {
            $cliEnvironment2 = new CliEnvironment();
            $this->expectException(WebRootAllReadyDefinedException::class);
            $cliEnvironment2->initialize('/var/otherwww');
        } finally {
            $cliEnvironment->restore();
        }
    }

    #[Test]
    public function canInitializeTwiceWhenUsedAsSingleton(): void
    {
        $cliEnvironment = GeneralUtility::makeInstance(CliEnvironment::class);

        // the result should be true because the web root should have been set
        $firstInit = $cliEnvironment->initialize('/var/www');

        // the second init should return false because an initialization was already done before
        $secondInit = $cliEnvironment->initialize('/var/www2');

        self::assertTrue($firstInit);
        self::assertFalse($secondInit);

        $cliEnvironment->restore();
    }
}
