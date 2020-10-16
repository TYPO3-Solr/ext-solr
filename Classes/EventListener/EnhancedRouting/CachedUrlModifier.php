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

namespace ApacheSolrForTypo3\Solr\EventListener\EnhancedRouting;

use ApacheSolrForTypo3\Solr\Event\EnhancedRouting\BeforeReplaceVariableInCachedUrlEvent;

/**
 * This modifier is in use if the URL processed by a route enhancer
 *
 * In this case some characters need to be replaced in order to do a placeholder replacement later
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class CachedUrlModifier
{
    public function __invoke(BeforeReplaceVariableInCachedUrlEvent $event): void
    {
        $uri = $event->getUri();
        $path = $uri->getPath();
        $path = str_replace(':', '%3A', $path);

        $uri = $uri->withPath($path);
        $event->replaceUri($uri);
    }
}