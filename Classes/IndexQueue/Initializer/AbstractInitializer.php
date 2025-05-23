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

namespace ApacheSolrForTypo3\Solr\IndexQueue\Initializer;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LogLevel;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract Index Queue initializer with implementation  of methods for common
 * needs during Index Queue initialization.
 */
abstract class AbstractInitializer implements IndexQueueInitializer
{
    /**
     * Site to initialize
     */
    protected ?Site $site;

    /**
     * The type of items this initializer is handling.
     */
    protected string $type;

    /**
     * Index Queue configuration.
     */
    protected array $indexingConfiguration = [];

    /**
     * Indexing configuration name.
     */
    protected string $indexingConfigurationName;

    /**
     * Flash message queue
     */
    protected FlashMessageQueue $flashMessageQueue;

    protected SolrLogManager $logger;

    protected QueueItemRepository $queueItemRepository;

    protected PagesRepository $pagesRepository;

    /**
     * Constructor, prepares the flash message queue
     */
    public function __construct(
        ?QueueItemRepository $queueItemRepository = null,
        ?PagesRepository $pagesRepository = null,
    ) {
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $this->flashMessageQueue = $flashMessageService->getMessageQueueByIdentifier('solr.queue.initializer');
        $this->queueItemRepository = $queueItemRepository ?? GeneralUtility::makeInstance(QueueItemRepository::class);
        $this->pagesRepository = $pagesRepository ?? GeneralUtility::makeInstance(PagesRepository::class);
    }

    /**
     * Sets the site for the initializer.
     *
     * @param Site $site The site to initialize Index Queue items for.
     */
    public function setSite(Site $site): void
    {
        $this->site = $site;
    }

    /**
     * Set the type (usually a Db table name) of items to initialize.
     *
     * @param string $type Type to initialize.
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * Sets the configuration for how to index a type of items.
     *
     * @param array $indexingConfiguration Indexing configuration from TypoScript
     */
    public function setIndexingConfiguration(array $indexingConfiguration): void
    {
        $this->indexingConfiguration = $indexingConfiguration;
    }

    /**
     * Sets the name of the indexing configuration to initialize.
     *
     * @param string $indexingConfigurationName Indexing configuration name
     */
    public function setIndexingConfigurationName(string $indexingConfigurationName): void
    {
        $this->indexingConfigurationName = $indexingConfigurationName;
    }

    /**
     * Initializes Index Queue items for a certain site and indexing
     * configuration.
     *
     * @return bool TRUE if initialization was successful, FALSE on error.
     *
     * @throws DBALException
     */
    public function initialize(): bool
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
                $items = $connectionPool->getConnectionForTable($this->type)->fetchAllAssociative($fetchItemsQuery);
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
     */
    protected function buildSelectStatement(): string
    {
        $changedField = $GLOBALS['TCA'][$this->type]['ctrl']['tstamp'];
        if (!empty($GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['starttime'])) {
            $changedField = 'GREATEST(' . $GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['starttime'] . ',' . $GLOBALS['TCA'][$this->type]['ctrl']['tstamp'] . ')';
        }
        return 'SELECT '
            . '\'' . $this->site->getRootPageId() . '\' as root, '
            . '\'' . $this->type . '\' AS item_type, '
            . 'uid AS item_uid, '
            . '\'' . $this->indexingConfigurationName . '\' as indexing_configuration, '
            . $this->getIndexingPriority() . ' AS indexing_priority, '
            . $changedField . ' AS changed';
    }

    // initialization query building

    /**
     * Reads the indexing priority for an indexing configuration.
     *
     * @return int Indexing priority
     */
    protected function getIndexingPriority(): int
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
     * @throws DBALException
     */
    protected function buildPagesClause(): string
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
     *
     * @throws DBALException
     */
    protected function getPages(): array
    {
        $pages = $this->site->getPages(null, $this->indexingConfigurationName);
        $additionalPageIds = [];
        if (!empty($this->indexingConfiguration['additionalPageIds'])) {
            $additionalPageIds = GeneralUtility::intExplode(',', $this->indexingConfiguration['additionalPageIds']);
        }

        $pages = array_merge($pages, $additionalPageIds);
        sort($pages, SORT_NUMERIC);

        $pagesWithinNoSearchSubEntriesPages = $this->pagesRepository->findAllPagesWithinNoSearchSubEntriesMarkedPages();
        // @todo: log properly if $additionalPageIds are within $pagesWithinNoSearchSubEntriesPages
        return array_values(array_diff($pages, $pagesWithinNoSearchSubEntriesPages));
    }

    /**
     * Builds the WHERE clauses of the Index Queue initialization query based
     * on TCA information for the type to be initialized.
     *
     * @return string Conditions to only add indexable items to the Index Queue
     */
    protected function buildTcaWhereClause(): string
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
                // default language
                $GLOBALS['TCA'][$this->type]['ctrl']['languageField'] . ' = 0',
                // all languages
                $GLOBALS['TCA'][$this->type]['ctrl']['languageField'] . ' = -1',
            ];
            // all "free"-Mode languages for "non-pages"-records only
            if ($this->type !== 'pages' && $this->site->hasFreeContentModeLanguages()) {
                $conditions['languageField'][]
                    = $GLOBALS['TCA'][$this->type]['ctrl']['languageField']
                    . ' IN(/* free content mode */ '
                        . implode(',', $this->site->getFreeContentModeLanguages())
                    . ')';
            }

            if (isset($GLOBALS['TCA'][$this->type]['ctrl']['transOrigPointerField'])) {
                $conditions['languageField'][] = $GLOBALS['TCA'][$this->type]['ctrl']['transOrigPointerField'] . ' = 0'; // translations without original language source
            }
            $conditions['languageField'] = '(' . implode(
                ' OR ',
                $conditions['languageField'],
            ) . ')';
        }

        if (!empty($GLOBALS['TCA'][$this->type]['ctrl']['versioningWS'])) {
            // versioning is enabled for this table: exclude draft workspace records
            /* @see \TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction::buildExpression */
            $conditions['versioningWS'] = 't3ver_wsid = 0';
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
    protected function buildUserWhereClause(): string
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
     */
    protected function logInitialization(array $logData): void
    {
        if (!$this->site->getSolrConfiguration()->getLoggingIndexingIndexQueueInitialization()) {
            return;
        }

        $logSeverity = isset($logData['error']) ? LogLevel::ERROR : LogLevel::NOTICE;
        $logData = array_merge($logData, [
            'site' => $this->site->getLabel(),
            'indexing configuration name' => $this->indexingConfigurationName,
            'type' => $this->type,
        ]);

        $message = 'Index Queue initialized for indexing configuration ' . $this->indexingConfigurationName;
        $this->logger->log($logSeverity, $message, $logData);
    }
}
