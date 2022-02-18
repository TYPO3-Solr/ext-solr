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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\Statistic;

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use PDO;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\UnsupportedMethodException;

/**
 * Class QueueStatisticsRepository
 */
class QueueStatisticsRepository extends AbstractRepository
{
    /**
     * @var string
     */
    protected $table = 'tx_solr_indexqueue_item';

    /**
     * Extracts the number of pending, indexed and erroneous items from the
     * Index Queue.
     *
     * @param int $rootPid
     * @param string|null $indexingConfigurationName
     *
     * @return QueueStatistic
     *
     * @throws DBALDriverException
     * @throws DBALException
     */
    public function findOneByRootPidAndOptionalIndexingConfigurationName(
        int $rootPid,
        ?string $indexingConfigurationName = null
    ): QueueStatistic {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->add('select', vsprintf('(%s < %s) AS %s', [
                $queryBuilder->quoteIdentifier('indexed'),
                $queryBuilder->quoteIdentifier('changed'),
                $queryBuilder->quoteIdentifier('pending')
            ]), true)
            ->add('select', vsprintf('(%s) AS %s', [
                $queryBuilder->expr()->notLike('errors', $queryBuilder->createNamedParameter('')),
                $queryBuilder->quoteIdentifier('failed')
            ]), true)
            ->add('select', $queryBuilder->expr()->count('*', 'count'), true)
            ->from($this->table)
            ->where(
                /** @scrutinizer ignore-type */
                $queryBuilder->expr()->eq('root', $queryBuilder->createNamedParameter($rootPid, PDO::PARAM_INT))
            )->groupBy('pending', 'failed');

        if (!empty($indexingConfigurationName)) {
            $queryBuilder->andWhere(
                /** @scrutinizer ignore-type */
                $queryBuilder->expr()->eq('indexing_configuration', $queryBuilder->createNamedParameter($indexingConfigurationName))
            );
        }

        return $this->buildQueueStatisticFromResultSet(
            $queryBuilder
                ->execute()
                ->fetchAllAssociative()
        );
    }

    /**
     * Instantiates and fills QueueStatistic with values
     *
     * @param array $indexQueueStatisticResultSet
     * @return QueueStatistic
     */
    protected function buildQueueStatisticFromResultSet(array $indexQueueStatisticResultSet): QueueStatistic
    {
        /* @var $statistic QueueStatistic */
        $statistic = GeneralUtility::makeInstance(QueueStatistic::class);
        foreach ($indexQueueStatisticResultSet as $row) {
            if ($row['failed'] == 1) {
                $statistic->setFailedCount((int)$row['count']);
            } elseif ($row['pending'] == 1) {
                $statistic->setPendingCount((int)$row['count']);
            } else {
                $statistic->setSuccessCount((int)$row['count']);
            }
        }

        return $statistic;
    }

    /**
     * Don't use this method.
     *
     * @return int
     * @throws UnsupportedMethodException
     */
    public function count(): int
    {
        throw new UnsupportedMethodException('Can not count the Index Queue Statistics.', 1504694750);
    }
}
