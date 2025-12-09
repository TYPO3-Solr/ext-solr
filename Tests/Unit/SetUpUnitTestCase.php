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

namespace ApacheSolrForTypo3\Solr\Tests\Unit;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use RuntimeException;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Base class for all unit tests in the solr project
 */
abstract class SetUpUnitTestCase extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;
    protected ?string $originalEncryptionKey;

    protected function setUp(): void
    {
        date_default_timezone_set('Europe/Berlin');
        $this->originalEncryptionKey = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'solr-tests-secret-encryption-key';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
        if ($this->originalEncryptionKey !== null) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $this->originalEncryptionKey;
        }
        parent::tearDown();
    }

    /**
     * Returns the absolute root path to the fixtures.
     *
     * @return string
     */
    protected static function getFixtureRootPath(): string
    {
        return self::getRuntimeDirectory() . '/Fixtures/';
    }

    /**
     * Returns the absolute path to a fixture file.
     */
    protected static function getFixturePathByName(string $fixtureName): string
    {
        return self::getFixtureRootPath() . $fixtureName;
    }

    /**
     * Returns the content of a fixture file.
     */
    protected static function getFixtureContentByName(string $fixtureName): string
    {
        return file_get_contents(self::getFixturePathByName($fixtureName));
    }

    /**
     * Returns the directory on runtime.
     */
    protected static function getRuntimeDirectory(): string
    {
        $rc = new ReflectionClass(static::class);
        return dirname($rc->getFileName());
    }

    /**
     * Helper function to call protected or private methods
     *
     * @param object $object The object to be invoked
     * @param string $name the name of the method to call
     * @param mixed $arguments
     * @return mixed
     * @throws ReflectionException
     */
    protected function callInaccessibleMethod(object $object, string $name, ...$arguments): mixed
    {
        $reflectionObject = new ReflectionObject($object);
        $reflectionMethod = $reflectionObject->getMethod($name);
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
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    protected function inject(
        object $target,
        string $name,
        mixed $dependency,
    ): void {
        if (!is_object($target)) {
            throw new InvalidArgumentException('Wrong type for argument $target, must be object.', 1476107338);
        }

        $objectReflection = new ReflectionObject($target);
        $methodNamePart = strtoupper($name[0]) . substr($name, 1);
        if ($objectReflection->hasMethod('set' . $methodNamePart)) {
            $methodName = 'set' . $methodNamePart;
            $target->$methodName($dependency);
        } elseif ($objectReflection->hasMethod('inject' . $methodNamePart)) {
            $methodName = 'inject' . $methodNamePart;
            $target->$methodName($dependency);
        } elseif ($objectReflection->hasProperty($name)) {
            $property = $objectReflection->getProperty($name);
            $property->setValue($target, $dependency);
        } else {
            throw new RuntimeException(
                'Could not inject ' . $name . ' into object of type ' . get_class($target),
                1476107339
            );
        }
    }
}
