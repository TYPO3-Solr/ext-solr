<?php

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

namespace ApacheSolrForTypo3\Solr\Search;

use ApacheSolrForTypo3\Solr\Search\SearchComponent;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Search components manager, registration and stuff...
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class SearchComponentManager
{

    /**
     * Search component registry.
     *
     * @var array
     */
    protected static $searchComponents = [];

    /**
     * Registers a search component.
     *
     * @param string $componentName Search component name
     * @param string $componentClassName Component class
     */
    public static function registerSearchComponent(
        $componentName,
        $componentClassName
    ) {
        self::$searchComponents[$componentName] = $componentClassName;
    }

    /**
     * Returns all currently registered search components.
     *
     * @return array An array of search component instances
     */
    public function getSearchComponents()
    {
        $searchComponents = [];

        foreach (self::$searchComponents as $componentName => $componentClass) {
            $searchComponents[$componentName] = $this->getSearchComponent($componentName);
        }

        return $searchComponents;
    }

    /**
     * Instanciates a registered search component
     *
     * @param string $componentName Search component name
     * @return SearchComponent Instance of the requested search component
     * @throws \InvalidArgumentException if $componentName is not a registered search component
     * @throws \RuntimeException if the class registered for $componentName is not an implementation of ApacheSolrForTypo3\Solr\Search\SearchComponent
     */
    public function getSearchComponent($componentName)
    {
        if (!array_key_exists($componentName, self::$searchComponents)) {
            throw new \InvalidArgumentException(
                'No search component registered named ' . $componentName,
                1343398440
            );
        }

        $searchComponent = GeneralUtility::makeInstance(self::$searchComponents[$componentName]);

        if (!($searchComponent instanceof SearchComponent)) {
            throw new \RuntimeException(
                'Class ' . self::$searchComponents[$componentName] . ' must implement interface ' . SearchComponent::class,
                1343398621
            );
        }

        return $searchComponent;
    }

    /**
     * Unregisters a search component
     *
     * @param string $componentName Search component name
     */
    public function removeSearchComponent($componentName)
    {
        if (!array_key_exists($componentName, self::$searchComponents)) {
            return;
        }

        unset(self::$searchComponents[$componentName]);
    }
}
