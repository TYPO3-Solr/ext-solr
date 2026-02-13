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

namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

use Psr\Container\ContainerInterface;
use RuntimeException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Index Queue Page Indexer frontend helper manager.
 *
 * Manages frontend helpers and creates instances.
 */
class Manager
{
    /**
     * Frontend helper descriptions.
     */
    protected static array $frontendHelperRegistry = [];

    /**
     * Instances of activated frontend helpers.
     */
    protected array $activatedFrontendHelpers = [];

    protected ContainerInterface $container;

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container ?? GeneralUtility::getContainer();
    }

    /**
     * Registers a frontend helper class for a certain action.
     *
     * @param string $action Action to register.
     * @param string $class Class to register for an action.
     */
    public static function registerFrontendHelper(string $action, string $class): void
    {
        self::$frontendHelperRegistry[$action] = $class;
    }

    /**
     * Tries to find a frontend helper for a given action. If found, creates an
     * instance of the helper.
     *
     * @param string $action The action to get a frontend helper for.
     * @return FrontendHelper|null Index Queue page indexer frontend helper
     */
    public function resolveAction(string $action): ?FrontendHelper
    {
        if (!array_key_exists($action, self::$frontendHelperRegistry)) {
            return null;
        }

        $frontendHelperClass = self::$frontendHelperRegistry[$action];
        $frontendHelper = $this->container->get($frontendHelperClass);
        if (!$frontendHelper instanceof FrontendHelper) {
            $message = $frontendHelperClass . ' is not an implementation of ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\FrontendHelper';
            throw new RuntimeException($message, 1292497896);
        }

        $this->activatedFrontendHelpers[$action] = $frontendHelper;
        return $frontendHelper;
    }

    /**
     * Gets an array with references to activated frontend helpers.
     *
     * @return FrontendHelper[] Array of references to activated frontend helpers.
     */
    public function getActivatedFrontendHelpers(): array
    {
        return $this->activatedFrontendHelpers;
    }
}
