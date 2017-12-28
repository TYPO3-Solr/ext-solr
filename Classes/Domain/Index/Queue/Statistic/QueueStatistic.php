<?php

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\Statistic;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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
