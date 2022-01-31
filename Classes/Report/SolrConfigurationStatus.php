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

use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use RuntimeException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
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
     * @var ExtensionConfiguration
     */
    protected $extensionConfiguration;

    /**
     * @var FrontendEnvironment
     */
    protected $frontendEnvironment = null;

    /**
     * SolrConfigurationStatus constructor.
     * @param ExtensionConfiguration|null $extensionConfiguration
     * @param FrontendEnvironment|null $frontendEnvironment

     */
    public function __construct(
        ExtensionConfiguration $extensionConfiguration = null,
        FrontendEnvironment $frontendEnvironment = null
    )
    {
        $this->extensionConfiguration = $extensionConfiguration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->frontendEnvironment = $frontendEnvironment ?? GeneralUtility::makeInstance(FrontendEnvironment::class);
    }

    /**
     * Compiles a collection of configuration status checks.
     *
     * @return array
     * @throws ImmediateResponseException
     */
    public function getStatus(): array
    {
        $reports = [];

        $rootPageFlagStatus = $this->getRootPageFlagStatus();
        if (!is_null($rootPageFlagStatus)) {
            $reports[] = $rootPageFlagStatus;

            // intended early return, no sense in going on if there are no root pages
            return $reports;
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
    protected function getRootPageFlagStatus(): ?Status
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
     * Checks whether config.index_enable is set to 1, otherwise indexing will
     * not work.
     *
     * @return NULL|Status An error status is returned for each site root page config.index_enable = 0.
     * @throws ImmediateResponseException
     */
    protected function getConfigIndexEnableStatus(): ?Status
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
     * Returns an array of rootPages where the indexing is off and EXT:solr is enabled.
     *
     * @return array
     * @throws ImmediateResponseException
     */
    protected function getRootPagesWithIndexingOff(): array
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
            } catch (RuntimeException $rte) {
                $rootPagesWithIndexingOff[] = $rootPage;
            } catch (ServiceUnavailableException $sue) {
                if ($sue->getCode() == 1294587218) {
                    //  No TypoScript template found, continue with next site
                    $rootPagesWithIndexingOff[] = $rootPage;
                    continue;
                }
            } catch (SiteNotFoundException $sue) {
                if ($sue->getCode() == 1521716622) {
                    //  No site found, continue with next site
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
    protected function getIsSolrEnabled(): bool
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
    protected function getIsIndexingEnabled(): bool
    {
        if (empty($GLOBALS['TSFE']->config['config']['index_enable'])) {
            return false;
        }

        return (bool)$GLOBALS['TSFE']->config['config']['index_enable'];
    }

    /**
     * Initializes TSFE via FrontendEnvironment.
     *
     * Purpose: Unit test mocking helper method.
     *
     * @param array $rootPageRecord
     * @throws ImmediateResponseException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     */
    protected function initializeTSFE(array $rootPageRecord)
    {
        $this->frontendEnvironment->initializeTsfe($rootPageRecord['uid']);
    }
}
