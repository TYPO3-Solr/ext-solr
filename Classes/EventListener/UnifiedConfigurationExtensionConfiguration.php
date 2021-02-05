<?php
declare(strict_types=1);
namespace ApacheSolrForTypo3\Solr\EventListener;

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

use ApacheSolrForTypo3\Solr\Event\UnifiedConfigurationEvent;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Insert the extension configuration into the unified configuration
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class UnifiedConfigurationExtensionConfiguration
{
    public function __invoke(UnifiedConfigurationEvent $event): void
    {
        /* @var ConfigurationManager $configurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $extensionConfiguration = $configurationManager->getExtensionConfiguration();
        $event->getUnifiedConfiguration()->mergeConfigurationByObject($extensionConfiguration);
    }
}
