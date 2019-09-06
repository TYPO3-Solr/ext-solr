<?php
namespace ApacheSolrForTypo3\Solr\System\Environment;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2016 Timo Hund <timo.hund@dkd.de>
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

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Helper class for the cli environment helps to define the variables and constants
 * that are required in the cli context to allow frontend related operations in the cli context.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class CliEnvironment implements SingletonInterface
{

    /**
     * @var array
     */
    protected $backupServerVariables = [];

    /**
     * @var bool
     */
    protected $isInitialized = false;

    /**
     * @return void
     */
    public function backup()
    {
        $this->backupServerVariables = $_SERVER;
    }

    /**
     * Initializes the frontend related server variables for the cli context.
     *
     * @param string $webRoot
     * @param string $scriptFileName
     * @param string $phpSelf
     * @param string $scriptName
     * @throws WebRootAllReadyDefinedException
     * @return bool
     */
    public function initialize($webRoot, $scriptFileName = '', $phpSelf = '/index.php', $scriptName = '/index.php')
    {
        // if the environment has be initialized once, we do not need to initialize it twice.
        if ($this->isInitialized) {
            return false;
        }

        if (defined('TYPO3_PATH_WEB')) {
            throw new WebRootAllReadyDefinedException('TYPO3_PATH_WEB is already defined');
        }

        if ($scriptFileName === '') {
            $scriptFileName = Environment::getPublicPath() . '/';
        }

        define('TYPO3_PATH_WEB', $webRoot);
        $_SERVER['SCRIPT_FILENAME'] = $scriptFileName;
        $_SERVER['PHP_SELF'] = $phpSelf;
        $_SERVER['SCRIPT_NAME'] = $scriptName;

        $this->isInitialized = true;
        return true;
    }

    /**
     * @return bool
     */
    public function getIsInitialized()
    {
        return $this->isInitialized;
    }

    /**
     * @return void
     */
    public function restore()
    {
        $_SERVER = $this->backupServerVariables;
    }
}
