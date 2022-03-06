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
use ApacheSolrForTypo3\Solr\IndexQueue\InitializationPostProcessor;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\AbstractInitializer;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception as DBALException;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UnexpectedValueException;

/**
 * The queue initialization service is responsible to run the initialization of the index queue for a combination of sites
 * and index queue configurations.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Ingo Renner <ingo.renner@dkd.de>
 */
class QueueInitializationService
{

    /**
     * @var Queue
     */
    protected Queue $queue;

    /**
     * QueueInitializationService constructor.
     */
    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
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
     * @throws ConnectionException
     * @throws DBALException
     * @throws Throwable
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
     * @return array
     * @throws ConnectionException
     * @throws DBALException
     * @throws Throwable
     */
    public function initializeBySitesAndConfigurations(array $sites, array $indexingConfigurationNames = ['*']): array
    {
        $initializationStatesBySiteId = [];
        foreach ($sites as $site) {
            /** @var  Site $site */
            $initializationResult = $this->initializeBySiteAndIndexConfigurations($site, $indexingConfigurationNames);
            $initializationStatesBySiteId[$site->getRootPageId()] = $initializationResult;
        }

        return $initializationStatesBySiteId;
    }

    /**
     * Initializes a set index configurations for a given site.
     *
     * @param Site $site
     * @param array $indexingConfigurationNames if one of the names is a * (wildcard) all configurations are used,
     * @return array
     * @throws ConnectionException
     * @throws Throwable
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

        if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueInitialization'])) {
            return $initializationStatus;
        }

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueInitialization'] as $classReference) {
            $indexQueueInitializationPostProcessor = GeneralUtility::makeInstance($classReference);
            if ($indexQueueInitializationPostProcessor instanceof InitializationPostProcessor) {
                $indexQueueInitializationPostProcessor->postProcessIndexQueueInitialization($site, $indexingConfigurationNames, $initializationStatus);
            } else {
                throw new UnexpectedValueException(get_class($indexQueueInitializationPostProcessor) . ' must implement interface ' . InitializationPostProcessor::class, 1345815561);
            }
        }

        return $initializationStatus;
    }

    /**
     * Initializes the Index Queue for a specific indexing configuration.
     *
     * @param Site $site The site to initialize
     * @param string $indexingConfigurationName name of a specific
     *      indexing configuration
     * @return bool TRUE if the initialization was successful, FALSE otherwise
     * @throws ConnectionException
     * @throws DBALException
     * @throws Throwable
     */
    protected function applyInitialization(Site $site, string $indexingConfigurationName): bool
    {
        // clear queue
        $this->queue->deleteItemsBySite($site, $indexingConfigurationName);

        $solrConfiguration = $site->getSolrConfiguration();
        $tableToIndex = $solrConfiguration->getIndexQueueTableNameOrFallbackToConfigurationName($indexingConfigurationName);
        $initializerClass = $solrConfiguration->getIndexQueueInitializerClassByConfigurationName($indexingConfigurationName);
        $indexConfiguration = $solrConfiguration->getIndexQueueConfigurationByName($indexingConfigurationName);

        return $this->executeInitializer($site, $indexingConfigurationName, $initializerClass, $tableToIndex, $indexConfiguration);
    }

    /**
     * @param Site $site
     * @param string $indexingConfigurationName
     * @param string $initializerClass
     * @param string $tableToIndex
     * @param array $indexConfiguration
     * @return bool
     */
    protected function executeInitializer(
        Site $site,
        string $indexingConfigurationName,
        string $initializerClass,
        string $tableToIndex,
        array $indexConfiguration
    ): bool {
        $initializer = GeneralUtility::makeInstance($initializerClass);
        /* @var AbstractInitializer $initializer */
        $initializer->setSite($site);
        $initializer->setType($tableToIndex);
        $initializer->setIndexingConfigurationName($indexingConfigurationName);
        $initializer->setIndexingConfiguration($indexConfiguration);

        return $initializer->initialize();
    }
}
