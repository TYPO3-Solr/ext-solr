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
 * This event will be triggered before start to replace placeholder inside cached URLs
 */
class BeforeVariableInCachedUrlAreReplacedEvent
{
    protected UriInterface $uri;

    /**
     * Routing is enabled
     */
    protected bool $routing = false;

    /**
     * BeforeReplaceVariableInCachedUrlEvent constructor.
     */
    public function __construct(UriInterface $uri, bool $routing = false)
    {
        $this->uri = $uri;
        $this->routing = $routing;
    }

    /**
     * Returns the URI
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * Replace the URI object
     */
    public function replaceUri(UriInterface $uri): void
    {
        $this->uri = $uri;
    }

    public function hasRouting(): bool
    {
        return $this->routing;
    }
}
