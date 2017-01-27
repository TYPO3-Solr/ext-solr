<?php
namespace ApacheSolrForTypo3\Solr\Backend\SolrModule;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Index Queue Module
 *
 * @author Ingo Renner <ingo@typo3.org>
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
     * Lists the available indexing configurations
     *
     * @return void
     */
    public function indexAction()
    {
        $statisticsCounts = $this->getIndexQueueStatistics();
        $statisticsPercentages = $this->getIndexQueueStatisticsPercentages($statisticsCounts);

        $this->view->assign('indexQueueInitializationSelector',
            $this->getIndexQueueInitializationSelector());
        $this->view->assign('indexqueue_statistics_counts', $statisticsCounts);
        $this->view->assign('indexqueue_statistics_percentages',
            $statisticsPercentages);
        $this->view->assign('indexqueue_errors', $this->getIndexQueueErrors());
    }

    /**
     * Extracts the number of pending, indexed and erroneous items from the
     * Index Queue.
     *
     * @return array
     */
    protected function getIndexQueueStatistics()
    {
        $indexQueueStats = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'indexed < changed as pending,'
            . '(errors not like "") as failed,'
            . 'COUNT(*) as count',
            'tx_solr_indexqueue_item',
            'root = ' . $this->site->getRootPageId(),
            'pending, failed'
        );

        $statistics = array(
            'errors' => 0,
            'pending' => 0,
            'indexed' => 0
        );
        foreach ($indexQueueStats as $row) {
            if ($row['failed'] == 1) {
                $statistics['errors'] = $row['count'];
            } elseif ($row['pending'] == 1) {
                $statistics['pending'] = $row['count'];
            } else {
                $statistics['indexed'] = $row['count'];
            }
        }

        $total = 0;
        foreach ($statistics as $count) {
            $total += $count;
        }
        $statistics['total'] = $total;

        return $statistics;
    }

    /**
     * Gets the Index Queue statistics as percentages
     *
     * @param array $statistics Input from getIndexQueueStatistics()
     * @return array
     */
    protected function getIndexQueueStatisticsPercentages($statistics)
    {
        $percentages = array(
            'errors' => 0,
            'pending' => 0,
            'indexed' => 0
        );

        if ($statistics['total'] > 0) {
            foreach ($statistics as $key => $count) {
                $percentages[$key] = round(($count * 100 / $statistics['total']),
                    0);
            }
        }

        return $percentages;
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
        $selector = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Backend\\IndexingConfigurationSelectorField',
            $this->site);
        $selector->setFormElementName('tx_solr-index-queue-initialization');

        return $selector->render();
    }

    /**
     * Finds indexing errors for the current site
     *
     * @return array Error items for the current site's Index Queue
     */
    protected function getIndexQueueErrors()
    {
        return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid, item_type, item_uid, errors',
            'tx_solr_indexqueue_item',
            'errors NOT LIKE "" AND root = ' . $this->site->getRootPageId()
        );
    }

    /**
     * Initializes the Index Queue for selected indexing configurations
     *
     * @return void
     */
    public function initializeIndexQueueAction()
    {
        $initializedIndexingConfigurations = array();

        $itemIndexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');
        $indexingConfigurationsToInitialize = GeneralUtility::_POST('tx_solr-index-queue-initialization');
        if (!empty($indexingConfigurationsToInitialize)) {
            // initialize selected indexing configuration
            foreach ($indexingConfigurationsToInitialize as $indexingConfigurationName) {
                $initializedIndexingConfiguration = $itemIndexQueue->initialize(
                    $this->site,
                    $indexingConfigurationName
                );

                // track initialized indexing configurations for the flash message
                $initializedIndexingConfigurations = array_merge(
                    $initializedIndexingConfigurations,
                    $initializedIndexingConfiguration
                );
            }
        } else {
            $messageLabel = 'solr.backend.index_queue_module.flashmessage.initialize.no_selection';
            $titleLabel = 'solr.backend.index_queue_module.flashmessage.not_initialized.title';
            $this->addFlashMessage(
                LocalizationUtility::translate($messageLabel, 'Solr'),
                LocalizationUtility::translate($titleLabel, 'Solr'),
                FlashMessage::WARNING
            );
        }

        $messagesForConfigurations = array();
        foreach (array_keys($initializedIndexingConfigurations) as $indexingConfigurationName) {
            $itemCount = $itemIndexQueue->getItemsCountBySite($this->site,
                $indexingConfigurationName);
            if (!is_int($itemCount)) {
                $itemCount = 0;
            }
            $messagesForConfigurations[] = $indexingConfigurationName . ' (' . $itemCount . ' records)';
        }

        if (!empty($initializedIndexingConfigurations)) {
            $messageLabel = 'solr.backend.index_queue_module.flashmessage.initialize.success';
            $titleLabel = 'solr.backend.index_queue_module.flashmessage.initialize.title';
            $this->addFlashMessage(
                LocalizationUtility::translate($messageLabel, 'Solr',
                    array(implode(', ', $messagesForConfigurations))),
                LocalizationUtility::translate($titleLabel, 'Solr'),
                FlashMessage::OK
            );

            $this->addFlashMessagesByQueueIdentifier('solr.queue.initializer');
        }

        $this->forward('index');
    }

    /**
     * Empties the Index Queue
     *
     * @return void
     */
    public function clearIndexQueueAction()
    {
        $indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');
        $indexQueue->deleteItemsBySite($this->site);

        $this->forward('index');
    }

    /**
     * Removes all errors in the index queue list. So that the items can be indexed again.
     *
     * @return void
     */
    public function resetLogErrorsAction()
    {
        /** @var DatabaseConnection $database */
        $database = $GLOBALS['TYPO3_DB'];
        $resetResult = $database->exec_UPDATEquery(
            'tx_solr_indexqueue_item',
            'errors NOT LIKE ""',
            array('errors' => '')
        );

        $label = 'solr.backend.index_queue_module.flashmessage.success.reset_errors';
        $severity = FlashMessage::OK;
        if (!$resetResult) {
            $label = 'solr.backend.index_queue_module.flashmessage.error.reset_errors';
            $severity = FlashMessage::ERROR;
        }

        $this->addFlashMessage(
            LocalizationUtility::translate($label, 'Solr'),
            LocalizationUtility::translate('solr.backend.index_queue_module.flashmessage.title',
                'Solr'),
            $severity
        );

        $this->forward('index');
    }

    /**
     * Retrieves an queue item by uid.
     *
     * @param integer $uid
     * @return array
     */
    protected function getIndexQueueItemById($uid)
    {
        return $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
            'uid, item_type, item_uid, errors',
            'tx_solr_indexqueue_item',
            'uid = ' . intval($uid)
        );
    }

    /**
     * Shows the error message for one queue item.
     *
     * @return void
     */
    public function showErrorAction()
    {
        $queueItemId = $this->getRequestArgumentOrDefaultValue('indexQueueItemId', null);

        if (is_null($queueItemId)) {
            // add a flash message and quit
            $label = 'solr.backend.index_queue_module.flashmessage.error.no_queue_item_for_queue_error';
            $severity = FlashMessage::ERROR;
            $this->addFlashMessage(
                LocalizationUtility::translate($label, 'Solr'),
                LocalizationUtility::translate('solr.backend.index_queue_module.flashmessage.title',
                    'Solr'),
                $severity
            );

            return;
        }

        $item = $this->getIndexQueueItemById($queueItemId);
        $this->view->assign('indexQueueItem', $item);
    }
}
