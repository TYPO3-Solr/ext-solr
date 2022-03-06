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

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Psr\Http\Message\UriInterface;
use Throwable;
use TYPO3\CMS\Core\Site\Entity\Site as Typo3Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Provides a status report about current state of site handling configurations.
 *
 * Following things are checked currently:
 * * Entry Point[base] scheme expects -> http[s]
 * * Entry Point[base] authority expects -> [user-info@]host[:port]
 */
class SiteHandlingStatus extends AbstractSolrStatus
{
    const TITLE_SITE_HANDLING_CONFIGURATION = 'Site handling configuration';

    /**
     * @var string
     */
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
    protected $siteRepository;

    /**
     * @var ExtensionConfiguration
     */
    protected $extensionConfiguration;

    /**
     * SolrStatus constructor.
     * @param ExtensionConfiguration|null $extensionConfiguration
     * @param SiteRepository|null $siteRepository
     */
    public function __construct(
        ExtensionConfiguration $extensionConfiguration = null,
        SiteRepository $siteRepository = null
    ) {
        $this->extensionConfiguration = $extensionConfiguration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->siteRepository = $siteRepository ?? GeneralUtility::makeInstance(SiteRepository::class);
    }

    /**
     * @return array
     *
     * @throws DBALDriverException
     * @throws Throwable
     * @noinspection PhpMissingReturnTypeInspection see {@link \TYPO3\CMS\Reports\StatusProviderInterface::getStatus()}
     */
    public function getStatus()
    {
        $reports = [];

        /* @var Site $site */
        foreach ($this->siteRepository->getAvailableSites() as $site) {
            if (!($site instanceof Site)) {
                $reports[] = GeneralUtility::makeInstance(
                    Status::class,
                    /** @scrutinizer ignore-type */
                    self::TITLE_SITE_HANDLING_CONFIGURATION,
                    /** @scrutinizer ignore-type */
                    'Something went wrong',
                    /** @scrutinizer ignore-type */
                    vsprintf('The configured Site "%s" is not TYPO3 managed site. Please refer to TYPO3 site management docs and configure the site properly.', [$site->getLabel()]),
                    /** @scrutinizer ignore-type */
                    Status::ERROR
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
            'identifier' => $ypo3Site->getIdentifier(),
        ];
        $globalPassedStateForThisSite = true;

        foreach ($ypo3Site->getAllLanguages() as $siteLanguage) {
            if (!$siteLanguage->isEnabled()) {
                $variables['validationResults'][$siteLanguage->getTitle()] = [
                    'label' => 'Language: ' . $siteLanguage->getTitle(),
                    'message' => 'No checks: The language is disabled in site configuration.',
                    'CSSClassesFor' => [
                        'tr' => self::CSS_STATUS_NOTICE,
                    ],
                    'passed' => true,
                ];
                continue;
            }
            $variables['validationResults'][$siteLanguage->getTitle()] = $this->generateValidationResultsForSingleSiteLanguage($siteLanguage);
            $globalPassedStateForThisSite = $globalPassedStateForThisSite && $variables['validationResults'][$siteLanguage->getTitle()]['passed'];
        }

        $renderedReport = $this->getRenderedReport('SiteHandlingStatus.html', $variables);
        /* @var Status $status */
        return GeneralUtility::makeInstance(
            Status::class,
            /** @scrutinizer ignore-type */
            sprintf('Site Identifier: "%s"', $ypo3Site->getIdentifier()),
            /** @scrutinizer ignore-type */
            '',
            /** @scrutinizer ignore-type */
            $renderedReport,
            /** @scrutinizer ignore-type */
            $globalPassedStateForThisSite == true ? Status::OK : Status::ERROR
        );
    }

    /**
     * Generates the validation result array for using them in standalone view as a table row.
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
                'tr' => self::CSS_STATUS_OK,
            ],
        ];

        if (!GeneralUtility::isValidUrl((string)$siteLanguage->getBase())) {
            $validationResult['message'] =
                sprintf(
                    'Entry Point[base]="%s" is not valid URL.'
                    . ' Following parts of defined URL are empty or invalid: "%s"',
                    $siteLanguage->getBase()->__toString(),
                    $this->fetchInvalidPartsOfUri($siteLanguage->getBase())
                );
            $validationResult['passed'] = false;
            $validationResult['CSSClassesFor']['tr'] = self::CSS_STATUS_ERROR;
        } else {
            $validationResult['message'] = sprintf(
                'Entry Point[base]="%s" is valid URL.',
                $siteLanguage->getBase()->__toString()
            );
        }

        return $validationResult;
    }

    /**
     * @param UriInterface $uri
     * @return string
     */
    protected function fetchInvalidPartsOfUri(UriInterface $uri): string
    {
        $invalidParts = [];
        if (empty($uri->getScheme())) {
            $invalidParts[] = 'scheme';
        }
        if (empty($uri->getHost())) {
            $invalidParts[] = 'host';
        }

        return implode(', ', $invalidParts);
    }
}
