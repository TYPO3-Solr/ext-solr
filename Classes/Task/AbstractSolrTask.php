<?php
namespace ApacheSolrForTypo3\Solr\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Abstract scheduler task for solr scheduler tasks, contains the logic to
 * retrieve the site, avoids serialization of site, when scheduler task is saved.
 *
 */
abstract class AbstractSolrTask extends AbstractTask
{
    /**
     * The site this task is supposed to initialize the index queue for.
     *
     * @var Site
     */
    protected $site;

    /**
     * The rootPageId of the site that should be reIndexed
     *
     * @var int
     */
    protected $rootPageId;

    /**
     * @return int
     */
    public function getRootPageId()
    {
        return $this->rootPageId;
    }

    /**
     * @param int $rootPageId
     */
    public function setRootPageId($rootPageId)
    {
        $this->rootPageId = $rootPageId;
    }

    /**
     * @deprecated Use setRootPageId() with rootPageId of the site instead, will be removed in 8.0
     * @param Site $site
     */
    public function setSite(Site $site)
    {
        $this->site = $site;
        $this->rootPageId = $site->getRootPageId();
    }

    /**
     * @return Site
     */
    public function getSite()
    {
        if (!is_null($this->site)) {
            return $this->site;
        }
            /** @var $siteRepository SiteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $this->site = $siteRepository->getSiteByRootPageId($this->rootPageId);

        return $this->site;
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        $properties = get_object_vars($this);
        // avoid serialization if the site object
        unset($properties['site']);
        return array_keys($properties);
    }
}
