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

namespace ApacheSolrForTypo3\Solr\System\Object;

use InvalidArgumentException;
use stdClass;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

/**
 * Abstract class to hold the logic to register and retrieve different classes
 * for a specific key.
 *
 * Can be used to retrieve different "strategies" for the same thing.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class AbstractClassRegistry implements SingletonInterface
{
    /**
     * Holds the mapping key => className
     * @var array
     */
    protected array $classMap = [];

    /**
     * Name for the default implementation
     *
     * @var string
     */
    protected string $defaultClass = stdClass::class;

    /**
     * @var ObjectManagerInterface|null
     */
    protected ?ObjectManagerInterface $objectManager = null;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Retrieves an instance for a registered type.
     *
     * @param string $type
     * @return object
     */
    public function getInstance(string $type): object
    {
        $className = $this->resolveClassName($type);
        return $this->createInstance($className);
    }

    /**
     * @param string $type
     * @return string
     */
    protected function resolveClassName(string $type): string
    {
        $className = $this->defaultClass;
        if (isset($this->classMap[$type])) {
            return $this->classMap[$type];
        }
        return $className;
    }

    /**
     * Create an instance of a certain class
     *
     * @param string $className
     * @return object
     */
    protected function createInstance(string $className): object
    {
        return $this->objectManager->get($className);
    }

    /**
     * Can be used to register an implementation in the classMap.
     *
     * @param string $className
     * @param string $type
     * @param string $requiredBaseClass
     */
    protected function register(string $className, string $type, string $requiredBaseClass): void
    {
        // check if the class is available for TYPO3 before registering the driver
        if (!class_exists($className)) {
            throw new InvalidArgumentException('Class ' . $className . ' does not exist.', 1462883324);
        }

        if (!is_subclass_of($className, $requiredBaseClass)) {
            throw new InvalidArgumentException('Parser ' . $className . ' needs to extend the ' . $requiredBaseClass . '.', 1462883325);
        }

        $this->classMap[$type] = $className;
    }
}
