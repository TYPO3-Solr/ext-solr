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
    protected int $failedCount = 0;

    protected int $pendingCount = 0;

    protected int $successCount = 0;

    public function setFailedCount(int $failedCount): void
    {
        $this->failedCount = $failedCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function getFailedPercentage(): float
    {
        return $this->getPercentage($this->getFailedCount());
    }

    public function setPendingCount(int $pendingCount): void
    {
        $this->pendingCount = $pendingCount;
    }

    public function getPendingCount(): int
    {
        return $this->pendingCount;
    }

    public function getPendingPercentage(): float
    {
        return $this->getPercentage($this->getPendingCount());
    }

    public function setSuccessCount(int $successCount): void
    {
        $this->successCount = $successCount;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getSuccessPercentage(): float
    {
        return $this->getPercentage($this->getSuccessCount());
    }

    public function getTotalCount(): int
    {
        return $this->pendingCount + $this->failedCount + $this->successCount;
    }

    protected function getPercentage(int $count): float
    {
        $total = $this->getTotalCount();
        if ($total === 0) {
            return 0.0;
        }
        return round((100 / $total) * $count, 2);
    }
}
