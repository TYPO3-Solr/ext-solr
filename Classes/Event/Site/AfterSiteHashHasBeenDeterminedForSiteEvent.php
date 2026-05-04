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

namespace ApacheSolrForTypo3\Solr\Event\Site;

use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * This event is dispatched when site identifier is determined to use for a site
 */
final class AfterSiteHashHasBeenDeterminedForSiteEvent
{
    public function __construct(
        private string $siteHash,
        private readonly Site $typo3Site,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function getSiteHash(): String
    {
        return $this->siteHash;
    }

    public function setSiteHash(string $siteHash): void
    {
        $this->siteHash = $siteHash;
    }

    public function getTypo3Site(): Site
    {
        return $this->typo3Site;
    }

    public function getExtensionConfiguration(): ExtensionConfiguration
    {
        return $this->extensionConfiguration;
    }
}
