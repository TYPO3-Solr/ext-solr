<?php
namespace ApacheSolrForTypo3\Solr\Backend\SolrModule;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Administration Module Manager
 *
 * @deprecated Not supported with fluid templating, will be removed in 8.0
 * @author Ingo Renner <ingo@typo3.org>
 */
class AdministrationModuleManager
{

    /**
     * Registers a Solr administration module
     *
     * @deprecated Not supported with fluid templating, please register your module as solr submodule, will be removed in 8.0
     * @param string $extensionIdentifier Identifier for the extension, that is the vendor followed by a dot followed by the extension key
     * @param string $controllerName Controller name
     * @param array $controllerActions Array of valid controller actions
     * @return void
     */
    public static function registerModule($extensionIdentifier, $controllerName, array $controllerActions)
    {
        GeneralUtility::logDeprecatedFunction();
    }
}