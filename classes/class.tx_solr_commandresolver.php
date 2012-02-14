<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Ingo Renner <ingo@typo3.org>
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


/**
 * command resolver
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_CommandResolver {

	/**
	 * A mapping of command names to caommand classes to use when executing plugins.
	 *
	 * @var	array
	 */
	protected static $commands = array();

	/**
	 * Registers a command and its command class for several plugins
	 *
	 * @param	string	$plugins comma separated list of plugin names (without pi_ prefix)
	 * @param	string	$commandName command name
	 * @param	string	$commandClass name of the class implementing the command
	 * @param	integer	Bitmask of which requirements need to be met for a command to be executed
	 */
	public static function registerPluginCommand($plugins, $commandName, $commandClass, $requirements = tx_solr_PluginCommand::REQUIREMENT_HAS_SEARCHED) {
		if (!array_key_exists($commandName, self::$commands)) {
			$plugins = t3lib_div::trimExplode(',', $plugins, TRUE);

			self::$commands[$commandName] = array(
				'plugins'      => $plugins,
				'commandName'  => $commandName,
				'commandClass' => $commandClass,
				'requirements' => $requirements
			);
		}
	}

	/**
	 * Gets the commands registered for a specific plugin.
	 *
	 * @param	string	Plugin name to get the registered commands for.
	 * @param	integer	Bitmask required by commands to be registered for.
	 * @return	array	An array of plugin command names registered
	 */
	public static function getPluginCommands($pluginName, $pluginStatus = tx_solr_PluginCommand::REQUIREMENT_NONE) {
		$commands = array();

		$requiredBits = self::getRequiredBits($pluginStatus);
		foreach (self::$commands as $command) {
			if (!in_array($pluginName, $command['plugins'])) {
				continue;
			}

			if ($command['requirements'] == tx_solr_PluginCommand::REQUIREMENT_NONE) {
				$commands[] = $command['commandName'];
				continue;
			}

			foreach ($requiredBits as $requiredBit) {
				$currentBitValue = (1 << $requiredBit);
				$bitMatched = (boolean) ($command['requirements'] & $currentBitValue);

				if (!$bitMatched) {
					continue 2;
				}
			}
			$commands[] = $command['commandName'];
		}

		return $commands;
	}

	/**
	 * Determines which bits are set as a requirement for the plugin commands
	 * to be registered for.
	 *
	 * @param	integer	$bitmask Bitmask
	 * @return	array	An array of integers - the bit positions set to 1
	 */
	protected static function getRequiredBits($bitmask) {
		$requiredBits = array();

		for ($i = 0; $i < tx_solr_PluginCommand::REQUIREMENTS_NUM_BITS; $i++) {
			if (!(($bitmask & pow(2, $i)) == 0)) {
				$requiredBits[] = $i;
			}
		}

		return $requiredBits;
	}

	/**
	 * Creates an instance of a command class
	 *
	 * @param	string	command name
	 * @param	object	parent object, most likely a plugin object
	 * @return	tx_solr_PluginCommand the requested command if found, or NULL othwise
	 */
	public function getCommand($commandName, $parent) {
		$command = NULL;

		if (array_key_exists($commandName, self::$commands)) {
			$className = self::$commands[$commandName]['commandClass'];
			$command   = t3lib_div::makeInstance($className, $parent);

			if (!($command instanceof tx_solr_PluginCommand)) {
				throw new RuntimeException(
					self::$commands[$commandName]['commandClass'] . ' is not an implementation of tx_solr_PluginCommand',
					1297899998
				);
			}
		}

		return $command;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_commandresolver.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_commandresolver.php']);
}

?>