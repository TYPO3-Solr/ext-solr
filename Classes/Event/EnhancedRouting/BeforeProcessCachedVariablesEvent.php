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

namespace ApacheSolrForTypo3\Solr\Event\EnhancedRouting;

use ApacheSolrForTypo3\Solr\Event\Routing\BeforeProcessCachedVariablesEvent as OriginBeforeProcessCachedVariablesEvent;
use Psr\Http\Message\UriInterface;

/**
 * This event will triggered before start to replace placeholder inside of cached URLs
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class BeforeProcessCachedVariablesEvent extends OriginBeforeProcessCachedVariablesEvent
{
    /**
     * The uri, used to identify, what placeholder is part of the path and which one is part of the query
     *
     * @var UriInterface
     */
    protected $uri;

    /**
     * A list of router configurations, containing information how to process variables
     *
     * @var array
     */
    protected $routerConfiguration = [];

    public function __construct(
        UriInterface $uri,
        array $routerConfiguration,
        array $variableKeys,
        array $variableValues
    ) {
        parent::__construct($variableKeys, $variableValues);
        $this->uri = $uri;
        $this->routerConfiguration = $routerConfiguration;
    }

    /**
     * The URI containing placeholder
     *
     * @return UriInterface
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * Available router configurations
     *
     * @return array
     */
    public function getRouterConfiguration(): array
    {
        return $this->routerConfiguration;
    }
}