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
 * This event will be triggered before process variable keys and values
 */
class BeforeCachedVariablesAreProcessedEvent
{
    /**
     * The uri, used to identify, what placeholder is part of the path and which one is part of the query
     */
    protected UriInterface $uri;

    /**
     * A list of router configurations, containing information how to process variables
     */
    protected array $routerConfiguration = [];

    /**
     * List of variable keys
     */
    protected array $variableKeys = [];

    /**
     * List of variable values
     */
    protected array $variableValues = [];

    /**
     * BeforeCachedVariablesAreProcessedEvent constructor.
     */
    public function __construct(
        UriInterface $uri,
        array $routerConfiguration,
        array $variableKeys,
        array $variableValues,
    ) {
        $this->uri = $uri;
        $this->routerConfiguration = $routerConfiguration;
        $this->variableKeys = $variableKeys;
        $this->variableValues = $variableValues;
    }

    /**
     * Returns the variable keys
     */
    public function getVariableKeys(): array
    {
        return $this->variableKeys;
    }

    /**
     * Sets the variable keys
     *
     * @return $this
     */
    public function setVariableKeys(array $variableKeys): self
    {
        $this->variableKeys = $variableKeys;
        return $this;
    }

    /**
     * Returns the variable values
     */
    public function getVariableValues(): array
    {
        return $this->variableValues;
    }

    /**
     * Sets the variable values
     *
     * @return $this
     */
    public function setVariableValues(array $variableValues): self
    {
        $this->variableValues = $variableValues;
        return $this;
    }

    public function hasRouting(): bool
    {
        return !empty($this->routerConfiguration);
    }

    /**
     * The URI containing placeholder
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * Available router configurations
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
