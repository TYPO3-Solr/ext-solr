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

namespace ApacheSolrForTypo3\Solr\System\Mvc\Backend;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;

/**
 * Represents the state of needed for backend module components e.g. selected option from select menu, enabled or disabled button, etc..
 */
class ModuleData
{
    /**
     * @var Site|null
     */
    protected ?Site $site;

    /**
     * @var string
     */
    protected string $core = '';

    /**
     * Gets the site to work with.
     *
     * @return Site
     */
    public function getSite(): ?Site
    {
        return $this->site;
    }

    /**
     * Sets the site to work with.
     *
     * @param Site $site
     */
    public function setSite(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Gets the name of the currently selected core
     *
     * @return string Selected core name
     */
    public function getCore(): string
    {
        return $this->core;
    }

    /**
     * Sets the name of the currently selected core
     *
     * @param string $core Selected core name
     */
    public function setCore(string $core)
    {
        $this->core = $core;
    }
}
