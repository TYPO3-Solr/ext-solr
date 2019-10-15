<?php
namespace ApacheSolrForTypo3\Solr\Report;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019- dkd Internet Services GmbH (info@dkd.de)
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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Typo3ManagedSite;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use Exception;
use TYPO3\CMS\Core\Site\Entity\Site as Typo3Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides an status report about current state of site handling configurations.
 *
 * Following thigs are checked currently:
 * * Entry Point[base] scheme expects -> http[s]
 * * Entry Point[base] authority expects -> [user-info@]host[:port]
 */
class SiteHandlingStatus extends AbstractSolrStatus
{
    const TITLE_SITE_HANDLING_CONFIGURATION = 'Site handling configuration';

    /**
     * Site Repository
     *
     * @var SiteRepository
     */
    protected $siteRepository = null;

    /**
     * @var ExtensionConfiguration
     */
    protected $extensionConfiguration = null;

    /**
     * SolrStatus constructor.
     * @param ExtensionConfiguration $extensionConfiguration
     * @param SiteRepository|null $siteRepository
     */
    public function __construct(ExtensionConfiguration $extensionConfiguration = null, SiteRepository $siteRepository = null)
    {
        $this->extensionConfiguration = $extensionConfiguration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->siteRepository = $siteRepository ?? GeneralUtility::makeInstance(SiteRepository::class);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getStatus()
    {
        $reports = [];

        if ($this->extensionConfiguration->getIsAllowLegacySiteModeEnabled()) {
            $reports[] = GeneralUtility::makeInstance(
                Status::class,
                /** @scrutinizer ignore-type */ self::TITLE_SITE_HANDLING_CONFIGURATION,
                /** @scrutinizer ignore-type */ 'The lagacy site mode is enabled.',
                /** @scrutinizer ignore-type */ 'The legacy site mode is not recommended and will be removed in EXT:Solr 11. Please switch to site handling as soon as possible.',
                /** @scrutinizer ignore-type */ Status::WARNING
            );
            return $reports;
        }

        foreach ($this->siteRepository->getAvailableSites() as $site) {
            if (!($site instanceof Typo3ManagedSite)) {
                $reports[] = GeneralUtility::makeInstance(
                    Status::class,
                    /** @scrutinizer ignore-type */ self::TITLE_SITE_HANDLING_CONFIGURATION,
                    /** @scrutinizer ignore-type */ 'Something went wrong',
                    /** @scrutinizer ignore-type */ vsprintf('The configured Site "%s" is not TYPO3 managed site. Please refer to TYPO3 site management docs and configure the site properly.', [$site->getLabel()]),
                    /** @scrutinizer ignore-type */ Status::ERROR
                );
                return $reports;
            }

            $reports[] = $this->generateValidationReportForSiteHandlingConfiguration($site->getTypo3SiteObject());
        }

        return $reports;
    }

    /**
     * Renders validation results for desired typo3 site configuration.
     *
     * @param Typo3Site $ypo3Site
     * @return Status
     */
    protected function generateValidationReportForSiteHandlingConfiguration(Typo3Site $ypo3Site): Status
    {
        $variables = [
            'identifier' => $ypo3Site->getIdentifier()
        ];
        $globalPassedStateForThisSite = true;

        // check scheme of base URI
        $schemeValidationStatus = !empty($ypo3Site->getBase()->getScheme()) && (strpos('http', $ypo3Site->getBase()->getScheme()) !== false);
        $variables['validationResults']['scheme'] = [
            'label' => 'Requirement: Valid scheme in Entry Point[base].',
            'message' => 'Entry Point[base] must contain valid HTTP scheme as http[s]:// to be able to index records.',
            'passed' => $schemeValidationStatus
        ];
        $globalPassedStateForThisSite = $globalPassedStateForThisSite && $schemeValidationStatus;

        // check authority of base URI
        $authorityValidationStatus = !empty($ypo3Site->getBase()->getAuthority());
        $variables['validationResults']['authority'] = [
            'label' => 'Requirement: Valid Authority in Entry Point[base].',
            'message' => 'Entry Point[base] must define the authority as [user-info@]host[:port] to be able to index records.',
            'passed' => $authorityValidationStatus
        ];
        $globalPassedStateForThisSite = $globalPassedStateForThisSite && $authorityValidationStatus;

        $renderedReport = $this->getRenderedReport('SiteHandlingStatus.html', $variables);
        /* @var Status $status */
        $status = GeneralUtility::makeInstance(
            Status::class,
            /** @scrutinizer ignore-type */ 'Site Identifier: ' . $ypo3Site->getIdentifier(),
            /** @scrutinizer ignore-type */ '',
            /** @scrutinizer ignore-type */ $renderedReport,
            /** @scrutinizer ignore-type */ $globalPassedStateForThisSite == true ? Status::OK : Status::ERROR
        );
        return $status;
    }
}
