<?php declare(strict_types = 1);
namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2021 Markus Friedrich <markus.friedrich@dkd.de>
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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\DataUpdateHandler;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\DataUpdateEventInterface;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\Events\ProcessingFinishedEventInterface;

/**
 * Base Event listener
 */
abstract class AbstractBaseEventListener
{
    public const MONITORING_TYPE = -1;

    /**
     * @var ExtensionConfiguration
     */
    private $extensionConfiguration;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * Constructor
     *
     * @param ExtensionConfiguration $extensionConfiguration
     * @param EventDispatcherInterface
     */
    public function __construct(
        ExtensionConfiguration $extensionConfiguration,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->extensionConfiguration = $extensionConfiguration;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Returns the configured monitoring type
     *
     * @return int
     */
    final protected function getMonitoringType(): int
    {
        return $this->extensionConfiguration->getMonitoringType();
    }

    /**
     * Returns the DataUpdateHandler
     *
     * @return DataUpdateHandler
     */
    final protected function getDataUpdateHandler(): DataUpdateHandler
    {
        return GeneralUtility::makeInstance(DataUpdateHandler::class);
    }

    /**
     * Returns the GarbageHandler
     *
     * @return GarbageHandler
     */
    final protected function getGarbageHandler(): GarbageHandler
    {
        return GeneralUtility::makeInstance(GarbageHandler::class);
    }

    /**
     * Dispatches a data update processing finished event
     *
     * @param string $eventClass
     * @param DataUpdateEventInterface $event
     * @throws \InvalidArgumentException
     */
    final protected function dispatchEvent(string $eventClass, DataUpdateEventInterface $event): void
    {
        if (!is_subclass_of($eventClass, ProcessingFinishedEventInterface::class)) {
            throw new \InvalidArgumentException(
                'Data update event listener can only dispatch processing finished events ('
                . ProcessingFinishedEventInterface::class . ')',
                1639987620
            );
        }

        $this->eventDispatcher->dispatch(
            new $eventClass($event)
        );
    }

    /**
     * Indicates if immediate monitoring is allowed
     *
     * @return bool
     */
    protected function isProcessingEnabled(): bool
    {
        return ($this->getMonitoringType() === static::MONITORING_TYPE);
    }
}
