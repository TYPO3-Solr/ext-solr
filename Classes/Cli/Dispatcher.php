<?php
namespace ApacheSolrForTypo3\Solr\Cli;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

use TYPO3\CMS\Core\Controller\CommandLineController;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A CLI command dispatcher
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Dispatcher extends CommandLineController
{

    /**
     * Constructor.
     *
     * Initializes the help parameters.
     *
     */
    public function __construct()
    {
        parent::__construct();

        // Setting help texts:
        $this->cli_help['name'] = 'solr -- Solr commands for TYPO3 installations';
        $this->cli_help['synopsis'] = 'command ###OPTIONS###';
        $this->cli_help['description'] = 'Dispatches to various Solr commands.';
        $this->cli_help['examples'] = "./cli_dispatch.phpsh solr updateConnections \nThis will update the Solr connections.";
        $this->cli_help['author'] = 'Ingo Renner';
    }

    /**
     * Dispatches the given command.
     *
     */
    public function dispatch()
    {
        $command = (string)$this->cli_args['_DEFAULT'][1];

        switch ($command) {
            case 'updateConnections':
                $connectionManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager');
                $connectionManager->updateConnections();
                break;

            default:
                echo 'Unknown Command.' . LF;
                $this->cli_help();
        }

        echo LF;
    }
}
