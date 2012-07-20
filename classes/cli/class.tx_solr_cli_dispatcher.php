<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Ingo Renner <ingo.renner@dkd.de>
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
 * A CLI command dispatcher
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_cli_Dispatcher extends t3lib_cli {

	/**
	 * Constructor.
	 *
	 * Initializes the help parameters.
	 *
	 */
	public function __construct() {
		parent::t3lib_cli();

			// Setting help texts:
		$this->cli_help['name']        = 'solr -- Solr commands for TYPO3 installations';
		$this->cli_help['synopsis']    = 'command ###OPTIONS###';
		$this->cli_help['description'] = 'Dispatches to various Solr commands.';
		$this->cli_help['examples']    = "./cli_dispatch.phpsh solr updateConnections \nThis will update the Solr connections.";
		$this->cli_help['author']      = 'Ingo Renner';
	}

	/**
	 * Dispatches the given command.
	 *
	 */
	public function dispatch() {
		$command = (string) $this->cli_args['_DEFAULT'][1];

		switch ($command) {
			case 'updateConnections':
				$connectionManager = t3lib_div::makeInstance('tx_solr_ConnectionManager');
				$connectionManager->updateConnections();
				break;

			default:
				echo 'Unknown Command.' . LF;
				$this->cli_help();
		}

		echo LF;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/cli/class.tx_solr_cli_dispatcher.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/cli/class.tx_solr_cli_dispatcher.php']);
}

?>