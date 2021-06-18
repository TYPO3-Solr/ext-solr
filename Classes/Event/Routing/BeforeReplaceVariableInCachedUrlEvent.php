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
 * This event will triggered before start to replace placeholder inside of cached URLs
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class BeforeReplaceVariableInCachedUrlEvent
{
    /**
     * @var UriInterface
     */
    protected $uri;

    /**
     * Routing is enabled
     *
     * @var bool
     */
    protected $routing = false;

    /**
     * BeforeReplaceVariableInCachedUrlEvent constructor.
     * @param UriInterface $uri
     * @param bool $routing
     */
    public function __construct(UriInterface $uri, bool $routing = false)
    {
        $this->uri = $uri;
        $this->routing = $routing;
    }

    /**
     * Returns the URI
     *
     * @return UriInterface
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * Replace the URI object
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
        return $this->routing;
    }
}
