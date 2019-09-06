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

use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\System\Environment\CliEnvironment;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\ProgressProviderInterface;

/**
 * A worker indexing the items in the index queue. Needs to be set up as one
 * task per root page.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class IndexQueueWorkerTask extends AbstractSolrTask implements ProgressProviderInterface
{
    /**
     * @var int
     */
    protected $documentsToIndexLimit;

    /**
     * @var string
     */
    protected $forcedWebRoot = '';

    /**
     * Works through the indexing queue and indexes the queued items into Solr.
     *
     * @return bool Returns TRUE on success, FALSE if no items were indexed or none were found.
     */
    public function execute()
    {
        $cliEnvironment = null;

        // Wrapped the CliEnvironment to avoid defining TYPO3_PATH_WEB since this
        // should only be done in the case when running it from outside TYPO3 BE
        // @see #921 and #934 on https://github.com/TYPO3-Solr
        if (TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_CLI) {
            $cliEnvironment = GeneralUtility::makeInstance(CliEnvironment::class);
            $cliEnvironment->backup();
            $cliEnvironment->initialize($this->getWebRoot(), Environment::getPublicPath() . '/');
        }

        $site = $this->getSite();
        $indexService = $this->getInitializedIndexService($site);
        $indexService->indexItems($this->documentsToIndexLimit);

        if (TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_CLI) {
            $cliEnvironment->restore();
        }

        $executionSucceeded = true;

        return $executionSucceeded;
    }

    /**
     * In the cli context TYPO3 has chance to determine the webroot.
     * Since we need it for the TSFE related things we allow to set it
     * in the scheduler task and use the ###PATH_typo3### marker in the
     * setting to be able to define relative paths.
     *
     * @return string
     */
    public function getWebRoot()
    {
        if ($this->forcedWebRoot !== '') {
            return $this->replaceWebRootMarkers($this->forcedWebRoot);
        }

        return Environment::getPublicPath() . '/';
    }

    /**
     * @param string $webRoot
     * @return string
     */
    protected function replaceWebRootMarkers($webRoot)
    {
        if (strpos($webRoot, '###PATH_typo3###') !== false) {
            $webRoot = str_replace('###PATH_typo3###', Environment::getPublicPath() . '/typo3/', $webRoot);
        }

        if (strpos($webRoot, '###PATH_site###') !== false) {
            $webRoot = str_replace('###PATH_site###', Environment::getPublicPath() . '/', $webRoot);
        }

        return $webRoot;
    }

    /**
     * Returns some additional information about indexing progress, shown in
     * the scheduler's task overview list.
     *
     * @return string Information to display
     */
    public function getAdditionalInformation()
    {
        $site = $this->getSite();

        if (is_null($site)) {
            return 'Invalid site configuration for scheduler please re-create the task!';
        }

        $message = 'Site: ' . $site->getLabel();

        /** @var $indexService \ApacheSolrForTypo3\Solr\Domain\Index\IndexService */
        $indexService = $this->getInitializedIndexService($site);
        $failedItemsCount = $indexService->getFailCount();

        if ($failedItemsCount) {
            $message .= ' Failures: ' . $failedItemsCount;
        }

        $message .= ' / Using webroot: ' . htmlspecialchars($this->getWebRoot());

        return $message;
    }

    /**
     * Gets the indexing progress.
     *
     * @return float Indexing progress as a two decimal precision float. f.e. 44.87
     */
    public function getProgress()
    {
        $site = $this->getSite();
        if (is_null($site)) {
            return 0.0;
        }

        /** @var $indexService \ApacheSolrForTypo3\Solr\Domain\Index\IndexService */
        $indexService = $this->getInitializedIndexService($site);

        return $indexService->getProgress();
    }

    /**
     * @return mixed
     */
    public function getDocumentsToIndexLimit()
    {
        return $this->documentsToIndexLimit;
    }

    /**
     * @param int $limit
     */
    public function setDocumentsToIndexLimit($limit)
    {
        $this->documentsToIndexLimit = $limit;
    }

    /**
     * @param string $forcedWebRoot
     */
    public function setForcedWebRoot($forcedWebRoot)
    {
        $this->forcedWebRoot = $forcedWebRoot;
    }

    /**
     * @return string
     */
    public function getForcedWebRoot()
    {
        return $this->forcedWebRoot;
    }

    /**
     * Returns the initialize IndexService instance.
     *
     * @param Site $site
     * @return IndexService
     * @internal param $Site
     */
    protected function getInitializedIndexService(Site $site)
    {
        $indexService = GeneralUtility::makeInstance(IndexService::class, /** @scrutinizer ignore-type */ $site);
        $indexService->setContextTask($this);
        return $indexService;
    }
}
