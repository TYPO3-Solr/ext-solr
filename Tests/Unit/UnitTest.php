<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Base class for all unit tests in the solr project
 *
 * @author Timo Schmidt
 */
abstract class UnitTest extends UnitTestCase
{
    protected $resetSingletonInstances = true;

    /**
     * Returns a mock class where every behaviour is mocked, just to full fill
     * the datatype and have the possibility to mock the behaviour.
     *
     * @param string $className
     * @return MockObject
     */
    protected function getDumbMock($className)
    {
        return $this->getMockBuilder($className)->disableOriginalConstructor()->getMock();
    }

    /**
     * Returns the absolute root path to the fixtures.
     *
     * @return string
     */
    protected function getFixtureRootPath()
    {
        return $this->getRuntimeDirectory() . '/Fixtures/';
    }

    /**
     * Returns the absolute path to a fixture file.
     *
     * @param $fixtureName
     * @return string
     */
    protected function getFixturePathByName($fixtureName)
    {
        return $this->getFixtureRootPath() . $fixtureName;
    }

    /**
     * Returns the content of a fixture file.
     *
     * @param string $fixtureName
     * @return string
     */
    protected function getFixtureContentByName($fixtureName)
    {
        return file_get_contents($this->getFixturePathByName($fixtureName));
    }

    /**
     * Returns the directory on runtime.
     *
     * @return string
     */
    protected function getRuntimeDirectory()
    {
        $rc = new \ReflectionClass(get_class($this));
        return dirname($rc->getFileName());
    }

    /**
     * @param string $version
     */
    protected function skipInVersionBelow($version)
    {
        if (version_compare(GeneralUtility::makeInstance(Typo3Version::class)->getBranch(), $version, '<')) {
            $this->markTestSkipped('This test requires at least version ' . $version);
        }
    }

    /**
     * Helper function to call protected or private methods
     *
     * @param object $object The object to be invoked
     * @param string $name the name of the method to call
     * @param mixed $arguments
     * @return mixed
     */
    protected function callInaccessibleMethod($object, $name, ...$arguments)
    {
        $reflectionObject = new \ReflectionObject($object);
        $reflectionMethod = $reflectionObject->getMethod($name);
        $reflectionMethod->setAccessible(true);
        return $reflectionMethod->invokeArgs($object, $arguments);
    }

    /**
     * Injects $dependency into property $name of $target
     *
     * This is a convenience method for setting a protected or private property in
     * a test subject for the purpose of injecting a dependency.
     *
     * @param object $target The instance which needs the dependency
     * @param string $name Name of the property to be injected
     * @param mixed $dependency The dependency to inject – usually an object but can also be any other type
     * @return void
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function inject($target, $name, $dependency)
    {
        if (!is_object($target)) {
            throw new \InvalidArgumentException('Wrong type for argument $target, must be object.', 1476107338);
        }

        $objectReflection = new \ReflectionObject($target);
        $methodNamePart = strtoupper($name[0]) . substr($name, 1);
        if ($objectReflection->hasMethod('set' . $methodNamePart)) {
            $methodName = 'set' . $methodNamePart;
            $target->$methodName($dependency);
        } elseif ($objectReflection->hasMethod('inject' . $methodNamePart)) {
            $methodName = 'inject' . $methodNamePart;
            $target->$methodName($dependency);
        } elseif ($objectReflection->hasProperty($name)) {
            $property = $objectReflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($target, $dependency);
        } else {
            throw new \RuntimeException(
                'Could not inject ' . $name . ' into object of type ' . get_class($target),
                1476107339
            );
        }
    }
}
