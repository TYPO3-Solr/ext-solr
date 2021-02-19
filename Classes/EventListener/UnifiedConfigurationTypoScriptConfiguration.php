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

namespace ApacheSolrForTypo3\Solr\EventListener;

use ApacheSolrForTypo3\Solr\Event\UnifiedConfigurationEvent;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\GlobalConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Event listener to merge the typoscript configuration into the unified configuration
 *
 * @author Lars Tode <lars.tode@dkd.de>
 * @copyright (c) 2020-2021 Lars Tode <lars.tode@dkd.de>
 */
class UnifiedConfigurationTypoScriptConfiguration
{
    public function __invoke(UnifiedConfigurationEvent $event): void
    {
        /* @var ConfigurationManager $configurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $typoScriptConfiguration = $configurationManager->getTypoScriptConfigurationByPageAndLanguage(
            $event->getUnifiedConfiguration()->getRootPageUid(),
            $event->getUnifiedConfiguration()->getLanguageUid(),
        );
        $event->getUnifiedConfiguration()->mergeConfigurationByObject($typoScriptConfiguration);
    }
}
