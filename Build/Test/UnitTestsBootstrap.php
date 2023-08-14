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

use TYPO3\CMS\Core\Cache\Backend\NullBackend;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\CMS\Core\Configuration\ConfigurationManager;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Package\UnitTestPackageManager;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Testbase;

call_user_func(static function () {
    $testbase = new Testbase();

    // These if's are for core testing (package typo3/cms) only. cms-composer-installer does
    // not create the autoload-include.php file that sets these env vars and sets composer
    // mode to true. testing-framework can not be used without composer anyway, so it is safe
    // to do this here. This way it does not matter if 'bin/phpunit' or 'vendor/phpunit/phpunit/phpunit'
    // is called to run the tests since the 'relative to entry script' path calculation within
    // SystemEnvironmentBuilder is not used. However, the binary must be called from the document
    // root since getWebRoot() uses 'getcwd()'.
    if (!getenv('TYPO3_PATH_ROOT')) {
        putenv('TYPO3_PATH_ROOT=' . rtrim($testbase->getWebRoot(), '/'));
    }
    if (!getenv('TYPO3_PATH_WEB')) {
        putenv('TYPO3_PATH_WEB=' . rtrim($testbase->getWebRoot(), '/'));
    }

    $testbase->defineSitePath();

    $requestType = SystemEnvironmentBuilder::REQUESTTYPE_BE | SystemEnvironmentBuilder::REQUESTTYPE_CLI;
    SystemEnvironmentBuilder::run(0, $requestType);

    $testbase->createDirectory(Environment::getPublicPath() . '/typo3conf/ext');
    $testbase->createDirectory(Environment::getPublicPath() . '/typo3temp/assets');
    $testbase->createDirectory(Environment::getPublicPath() . '/typo3temp/var/tests');
    $testbase->createDirectory(Environment::getPublicPath() . '/typo3temp/var/transient');

    // Retrieve an instance of class loader and inject to core bootstrap
    $classLoader = require $testbase->getPackagesPath() . '/autoload.php';
    Bootstrap::initializeClassLoader($classLoader);

    // Initialize default TYPO3_CONF_VARS
    $configurationManager = new ConfigurationManager();
    $GLOBALS['TYPO3_CONF_VARS'] = $configurationManager->getDefaultConfiguration();

    $cache = new PhpFrontend(
        'core',
        new NullBackend('production', [])
    );

    // Set all packages to active
    $packageManager = Bootstrap::createPackageManager(
        UnitTestPackageManager::class,
        Bootstrap::createPackageCache($cache)
    );

    GeneralUtility::setSingletonInstance(PackageManager::class, $packageManager);
    ExtensionManagementUtility::setPackageManager($packageManager);

    $testbase->dumpClassLoadingInformation();

    GeneralUtility::purgeInstances();
});
