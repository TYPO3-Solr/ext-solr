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

use ApacheSolrForTypo3\Solr\Event\Routing\PostProcessUriEvent;
use ApacheSolrForTypo3\Solr\Routing\RoutingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This event listener concat the filter if configured or masking is active.
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class PostEnhancedUriProcessor
{
    public function __invoke(PostProcessUriEvent $event): void
    {
        if (!$event->hasRouting()) {
            return;
        }
        $configuration = $event->getRouterConfiguration();

        /* @var RoutingService $routingService */
        $routingService = GeneralUtility::makeInstance(
            RoutingService::class,
            $configuration['solr'],
            (string)$configuration['extensionKey']
        );

        $routingService->fromRoutingConfiguration($configuration);

        if (!$routingService->shouldConcatQueryParameters()) {
            return;
        }

        $uri = $event->getUri();
        parse_str($uri->getQuery(), $queryParameters);

        if (empty($queryParameters) || !is_array($queryParameters)) {
            $queryParameters = [];
        }

        /*
         * The order here is important.
         * Method maskQueryParameters expects that the filter array does not contain multiple entries for the same facet.
         */
        $queryParameters = $routingService->concatQueryParameter($queryParameters);
        $queryParameters = $routingService->maskQueryParameters($queryParameters);
        $path = $routingService->finalizePathQuery($uri->getPath());
        $query = http_build_query($queryParameters);
        $uri = $uri->withQuery($query);
        $uri = $uri->withPath($path);
        $event->replaceUri($uri);
    }
}
