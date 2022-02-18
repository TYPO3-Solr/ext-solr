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

namespace ApacheSolrForTypo3\Solr\Domain\Search\LastSearches;

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;

class LastSearchesRepository extends AbstractRepository
{
    /**
     * @var string
     */
    protected $table = 'tx_solr_last_searches';

    /**
     * Finds the last searched keywords from the database
     *
     * @param int $limit
     * @return array An array containing the last searches of the current user
     */
    public function findAllKeywords($limit = 10) : array
    {
        $lastSearchesResultSet = $this->getLastSearchesResultSet($limit);
        if (empty($lastSearchesResultSet)) {
            return [];
        }

        $lastSearches = [];
        foreach ($lastSearchesResultSet as $row) {
            $lastSearches[] = html_entity_decode($row['keywords'], ENT_QUOTES, 'UTF-8');
        }

        return $lastSearches;
    }

    /**
     * Returns all last searches
     *
     * @param $limit
     * @return array
     */
    protected function getLastSearchesResultSet($limit) : array
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->select('keywords')
            ->addSelectLiteral(
                $queryBuilder->expr()->max('tstamp','maxtstamp')
            )
            ->from($this->table)
            // There is no support for DISTINCT, a ->groupBy() has to be used instead.
            ->groupBy('keywords')
            ->orderBy('maxtstamp', 'DESC')
            ->setMaxResults($limit)->execute()->fetchAll();
    }

    /**
     * Adds keywords to last searches or updates the oldest row by given limit.
     *
     * @param string $lastSearchesKeywords
     * @param int $lastSearchesLimit
     * @return void
     */
    public function add(string $lastSearchesKeywords, int $lastSearchesLimit)
    {
        $nextSequenceId = $this->resolveNextSequenceIdForGivenLimit($lastSearchesLimit);
        $rowsCount = $this->count();
        if ($nextSequenceId < $rowsCount) {
            $this->update([
                'sequence_id' => $nextSequenceId,
                'keywords' => $lastSearchesKeywords
            ]);
            return;
        }

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->insert($this->table)
            ->values([
                'sequence_id' => $nextSequenceId,
                'keywords' => $lastSearchesKeywords,
                'tstamp' => time()
            ])
            ->execute();
    }

    /**
     * Resolves next sequence id by given last searches limit.
     *
     * @param int $lastSearchesLimit
     * @return int
     */
    protected function resolveNextSequenceIdForGivenLimit(int $lastSearchesLimit) : int
    {
        $nextSequenceId = 0;

        $queryBuilder = $this->getQueryBuilder();
        $result = $queryBuilder->select('sequence_id')
            ->from($this->table)
            ->orderBy('tstamp', 'DESC')
            ->setMaxResults(1)
            ->execute()->fetch();

        if (!empty($result)) {
            $nextSequenceId = ($result['sequence_id'] + 1) % $lastSearchesLimit;
        }

        return $nextSequenceId;
    }

    /**
     * Updates last searches row by using sequence_id from given $lastSearchesRow array
     *
     * @param array $lastSearchesRow
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function update(array $lastSearchesRow)
    {
        $queryBuilder = $this->getQueryBuilder();

        $affectedRows = $queryBuilder
            ->update($this->table)
            ->where(
                $queryBuilder->expr()->eq('sequence_id', $queryBuilder->createNamedParameter($lastSearchesRow['sequence_id']))
            )
            ->set('tstamp', time())
            ->set('keywords', $lastSearchesRow['keywords'])
            ->execute();

        if ($affectedRows < 1) {
            throw new \InvalidArgumentException(vsprintf('By trying to update last searches row with values "%s" nothing was updated, make sure the given "sequence_id" exists in database.', [\json_encode($lastSearchesRow)]), 1502717923);
        }
    }
}
