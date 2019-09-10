<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Helper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
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

use Doctrine\Instantiator\Exception\InvalidArgumentException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

/**
 * This class is a light weight fake object manager that just dispatches the creation
 * of an object to GeneralUtitlity::makeInstance. When the object contains a method,
 * injectObjectManager it injects the object manager into the instance.
 *
 * @package ApacheSolrForTypo3\Solr\Tests\Unit\Helper
 */
class FakeObjectManager implements ObjectManagerInterface
{

    /**
     * Returns TRUE if an object with the given name is registered
     *
     * @param string $objectName Name of the object
     * @return bool TRUE if the object has been registered, otherwise FALSE
     */
    public function isRegistered($objectName)
    {
        throw new InvalidArgumentException("Not implemented in the FakeObjectManager");
    }

    /**
     * Returns a fresh or existing instance of the object specified by $objectName.
     *
     * @param string $objectName The name of the object to return an instance of
     * @return object The object instance
     * @api
     */
    public function get($objectName, ...$constructorArguments)
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
    public function getEmptyObject($className)
    {
        throw new InvalidArgumentException("Not implemented in the FakeObjectManager");
    }

    /**
     * Returns the scope of the specified object.
     *
     * @param string $objectName The object name
     * @return int One of the Container::SCOPE_ constants
     */
    public function getScope($objectName)
    {
        throw new InvalidArgumentException("Not implemented in the FakeObjectManager");
    }
}
