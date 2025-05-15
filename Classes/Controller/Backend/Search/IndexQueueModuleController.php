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

namespace ApacheSolrForTypo3\Solr\Controller\Backend\Search;

use ApacheSolrForTypo3\Solr\Backend\IndexingConfigurationSelectorField;
use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueInitializationService;
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\IndexQueue\QueueInterface;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use TYPO3\CMS\Backend\Form\Exception as BackendFormException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Index Queue Module
 *
 * @todo: Support all index queues in actions beside "initializeIndexQueueAction" and
 *        "resetLogErrorsAction"
 */
class IndexQueueModuleController extends AbstractModuleController
{
    protected array $enabledIndexQueues;

    protected function initializeAction(): void
    {
        parent::initializeAction();

        $this->enabledIndexQueues = $this->getIndexQueues();
        if (!empty($this->enabledIndexQueues)) {
            $this->indexQueue = $this->enabledIndexQueues[Queue::class] ?? reset($this->enabledIndexQueues);
        }
    }

    public function setIndexQueue(QueueInterface $indexQueue): void
    {
        $this->indexQueue = $indexQueue;
    }

    /**
     * Lists the available indexing configurations
     *
     * @throws BackendFormException
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function indexAction(): ResponseInterface
    {
        if (!$this->canQueueSelectedSite()) {
            $this->moduleTemplate->assign('can_not_proceed', true);
            return $this->moduleTemplate->renderResponse('Backend/Search/IndexQueueModule/Index');
        }

        $statistics = $this->indexQueue->getStatisticsBySite($this->selectedSite);
        $this->moduleTemplate->assign('indexQueueInitializationSelector', $this->getIndexQueueInitializationSelector());
        $this->moduleTemplate->assign('indexqueue_statistics', $statistics);
        $this->moduleTemplate->assign('indexqueue_errors', $this->indexQueue->getErrorsBySite($this->selectedSite));
        return $this->moduleTemplate->renderResponse('Backend/Search/IndexQueueModule/Index');
    }

    /**
     * Checks if selected site can be queued.
     */
    protected function canQueueSelectedSite(): bool
    {
        if ($this->selectedSite === null || empty($this->solrConnectionManager->getConnectionsBySite($this->selectedSite))) {
            return false;
        }

        if (!isset($this->indexQueue)) {
            return false;
        }

        $enabledIndexQueueConfigurationNames = $this->selectedSite->getSolrConfiguration()->getEnabledIndexQueueConfigurationNames();
        if (empty($enabledIndexQueueConfigurationNames)) {
            return false;
        }
        return true;
    }

    /**
     * Renders the Markup for the select field, which indexing configurations to be initialized.
     * Uses TCEforms.
     *
     * @throws BackendFormException
     */
    protected function getIndexQueueInitializationSelector(): string
    {
        $selector = GeneralUtility::makeInstance(IndexingConfigurationSelectorField::class, $this->selectedSite);
        $selector->setFormElementName('tx_solr-index-queue-initialization');

        return $selector->render();
    }

    /**
     * Initializes the Index Queue for selected indexing configurations
     *
     * @throws DBALException
     *
     * @noinspection PhpUnused Is *Action
     */
    public function initializeIndexQueueAction(): ResponseInterface
    {
        $initializedIndexingConfigurations = [];

        $indexingConfigurationsToInitialize = $this->request->getArgument('tx_solr-index-queue-initialization');
        if ((!empty($indexingConfigurationsToInitialize)) && (is_array($indexingConfigurationsToInitialize))) {
            $initializationService = GeneralUtility::makeInstance(QueueInitializationService::class);
            foreach ($indexingConfigurationsToInitialize as $configurationToInitialize) {
                $indexQueueClass = $this->selectedSite->getSolrConfiguration()->getIndexQueueClassByConfigurationName($configurationToInitialize);
                $indexQueue = $this->enabledIndexQueues[$indexQueueClass];

                try {
                    $status = $initializationService->initializeBySiteAndIndexConfigurations($this->selectedSite, [$configurationToInitialize]);
                    $initializedIndexingConfiguration = [
                        'status' => $status[$configurationToInitialize],
                        'statistic' => 0,
                    ];
                    if ($status[$configurationToInitialize] === true) {
                        $initializedIndexingConfiguration['totalCount'] = $indexQueue->getStatisticsBySite($this->selectedSite, $configurationToInitialize)->getTotalCount();
                    }
                    $initializedIndexingConfigurations[$configurationToInitialize] = $initializedIndexingConfiguration;
                } catch (Throwable $e) {
                    $this->addFlashMessage(
                        sprintf(
                            LocalizationUtility::translate(
                                'solr.backend.index_queue_module.flashmessage.initialize_failure',
                                'Solr',
                            ),
                            $e->getMessage(),
                            $e->getCode(),
                        ),
                        LocalizationUtility::translate(
                            'solr.backend.index_queue_module.flashmessage.initialize_failure.title',
                            'Solr',
                        ),
                        ContextualFeedbackSeverity::ERROR,
                    );
                }
            }
        } else {
            $messageLabel = 'solr.backend.index_queue_module.flashmessage.initialize.no_selection';
            $titleLabel = 'solr.backend.index_queue_module.flashmessage.not_initialized.title';
            $this->addFlashMessage(
                LocalizationUtility::translate($messageLabel, 'Solr'),
                LocalizationUtility::translate($titleLabel, 'Solr'),
                ContextualFeedbackSeverity::WARNING,
            );
        }

        $messagesForConfigurations = [];
        foreach ($initializedIndexingConfigurations as $indexingConfigurationName => $initializationData) {
            if ($initializationData['status'] === true) {
                $messagesForConfigurations[] = $indexingConfigurationName . ' (' . $initializationData['totalCount'] . ' records)';
            } else {
                $this->addFlashMessage(
                    sprintf(
                        LocalizationUtility::translate(
                            'solr.backend.index_queue_module.flashmessage.initialize_failure',
                            'Solr',
                        ),
                        $indexingConfigurationName,
                        1662117020,
                    ),
                    LocalizationUtility::translate(
                        'solr.backend.index_queue_module.flashmessage.initialize_failure.title',
                        'Solr',
                    ),
                    ContextualFeedbackSeverity::ERROR,
                );
            }
        }

        if (!empty($messagesForConfigurations)) {
            $messageLabel = 'solr.backend.index_queue_module.flashmessage.initialize.success';
            $titleLabel = 'solr.backend.index_queue_module.flashmessage.initialize.title';
            $this->addFlashMessage(
                LocalizationUtility::translate($messageLabel, 'Solr', [implode(', ', $messagesForConfigurations)]),
                LocalizationUtility::translate($titleLabel, 'Solr'),
                ContextualFeedbackSeverity::OK,
            );
        }

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * Removes all errors in the index queue list. So that the items can be indexed again.
     *
     * @noinspection PhpUnused Is *Action
     */
    public function resetLogErrorsAction(): ResponseInterface
    {
        foreach ($this->enabledIndexQueues as $queue) {
            $resetResult = $queue->resetAllErrors();

            $label = 'solr.backend.index_queue_module.flashmessage.success.reset_errors';
            $severity = ContextualFeedbackSeverity::OK;
            if (!$resetResult) {
                $label = 'solr.backend.index_queue_module.flashmessage.error.reset_errors';
                $severity = ContextualFeedbackSeverity::ERROR;
            }

            $this->addIndexQueueFlashMessage($label, $severity);
        }

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * ReQueues a single item in the indexQueue.
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function requeueDocumentAction(string $type, int $uid): ResponseInterface
    {
        $label = 'solr.backend.index_queue_module.flashmessage.error.single_item_not_requeued';
        $severity = ContextualFeedbackSeverity::ERROR;

        $updateCount = $this->indexQueue->updateItem($type, $uid, time());
        if ($updateCount > 0) {
            $label = 'solr.backend.index_queue_module.flashmessage.success.single_item_was_requeued';
            $severity = ContextualFeedbackSeverity::OK;
        }

        $this->addIndexQueueFlashMessage($label, $severity);

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * Shows the error message for one queue item.
     *
     * @throws DBALException
     *
     * @noinspection PhpUnused Is *Action
     */
    public function showErrorAction(int $indexQueueItemId): ResponseInterface
    {
        $item = $this->indexQueue->getItem($indexQueueItemId);
        if ($item === null) {
            // add a flash message and quit
            $label = 'solr.backend.index_queue_module.flashmessage.error.no_queue_item_for_queue_error';
            $severity = ContextualFeedbackSeverity::ERROR;
            $this->addIndexQueueFlashMessage($label, $severity);

            return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
        }

        $this->moduleTemplate->assign('indexQueueItem', $item);
        return $this->moduleTemplate->renderResponse('Backend/Search/IndexQueueModule/ShowError');
    }

    /**
     * Indexes a few documents with the index service.
     *
     * @throws ConnectionException
     * @throws DBALException
     *
     * @noinspection PhpUnused Is *Action
     */
    public function doIndexingRunAction(): ResponseInterface
    {
        /** @var IndexService $indexService */
        $indexService = GeneralUtility::makeInstance(IndexService::class, $this->selectedSite);
        $indexWithoutErrors = $indexService->indexItems(1);

        $label = 'solr.backend.index_queue_module.flashmessage.success.index_manual';
        $severity = ContextualFeedbackSeverity::OK;
        if (!$indexWithoutErrors) {
            $label = 'solr.backend.index_queue_module.flashmessage.error.index_manual';
            $severity = ContextualFeedbackSeverity::ERROR;
        }

        $this->addFlashMessage(
            LocalizationUtility::translate($label, 'Solr'),
            LocalizationUtility::translate('solr.backend.index_queue_module.flashmessage.index_manual', 'Solr'),
            $severity,
        );

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * Adds a flash message for the index queue module.
     */
    protected function addIndexQueueFlashMessage(string $label, ContextualFeedbackSeverity $severity): void
    {
        $this->addFlashMessage(LocalizationUtility::translate($label, 'Solr'), LocalizationUtility::translate('solr.backend.index_queue_module.flashmessage.title', 'Solr'), $severity);
    }

    /**
     * @return QueueInterface[]
     */
    protected function getIndexQueues(): array
    {
        $queues = [];
        if ($this->selectedSite === null) {
            return [];
        }
        $configuration = $this->selectedSite->getSolrConfiguration();
        foreach ($configuration->getEnabledIndexQueueConfigurationNames() as $indexingConfiguration) {
            $indexQueueClass = $configuration->getIndexQueueClassByConfigurationName($indexingConfiguration);
            if (!isset($queues[$indexQueueClass])) {
                $queues[$indexQueueClass] = GeneralUtility::makeInstance($indexQueueClass);
            }
        }

        return $queues;
    }
}
