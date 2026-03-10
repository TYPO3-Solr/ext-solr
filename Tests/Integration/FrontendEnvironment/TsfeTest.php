<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\FrontendEnvironment;

use ApacheSolrForTypo3\Solr\FrontendSimulation\FrontendAwareEnvironment;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TsfeTest extends IntegrationTestBase
{
    #[Test]
    public function canInitializeFrontendEnvironmentForPageWithDifferentFeGroupsSettings(): void
    {
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_initialize_tsfe_for_page_with_different_fe_groups_settings.csv');

        $frontendAwareEnvironment = new FrontendAwareEnvironment(
            GeneralUtility::makeInstance(SiteFinder::class),
            GeneralUtility::makeInstance(ConfigurationManager::class),
        );

        $requestNotRestricted = $frontendAwareEnvironment->getServerRequestByPageIdIgnoringLanguage(1);
        self::assertInstanceOf(
            ServerRequest::class,
            $requestNotRestricted,
            'The ServerRequest can not be initialized at all, nor for public page either for access restricted(fe_group) page. ' .
                'Most probably nothing will work.',
        );

        $requestRestrictedForExistingFeGroup = $frontendAwareEnvironment->getServerRequestByPageIdIgnoringLanguage(2);
        self::assertInstanceOf(
            ServerRequest::class,
            $requestRestrictedForExistingFeGroup,
            'The ServerRequest can not be initialized for existing fe_group. ' .
                'This will lead to failures on editing the access restricted [sub]pages in BE.',
        );

        $requestForLoggedInUserOnly = $frontendAwareEnvironment->getServerRequestByPageIdIgnoringLanguage(3);
        self::assertInstanceOf(
            ServerRequest::class,
            $requestForLoggedInUserOnly,
            'The ServerRequest can not be initialized for page with fe_group="-2". ' .
                'This will lead to failures on editing the [sub]pages in BE for pages with fe_group="-2".',
        );
    }
}
