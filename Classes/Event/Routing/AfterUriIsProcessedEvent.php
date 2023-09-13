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

namespace ApacheSolrForTypo3\Solr\Event\Routing;

use Psr\Http\Message\UriInterface;

/**
 * This event is invoked after an uri was processed.
 */
class AfterUriIsProcessedEvent
{
    /**
     * The router configuration
     */
    protected array $routerConfiguration = [];

    protected UriInterface $uri;

    /**
     * BeforeReplaceVariableInCachedUrlEvent constructor.
     */
    public function __construct(UriInterface $uri, array $routerConfiguration)
    {
        $this->uri = $uri;
        $this->routerConfiguration = $routerConfiguration;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * Replace the URI inside of this event
     */
    public function replaceUri(UriInterface $uri): void
    {
        $this->uri = $uri;
    }

    public function hasRouting(): bool
    {
        return !empty($this->routerConfiguration);
    }

    /**
     * Available router configurations
     */
    public function getRouterConfiguration(): array
    {
        if (!isset($this->routerConfiguration['type']) && isset($this->routerConfiguration['0'])) {
            return $this->routerConfiguration[0];
        }
        return $this->routerConfiguration;
    }

    /**
     * Return all the configuration settings
     *
     * @return array[]
     */
    public function getRouterConfigurations(): array
    {
        if (isset($this->routerConfiguration['type'])) {
            return [$this->routerConfiguration];
        }

        return $this->routerConfiguration;
    }
}
