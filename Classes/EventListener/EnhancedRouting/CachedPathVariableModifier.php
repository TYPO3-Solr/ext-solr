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

use ApacheSolrForTypo3\Solr\Event\Routing\BeforeCachedVariablesAreProcessedEvent;
use ApacheSolrForTypo3\Solr\Routing\RoutingService;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Event listener to handle path elements containing placeholder
 *
 *
 * @noinspection PhpUnused Listener for {@link BeforeCachedVariablesAreProcessedEvent}
 */
class CachedPathVariableModifier
{
    protected RoutingService $routingService;

    public function __invoke(BeforeCachedVariablesAreProcessedEvent $event): void
    {
        if (!$event->hasRouting()) {
            return;
        }
        $pathVariables = $this->getPathVariablesFromUri($event->getUri());

        // No path variables exists .. skip processing
        if (empty($pathVariables)) {
            return;
        }

        $variableKeys = $event->getVariableKeys();
        $variableValues = $event->getVariableValues();
        $enhancerConfiguration = $event->getRouterConfiguration();

        $this->routingService = GeneralUtility::makeInstance(
            RoutingService::class,
            $enhancerConfiguration['solr'] ?? [],
            (string)$enhancerConfiguration['extensionKey']
        );

        if (!$this->routingService->isRouteEnhancerForSolr((string)$enhancerConfiguration['type'])) {
            return;
        }

        $standardizedKeys = $variableKeys;

        $variableKeysCount = count($variableKeys);
        for ($i = 0; $i < $variableKeysCount; $i++) {
            $standardizedKey = $this->standardizeKey($variableKeys[$i]);
            if (!$this->containsPathVariable($standardizedKey, $pathVariables) || empty($variableValues[$standardizedKey])) {
                continue;
            }
            // Note: Some values contain the multi value separator
            if ($this->containsMultiValue()) {
                // Note: if the customer configured a + as separator an additional check on the facet value is required!
                $facets = $this->routingService->pathFacetStringToArray(
                    $this->standardizeKey((string)$variableValues[$standardizedKey])
                );

                $singleValues = [];
                $index = 0;
                foreach ($facets as $facet) {
                    if (str_contains($facet, ':')) {
                        $value = explode(':', $facet, 2)[1];
                        $singleValues[] = $value;
                        $index++;
                    } else {
                        $singleValues[$index - 1] .= ' ' . $facet;
                    }
                }
                $value = $this->routingService->pathFacetsToString($singleValues);
            } else {
                $value = explode(
                    ':',
                    $this->standardizeKey((string)$variableValues[$standardizedKey]),
                    2
                )[1];
            }
            $standardizedKeys[$i] = $standardizedKey;
            $variableValues[$standardizedKey] = $value;
        }

        $event->setVariableValues($variableValues);
        $event->setVariableKeys($standardizedKeys);
    }

    /**
     * Extract path variables from URI
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
            if (!str_starts_with($element, '###')) {
                continue;
            }

            $variables[] = $element;
        }

        return $variables;
    }

    /**
     * Standardize a given string in order to reduce the amount of if blocks
     */
    protected function standardizeKey(string $key): string
    {
        $map = [
            '%23' => '#',
            '%3A' => ':',
        ];
        return str_replace(array_keys($map), array_values($map), $key);
    }

    /**
     * Check if the variable is includes within the path variables
     */
    protected function containsPathVariable(string $variableName, array $pathVariables): bool
    {
        if (in_array($variableName, $pathVariables)) {
            return true;
        }
        foreach ($pathVariables as $value) {
            $segments = explode($this->routingService->getUrlFacetPathService()->getMultiValueSeparator(), $value);
            if (in_array($variableName, $segments)) {
                return true;
            }
        }

        return false;
    }

    protected function containsMultiValue(): bool
    {
        // @todo: implement the check, or remove contents of if statement.
        return false;
    }
}
