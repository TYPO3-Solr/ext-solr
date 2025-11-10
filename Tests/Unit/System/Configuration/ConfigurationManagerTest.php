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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Configuration;

use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;

class ConfigurationManagerTest extends SetUpUnitTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    #[Test]
    public function getTypoScriptConfigurationFallsBackToTypo3RequestWhenPageIsDeleted(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = $this->createMock(ServerRequestInterface::class);
        $expectedConfiguration = new TypoScriptConfiguration([]);

        $manager = $this->createPartialMock(
            ConfigurationManager::class,
            ['fetchPageRecord', 'getTypoScriptFromRequest'],
        );

        // Simulate a page that was deleted in a workspace swap
        $manager->method('fetchPageRecord')
            ->with(999)
            ->willReturn(null);

        // The fallback must be called with TYPO3_REQUEST, not a page-derived request
        $manager->expects(self::once())
            ->method('getTypoScriptFromRequest')
            ->with($GLOBALS['TYPO3_REQUEST'])
            ->willReturn($expectedConfiguration);

        $result = $manager->getTypoScriptConfiguration(999);

        self::assertSame($expectedConfiguration, $result);
    }
}
