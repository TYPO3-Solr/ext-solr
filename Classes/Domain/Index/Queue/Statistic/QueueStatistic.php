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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\Statistic;

/**
 * Class QueueStatistic is responsible for index queue metrics calculations.
 */
class QueueStatistic
{
    /**
     * @var int
     */
    protected int $failedCount = 0;

    /**
     * @var int
     */
    protected int $pendingCount = 0;

    /**
     * @var int
     */
    protected int $successCount = 0;

    /**
     * @param int $failedCount
     */
    public function setFailedCount(int $failedCount)
    {
        $this->failedCount = $failedCount;
    }

    /**
     * @return int
     */
    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    /**
     * @return float
     */
    public function getFailedPercentage(): float
    {
        return $this->getPercentage($this->getFailedCount());
    }

    /**
     * @param int $pendingCount
     */
    public function setPendingCount(int $pendingCount)
    {
        $this->pendingCount = $pendingCount;
    }

    /**
     * @return int
     */
    public function getPendingCount(): int
    {
        return $this->pendingCount;
    }

    /**
     * @return float
     */
    public function getPendingPercentage(): float
    {
        return $this->getPercentage($this->getPendingCount());
    }

    /**
     * @param int $successCount
     */
    public function setSuccessCount(int $successCount)
    {
        $this->successCount = $successCount;
    }

    /**
     * @return int
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * @return float
     */
    public function getSuccessPercentage(): float
    {
        return $this->getPercentage($this->getSuccessCount());
    }

    /**
     * @return int
     */
    public function getTotalCount(): int
    {
        return $this->pendingCount + $this->failedCount + $this->successCount;
    }

    /**
     * @param int $count
     * @return float
     */
    protected function getPercentage(int $count): float
    {
        $total = $this->getTotalCount();
        if ($total === 0) {
            return 0.0;
        }
        return round((100 / $total) * $count, 2);
    }
}
