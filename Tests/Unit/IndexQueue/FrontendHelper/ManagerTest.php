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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\Manager;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Container\ContainerInterface;
use RuntimeException;

class ManagerTest extends SetUpUnitTestCase
{
    protected Manager $manager;

    protected function setUp(): void
    {
        $containerMock = $this->createMock(ContainerInterface::class);
        $containerMock->method('get')->willReturnCallback(
            fn(string $class) => new $class(),
        );
        $this->manager = new Manager($containerMock);
        parent::setUp();
    }

    #[Test]
    public function resolveActionReturnsNullWhenNoHandlerIsRegistered(): void
    {
        $handler = $this->manager->resolveAction('foo');
        self::assertNull($handler, 'Unregistered action should return null when it will be resolved');
    }

    #[Test]
    public function exceptionIsThrownWhenInvalidActionHandlerIsRetrieved(): void
    {
        Manager::registerFrontendHelper('test', InvalidFakeHelper::class);
        $this->expectException(RuntimeException::class);
        $message = InvalidFakeHelper::class . ' is not an implementation of ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\FrontendHelper';
        $this->expectExceptionMessage($message);
        $handler = $this->manager->resolveAction('test');
    }
}
