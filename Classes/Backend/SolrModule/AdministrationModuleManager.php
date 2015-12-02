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


/**
 * Administration Module Manager
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class AdministrationModuleManager
{

    /**
     * Registered modules
     *
     * @var array
     */
    protected static $modules = array();

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     * @inject
     */
    protected $objectManager;


    /**
     * Registers a Solr administration module
     *
     * @param string $extensionIdentifier Identifier for the extension, that is the vendor followed by a dot followed by the extension key
     * @param string $controllerName Controller name
     * @param array $controllerActions Array of valid controller actions
     * @return void
     */
    public static function registerModule(
        $extensionIdentifier,
        $controllerName,
        array $controllerActions
    ) {
        $vendor = '';
        $extensionKey = $extensionIdentifier;

        if (strpos($extensionIdentifier, '.') !== false) {
            list($vendor, $extensionKey) = explode('.', $extensionIdentifier);
        }

        self::$modules[$controllerName] = array(
            'vendor' => $vendor,
            'extensionKey' => $extensionKey,
            'controller' => $controllerName,
            'actions' => $controllerActions
        );
    }

    /**
     * Makes sure all third party modules come after the EXT:solr modules
     *
     */
    public function sortModules()
    {
        $thirdPartyModules = array();

        foreach (self::$modules as $moduleName => $module) {
            if ($module['extensionKey'] != 'solr') {
                $thirdPartyModules[$moduleName] = $module;
                unset(self::$modules[$moduleName]);
            }
        }

        self::$modules = array_merge(self::$modules, $thirdPartyModules);
    }

    /**
     * Returns all currently registered administration modules.
     *
     * @return array An array of administration module instances
     */
    public function getModules()
    {
        $modules = array();

        foreach (self::$modules as $moduleName => $moduleClass) {
            $modules[$moduleName] = $this->getModule($moduleName);
        }

        return $modules;
    }

    /**
     * Instanciates a registered administration module.
     *
     * @param string $moduleName Administration module name
     * @return \ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleInterface Instance of the requested administration module
     * @throws \InvalidArgumentException if $moduleName is not a registered administration module
     * @throws \RuntimeException if the class registered for $moduleName is not an implementation of \ApacheSolrForTypo3\Solr\Backend\SolrModule\ModuleInterface
     */
    public function getModule($moduleName)
    {
        $this->validateModuleIsRegistered($moduleName);

        $module = $this->objectManager->get($this->getModuleControllerClassName($moduleName));
        $module->setExtensionKey(self::$modules[$moduleName]['extensionKey']);

        if (!($module instanceof AdministrationModuleInterface)) {
            throw new \RuntimeException(
                'Class ' . self::$modules[$moduleName] . ' must implement interface \ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleInterface',
                1360373784
            );
        }

        return $module;
    }

    /**
     * Checks whether a given module has been registered.
     *
     * @param string $moduleName Module name
     * @throws \InvalidArgumentException if the given $moduleName does not exist
     */
    public function validateModuleIsRegistered($moduleName)
    {
        if (!self::isRegisteredModule($moduleName)) {
            throw new \InvalidArgumentException(
                'No module registered named ' . $moduleName,
                1360373482
            );
        }
    }

    /**
     * Checks whether a module is registered for a given name.
     *
     * @param string $moduleName SolrModule name to check whether it is registered
     * @return bool TRUE if the we have a module with that name, FALSE otherwise
     */
    public static function isRegisteredModule($moduleName)
    {
        return array_key_exists($moduleName, self::$modules);
    }

    /**
     * Constructs the class name of the module's controller
     *
     * @param string $moduleName Module name
     * @return string Module controller class name
     */
    protected function getModuleControllerClassName($moduleName)
    {
        $moduleDescription = self::$modules[$moduleName];

        $controllerClassName = $moduleDescription['vendor'] . '\\'
            . ucfirst($moduleDescription['extensionKey']) . '\\'
            . 'Backend\\SolrModule\\'
            . $moduleDescription['controller'] . 'ModuleController';

        return $controllerClassName;
    }

    /**
     * Gets a module's registration data
     *
     * @param string $moduleName Module name
     * @return array An array of the module's registration data
     */
    public function getModuleDescription($moduleName)
    {
        $this->validateModuleIsRegistered($moduleName);

        return self::$modules[$moduleName];
    }
}
