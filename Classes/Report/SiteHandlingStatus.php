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
                $this->translate('status.siteHandling.configuration.title'),
                $this->translate('status.value.noSitesFound'),
                '',
                ContextualFeedbackSeverity::WARNING,
            );

            return $reports;
        }

        foreach ($this->siteRepository->getAvailableSites() as $site) {
            if (!($site instanceof Site)) {
                $reports[] = GeneralUtility::makeInstance(
                    Status::class,
                    $this->translate('status.siteHandling.configuration.title'),
                    $this->translate('status.value.error'),
                    $this->translate('status.siteHandling.notTypo3Site.message', ['site' => $site->getLabel()]),
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
                    'label' => $this->translate('status.siteHandling.language.label', ['language' => $siteLanguage->getTitle()]),
                    'message' => $this->translate('status.siteHandling.language.disabled.message'),
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
            $this->translate('status.siteHandling.identifier.title', ['identifier' => $ypo3Site->getIdentifier()]),
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
            'label' => $this->translate('status.siteHandling.language.label', ['language' => $siteLanguage->getTitle()]),
            'passed' => true,
            'CSSClassesFor' => [
                'tr' => self::CSS_STATUS_OK,
            ],
        ];

        if (!GeneralUtility::isValidUrl((string)$siteLanguage->getBase())) {
            $validationResult['message'] = $this->translate('status.siteHandling.base.invalid.message', [
                'base' => $siteLanguage->getBase()->__toString(),
                'parts' => $this->fetchInvalidPartsOfUri($siteLanguage->getBase()),
            ]);
            $validationResult['passed'] = false;
            $validationResult['CSSClassesFor']['tr'] = self::CSS_STATUS_ERROR;
        } else {
            $validationResult['message'] = $this->translate('status.siteHandling.base.valid.message', [
                'base' => $siteLanguage->getBase()->__toString(),
            ]);
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
            $invalidParts[] = $this->translate('status.siteHandling.urlPart.scheme');
        }
        if (empty($uri->getHost())) {
            $invalidParts[] = $this->translate('status.siteHandling.urlPart.host');
        }

        return implode(', ', $invalidParts);
    }
}
