<?php

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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Helper;

use Doctrine\Instantiator\Exception\InvalidArgumentException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

/**
 * This class is a light weight fake object manager that just dispatches the creation
 * of an object to GeneralUtitlity::makeInstance. When the object contains a method,
 * injectObjectManager it injects the object manager into the instance.
 */
class FakeObjectManager implements ObjectManagerInterface
{

    /**
     * Returns TRUE if an object with the given name is registered
     *
     * @param string $objectName Name of the object
     * @return bool TRUE if the object has been registered, otherwise FALSE
     */
    public function isRegistered(string $objectName): bool
    {
        throw new InvalidArgumentException('Not implemented in the FakeObjectManager');
    }

    /**
     * Returns a fresh or existing instance of the object specified by $objectName.
     *
     * @param string $objectName The name of the object to return an instance of
     * @return object The object instance
     * @api
     */
    public function get(string $objectName, ...$constructorArguments): object
    {
        $arguments = func_get_args();

        $instance = call_user_func_array([GeneralUtility::class, 'makeInstance'], $arguments);
        if (method_exists($instance, 'injectObjectManager')) {
            // @extensionScannerIgnoreLine
            $instance->injectObjectManager($this);
        }

        return $instance;
    }

    /**
     * Create an instance of $className without calling its constructor
     *
     * @param string $className
     * @return object
     * @api
     */
    public function getEmptyObject(string $className): object
    {
        throw new InvalidArgumentException('Not implemented in the FakeObjectManager');
    }

    /**
     * Returns the scope of the specified object.
     *
     * @param string $objectName The object name
     * @return int One of the Container::SCOPE_ constants
     */
    public function getScope(string $objectName): int
    {
        throw new InvalidArgumentException('Not implemented in the FakeObjectManager');
    }
}
