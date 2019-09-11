<?php
namespace ApacheSolrForTypo3\Solr\Controller\Backend\Search;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Backend\IndexingConfigurationSelectorField;
use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Index Queue Module
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @property BackendTemplateView $view
 */
class IndexQueueModuleController extends AbstractModuleController
{

    /**
     * Module name, used to identify a module f.e. in URL parameters.
     *
     * @var string
     */
    protected $moduleName = 'IndexQueue';

    /**
     * Module title, shows up in the module menu.
     *
     * @var string
     */
    protected $moduleTitle = 'Index Queue';

    /**
     * @var Queue
     */
    protected $indexQueue;

    /**
     * Initializes the controller before invoking an action method.
     */
    protected function initializeAction()
    {
        parent::initializeAction();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
    }

    /**
     * @param Queue $indexQueue
     */
    public function setIndexQueue(Queue $indexQueue)
    {
        $this->indexQueue = $indexQueue;
    }

    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     * @return void
     */
    protected function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);
    }

    /**
     * Lists the available indexing configurations
     *
     * @return void
     */
    public function indexAction()
    {
        if (!$this->canQueueSelectedSite()) {
            $this->view->assign('can_not_proceed', true);
            return;
        }

        $statistics = $this->indexQueue->getStatisticsBySite($this->selectedSite);
        $this->view->assign('indexQueueInitializationSelector', $this->getIndexQueueInitializationSelector());
        $this->view->assign('indexqueue_statistics', $statistics);
        $this->view->assign('indexqueue_errors', $this->indexQueue->getErrorsBySite($this->selectedSite));
    }

    /**
     * Checks if selected site can be queued.
     *
     * @return bool
     */
    protected function canQueueSelectedSite()
    {
        if ($this->selectedSite === null || empty($this->solrConnectionManager->getConnectionsBySite($this->selectedSite))) {
            return false;
        }
        $enabledIndexQueueConfigurationNames = $this->selectedSite->getSolrConfiguration()->getEnabledIndexQueueConfigurationNames();
        if (empty($enabledIndexQueueConfigurationNames)) {
            return false;
        }
        return true;
    }

    /**
     * Renders a field to select which indexing configurations to initialize.
     *
     * Uses TCEforms.
     *
     * @return string Markup for the select field
     */
    protected function getIndexQueueInitializationSelector()
    {
        $selector = GeneralUtility::makeInstance(IndexingConfigurationSelectorField::class, /** @scrutinizer ignore-type */ $this->selectedSite);
        $selector->setFormElementName('tx_solr-index-queue-initialization');

        return $selector->render();
    }

    /**
     * Initializes the Index Queue for selected indexing configurations
     *
     * @return void
     */
    public function initializeIndexQueueAction()
    {
        $initializedIndexingConfigurations = [];

        $indexingConfigurationsToInitialize = GeneralUtility::_POST('tx_solr-index-queue-initialization');
        if ((!empty($indexingConfigurationsToInitialize)) && (is_array($indexingConfigurationsToInitialize))) {
            // initialize selected indexing configuration
            $initializedIndexingConfigurations = $this->indexQueue->getInitializationService()->initializeBySiteAndIndexConfigurations($this->selectedSite, $indexingConfigurationsToInitialize);
        } else {
            $messageLabel = 'solr.backend.index_queue_module.flashmessage.initialize.no_selection';
            $titleLabel = 'solr.backend.index_queue_module.flashmessage.not_initialized.title';
            $this->addFlashMessage(
                LocalizationUtility::translate($messageLabel, 'Solr'),
                LocalizationUtility::translate($titleLabel, 'Solr'),
                FlashMessage::WARNING
            );
        }
        $messagesForConfigurations = [];
        foreach (array_keys($initializedIndexingConfigurations) as $indexingConfigurationName) {
            $itemCount = $this->indexQueue->getStatisticsBySite($this->selectedSite, $indexingConfigurationName)->getTotalCount();
            $messagesForConfigurations[] = $indexingConfigurationName . ' (' . $itemCount . ' records)';
        }

        if (!empty($initializedIndexingConfigurations)) {
            $messageLabel = 'solr.backend.index_queue_module.flashmessage.initialize.success';
            $titleLabel = 'solr.backend.index_queue_module.flashmessage.initialize.title';
            $this->addFlashMessage(
                LocalizationUtility::translate($messageLabel, 'Solr', [implode(', ', $messagesForConfigurations)]),
                LocalizationUtility::translate($titleLabel, 'Solr'),
                FlashMessage::OK
            );
        }

        $this->redirect('index');
    }

    /**
     * Removes all errors in the index queue list. So that the items can be indexed again.
     *
     * @return void
     */
    public function resetLogErrorsAction()
    {
        $resetResult = $this->indexQueue->resetAllErrors();

        $label = 'solr.backend.index_queue_module.flashmessage.success.reset_errors';
        $severity = FlashMessage::OK;
        if (!$resetResult) {
            $label = 'solr.backend.index_queue_module.flashmessage.error.reset_errors';
            $severity = FlashMessage::ERROR;
        }

        $this->addIndexQueueFlashMessage($label, $severity);

        $this->redirect('index');
    }

    /**
     * ReQueues a single item in the indexQueue.
     *
     * @param string $type
     * @param int $uid
     *
     * @return void
     */
    public function requeueDocumentAction(string $type, int $uid)
    {
        $label = 'solr.backend.index_queue_module.flashmessage.error.single_item_not_requeued';
        $severity = FlashMessage::ERROR;

        $updateCount = $this->indexQueue->updateItem($type, $uid, time());
        if ($updateCount > 0) {
            $label = 'solr.backend.index_queue_module.flashmessage.success.single_item_was_requeued';
            $severity = FlashMessage::OK;
        }

        $this->addIndexQueueFlashMessage($label, $severity);

        $this->redirect('index');
    }

    /**
     * Shows the error message for one queue item.
     *
     * @param int $indexQueueItemId
     * @return void
     */
    public function showErrorAction(int $indexQueueItemId)
    {
        if (is_null($indexQueueItemId)) {
            // add a flash message and quit
            $label = 'solr.backend.index_queue_module.flashmessage.error.no_queue_item_for_queue_error';
            $severity = FlashMessage::ERROR;
            $this->addIndexQueueFlashMessage($label, $severity);

            return;
        }

        $item = $this->indexQueue->getItem($indexQueueItemId);
        $this->view->assign('indexQueueItem', $item);
    }

    /**
     * Indexes a few documents with the index service.
     * @return void
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function doIndexingRunAction()
    {
        /** @var $indexService \ApacheSolrForTypo3\Solr\Domain\Index\IndexService */
        $indexService = GeneralUtility::makeInstance(IndexService::class, /** @scrutinizer ignore-type */ $this->selectedSite);
        $indexWithoutErrors = $indexService->indexItems(10);

        $label = 'solr.backend.index_queue_module.flashmessage.success.index_manual';
        $severity = FlashMessage::OK;
        if (!$indexWithoutErrors) {
            $label = 'solr.backend.index_queue_module.flashmessage.error.index_manual';
            $severity = FlashMessage::ERROR;
        }

        $this->addFlashMessage(
            LocalizationUtility::translate($label, 'Solr'),
            LocalizationUtility::translate('solr.backend.index_queue_module.flashmessage.index_manual', 'Solr'),
            $severity
        );

        $this->redirect('index');
    }

    /**
     * Adds a flash message for the index queue module.
     *
     * @param string $label
     * @param int $severity
     */
    protected function addIndexQueueFlashMessage($label, $severity)
    {
        $this->addFlashMessage(LocalizationUtility::translate($label, 'Solr'), LocalizationUtility::translate('solr.backend.index_queue_module.flashmessage.title', 'Solr'), $severity);
    }
}
