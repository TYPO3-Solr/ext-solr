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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Report;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Report\SiteHandlingStatus;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Site\Entity\Site as Typo3Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

class SiteHandlingStatusTest extends SetUpUnitTestCase
{
    /**
     * Creates a SiteHandlingStatus instance with mocked dependencies.
     * Uses anonymous subclass to override getRenderedReport() and avoid Fluid template rendering.
     *
     * @param Site[] $sites
     */
    protected function createSiteHandlingStatusWithSites(array $sites): SiteHandlingStatus
    {
        $viewFactoryMock = $this->createMock(ViewFactoryInterface::class);
        $extensionConfig = $this->createMock(ExtensionConfiguration::class);

        $siteRepositoryMock = $this->createMock(SiteRepository::class);
        $siteRepositoryMock->method('hasAvailableSites')->willReturn(!empty($sites));
        $siteRepositoryMock->method('getAvailableSites')->willReturn($sites);

        return new class ($viewFactoryMock, $extensionConfig, $siteRepositoryMock) extends SiteHandlingStatus {
            protected function getRenderedReport(string $templateFilename = '', array $variables = []): string
            {
                return json_encode($variables['validationResults'] ?? []);
            }
        };
    }

    /**
     * Creates a mock EXT:solr Site wrapping a mock TYPO3 Site with the given languages.
     *
     * @param SiteLanguage[] $languages
     */
    protected function createSiteMock(string $identifier, array $languages): Site
    {
        $typo3Site = $this->createMock(Typo3Site::class);
        $typo3Site->method('getIdentifier')->willReturn($identifier);
        $typo3Site->method('getAllLanguages')->willReturn($languages);

        $site = $this->createMock(Site::class);
        $site->method('getTypo3SiteObject')->willReturn($typo3Site);

        return $site;
    }

    protected function createLanguageMock(string $title, string $baseUrl, bool $enabled = true): SiteLanguage
    {
        $parsed = parse_url($baseUrl);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getScheme')->willReturn($parsed['scheme'] ?? '');
        $uri->method('getHost')->willReturn($parsed['host'] ?? '');
        $uri->method('__toString')->willReturn($baseUrl);

        $lang = $this->createMock(SiteLanguage::class);
        $lang->method('getTitle')->willReturn($title);
        $lang->method('getBase')->willReturn($uri);
        $lang->method('isEnabled')->willReturn($enabled);

        return $lang;
    }

    #[Test]
    public function reportsWarningSeverityWhenNoSitesExist(): void
    {
        $status = $this->createSiteHandlingStatusWithSites([]);
        $results = $status->getStatus();

        self::assertCount(1, $results);
        self::assertSame(ContextualFeedbackSeverity::WARNING, $results[0]->getSeverity());
    }

    #[Test]
    public function allStatusChecksShouldBeOkForValidSites(): void
    {
        $sites = [
            $this->createSiteMock('site_one', [
                $this->createLanguageMock('English', 'http://testone.site/en/'),
                $this->createLanguageMock('German', 'http://testone.site/de/'),
            ]),
        ];

        $status = $this->createSiteHandlingStatusWithSites($sites);
        $results = $status->getStatus();

        self::assertCount(1, $results);
        self::assertSame(ContextualFeedbackSeverity::OK, $results[0]->getSeverity());

        $validationResults = json_decode($results[0]->getMessage(), true);
        self::assertTrue($validationResults['English']['passed']);
        self::assertTrue($validationResults['German']['passed']);
    }

    #[Test]
    public function statusCheckShouldFailIfSchemeIsNotDefined(): void
    {
        $sites = [
            $this->createSiteMock('site_one', [
                $this->createLanguageMock('English', 'authorityOnly.example.com'),
            ]),
        ];

        $status = $this->createSiteHandlingStatusWithSites($sites);
        $results = $status->getStatus();

        self::assertCount(1, $results);
        self::assertSame(ContextualFeedbackSeverity::ERROR, $results[0]->getSeverity());

        $validationResults = json_decode($results[0]->getMessage(), true);
        self::assertFalse($validationResults['English']['passed']);
        self::assertStringContainsString('scheme', $validationResults['English']['message']);
    }

    #[Test]
    public function statusCheckShouldFailIfAuthorityIsNotDefined(): void
    {
        $sites = [
            $this->createSiteMock('site_one', [
                $this->createLanguageMock('English', '/'),
            ]),
        ];

        $status = $this->createSiteHandlingStatusWithSites($sites);
        $results = $status->getStatus();

        self::assertCount(1, $results);
        self::assertSame(ContextualFeedbackSeverity::ERROR, $results[0]->getSeverity());

        $validationResults = json_decode($results[0]->getMessage(), true);
        self::assertFalse($validationResults['English']['passed']);
        self::assertStringContainsString('scheme, host', $validationResults['English']['message']);
    }

    #[Test]
    public function statusCheckShouldFailIfBaseIsSetWrongInLanguages(): void
    {
        $sites = [
            $this->createSiteMock('site_one', [
                $this->createLanguageMock('English', 'http://testone.site/en/'),
                $this->createLanguageMock('German', 'authorityOnly.example.com'),
            ]),
        ];

        $status = $this->createSiteHandlingStatusWithSites($sites);
        $results = $status->getStatus();

        self::assertCount(1, $results);
        self::assertSame(ContextualFeedbackSeverity::ERROR, $results[0]->getSeverity());

        $validationResults = json_decode($results[0]->getMessage(), true);
        // English should pass, German should fail
        self::assertTrue($validationResults['English']['passed']);
        self::assertFalse($validationResults['German']['passed']);
        self::assertStringContainsString('scheme', $validationResults['German']['message']);
    }

    #[Test]
    public function disabledLanguagesAreSkippedInValidation(): void
    {
        $sites = [
            $this->createSiteMock('site_one', [
                $this->createLanguageMock('English', 'http://testone.site/en/'),
                $this->createLanguageMock('Disabled', 'invalidUrl', false),
            ]),
        ];

        $status = $this->createSiteHandlingStatusWithSites($sites);
        $results = $status->getStatus();

        self::assertCount(1, $results);
        // Disabled language with invalid URL should not cause ERROR
        self::assertSame(ContextualFeedbackSeverity::OK, $results[0]->getSeverity());

        $validationResults = json_decode($results[0]->getMessage(), true);
        self::assertTrue($validationResults['Disabled']['passed']);
        self::assertStringContainsString('disabled', $validationResults['Disabled']['message']);
    }
}
