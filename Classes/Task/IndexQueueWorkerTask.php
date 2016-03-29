<?php
namespace ApacheSolrForTypo3\Solr\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\ProgressProviderInterface;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * A worker indexing the items in the index queue. Needs to be set up as one
 * task per root page.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class IndexQueueWorkerTask extends AbstractTask implements ProgressProviderInterface
{

    /**
     * The site this task is indexing.
     *
     * @var Site
     */
    protected $site;

    /**
     * @var integer
     */
    protected $documentsToIndexLimit;

    /**
     * Works through the indexing queue and indexes the queued items into Solr.
     *
     * @return boolean Returns TRUE on success, FALSE if no items were indexed or none were found.
     */
    public function execute()
    {
        $executionSucceeded = false;

        /** @var $indexService \ApacheSolrForTypo3\Solr\Domain\Index\IndexService */
        $indexService = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Domain\\Index\\IndexService', $this->site);
        $indexService->setContextTask($this);
        $indexService->indexItems($this->documentsToIndexLimit);
        $executionSucceeded = true;

        return $executionSucceeded;
    }

    /**
     * Returns some additional information about indexing progress, shown in
     * the scheduler's task overview list.
     *
     * @return string Information to display
     */
    public function getAdditionalInformation()
    {
        $message = 'Site: ' . $this->site->getLabel();

        $failedItemsCount = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
            'uid',
            'tx_solr_indexqueue_item',
            'root = ' . $this->site->getRootPageId() . ' AND errors != \'\''
        );
        if ($failedItemsCount) {
            $message .= ' Failures: ' . $failedItemsCount;
        }

        return $message;
    }

    /**
     * Gets the indexing progress.
     *
     * @return float Indexing progress as a two decimal precision float. f.e. 44.87
     */
    public function getProgress()
    {
        /** @var $indexService \ApacheSolrForTypo3\Solr\Domain\Index\IndexService */
        $indexService = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\Domain\Index\IndexService', $this->site);
        $indexService->setContextTask($this);

        return $indexService->getProgress();
    }

    /**
     * Gets the site / the site's root page uid this task is indexing.
     *
     * @return Site The site's root page uid this task is indexing
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * Sets the task's site to indexing.
     *
     * @param Site $site The site to index by this task
     * @return void
     */
    public function setSite(Site $site)
    {
        $this->site = $site;
    }

    /**
     * @return mixed
     */
    public function getDocumentsToIndexLimit()
    {
        return $this->documentsToIndexLimit;
    }

    /**
     * @param integer $limit
     */
    public function setDocumentsToIndexLimit($limit)
    {
        $this->documentsToIndexLimit = $limit;
    }
}
