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

use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Site\Entity\Site as Typo3Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
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
    public const TITLE_SITE_HANDLING_CONFIGURATION = 'Site handling configuration';

    public const
        CSS_STATUS_NOTICE = 'notice',
        CSS_STATUS_INFO = 'info',
        CSS_STATUS_OK = 'success',
        CSS_STATUS_WARNING = 'warning',
        CSS_STATUS_ERROR = 'danger';

    protected SiteRepository $siteRepository;

    protected ExtensionConfiguration $extensionConfiguration;

    public function __construct(
        ViewFactoryInterface $viewFactory,
        ?ExtensionConfiguration $extensionConfiguration = null,
        ?SiteRepository $siteRepository = null,
    ) {
        parent::__construct($viewFactory);
        $this->extensionConfiguration = $extensionConfiguration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->siteRepository = $siteRepository ?? GeneralUtility::makeInstance(SiteRepository::class);
    }

    /**
     * Verifies the site configuration.
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getStatus(): array
    {
        $reports = [];
        if (!$this->siteRepository->hasAvailableSites()) {
            $reports[] = GeneralUtility::makeInstance(
                Status::class,
                self::TITLE_SITE_HANDLING_CONFIGURATION,
                'No sites found',
                '',
                ContextualFeedbackSeverity::WARNING,
            );

            return $reports;
        }

        foreach ($this->siteRepository->getAvailableSites() as $site) {
            if (!($site instanceof Site)) {
                $reports[] = GeneralUtility::makeInstance(
                    Status::class,
                    self::TITLE_SITE_HANDLING_CONFIGURATION,
                    'Something went wrong',
                    vsprintf('The configured Site "%s" is not TYPO3 managed site. Please refer to TYPO3 site management docs and configure the site properly.', [$site->getLabel()]),
                    ContextualFeedbackSeverity::ERROR,
                );
                continue;
            }
            $reports[] = $this->generateValidationReportForSingleSite($site->getTypo3SiteObject());
        }

        return $reports;
    }

    /**
     * Renders validation results for desired typo3 site configuration.
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
        return GeneralUtility::makeInstance(
            Status::class,
            sprintf('Site Identifier: "%s"', $ypo3Site->getIdentifier()),
            '',
            $renderedReport,
            $globalPassedStateForThisSite ? ContextualFeedbackSeverity::OK : ContextualFeedbackSeverity::ERROR,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getLabel(): string
    {
        return 'LLL:EXT:solr/Resources/Private/Language/locallang_reports.xlf:status_solr_site-handling';
    }

    /**
     * Generates the validation result array for using them in standalone view as a table row.
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
                    $this->fetchInvalidPartsOfUri($siteLanguage->getBase()),
                );
            $validationResult['passed'] = false;
            $validationResult['CSSClassesFor']['tr'] = self::CSS_STATUS_ERROR;
        } else {
            $validationResult['message'] = sprintf(
                'Entry Point[base]="%s" is valid URL.',
                $siteLanguage->getBase()->__toString(),
            );
        }

        return $validationResult;
    }

    /**
     * Fetches the invalid parts of given URI.
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
