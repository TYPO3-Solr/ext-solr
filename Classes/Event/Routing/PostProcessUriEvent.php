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
 * This event is invoke after an uri was processed.
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class PostProcessUriEvent
{
    /**
     * The router configuration
     *
     * @var array
     */
    protected $routerConfiguration = [];

    /**
     * @var UriInterface
     */
    protected $uri;

    /**
     * BeforeReplaceVariableInCachedUrlEvent constructor.
     *
     * @param UriInterface $uri
     * @param array $routerConfiguration
     */
    public function __construct(UriInterface $uri, array $routerConfiguration)
    {
        $this->uri = $uri;
        $this->routerConfiguration = $routerConfiguration;
    }

    /**
     * @return UriInterface
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * Replace the URI inside of this event
     *
     * @param UriInterface $uri
     */
    public function replaceUri(UriInterface  $uri)
    {
        $this->uri = $uri;
    }

    /**
     * @return bool
     */
    public function hasRouting(): bool
    {
        return !empty($this->routerConfiguration);
    }

    /**
     * Available router configurations
     *
     * @return array
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
