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

use ApacheSolrForTypo3\Solr\Event\EnhancedRouting\BeforeProcessCachedVariablesEvent;
use ApacheSolrForTypo3\Solr\Routing\RoutingService;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Event listener to handle path elements containing placeholder
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class CachedPathVariableModifier
{
    public function __invoke(BeforeProcessCachedVariablesEvent $event): void
    {
        $pathVariables = $this->getPathVariablesFromUri($event->getUri());

        // No path variables exists .. skip processing
        if (empty($pathVariables)) {
            return;
        }

        $variableKeys = $event->getVariableKeys();
        $variableValues = $event->getVariableValues();
        $enhancerConfiguration = $event->getRouterConfiguration();

        $multiValue = false;

        // TODO: Instead of checking a string, check an interface (special interface for combined enhancer)
        //       This have be enabled by configuration to avoid long rendering times
        if ($enhancerConfiguration['type'] === 'CombinedFacetEnhancer') {
            $multiValue = true;
        }

        /* @var RoutingService $routingService */
        $routingService = GeneralUtility::makeInstance(RoutingService::class, $enhancerConfiguration['solr']);

        for ($i = 0; $i < count($variableKeys); $i++) {
            $standardizedKey = $this->standardizeKey($variableKeys[$i]);
            if (!in_array($standardizedKey, $pathVariables)) {
                continue;
            }
            if (empty($variableValues[$standardizedKey])) {
                continue;
            }
            $value = '';
            if ($multiValue) {
                $facets = $routingService->pathFacetStringToArray(
                    $this->standardizeKey((string)$variableValues[$standardizedKey])
                );
                $singleValues = [];
                foreach ($facets as $facet) {
                    [$prefix, $value] = explode(
                        ':',
                        $facet,
                        2
                    );
                    $singleValues[] = $value;
                }
                $value = $routingService->pathFacetsToString($singleValues);
            } else {
                [$prefix, $value] = explode(
                    ':',
                    $this->standardizeKey((string)$variableValues[$standardizedKey]),
                    2
                );
            }
            $variableValues[$standardizedKey] = $value;
        }

        $event->setVariableValues($variableValues);
    }

    /**
     * Extract path variables from URI
     *
     * @param UriInterface $uri
     * @return array
     */
    protected function getPathVariablesFromUri(UriInterface $uri): array
    {
        $elements = explode('/', $uri->getPath());
        $variables = [];

        foreach ($elements as $element) {
            if (empty($element)) {
                continue;
            }
            $element = $this->standardizeKey($element);
            if (substr($element, 0, 3) !== '###') {
                continue;
            }

            $variables[] = $element;
        }

        return $variables;
    }

    /**
     * Standardize a given string in order to reduce the amount of if blocks
     *
     * @param string $key
     * @return string
     */
    protected function standardizeKey(string $key): string
    {
        $map = [
            '%23' => '#',
            '%3A' => ':',
        ];
        return str_replace(array_keys($map), array_values($map), $key);
    }
}