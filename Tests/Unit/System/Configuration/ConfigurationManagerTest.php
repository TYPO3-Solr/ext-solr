<?php
declare(strict_types=1);

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

use ApacheSolrForTypo3\Solr\EventListener\UnifiedConfigurationGlobalConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to test the unified configuration with the configuration manager
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class ConfigurationManagerTest extends UnitTest
{

    /**
     * @test
     */
    public function testGetIsUseConfigurationFromClosestTemplateEnabled()
    {
        $listenderProvider = new class implements ListenerProviderInterface {
            public function getListenersForEvent(object $event): iterable
            {
                return [
                    new UnifiedConfigurationGlobalConfiguration()
                ];
            }
        };

        /* @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = GeneralUtility::makeInstance(
            EventDispatcher::class,
            $listenderProvider
        );

        $configurationManager = new ConfigurationManager();
        $configurationManager->injectEventDispatcher($eventDispatcher);

        $unifiedConfiguration = $configurationManager->getUnifiedConfiguration();
        print_r($unifiedConfiguration);

        $this->assertTrue($unifiedConfiguration->isTrue('settings.enabled'));
    }
}
