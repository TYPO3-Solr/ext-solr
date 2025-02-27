<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\System\Environment;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Helper class for the cli environment helps to define the variables and constants
 * that are required in the cli context to allow frontend related operations in the cli context.
 */
class CliEnvironment implements SingletonInterface
{
    protected array $backupServerVariables = [];

    protected bool $isInitialized = false;

    public function backup(): void
    {
        $this->backupServerVariables = $_SERVER;
    }

    /**
     * Initializes the frontend related server variables for the cli context.
     *
     *@throws WebRootAllReadyDefinedException
     */
    public function initialize(
        string $webRoot,
        string $scriptFileName = '',
        string $phpSelf = '/index.php',
        string $scriptName = '/index.php',
    ): bool {
        // if the environment has be initialized once, we do not need to initialize it twice.
        if ($this->isInitialized) {
            return false;
        }

        if (defined('TYPO3_PATH_WEB')) {
            throw new WebRootAllReadyDefinedException(
                'TYPO3_PATH_WEB is already defined',
                7904456080,
            );
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

    public function getIsInitialized(): bool
    {
        return $this->isInitialized;
    }

    public function restore(): void
    {
        $_SERVER = $this->backupServerVariables;
    }
}
