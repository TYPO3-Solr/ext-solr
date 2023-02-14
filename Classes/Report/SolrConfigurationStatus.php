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

namespace ApacheSolrForTypo3\Solr\Report;

use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use RuntimeException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report, which checks whether the configuration of the
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
    protected $frontendEnvironment;

    /**
     * SolrConfigurationStatus constructor.
     * @param ExtensionConfiguration|null $extensionConfiguration
     * @param FrontendEnvironment|null $frontendEnvironment
     */
    public function __construct(
        ExtensionConfiguration $extensionConfiguration = null,
        FrontendEnvironment $frontendEnvironment = null
    ) {
        $this->extensionConfiguration = $extensionConfiguration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->frontendEnvironment = $frontendEnvironment ?? GeneralUtility::makeInstance(FrontendEnvironment::class);
    }

    /**
     * Compiles a collection of configuration status checks.
     *
     * @return array
     *
     * @throws DBALDriverException
     *
     * @noinspection PhpMissingReturnTypeInspection see {@link \TYPO3\CMS\Reports\StatusProviderInterface::getStatus()}
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
     * @return Status|null An error status is returned if no root pages were found.
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
            /** @scrutinizer ignore-type */
            'Sites',
            /** @scrutinizer ignore-type */
            'No sites found',
            /** @scrutinizer ignore-type */
            $report,
            /** @scrutinizer ignore-type */
            Status::ERROR
        );
    }

    /**
     * Checks whether config.index_enable is set to 1, otherwise indexing will
     * not work.
     *
     * @return Status|null An error status is returned for each site root page config.index_enable = 0.
     * @throws DBALDriverException
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
            /** @scrutinizer ignore-type */
            'Page Indexing',
            /** @scrutinizer ignore-type */
            'Indexing is disabled',
            /** @scrutinizer ignore-type */
            $report,
            /** @scrutinizer ignore-type */
            Status::WARNING
        );
    }

    /**
     * Returns an array of rootPages where the indexing is off and EXT:solr is enabled.
     *
     * @return array
     * @throws DBALDriverException
     */
    protected function getRootPagesWithIndexingOff(): array
    {
        $rootPages = $this->getRootPages();
        $rootPagesWithIndexingOff = [];

        foreach ($rootPages as $rootPage) {
            try {
                $solrIsEnabledAndIndexingDisabled = $this->getIsSolrEnabled($rootPage['uid']) && !$this->getIsIndexingEnabled($rootPage['uid']);
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
    protected function getRootPages(): array
    {
        $pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);
        return $pagesRepository->findAllRootPages();
    }

    /**
     * Checks if the solr plugin is enabled with plugin.tx_solr.enabled.
     *
     * @param int $pageUid
     * @return bool
     * @throws DBALDriverException
     */
    protected function getIsSolrEnabled(int $pageUid): bool
    {
        return $this->frontendEnvironment->getSolrConfigurationFromPageId($pageUid)->getEnabled();
    }

    /**
     * Checks if the indexing is enabled with config.index_enable
     *
     * @param int $pageUid
     * @return bool
     * @throws DBALDriverException
     */
    protected function getIsIndexingEnabled(int $pageUid): bool
    {
        return (bool)$this->frontendEnvironment
            ->getConfigurationFromPageId($pageUid)
            ->getValueByPathOrDefaultValue('config.index_enable', false);
    }
}
