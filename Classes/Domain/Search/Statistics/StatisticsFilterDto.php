<?php

declare(strict_types=1);

namespace ApacheSolrForTypo3\Solr\Domain\Search\Statistics;

use DateTime;

final class StatisticsFilterDto
{
    /** typoscript constants */
    private int $siteRootPageId = 0;
    private int $topHitsLimit = 5;
    private int $noHitsLimit = 5;
    private int $queriesLimit = 100;
    private int $topHitsDays = 30;
    private int $noHitsDays = 30;
    private int $queriesDays = 30;

    /** Override properties */
    private ?DateTime $startDate = null;
    private ?DateTime $endDate = null;

    public function __construct()
    {
        $this->startDate = DateTime::createFromFormat('U', (string)$this->getQueriesStartDate());
        $this->endDate = DateTime::createFromFormat('U', (string)$this->getEndDateTimestamp());
    }

    public function setFromTypoScriptConstants(array $settings): StatisticsFilterDto
    {
        $this->topHitsLimit = (int)($settings['topHits.']['limit'] ?? 5);
        $this->noHitsLimit = (int)($settings['noHits.']['limit'] ?? 5);
        $this->queriesLimit = (int)($settings['queries.']['limit'] ?? 100);
        $this->topHitsDays = (int)($settings['topHits.']['days'] ?? 30);
        $this->noHitsDays = (int)($settings['noHits.']['days'] ?? 30);
        $this->queriesDays = (int)($settings['queries.']['days'] ?? 30);

        return $this;
    }

    public function setSiteRootPageId(int $siteRootPageId): StatisticsFilterDto
    {
        $this->siteRootPageId = $siteRootPageId;
        return $this;
    }

    public function setStartDate(?DateTime $startDate): StatisticsFilterDto
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function setEndDate(?DateTime $endDate): StatisticsFilterDto
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getTopHitsDays(): int
    {
        return $this->topHitsDays;
    }

    public function getNoHitsDays(): int
    {
        return $this->noHitsDays;
    }

    public function getQueriesDays(): int
    {
        return $this->queriesDays;
    }

    public function getSiteRootPageId(): int
    {
        return $this->siteRootPageId;
    }

    public function getTopHitsLimit(): int
    {
        return $this->topHitsLimit;
    }

    public function getNoHitsLimit(): int
    {
        return $this->noHitsLimit;
    }

    public function getQueriesLimit(): int
    {
        return $this->queriesLimit;
    }

    public function getStartDate(): ?DateTime
    {
        return $this->startDate;
    }

    public function getEndDate(): ?DateTime
    {
        return $this->endDate;
    }

    public function getTopHitsStartDate(): int
    {
        if ($this->startDate !== null) {
            return $this->startDate->getTimestamp();
        }

        return $this->getTimeStampSinceDays($this->topHitsDays);
    }

    public function getNoHitsStartDate(): int
    {
        if ($this->startDate !== null) {
            return $this->startDate->getTimestamp();
        }

        return $this->getTimeStampSinceDays($this->noHitsDays);
    }

    public function getQueriesStartDate(): int
    {
        if ($this->startDate !== null) {
            return $this->startDate->getTimestamp();
        }

        return $this->getTimeStampSinceDays($this->queriesDays);
    }

    /**
     * End date can not be set by default in typoscript constants and is always now, so one override getter is enough
     */
    public function getEndDateTimestamp(): int
    {
        if ($this->endDate !== null) {
            return $this->endDate->getTimestamp();
        }

        return $this->getTimeStampSinceDays(0);
    }

    protected function getTimeStampSinceDays(int $days): int
    {
        $now = time();
        return $now - 86400 * $days; // 86400 seconds/day
    }
}
