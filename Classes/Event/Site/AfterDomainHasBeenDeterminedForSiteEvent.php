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
 * This event is dispatched when determining which domain to use for a site
 *
 * @deprecated AfterDomainHasBeenDeterminedForSiteEvent is deprecated and will be removed in v13.1.x+.
 *             Use AfterSiteHashHasBeenDeterminedForSiteEvent instead.
 */
final class AfterDomainHasBeenDeterminedForSiteEvent
{
    private string $domain;

    private array $rootPageRecord;

    private Site $typo3Site;

    private ExtensionConfiguration $extensionConfiguration;

    public function __construct(String $domain, array $rootPageRecord, Site $typo3Site, ExtensionConfiguration $extensionConfiguration)
    {
        $this->domain = $domain;
        $this->rootPageRecord = $rootPageRecord;
        $this->typo3Site = $typo3Site;
        $this->extensionConfiguration = $extensionConfiguration;
    }

    public function getDomain(): String
    {
        return $this->domain;
    }

    public function setDomain(string $domain)
    {
        $this->domain = $domain;
    }

    public function getRootPageRecord(): array
    {
        return $this->rootPageRecord;
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
