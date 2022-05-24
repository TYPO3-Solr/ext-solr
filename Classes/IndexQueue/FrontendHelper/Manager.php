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

use RuntimeException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Index Queue Page Indexer frontend helper manager.
 *
 * Manages frontend helpers and creates instances.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Manager
{

    /**
     * Frontend helper descriptions.
     *
     * @var array
     */
    protected static $frontendHelperRegistry = [];

    /**
     * Instances of activated frontend helpers.
     *
     * @var array
     */
    protected $activatedFrontendHelpers = [];

    /**
     * Registers a frontend helper class for a certain action.
     *
     * @param string $action Action to register.
     * @param string $class Class to register for an action.
     */
    public static function registerFrontendHelper($action, $class)
    {
        self::$frontendHelperRegistry[$action] = $class;
    }

    /**
     * Tries to find a frontend helper for a given action. If found, creates an
     * instance of the helper.
     *
     * @param string $action The action to get a frontend helper for.
     * @return FrontendHelper Index Queue page indexer frontend helper
     * @throws RuntimeException if the class registered for an action is not an implementation of ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\FrontendHelper
     */
    public function resolveAction($action)
    {
        if (!array_key_exists($action, self::$frontendHelperRegistry)) {
            return null;
        }

        $frontendHelper = GeneralUtility::makeInstance(self::$frontendHelperRegistry[$action]);
        if (!$frontendHelper instanceof FrontendHelper) {
            $message = self::$frontendHelperRegistry[$action] . ' is not an implementation of ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\FrontendHelper';
            throw new RuntimeException($message, 1292497896);
        }

        $this->activatedFrontendHelpers[$action] = $frontendHelper;
        return $frontendHelper;
    }

    /**
     * Gets an array with references to activated frontend helpers.
     *
     * @return array Array of references to activated frontend helpers.
     */
    public function getActivatedFrontendHelpers()
    {
        return $this->activatedFrontendHelpers;
    }
}
