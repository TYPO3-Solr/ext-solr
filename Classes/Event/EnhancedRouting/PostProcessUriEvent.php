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

use ApacheSolrForTypo3\Solr\Event\Routing\PostProcessUriEvent as OriginPostProcessUriEvent;
use Psr\Http\Message\UriInterface;

/**
 * This event is invoke after an uri with router configuration was processed.
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class PostProcessUriEvent extends OriginPostProcessUriEvent
{
    /**
     * The router configuration
     *
     * @var array
     */
    protected $routerConfiguration = [];

    /**
     * BeforeReplaceVariableInCachedUrlEvent constructor.
     * @param UriInterface $uri
     * @param array $routerConfiguration
     */
    public function __construct(UriInterface $uri, array $routerConfiguration)
    {
        parent::__construct($uri);
        $this->routerConfiguration = $routerConfiguration;
    }

    /**
     * @return array
     */
    public function getRouterConfiguration(): array
    {
        return $this->routerConfiguration;
    }
}