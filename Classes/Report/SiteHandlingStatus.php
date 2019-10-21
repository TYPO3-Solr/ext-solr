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

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Typo3ManagedSite;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Url\UrlHelper;
use Exception;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Site\Entity\Site as Typo3Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
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

    const
        CSS_STATUS_NOTICE = 'notice',
        CSS_STATUS_INFO = 'info',
        CSS_STATUS_OK = 'success',
        CSS_STATUS_WARNING = 'warning',
        CSS_STATUS_ERROR = 'danger';

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
                /** @scrutinizer ignore-type */ 'The lagacy site mode is enabled. This setting is global and can not be applied per site.',
                /** @scrutinizer ignore-type */ 'The legacy site mode is not recommended and will be removed in EXT:Solr 11. Please switch to site handling as soon as possible.',
                /** @scrutinizer ignore-type */ Status::WARNING
            );
            return $reports;
        }

        /* @var Site $site */
        foreach ($this->siteRepository->getAvailableSites() as $site) {
            if (!($site instanceof Typo3ManagedSite)) {
                $reports[] = GeneralUtility::makeInstance(
                    Status::class,
                    /** @scrutinizer ignore-type */ self::TITLE_SITE_HANDLING_CONFIGURATION,
                    /** @scrutinizer ignore-type */ 'Something went wrong',
                    /** @scrutinizer ignore-type */ vsprintf('The configured Site "%s" is not TYPO3 managed site. Please refer to TYPO3 site management docs and configure the site properly.', [$site->getLabel()]),
                    /** @scrutinizer ignore-type */ Status::ERROR
                );
                continue;
            }
            $reports[] = $this->generateValidationReportForSingleSite($site->getTypo3SiteObject());
        }

        return $reports;
    }

    /**
     * Renders validation results for desired typo3 site configuration.
     *
     * @param Typo3Site $ypo3Site
     * @return Status
     */
    protected function generateValidationReportForSingleSite(Typo3Site $ypo3Site): Status
    {
        $variables = [
            'identifier' => $ypo3Site->getIdentifier()
        ];
        $globalPassedStateForThisSite = true;

        foreach ($ypo3Site->getAllLanguages() as $siteLanguage) {
            if (!$siteLanguage->isEnabled()) {
                $variables['validationResults'][$siteLanguage->getTitle()] = [
                    'label' => 'Language: ' . $siteLanguage->getTitle(),
                    'message' => 'No checks: The language is disabled in site configuration.',
                    'CSSClassesFor' => [
                        'tr' => self::CSS_STATUS_NOTICE
                    ],
                    'passed' => true
                ];
                continue;
            }
            $variables['validationResults'][$siteLanguage->getTitle()] = $this->generateValidationResultsForSingleSiteLanguage($siteLanguage);
            $globalPassedStateForThisSite = $globalPassedStateForThisSite && $variables['validationResults'][$siteLanguage->getTitle()]['passed'];
        }

        $renderedReport = $this->getRenderedReport('SiteHandlingStatus.html', $variables);
        /* @var Status $status */
        $status = GeneralUtility::makeInstance(
            Status::class,
            /** @scrutinizer ignore-type */ sprintf('Site Identifier: "%s"', $ypo3Site->getIdentifier()),
            /** @scrutinizer ignore-type */ '',
            /** @scrutinizer ignore-type */ $renderedReport,
            /** @scrutinizer ignore-type */ $globalPassedStateForThisSite == true ? Status::OK : Status::ERROR
        );
        return $status;
    }

    /**
     * Generates the validation result array for using them in standalone view as an table row.
     *
     * @param SiteLanguage $siteLanguage
     * @return array
     */
    protected function generateValidationResultsForSingleSiteLanguage(SiteLanguage $siteLanguage): array
    {
        $validationResult = [
            'label' => 'Language: ' . $siteLanguage->getTitle(),
            'passed' => true,
            'CSSClassesFor' => [
                'tr' => self::CSS_STATUS_OK
            ]
        ];

        if (!GeneralUtility::isValidUrl((string)$siteLanguage->getBase())) {
            $validationResult['message'] = sprintf('Entry Point[base]="%s" is not valid URL. Following parts of defined URL are empty or invalid: "%s"', (string)$siteLanguage->getBase(), $this->fetchInvalidPartsOfUri($siteLanguage->getBase()));
            $validationResult['passed'] = false;
            $validationResult['CSSClassesFor']['tr'] = self::CSS_STATUS_ERROR;
        } else {
            $validationResult['message'] = sprintf('Entry Point[base]="%s" is valid URL.', (string)$siteLanguage->getBase());
        }

        return $validationResult;
    }

    /**
     * @param UriInterface $uri
     * @return string
     */
    protected function fetchInvalidPartsOfUri(UriInterface $uri): string
    {
        $invalidParts = '';
        /* @var UrlHelper $solrUriHelper */
        $solrUriHelper = GeneralUtility::makeInstance(UrlHelper::class, $uri);
        try {
            $solrUriHelper->getScheme();
        } catch (\TypeError $error) {
            $invalidParts .= 'scheme';
        }

        try {
            $solrUriHelper->getHost();
        } catch (\TypeError $error) {
            $invalidParts .= ', host';
        }

        return $invalidParts;
    }
}
