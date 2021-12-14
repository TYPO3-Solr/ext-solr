<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\UpdateHandler\EventListener;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2022 Markus Friedrich <markus.friedrich@dkd.de>
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

use Psr\EventDispatcher\EventDispatcherInterface;
use PHPUnit\Framework\MockObject\MockObject;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\AbstractBaseEventListener;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract testcase for the event listeners
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
abstract class AbstractEventListenerTest extends UnitTest
{
    private const MONITORING_TYPES_TO_TEST = [0,1,2,99];

    /**
     * @var AbstractBaseEventListener
     */
    protected $listener;

    /**
     * @var MockObject|ExtensionConfiguration
     */
    protected $extensionConfigurationMock;

    /**
     * @var MockObject|EventDispatcherInterface
     */
    protected $eventDispatcherMock;

    public function setUp(): void
    {
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->listener = $this->initListener();
    }

    public function tearDown(): void
    {
        GeneralUtility::purgeInstances();
    }

    /**
     * Init listener
     *
     * @return AbstractBaseEventListener
     */
    abstract protected function initListener(): AbstractBaseEventListener;

    /**
     * @test
     */
    public function canIndicateActiveMonitoring(): void
    {
        $this->extensionConfigurationMock
            ->expects($this->once())
            ->method('getMonitoringType')
            ->willReturn($this->getMonitoringType());

        $status = $this->callInaccessibleMethod($this->listener, 'isProcessingEnabled');
        $this->assertTrue($status);
    }

    /**
     * @param int $currentType
     *
     * @test
     * @dataProvider inactiveMonitoringDataProvider
     */
    public function canIndicateInactiveMonitoring(int $currentType): void
    {
        $this->extensionConfigurationMock
        ->expects($this->once())
        ->method('getMonitoringType')
        ->willReturn($currentType);

        $status = $this->callInaccessibleMethod($this->listener, 'isProcessingEnabled');
        $this->assertFalse($status);
    }

    /**
     * Data provider for canIndicateInactiveMonitoring
     */
    public function inactiveMonitoringDataProvider(): array
    {
        $invalidTypes = array_diff(
            self::MONITORING_TYPES_TO_TEST,
            [$this->getMonitoringType()]
        );

        $testData = [];
        foreach ($invalidTypes as $type) {
            $testData[] = [$type];
        }

        return $testData;
    }

    /**
     * Returns the current monitoring type
     *
     * @return int
     */
    abstract protected function getMonitoringType(): int;
}
