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

namespace ApacheSolrForTypo3\Solr\Middleware;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * @internal
 */
final readonly class SolrRoutingContext
{
    public function __construct(
        private Site $site,
        private SiteLanguage $siteLanguage,
        private array $page,
        private array $enhancerConfiguration,
    ) {}

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getSiteLanguage(): SiteLanguage
    {
        return $this->siteLanguage;
    }

    public function getPage(): array
    {
        return $this->page;
    }

    public function getEnhancerConfiguration(): array
    {
        return $this->enhancerConfiguration;
    }
}
