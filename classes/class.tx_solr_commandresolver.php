<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Ingo Renner <ingo@typo3.org>
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
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_CommandResolver {

	protected $commandPath;
	protected $defaultCommand;

	/**
	 * constructor for class tx_solr_CommandResolver
	 */
	public function __construct($commandPath, $commandPrefix = 'tx_solr_', $defaultCommand = 'Default') {
		$this->commandPath    = $commandPath;
		$this->commandPrefix  = $commandPrefix;
		$this->defaultCommand = $defaultCommand;
	}

	/**
	 * Creates an instance of a command class
	 *
	 * @param	string	command name
	 * @param	object	parent object, most likely a plugin object
	 * @return 	tx_solr_Command the requested command if found, or null othwise
	 */
	public function getCommand($commandName, $parent) {
		$command = null;

		if (!empty($commandName)) {
			$command = $this->loadCommand($commandName, $parent);

			if (!($command instanceof tx_solr_Command)) {
				$command = $this->loadCommand($this->defaultCommand);
			}
		}

		return $command;
	}

	/**
	 * Tries to load a file containing a command class and instantiates that
	 * command if a file containing the right class was found.
	 *
	 * @param	string	command name
	 * @param	object	parent object, most likely a plugin object
	 * @return 	tx_solr_Command the requested command if found, or null othwise
	 */
	protected function loadCommand($commandName, $parent) {
		$className = $this->commandPrefix . tx_solr_Util::camelize($commandName) . 'Command';
		$file      = strtolower($this->commandPath . 'class.' . $className . '.php');

		$command   = false;

		if (file_exists($file)) {
			include_once($file);
		}

		if (class_exists($className)) {
			$command = t3lib_div::makeInstance($className, $parent);
		}

		return $command;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_commandresolver.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_commandresolver.php']);
}

?>