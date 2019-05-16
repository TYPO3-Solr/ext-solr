<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue\Initializer;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use Doctrine\DBAL\DBALException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract Index Queue initializer with implementation  of methods for common
 * needs during Index Queue initialization.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
abstract class AbstractInitializer implements IndexQueueInitializer
{

    /**
     * Site to initialize
     *
     * @var Site
     */
    protected $site;

    /**
     * The type of items this initializer is handling.
     *
     * @var string
     */
    protected $type;

    /**
     * Index Queue configuration.
     *
     * @var array
     */
    protected $indexingConfiguration;

    /**
     * Indexing configuration name.
     *
     * @var string
     */
    protected $indexingConfigurationName;

    /**
     * Flash message queue
     *
     * @var \TYPO3\CMS\Core\Messaging\FlashMessageQueue
     */
    protected $flashMessageQueue;

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager
     */
    protected $logger = null;

    /**
     * @var QueueItemRepository
     */
    protected $queueItemRepository;

    /**
     * Constructor, prepares the flash message queue
     * @param QueueItemRepository|null $queueItemRepository
     */
    public function __construct(QueueItemRepository $queueItemRepository = null)
    {
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $this->flashMessageQueue = $flashMessageService->getMessageQueueByIdentifier('solr.queue.initializer');
        $this->queueItemRepository = $queueItemRepository ?? GeneralUtility::makeInstance(QueueItemRepository::class);
    }

    /**
     * Sets the site for the initializer.
     *
     * @param Site $site The site to initialize Index Queue items for.
     */
    public function setSite(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Set the type (usually a Db table name) of items to initialize.
     *
     * @param string $type Type to initialize.
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Sets the configuration for how to index a type of items.
     *
     * @param array $indexingConfiguration Indexing configuration from TypoScript
     */
    public function setIndexingConfiguration(array $indexingConfiguration)
    {
        $this->indexingConfiguration = $indexingConfiguration;
    }

    /**
     * Sets the name of the indexing configuration to initialize.
     *
     * @param string $indexingConfigurationName Indexing configuration name
     */
    public function setIndexingConfigurationName($indexingConfigurationName)
    {
        $this->indexingConfigurationName = (string)$indexingConfigurationName;
    }

    /**
     * Initializes Index Queue items for a certain site and indexing
     * configuration.
     *
     * @return bool TRUE if initialization was successful, FALSE on error.
     */
    public function initialize()
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $fetchItemsQuery = $this->buildSelectStatement() . ', "" as errors '
            . 'FROM ' . $this->type . ' '
            . 'WHERE '
            . $this->buildPagesClause()
            . $this->buildTcaWhereClause()
            . $this->buildUserWhereClause();

        try {
            if ($connectionPool->getConnectionForTable($this->type)->getParams() === $connectionPool->getConnectionForTable('tx_solr_indexqueue_item')->getParams()) {
                // If both tables are in the same DB, send only one query to copy all datas from one table to the other
                $initializationQuery = 'INSERT INTO tx_solr_indexqueue_item (root, item_type, item_uid, indexing_configuration, indexing_priority, changed, errors) ' . $fetchItemsQuery;
                $logData = ['query' => $initializationQuery];
                $logData['rows'] = $this->queueItemRepository->initializeByNativeSQLStatement($initializationQuery);
            } else {
                // If tables are using distinct connections, start by fetching items matching criteria
                $logData = ['query' => $fetchItemsQuery];
                $items = $connectionPool->getConnectionForTable($this->type)->fetchAll($fetchItemsQuery);
                $logData['rows'] = count($items);

                if (count($items)) {
                    // Add items to the queue (if any)
                    $logData['rows'] = $connectionPool
                        ->getConnectionForTable('tx_solr_indexqueue_item')
                        ->bulkInsert('tx_solr_indexqueue_item', $items, array_keys($items[0]));
                }
            }
        } catch (DBALException $DBALException) {
            $logData['error'] = $DBALException->getCode() . ': ' . $DBALException->getMessage();
        }

        $this->logInitialization($logData);
        return true;
    }

    /**
     * Builds the SELECT part of the Index Queue initialization query.
     *
     */
    protected function buildSelectStatement()
    {
        $changedField = $GLOBALS['TCA'][$this->type]['ctrl']['tstamp'];
        if (!empty($GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['starttime'])) {
            $changedField = 'GREATEST(' . $GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['starttime'] . ',' . $GLOBALS['TCA'][$this->type]['ctrl']['tstamp'] . ')';
        }
        $select = 'SELECT '
            . '\'' . $this->site->getRootPageId() . '\' as root, '
            . '\'' . $this->type . '\' AS item_type, '
            . 'uid AS item_uid, '
            . '\'' . $this->indexingConfigurationName . '\' as indexing_configuration, '
            . $this->getIndexingPriority() . ' AS indexing_priority, '
            . $changedField . ' AS changed';

        return $select;
    }

    // initialization query building

    /**
     * Reads the indexing priority for an indexing configuration.
     *
     * @return int Indexing priority
     */
    protected function getIndexingPriority()
    {
        $priority = 0;

        if (!empty($this->indexingConfiguration['indexingPriority'])) {
            $priority = (int)$this->indexingConfiguration['indexingPriority'];
        }

        return $priority;
    }

    /**
     * Builds a part of the WHERE clause of the Index Queue initialization
     * query. This part selects the limits items to be selected from the pages
     * in a site only, plus additional pages that may have been configured.
     *
     */
    protected function buildPagesClause()
    {
        $pages = $this->getPages();
        $pageIdField = ($this->type === 'pages') ? 'uid' : 'pid';

        return $pageIdField . ' IN(' . implode(',', $pages) . ')';
    }

    /**
     * Gets the pages in a site plus additional pages that may have been
     * configured.
     *
     * @return array A (sorted) array of page IDs in a site
     */
    protected function getPages()
    {
        $pages = $this->site->getPages();
        $additionalPageIds = [];
        if (!empty($this->indexingConfiguration['additionalPageIds'])) {
            $additionalPageIds = GeneralUtility::intExplode(',', $this->indexingConfiguration['additionalPageIds']);
        }

        $pages = array_merge($pages, $additionalPageIds);
        sort($pages, SORT_NUMERIC);

        return $pages;
    }

    /**
     * Builds the WHERE clauses of the Index Queue initialization query based
     * on TCA information for the type to be initialized.
     *
     * @return string Conditions to only add indexable items to the Index Queue
     */
    protected function buildTcaWhereClause()
    {
        $tcaWhereClause = '';
        $conditions = [];

        if (isset($GLOBALS['TCA'][$this->type]['ctrl']['delete'])) {
            $conditions['delete'] = $GLOBALS['TCA'][$this->type]['ctrl']['delete'] . ' = 0';
        }

        if (isset($GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['disabled'])) {
            $conditions['disabled'] = $GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['disabled'] . ' = 0';
        }

        if (isset($GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['endtime'])) {
            // only include records with a future endtime or default value (0)
            $endTimeFieldName = $GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['endtime'];
            $conditions['endtime'] = '(' . $endTimeFieldName . ' > ' . time() . ' OR ' . $endTimeFieldName . ' = 0)';
        }

        if (BackendUtility::isTableLocalizable($this->type)) {
            $conditions['languageField'] = [
                $GLOBALS['TCA'][$this->type]['ctrl']['languageField'] . ' = 0',
                // default language
                $GLOBALS['TCA'][$this->type]['ctrl']['languageField'] . ' = -1'
                // all languages
            ];
            if (isset($GLOBALS['TCA'][$this->type]['ctrl']['transOrigPointerField'])) {
                $conditions['languageField'][] = $GLOBALS['TCA'][$this->type]['ctrl']['transOrigPointerField'] . ' = 0'; // translations without original language source
            }
            $conditions['languageField'] = '(' . implode(' OR ',
                    $conditions['languageField']) . ')';
        }

        if (!empty($GLOBALS['TCA'][$this->type]['ctrl']['versioningWS'])) {
            // versioning is enabled for this table: exclude draft workspace records
            $conditions['versioningWS'] = 'pid != -1';
        }

        if (count($conditions)) {
            $tcaWhereClause = ' AND ' . implode(' AND ', $conditions);
        }

        return $tcaWhereClause;
    }

    /**
     * Builds the WHERE clauses of the Index Queue initialization query based
     * on TypoScript configuration for the type to be initialized.
     *
     * @return string Conditions to add items to the Index Queue based on TypoScript configuration
     */
    protected function buildUserWhereClause()
    {
        $condition = '';

        // FIXME replace this with the mechanism described below
        if (isset($this->indexingConfiguration['additionalWhereClause'])) {
            $condition = ' AND ' . $this->indexingConfiguration['additionalWhereClause'];
        }

        return $condition;

        // TODO add a query builder implementation based on TypoScript configuration

        /* example TypoScript

                @see http://docs.jboss.org/drools/release/5.4.0.Final/drools-expert-docs/html_single/index.html
                @see The Java Rule Engine API (JSR94)

                tt_news {

                        // RULES cObject provided by EXT:rules, simply evaluates to boolean TRUE or FALSE
                    conditions = RULES
                    conditions {

                        and {

                            10 {
                                field = pid
                                value = 2,3,5
                                condition = in / equals / notEquals / greaterThan / lessThan / greaterThanOrEqual / lessThanOrEqual
                            }

                            20 {
                                field = ...
                                value = ...
                                condition = ...

                                or {
                                    10 {
                                        field = ...
                                        value = ...
                                        condition =  ...
                                    }

                                    20 {
                                        field = ...
                                        value = ...
                                        condition = ...
                                    }
                                }
                            }

                        }

                    }

                    fields {
                        // field mapping
                    }
                }
        */
    }

    /**
     * Writes the passed log data to the log.
     *
     * @param array $logData
     */
    protected function logInitialization(array $logData)
    {
        if (!$this->site->getSolrConfiguration()->getLoggingIndexingIndexQueueInitialization()) {
            return;
        }

        $logSeverity = isset($logData['error']) ? SolrLogManager::ERROR : SolrLogManager::NOTICE;
        $logData = array_merge($logData, [
            'site' => $this->site->getLabel(),
            'indexing configuration name' => $this->indexingConfigurationName,
            'type' => $this->type,
        ]);

        $message = 'Index Queue initialized for indexing configuration ' . $this->indexingConfigurationName;
        $this->logger->log($logSeverity, $message, $logData);
    }
}
