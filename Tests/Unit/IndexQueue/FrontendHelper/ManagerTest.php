<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\FrontendHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018
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

    public function setUp()
    {
        $this->manager = new Manager();
    }

    /**
     * @test
     */
    public function resolveActionReturnsNullWhenNoHandlerIsRegistered()
    {
        $handler = $this->manager->resolveAction('foo');
        $this->assertNull($handler, 'Unregistered action should return null when it will be resolved');
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