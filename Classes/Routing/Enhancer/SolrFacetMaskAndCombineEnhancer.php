<?php
namespace ApacheSolrForTypo3\Solr\Routing\Enhancer;

use ApacheSolrForTypo3\Solr\Routing\RoutingService;
use ApacheSolrForTypo3\Solr\Utility\RoutingUtility;
use TYPO3\CMS\Core\Routing\Enhancer\AbstractEnhancer;
use TYPO3\CMS\Core\Routing\Enhancer\RoutingEnhancerInterface;
use TYPO3\CMS\Core\Routing\Route;
use TYPO3\CMS\Core\Routing\RouteCollection;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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

class SolrFacetMaskAndCombineEnhancer extends AbstractEnhancer implements RoutingEnhancerInterface, SolrRouteEnhancerInterface
{
    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $namespace;

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
        $this->namespace = $this->configuration['extensionKey'] ?? 'tx_solr';
    }

    /**
     * {@inheritdoc}
     */
    public function enhanceForMatching(RouteCollection $collection): void
    {
        /** @var Route $defaultPageRoute */
        $defaultPageRoute = $collection->get('default');
        $variant = $this->getVariant($defaultPageRoute, $this->configuration);
        $collection->add(
            'enhancer_' . $this->namespace . spl_object_hash($variant),
            $variant
        );
    }

    /**
     * Builds a variant of a route based on the given configuration.
     *
     * @param Route $defaultPageRoute
     * @param array $configuration
     * @return Route
     */
    protected function getVariant(Route $defaultPageRoute, array $configuration): Route
    {
        $arguments = $configuration['_arguments'] ?? [];
        unset($configuration['_arguments']);

        $variableProcessor = $this->getVariableProcessor();
        $routePath = $this->modifyRoutePath($configuration['routePath']);
        $routePath = $variableProcessor->deflateRoutePath($routePath, $this->namespace, $arguments);
        $variant = clone $defaultPageRoute;
        $variant->setPath(rtrim($variant->getPath(), '/') . '/' . ltrim($routePath, '/'));
        $variant->addOptions(['_enhancer' => $this, '_arguments' => $arguments]);
        $variant->setDefaults(
            $variableProcessor->deflateKeys($this->configuration['defaults'] ?? [], $this->namespace, $arguments)
        );
        $this->applyRouteAspects($variant, $this->aspects ?? [], $this->namespace);
        $this->applyRequirements($variant, $this->configuration['requirements'] ?? [], $this->namespace);
        return $variant;
    }

    /**
     * {@inheritdoc}
     */
    public function enhanceForGeneration(RouteCollection $collection, array $parameters): void
    {
        // No parameter for this namespace given, so this route does not fit the requirements
        if (!is_array($parameters[$this->namespace])) {
            return;
        }
        /** @var Route $defaultPageRoute */
        $defaultPageRoute = $collection->get('default');
        $variant = $this->getVariant($defaultPageRoute, $this->configuration);
        $compiledRoute = $variant->compile();
        $deflatedParameters = $this->deflateParameters($variant, $parameters);
        $deflatedParameters = $this->combineArrayParameters($deflatedParameters);
        $deflatedParameters = $this->inflateUnprocessedVariables($variant, $deflatedParameters);
        // TODO: Implode query parameters if configured!
        $deflatedParameters = $this->replaceVariableWithHash($variant, $deflatedParameters);
        $variant->setOption('deflatedParameters', $deflatedParameters);
        $variables = array_flip($compiledRoute->getPathVariables());
        $mergedParams = array_replace($variant->getDefaults(), $deflatedParameters);
        // all params must be given, otherwise we exclude this variant
        if ($diff = array_diff_key($variables, $mergedParams)) {
            return;
        }

        $collection->add('enhancer_' . $this->namespace . spl_object_hash($variant), $variant);
    }

    /**
     * This method combine arguments into a single string
     * It is possible to configure a path segment that contains more than one value.
     * This method build one single string out of it.
     *
     * @param array $parameters
     * @return array
     */
    protected function combineArrayParameters(array $parameters = []): array
    {
        $parametersCombined = [];
        foreach ($this->configuration['_arguments'] as $fieldName => $fieldPath) {
            $combinedKey = RoutingUtility::deflateString($fieldPath, $this->namespace);
            $parameterPattern = RoutingUtility::deflateString($fieldPath, $this->namespace);
            $elements = explode('-', $parameterPattern);
            array_pop($elements);
            $parameterPattern = implode('-', $elements);
            $parameterPattern .= '__\d+';

            // @TODO: Here the behaviour changed. The whole functionality need to be tested
            $pathElements = explode('-', $fieldPath);
            $facetTypeToHandle = array_pop($pathElements);

            if (empty($facetTypeToHandle)) {
                continue;
            }
            foreach ($parameters as $parameterName => $parameterValue) {
                // Skip parameters that we don't care for
                if (!preg_match('/' . $parameterPattern . '/', $parameterName)) {
                    $parametersCombined[$parameterName] = $parameterValue;
                    continue;
                }

                if (!array_key_exists($combinedKey, $parametersCombined)) {
                    $parametersCombined[$combinedKey] = [];
                }

                $parameterNameNew = $parameterName;
                $parameterValueNew = $parameterValue;

                // Placeholder for cached URIs (type is the last part of the parameter value)
                if (substr($parameterValue, 0, 3) === '###') {
                    $facetFieldElements = explode(':', $parameterValue);
                    $facetField = array_pop($facetFieldElements);
                    $facetField = substr($facetField, 0, strlen($facetField) - 3);

                    $facetValue = null;
                    if (strpos($facetField, '%3A') !== false) {
                        [$facetField, $facetValue] = explode('%3A', $facetField, 2);
                    }

                    if ($facetField === $facetTypeToHandle) {
                        $parameterNameNew = $combinedKey;
                        if ($facetValue !== null) {
                            $parameterValueNew = $facetValue;
                        }
                    }
                } else {
                    [$facetField, $facetValue] = explode(':', $parameterValue, 2);
                    if (substr($facetValue, 0, mb_strlen($facetField) + 1) === $facetField . ':') {
                        [$facetField, $facetValue] = explode(':', $facetValue, 2);
                    }
                    if ($facetField === $facetTypeToHandle) {
                        $parameterNameNew = $combinedKey;
                        $parameterValueNew = $facetValue;
                    }
                }
                if (is_array($parametersCombined[$parameterNameNew])) {
                    $parametersCombined[$parameterNameNew][] = $parameterValueNew;
                } else {
                    $parametersCombined[$parameterNameNew] = $parameterValueNew;
                }
            }

            if (isset($parametersCombined[$combinedKey]) && is_array($parametersCombined[$combinedKey])) {
                $parametersCombined[$combinedKey] = $this->getRoutingService()->facetsToString(
                    $parametersCombined[$combinedKey]
                );
            }
        }

        return $parametersCombined;
    }

    /**
     * We need to convert our internal names by hand into hashes.
     *
     * This needs to be done, because we not exactly configure a path inside of the site configuration.
     * What we are configure is a placeholder contains information, what we should process
     *
     * @param Route $route
     * @param array $parameters
     * @return array
     */
    protected function replaceVariableWithHash(Route $route, array $parameters = []): array
    {
        $routPath = $this->configuration['routePath'];
        $pathElements = explode('/', $routPath);
        // Compiles path token are in reversed order
        $pathElements = array_reverse($pathElements);
        $routeArguments = $route->getArguments();
        $pathTokens = $route->compile()->getTokens();

        $keepVariables = [];

        for ($i = 0; $i < count($pathTokens); $i++) {
            // wee only looking for variables
            if ($pathTokens[$i][0] !== 'variable') {
                continue;
            }

            $pathVariable = $pathElements[$i];
            // Remove marker
            $pathVariable = substr($pathVariable, 1, strlen($pathVariable) - 1);
            $pathVariable = substr($pathVariable, 0, strlen($pathVariable) - 1);

            // Skip if we could not resolve the variable
            if (!isset($routeArguments[$pathVariable])) {
                continue;
            }

            $parameterName = RoutingUtility::deflateString(
                $routeArguments[$pathVariable],
                $this->namespace
            );

            // Skip processing if variable not listed inside of given parameters
            if (!isset($parameters[$parameterName])) {
                continue;
            }

            $parameters[$pathTokens[$i][3]] = $parameters[$parameterName];
            $keepVariables[] = $pathTokens[$i][3];
            unset($parameters[$parameterName]);
        }

        return $parameters;
    }

    /**
     * This method inflates some variables back, because if a route has deflated variables
     * they are used in the upcoming process.
     *
     * @see \TYPO3\CMS\Core\Routing\PageRouter::generateUri
     *
     * @param Route $variant
     * @param array $deflatedParameters
     * @return array
     */
    protected function inflateUnprocessedVariables(Route $variant, array $deflatedParameters): array
    {
        $mixedVariables = [];
        $variablesToHandle = [];
        foreach ($variant->getArguments() as $argumentKey => $argumentPath) {
            $variablesToHandle[] = RoutingUtility::deflateString($argumentPath, $this->namespace);
        }

        foreach ($deflatedParameters as $argumentKey => $argumentPath) {
            if (in_array($argumentKey, $variablesToHandle)) {
                $mixedVariables[$argumentKey] = $deflatedParameters[$argumentKey];
            } else {
                $elements = explode('__', $argumentKey);
                $elements[] = $deflatedParameters[$argumentKey];
                $data = $this->inflateQueryParams($elements);

                $mixedVariables = array_merge_recursive($mixedVariables, $data);
            }
        }

        return $mixedVariables;
    }

    /**
     * Inflate deflated query parameters
     *
     * @param array $elements
     * @return array
     */
    protected function inflateQueryParams(array $elements = []): array
    {
        $result = [];
        if (count($elements) > 2) {
            $key = array_shift($elements);
            $result[$key] = $this->inflateQueryParams($elements);
        } elseif (count($elements) == 2) {
            $result[$elements[0]] = $elements[1];
        }

        return $result;
    }

    /**
     * Deflate query parameters
     *
     * @param Route $route
     * @param array $parameters
     * @return array
     */
    protected function deflateParameters(Route $route, array $parameters): array
    {
        return $this->getVariableProcessor()->deflateNamespaceParameters(
            $parameters,
            $this->namespace,
            $route->getArguments()
        );
    }

    /**
     * @return RoutingService
     */
    protected function getRoutingService(): RoutingService
    {
        return GeneralUtility::makeInstance(
            RoutingService::class,
            $this->configuration['solr'],
            (string)$this->configuration['extensionKey']
        )->withPathArguments($this->configuration['_arguments']);
    }
}
