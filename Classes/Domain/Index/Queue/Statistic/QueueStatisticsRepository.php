<?php declare(strict_types = 1);
namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\Statistic;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
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
     * @param string $indexingConfigurationName
     *
     * @return QueueStatistic
     */
    public function findOneByRootPidAndOptionalIndexingConfigurationName(int $rootPid, $indexingConfigurationName = null): QueueStatistic
    {
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
            ->where($queryBuilder->expr()->eq('root', $queryBuilder->createNamedParameter($rootPid, \PDO::PARAM_INT)))
            ->groupBy('pending', 'failed');

        if (!empty($indexingConfigurationName)) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('indexing_configuration', $queryBuilder->createNamedParameter($indexingConfigurationName)));
        }

        return $this->buildQueueStatisticFromResultSet($queryBuilder->execute()->fetchAll());
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
