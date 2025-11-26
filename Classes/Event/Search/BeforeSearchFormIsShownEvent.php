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

namespace ApacheSolrForTypo3\Solr\Event\Search;

use ApacheSolrForTypo3\Solr\Search;

/**
 * This event is triggered before setting the form values
 */
final class BeforeSearchFormIsShownEvent
{
    private Search $search;
    private array $additionalFilters;
    private string $pluginNamespace;
    private array $additionalVariables = [];

    public function __construct(Search $search, array $additionalFilters, string $pluginNamespace)
    {
        $this->search = $search;
        $this->additionalFilters = $additionalFilters;
        $this->pluginNamespace = $pluginNamespace;
    }

    public function getSearch(): Search
    {
        return $this->search;
    }

    public function getAdditionalFilters(): array
    {
        return $this->additionalFilters;
    }

    public function getPluginNamespace(): string
    {
        return $this->pluginNamespace;
    }

    public function getAdditionalVariables(): array
    {
        return $this->additionalVariables;
    }

    public function setAdditionalVariables(array $additionalVariables): void
    {
        $this->additionalVariables = $additionalVariables;
    }
}
