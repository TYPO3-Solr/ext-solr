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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\UpdateHandler\EventListener;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\AbstractBaseEventListener;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract testcase for the event listeners
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
abstract class AbstractEventListenerTest extends UnitTest
{
    private const MONITORING_TYPES_TO_TEST = [0, 1, 2, 99];

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

    protected function setUp(): void
    {
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->listener = $this->initListener();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
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
            ->expects(self::once())
            ->method('getMonitoringType')
            ->willReturn($this->getMonitoringType());

        $status = $this->callInaccessibleMethod($this->listener, 'isProcessingEnabled');
        self::assertTrue($status);
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
        ->expects(self::once())
        ->method('getMonitoringType')
        ->willReturn($currentType);

        $status = $this->callInaccessibleMethod($this->listener, 'isProcessingEnabled');
        self::assertFalse($status);
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
