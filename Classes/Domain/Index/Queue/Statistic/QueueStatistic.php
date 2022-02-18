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

class QueueStatistic
{

    /**
     * @var int
     */
    protected $failedCount = 0;

    /**
     * @var int
     */
    protected $pendingCount = 0;

    /**
     * @var int
     */
    protected $successCount = 0;

    /**
     * @param int $failedCount
     */
    public function setFailedCount($failedCount)
    {
        $this->failedCount = $failedCount;
    }

    /**
     * @return int
     */
    public function getFailedCount()
    {
        return $this->failedCount;
    }

    /**
     * @return float|int
     */
    public function getFailedPercentage()
    {
        return $this->getPercentage($this->getFailedCount());
    }

    /**
     * @param int $pendingCount
     */
    public function setPendingCount($pendingCount)
    {
        $this->pendingCount = $pendingCount;
    }

    /**
     * @return int
     */
    public function getPendingCount()
    {
        return $this->pendingCount;
    }

    /**
     * @return float|int
     */
    public function getPendingPercentage()
    {
        return $this->getPercentage($this->getPendingCount());
    }

    /**
     * @param int $successCount
     */
    public function setSuccessCount($successCount)
    {
        $this->successCount = $successCount;
    }

    /**
     * @return int
     */
    public function getSuccessCount()
    {
        return $this->successCount;
    }

    /**
     * @return float|int
     */
    public function getSuccessPercentage()
    {
        return $this->getPercentage($this->getSuccessCount());
    }

    /**
     * @return int
     */
    public function getTotalCount()
    {
        return $this->pendingCount + $this->failedCount + $this->successCount;
    }

    /**
     * @param integer $count
     * @return float
     */
    protected function getPercentage($count)
    {
        $total = $this->getTotalCount();
        if ($total === 0) {
            return 0.0;
        }
        return (float)round((100 / $total) * $count, 2);
    }
}
