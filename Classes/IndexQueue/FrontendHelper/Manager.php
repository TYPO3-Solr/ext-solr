<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

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
