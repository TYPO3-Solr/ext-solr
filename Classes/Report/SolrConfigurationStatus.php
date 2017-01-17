<?php
namespace ApacheSolrForTypo3\Solr\Report;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;

/**
 * Provides an status report, which checks whether the configuration of the
 * extension is ok.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class SolrConfigurationStatus implements StatusProviderInterface
{

    /**
     * Compiles a collection of configuration status checks.
     *
     */
    public function getStatus()
    {
        $reports = [];

        $rootPageFlagStatus = $this->getRootPageFlagStatus();
        if (!is_null($rootPageFlagStatus)) {
            $reports[] = $rootPageFlagStatus;

            // intended early return, no sense in going on if there are no root pages
            return $reports;
        }

        $domainRecordAvailableStatus = $this->getDomainRecordAvailableStatus();
        if (!is_null($domainRecordAvailableStatus)) {
            $reports[] = $domainRecordAvailableStatus;
        }

        $configIndexEnableStatus = $this->getConfigIndexEnableStatus();
        if (!is_null($configIndexEnableStatus)) {
            $reports[] = $configIndexEnableStatus;
        }

        return $reports;
    }

    /**
     * Checks whether the "Use as Root Page" page property has been set for any
     * site.
     *
     * @return NULL|Status An error status is returned if no root pages were found.
     */
    protected function getRootPageFlagStatus()
    {
        $status = null;
        $rootPages = $this->getRootPages();

        if (empty($rootPages)) {
            $status = GeneralUtility::makeInstance(Status::class,
                'Sites',
                'No sites found',
                'Connections to your Solr server are detected automatically.
				To make this work you need to set the "Use as Root Page" page
				property for your site root pages.',
                Status::ERROR
            );
        }

        return $status;
    }

    /**
     * Checks whether a domain record (sys_domain) has been configured for each site root.
     *
     * @return NULL|Status An error status is returned for each site root page without domain record.
     */
    protected function getDomainRecordAvailableStatus()
    {
        $status = null;
        $rootPages = $this->getRootPages();
        $rootPagesWithoutDomain = [];

        $rootPageIds = [];
        foreach ($rootPages as $rootPage) {
            $rootPageIds[] = $rootPage['uid'];
        }

        $domainRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid, pid',
            'sys_domain',
            'pid IN(' . implode(',',
                $rootPageIds) . ') AND redirectTo=\'\' AND hidden=0',
            'uid, pid, sorting',
            'pid, sorting',
            '',
            'pid'
        );

        foreach ($rootPageIds as $rootPageId) {
            if (!array_key_exists($rootPageId, $domainRecords)) {
                $rootPagesWithoutDomain[$rootPageId] = $rootPages[$rootPageId];
            }
        }

        if (!empty($rootPagesWithoutDomain)) {
            $standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
            $standaloneView->setTemplatePathAndFilename(
                GeneralUtility::getFileAbsFileName('EXT:solr/Resources/Private/Templates/Reports/SolrConfigurationStatusDomainRecord.html')
            );
            $standaloneView->assignMultiple([
                'pages' => $rootPagesWithoutDomain,
            ]);

            $status = GeneralUtility::makeInstance(Status::class,
                'Domain Records',
                'Domain records missing',
                $standaloneView->render(),
                Status::ERROR
            );
        }

        return $status;
    }

    /**
     * Checks whether config.index_enable is set to 1, otherwise indexing will
     * not work.
     *
     * @return NULL|Status An error status is returned for each site root page config.index_enable = 0.
     */
    protected function getConfigIndexEnableStatus()
    {
        $status = null;
        $rootPages = $this->getRootPages();
        $rootPagesWithIndexingOff = [];

        foreach ($rootPages as $rootPage) {
            try {
                Util::initializeTsfe($rootPage['uid']);

                if (!$GLOBALS['TSFE']->config['config']['index_enable']) {
                    $rootPagesWithIndexingOff[] = $rootPage;
                }
            } catch (\RuntimeException $rte) {
                $rootPagesWithIndexingOff[] = $rootPage;
            } catch (ServiceUnavailableException $sue) {
                if ($sue->getCode() == 1294587218) {
                    //  No TypoScript template found, continue with next site
                    $rootPagesWithIndexingOff[] = $rootPage;
                    continue;
                }
            }
        }

        if (!empty($rootPagesWithIndexingOff)) {
            $standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
            $standaloneView->setTemplatePathAndFilename(
                GeneralUtility::getFileAbsFileName('EXT:solr/Resources/Private/Templates/Reports/SolrConfigurationStatusIndexing.html')
            );
            $standaloneView->assignMultiple([
                'pages' => $rootPagesWithIndexingOff,
            ]);

            $status = GeneralUtility::makeInstance(Status::class,
                'Page Indexing',
                'Indexing is disabled',
                $standaloneView->render(),
                Status::ERROR
            );
        }

        return $status;
    }

    /**
     * Gets the site's root pages. The "Is root of website" flag must be set,
     * which usually is the case for pages with pid = 0.
     *
     * @return array An array of (partial) root page records, containing the uid and title fields
     */
    protected function getRootPages()
    {
        $rootPages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid, title',
            'pages',
            'is_siteroot = 1 AND deleted = 0 AND hidden = 0 AND pid != -1 AND doktype IN(1,4) ',
            '', '', '',
            'uid'
        );

        return $rootPages;
    }
}
