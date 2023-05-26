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

namespace ApacheSolrForTypo3\Solr\Task;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use InvalidArgumentException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Abstract scheduler task for solr scheduler tasks, contains the logic to
 * retrieve the site, avoids serialization of site, when scheduler task is saved.
 */
abstract class AbstractSolrTask extends AbstractTask
{
    /**
     * The site this task is supposed to initialize the index queue for.
     *
     * @var Site|null
     */
    protected ?Site $site = null;

    /**
     * The rootPageId of the site that should be reIndexed
     *
     * @var string|int|null
     */
    protected $rootPageId;

    /**
     * @return string|int|null
     */
    public function getRootPageId()
    {
        return $this->rootPageId;
    }

    /**
     * @param int $rootPageId
     */
    public function setRootPageId(int $rootPageId)
    {
        $this->rootPageId = $rootPageId;
    }

    /**
     * @return Site|null
     * @throws DBALDriverException
     */
    public function getSite(): ?Site
    {
        if (!is_null($this->site)) {
            return $this->site;
        }

        try {
            /* @var SiteRepository $siteRepository */
            $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
            $this->site = $siteRepository->getSiteByRootPageId((int)$this->rootPageId);
        } catch (InvalidArgumentException $e) {
            $logger = GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
            $logger->log(SolrLogManager::ERROR, 'Scheduler task tried to get invalid site');
        }

        return $this->site;
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        $properties = get_object_vars($this);
        // avoid serialization of the site and logger object
        unset($properties['site'], $properties['logger']);
        return array_keys($properties);
    }
}
