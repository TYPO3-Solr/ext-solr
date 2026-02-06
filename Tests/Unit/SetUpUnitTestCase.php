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

use ApacheSolrForTypo3\Solr\System\ContentObject\ContentObjectService;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use RuntimeException;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectFactory;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
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

        // Auto-setup mocks for TYPO3 14 classes that require many constructor dependencies.
        // This prevents ArgumentCountError when code uses GeneralUtility::makeInstance().
        // Tests can override by calling these methods again with different counts.
        $this->setUpTypoScriptConfigurationMocks(contentObjectServiceCount: 20);
        for ($i = 0; $i < 10; $i++) {
            $this->createContentObjectRendererMock(registerInstance: true);
        }
        $this->createFlexFormToolsMock(instanceCount: 5);
        $this->createSiteFinderMock(instanceCount: 5);
        $this->createTcaSchemaFactoryMock(instanceCount: 10);
    }

    protected function tearDown(): void
    {
        // Clean up registered instances to prevent tearDown integrity check failures
        GeneralUtility::purgeInstances();

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
                1476107339,
            );
        }
    }

    /**
     * Creates a mock ContentObjectRenderer and optionally registers it for GeneralUtility::makeInstance().
     *
     * In TYPO3 14, ContentObjectRenderer requires 26 constructor dependencies and must be mocked
     * for unit tests. This helper creates the mock and can register it so that code using
     * GeneralUtility::makeInstance(ContentObjectRenderer::class) will receive the mock.
     *
     * @param ServerRequest|null $request Optional request to set on the mock
     * @param bool $registerInstance If true, registers the mock with GeneralUtility::addInstance()
     * @return MockObject&ContentObjectRenderer
     */
    protected function createContentObjectRendererMock(
        ?ServerRequest $request = null,
        bool $registerInstance = false,
    ): MockObject&ContentObjectRenderer {
        $mock = $this->createMock(ContentObjectRenderer::class);
        if ($request !== null) {
            $mock->method('getRequest')->willReturn($request);
        }
        // Configure basic stdWrap behavior for tests
        // This handles common stdWrap properties like 'wrap', 'field', etc.
        $mock->method('stdWrap')->willReturnCallback(
            function (mixed $content, array $conf) {
                $result = (string)$content;
                // Handle 'wrap' property: wraps content with before|after parts
                if (isset($conf['wrap'])) {
                    $parts = explode('|', $conf['wrap'], 2);
                    $result = ($parts[0] ?? '') . $result . ($parts[1] ?? '');
                }
                return $result;
            },
        );
        // Configure basic cObjGetSingle behavior for tests
        // Returns serialized empty array for SOLR_* content objects (like SOLR_RELATION with multiValue)
        $mock->method('cObjGetSingle')->willReturnCallback(
            function (string $name, array $conf) {
                // For SOLR_* content objects with multiValue, return serialized empty array
                if (str_starts_with($name, 'SOLR_') && !empty($conf['multiValue'])) {
                    return serialize([]);
                }
                return '';
            },
        );
        if ($registerInstance) {
            GeneralUtility::addInstance(ContentObjectRenderer::class, $mock);
        }
        return $mock;
    }

    /**
     * Sets up a basic DI container with common mocks for unit testing.
     *
     * This registers mocks for:
     * - ContentObjectFactory
     * - EventDispatcherInterface (NoopEventDispatcher)
     *
     * Call this in setUp() when your test needs these services available via the container.
     *
     * @param array<string, object> $additionalServices Additional services to register in the container
     */
    protected function setUpBasicContainer(array $additionalServices = []): Container
    {
        $container = new Container();

        $cObjectFactoryMock = $this->getMockBuilder(ContentObjectFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $container->set(ContentObjectFactory::class, $cObjectFactoryMock);
        $container->set(EventDispatcherInterface::class, new NoopEventDispatcher());

        foreach ($additionalServices as $id => $service) {
            $container->set($id, $service);
        }

        GeneralUtility::setContainer($container);

        return $container;
    }

    /**
     * Convenience method to set up ContentObjectRenderer mock and basic container together.
     *
     * This is the recommended way to prepare tests that need ContentObjectRenderer.
     * It creates the mock, sets up the container, and optionally registers additional
     * instances for GeneralUtility::makeInstance().
     *
     * @param ServerRequest|null $request Optional request for the ContentObjectRenderer
     * @param int $instanceCount How many times to register the mock for makeInstance() calls
     * @return MockObject&ContentObjectRenderer
     */
    protected function setUpContentObjectRenderer(
        ?ServerRequest $request = null,
        int $instanceCount = 1,
    ): MockObject&ContentObjectRenderer {
        $this->setUpBasicContainer();
        $mock = $this->createContentObjectRendererMock($request);

        for ($i = 0; $i < $instanceCount; $i++) {
            GeneralUtility::addInstance(ContentObjectRenderer::class, $mock);
        }

        return $mock;
    }

    /**
     * Creates and registers a ContentObjectService mock.
     *
     * TypoScriptConfiguration uses GeneralUtility::makeInstance(ContentObjectService::class)
     * which in turn creates ContentObjectRenderer. By mocking ContentObjectService,
     * we prevent the ContentObjectRenderer instantiation chain.
     *
     * Use this when your test creates TypoScriptConfiguration instances.
     *
     * Why register the same instance multiple times?
     * GeneralUtility::addInstance() works as a FIFO queue - each call adds one instance
     * that will be consumed (and removed) by the NEXT call to GeneralUtility::makeInstance()
     * for that class. If a test or production code calls makeInstance() multiple times,
     * each call consumes one registered instance. When the queue is empty, makeInstance()
     * tries to actually instantiate the class, which fails due to missing constructor
     * dependencies. Registering multiple instances ensures enough mocks are available
     * for all makeInstance() calls during the test.
     *
     * @param int $instanceCount How many times to register the mock for makeInstance() calls
     * @return MockObject&ContentObjectService
     */
    protected function createContentObjectServiceMock(int $instanceCount = 1): MockObject&ContentObjectService
    {
        $mock = $this->createMock(ContentObjectService::class);

        for ($i = 0; $i < $instanceCount; $i++) {
            GeneralUtility::addInstance(ContentObjectService::class, $mock);
        }

        return $mock;
    }

    /**
     * Creates and registers a FlexFormTools mock.
     *
     * In TYPO3 14, FlexFormTools requires 3 constructor dependencies and must be mocked
     * for unit tests that use classes depending on it.
     *
     * @param int $instanceCount How many times to register the mock for makeInstance() calls
     * @return MockObject&FlexFormTools
     */
    protected function createFlexFormToolsMock(int $instanceCount = 1): MockObject&FlexFormTools
    {
        $mock = $this->createMock(FlexFormTools::class);

        for ($i = 0; $i < $instanceCount; $i++) {
            GeneralUtility::addInstance(FlexFormTools::class, $mock);
        }

        return $mock;
    }

    /**
     * Creates and registers a SiteFinder mock.
     *
     * In TYPO3 14, SiteFinder requires 2 constructor dependencies and must be mocked
     * for unit tests that use classes depending on it.
     *
     * @param int $instanceCount How many times to register the mock for makeInstance() calls
     * @return MockObject&SiteFinder
     */
    protected function createSiteFinderMock(int $instanceCount = 1): MockObject&SiteFinder
    {
        $mock = $this->createMock(SiteFinder::class);

        for ($i = 0; $i < $instanceCount; $i++) {
            GeneralUtility::addInstance(SiteFinder::class, $mock);
        }

        return $mock;
    }

    /**
     * Creates and registers a TcaSchemaFactory mock.
     *
     * In TYPO3 14, TcaSchemaFactory requires 4 constructor dependencies and must be mocked
     * for unit tests that use BackendUtility::getRecord() or similar methods.
     *
     * @param int $instanceCount How many times to register the mock for makeInstance() calls
     * @return MockObject&TcaSchemaFactory
     */
    protected function createTcaSchemaFactoryMock(int $instanceCount = 1): MockObject&TcaSchemaFactory
    {
        $mock = $this->createMock(TcaSchemaFactory::class);

        for ($i = 0; $i < $instanceCount; $i++) {
            GeneralUtility::addInstance(TcaSchemaFactory::class, $mock);
        }

        return $mock;
    }

    /**
     * Sets up common mocks needed for tests that use TypoScriptConfiguration.
     *
     * This is a convenience method that registers mocks for:
     * - ContentObjectService (prevents ContentObjectRenderer instantiation)
     * - FlexFormTools (if needed)
     *
     * Call this in setUp() or at the beginning of tests that create TypoScriptConfiguration.
     *
     * @param bool $includeFlexFormTools Whether to also mock FlexFormTools
     * @param int $contentObjectServiceCount How many ContentObjectService instances to register
     */
    protected function setUpTypoScriptConfigurationMocks(
        bool $includeFlexFormTools = false,
        int $contentObjectServiceCount = 1,
    ): void {
        $this->createContentObjectServiceMock($contentObjectServiceCount);

        if ($includeFlexFormTools) {
            $this->createFlexFormToolsMock();
        }
    }
}
