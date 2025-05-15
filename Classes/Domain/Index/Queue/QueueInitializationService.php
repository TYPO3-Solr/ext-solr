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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Event\IndexQueue\AfterIndexQueueHasBeenInitializedEvent;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\AbstractInitializer;
use ApacheSolrForTypo3\Solr\IndexQueue\QueueInitializationServiceAwareInterface;
use ApacheSolrForTypo3\Solr\IndexQueue\QueueInterface;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception as DBALException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The queue initialization service is responsible to run the initialization of the index queue for a combination of sites
 * and index queue configurations.
 */
class QueueInitializationService
{
    protected bool $clearQueueOnInitialization = true;
    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(?EventDispatcherInterface $eventDispatcher = null)
    {
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    public function setClearQueueOnInitialization(bool $clearQueueOnInitialization): void
    {
        $this->clearQueueOnInitialization = $clearQueueOnInitialization;
    }

    /**
     * Truncate and rebuild the tx_solr_indexqueue_item table. This is the most
     * complete way to force reindexing, or to build the Index Queue for the
     * first time. The Index Queue initialization is site-specific.
     *
     * @param Site $site The site to initialize
     * @param string $indexingConfigurationName Name of a specific indexing configuration, when * is passed any is used
     * @return array An array of booleans, each representing whether the
     *      initialization for an indexing configuration was successful
     *
     * @throws ConnectionException
     * @throws DBALException
     */
    public function initializeBySiteAndIndexConfiguration(Site $site, string $indexingConfigurationName = '*'): array
    {
        return $this->initializeBySiteAndIndexConfigurations($site, [$indexingConfigurationName]);
    }

    /**
     * Truncates and rebuilds the tx_solr_indexqueue_item table for a set of sites and a set of index configurations.
     *
     * @param array $sites The array of sites to initialize
     * @param array $indexingConfigurationNames the array of index configurations to initialize.
     *
     * @throws ConnectionException
     * @throws DBALException
     */
    public function initializeBySitesAndConfigurations(array $sites, array $indexingConfigurationNames = ['*']): array
    {
        $initializationStatesBySiteId = [];
        foreach ($sites as $site) {
            /** @var Site $site */
            $initializationResult = $this->initializeBySiteAndIndexConfigurations($site, $indexingConfigurationNames);
            $initializationStatesBySiteId[$site->getRootPageId()] = $initializationResult;
        }

        return $initializationStatesBySiteId;
    }

    /**
     * Initializes a set of index configurations for a given site.
     * If one of the indexing configuration names is a * (wildcard) all configurations are used,
     *
     * @param array<int, string> $indexingConfigurationNames
     * @return array<string, bool>
     * @throws ConnectionException
     * @throws DBALException
     */
    public function initializeBySiteAndIndexConfigurations(Site $site, array $indexingConfigurationNames): array
    {
        $initializationStatus = [];

        $hasWildcardConfiguration = in_array('*', $indexingConfigurationNames);
        $indexingConfigurationNames = $hasWildcardConfiguration ? $site->getSolrConfiguration()->getEnabledIndexQueueConfigurationNames() : $indexingConfigurationNames;
        foreach ($indexingConfigurationNames as $indexingConfigurationName) {
            $initializationStatus[$indexingConfigurationName] = $this->applyInitialization($site, (string)$indexingConfigurationName);
        }
        return $initializationStatus;
    }

    /**
     * Initializes the Index Queue for a specific indexing configuration.
     *
     * @return bool TRUE if the initialization was successful, FALSE otherwise
     *
     * @throws DBALException
     */
    protected function applyInitialization(Site $site, string $indexingConfigurationName): bool
    {
        $solrConfiguration = $site->getSolrConfiguration();

        /** @var QueueInterface $queue */
        $queue = GeneralUtility::makeInstance(
            $solrConfiguration->getIndexQueueClassByConfigurationName($indexingConfigurationName),
        );
        if ($queue instanceof QueueInitializationServiceAwareInterface) {
            $queue->setQueueInitializationService($this);
        }

        // clear queue
        if ($this->clearQueueOnInitialization) {
            $queue->deleteItemsBySite($site, $indexingConfigurationName);
        }

        $type = $solrConfiguration->getIndexQueueTypeOrFallbackToConfigurationName($indexingConfigurationName);
        $initializerClass = $solrConfiguration->getIndexQueueInitializerClassByConfigurationName($indexingConfigurationName);
        $indexConfiguration = $solrConfiguration->getIndexQueueConfigurationByName($indexingConfigurationName);

        return $this->executeInitializer($site, $indexingConfigurationName, $initializerClass, $type, $indexConfiguration);
    }

    /**
     * Executes desired initializer
     */
    protected function executeInitializer(
        Site $site,
        string $indexingConfigurationName,
        string $initializerClass,
        string $type,
        array $indexConfiguration,
    ): bool {
        $initializer = GeneralUtility::makeInstance($initializerClass);
        /** @var AbstractInitializer $initializer */
        $initializer->setSite($site);
        $initializer->setType($type);
        $initializer->setIndexingConfigurationName($indexingConfigurationName);
        $initializer->setIndexingConfiguration($indexConfiguration);

        $isInitialized = $initializer->initialize();
        $event = new AfterIndexQueueHasBeenInitializedEvent($initializer, $site, $indexingConfigurationName, $type, $indexConfiguration, $isInitialized);
        $event = $this->eventDispatcher->dispatch($event);
        return $event->isInitialized();
    }
}
