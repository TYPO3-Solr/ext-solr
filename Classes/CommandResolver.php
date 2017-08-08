<?php
namespace ApacheSolrForTypo3\Solr;
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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
 * command resolver
 *
 * @deprecated Not supported with fluid templating, will be removed in 8.0
 * @author Ingo Renner <ingo@typo3.org>
 */
class CommandResolver {

    /**
     * This method is deprecated an only the signature is kept, to avoid error during the update from 6.1.0 to 7.0.0
     *
     * @deprecated Not supported with fluid templating, will be removed in 8.0
     * @param string $plugins comma separated list of plugin names (without pi_ prefix)
     * @param string $commandName command name
     * @param string $commandClass name of the class implementing the command
     * @param int $requirements Bitmask of which requirements need to be met for a command to be executed
     */
    public static function registerPluginCommand($plugins, $commandName, $commandClass, $requirements = 2)
    {
        GeneralUtility::logDeprecatedFunction();
    }
}