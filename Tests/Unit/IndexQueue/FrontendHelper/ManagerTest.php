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
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ManagerTest extends UnitTest
{

    /**
     * @var Manager
     */
    protected $manager;

    protected function setUp(): void
    {
        $this->manager = new Manager();
        parent::setUp();
    }

    /**
     * @test
     */
    public function resolveActionReturnsNullWhenNoHandlerIsRegistered()
    {
        $handler = $this->manager->resolveAction('foo');
        self::assertNull($handler, 'Unregistered action should return null when it will be resolved');
    }

    /**
     * @test
     */
    public function exceptionIsThrownWhenInvalidActionHandlerIsRetrieved()
    {
        Manager::registerFrontendHelper('test', InvalidFakeHelper::class);
        $this->expectException(\RuntimeException::class);
        $message = InvalidFakeHelper::class . ' is not an implementation of ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\FrontendHelper';
        $this->expectExceptionMessage($message);
        $handler = $this->manager->resolveAction('test');
    }
}
