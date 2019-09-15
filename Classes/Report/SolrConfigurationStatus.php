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

use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\Records\SystemDomain\SystemDomainRepository;
use ApacheSolrForTypo3\Solr\System\Service\SiteService;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides an status report, which checks whether the configuration of the
 * extension is ok.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class SolrConfigurationStatus extends AbstractSolrStatus
{
    /**
     * @var SystemDomainRepository
     */
    protected $systemDomainRepository;

    /**
     * @var ExtensionConfiguration
     */
    protected $extensionConfiguration;

    /**
     * SolrConfigurationStatus constructor.
     * @param SystemDomainRepository|null $systemDomainRepository
     * @param ExtensionConfiguration|null $extensionConfiguration
     */
    public function __construct(SystemDomainRepository $systemDomainRepository = null, ExtensionConfiguration $extensionConfiguration = null)
    {
        $this->systemDomainRepository = $systemDomainRepository ?? GeneralUtility::makeInstance(SystemDomainRepository::class);
        $this->extensionConfiguration = $extensionConfiguration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
    }

    /**
     * Compiles a collection of configuration status checks.
     *
     * @return array
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
        $rootPages = $this->getRootPages();
        if (!empty($rootPages)) {
            return null;
        }

        $report = $this->getRenderedReport('RootPageFlagStatus.html');
        return GeneralUtility::makeInstance(
            Status::class,
            /** @scrutinizer ignore-type */ 'Sites',
            /** @scrutinizer ignore-type */ 'No sites found',
            /** @scrutinizer ignore-type */ $report,
            /** @scrutinizer ignore-type */ Status::ERROR
        );
    }

    /**
     * Checks whether a domain record (sys_domain) has been configured for each site root.
     *
     * @return NULL|Status An error status is returned for each site root page without domain record.
     */
    protected function getDomainRecordAvailableStatus()
    {
        // @deprecated we can drop that check when the legacy site mode is dropped
        if (!$this->extensionConfiguration->getIsAllowLegacySiteModeEnabled()) {
            // when no legacy mode is enabled we do not need to check for domain record
            return null;
        }
        $rootPagesWithoutDomain = $this->getRootPagesWithoutDomain();
        if (empty($rootPagesWithoutDomain)) {
            return null;
        }

        $report = $this->getRenderedReport('SolrConfigurationStatusDomainRecord.html', ['pages' => $rootPagesWithoutDomain]);
        return GeneralUtility::makeInstance(
            Status::class,
            /** @scrutinizer ignore-type */ 'Domain Records',
            /** @scrutinizer ignore-type */ 'Domain records missing',
            /** @scrutinizer ignore-type */ $report,
            /** @scrutinizer ignore-type */ Status::ERROR
        );
    }

    /**
     * Checks whether config.index_enable is set to 1, otherwise indexing will
     * not work.
     *
     * @return NULL|Status An error status is returned for each site root page config.index_enable = 0.
     */
    protected function getConfigIndexEnableStatus()
    {
        $rootPagesWithIndexingOff = $this->getRootPagesWithIndexingOff();
        if (empty($rootPagesWithIndexingOff)) {
            return null;
        }

        $report = $this->getRenderedReport('SolrConfigurationStatusIndexing.html', ['pages' => $rootPagesWithIndexingOff]);
        return GeneralUtility::makeInstance(
            Status::class,
            /** @scrutinizer ignore-type */ 'Page Indexing',
            /** @scrutinizer ignore-type */ 'Indexing is disabled',
            /** @scrutinizer ignore-type */ $report,
            /** @scrutinizer ignore-type */ Status::WARNING
        );
    }

    /**
     * Returns an array of rootPages without an existing domain record.
     *
     * @return array
     */
    protected function getRootPagesWithoutDomain()
    {
        $rootPagesWithoutDomain = [];
        $rootPages = $this->getRootPages();

        $rootPageIds = [];
        foreach ($rootPages as $rootPage) {
            $rootPageIds[] = $rootPage['uid'];
        }

        $domainRecords = $this->systemDomainRepository->findDomainRecordsByRootPagesIds($rootPageIds);
        foreach ($rootPageIds as $rootPageId) {
            $hasDomainRecord = true;
            $hasDomainInTypoScript = true;

            if (!array_key_exists($rootPageId, $domainRecords)) {
                $hasDomainRecord = false;
            }

            /** @var $siteService SiteService */
            $siteService = GeneralUtility::makeInstance(SiteService::class);
            $domain = $siteService->getFirstDomainForRootPage($rootPageId);
            if ($domain === '') {
                $hasDomainInTypoScript = false;
            }

            if (!$hasDomainRecord && !$hasDomainInTypoScript) {
                $rootPagesWithoutDomain[$rootPageId] = $rootPages[$rootPageId];
            }
        }
        return $rootPagesWithoutDomain;
    }

    /**
     * Returns an array of rootPages where the indexing is off and EXT:solr is enabled.
     *
     * @return array
     */
    protected function getRootPagesWithIndexingOff()
    {
        $rootPages = $this->getRootPages();
        $rootPagesWithIndexingOff = [];

        foreach ($rootPages as $rootPage) {
            try {
                $this->initializeTSFE($rootPage);
                $solrIsEnabledAndIndexingDisabled = $this->getIsSolrEnabled() && !$this->getIsIndexingEnabled();
                if ($solrIsEnabledAndIndexingDisabled) {
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

        return $rootPagesWithIndexingOff;
    }

    /**
     * Gets the site's root pages. The "Is root of website" flag must be set,
     * which usually is the case for pages with pid = 0.
     *
     * @return array An array of (partial) root page records, containing the uid and title fields
     */
    protected function getRootPages()
    {
        $pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);

        return $pagesRepository->findAllRootPages();
    }

    /**
     * Checks if the solr plugin is enabled with plugin.tx_solr.enabled.
     *
     * @return bool
     */
    protected function getIsSolrEnabled()
    {
        if (empty($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['enabled'])) {
            return false;
        }
        return (bool)$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['enabled'];
    }

    /**
     * Checks if the indexing is enabled with config.index_enable
     *
     * @return bool
     */
    protected function getIsIndexingEnabled()
    {
        if (empty($GLOBALS['TSFE']->config['config']['index_enable'])) {
            return false;
        }

        return (bool)$GLOBALS['TSFE']->config['config']['index_enable'];
    }

    /**
     * @param $rootPage
     */
    protected function initializeTSFE($rootPage)
    {
        Util::initializeTsfe($rootPage['uid']);
    }
}
