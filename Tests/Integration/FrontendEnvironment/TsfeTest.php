<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\FrontendEnvironment;

use ApacheSolrForTypo3\Solr\FrontendEnvironment\Tsfe;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use RuntimeException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class TsfeTest extends IntegrationTest
{
    /**
     * @test
     */
    public function initializeTsfeWithNoDefaultPageAndPageErrorHandlerDoNotThrowAnError()
    {
        self::markTestSkipped('Since TSFE is isolated/capsuled, no exceptions are thrown or delegated to else where.
        Other scenario is wanted for:
        https://github.com/TYPO3-Solr/ext-solr/issues/2914
        https://github.com/TYPO3-Solr/ext-solr/pull/2915/files');
        $this->expectException(RuntimeException::class);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/initialize_tsfe_with_no_default_page_and_page_error_handler_do_not_throw_an_error.csv');

        $defaultLanguage = $this->buildDefaultLanguageConfiguration('EN', '/en/');
        $defaultLanguage['solr_core_read'] = 'core_en';

        $this->writeSiteConfiguration(
            'integration_tree_one',
            $this->buildSiteConfiguration(1, 'http://testone.site/'),
            [
                $defaultLanguage,
            ],
            $this->buildErrorHandlingConfiguration('Page', [404])
        );

        $scheme = 'http';
        $host = 'localhost';
        $port = 8999;
        $globalSolrSettings = [
            'solr_scheme_read' => $scheme,
            'solr_host_read' => $host,
            'solr_port_read' => $port,
            'solr_timeout_read' => 20,
            'solr_path_read' => '/solr/',
            'solr_use_write_connection' => false,
        ];
        $this->mergeSiteConfiguration('integration_tree_one', $globalSolrSettings);
        clearstatcache();
        usleep(500);
        $siteCreatedHash = md5($scheme . $host . $port . '0-PageErrorHandler');
        self::$lastSiteCreated = $siteCreatedHash;

        $tsfeManager = GeneralUtility::makeInstance(Tsfe::class);
        $tsfeManager->getTsfeByPageIdAndLanguageId(1);
    }

    /**
     * @test
     */
    public function canInitializeTsfeForPageWithDifferentFeGroupsSettings()
    {
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_initialize_tsfe_for_page_with_different_fe_groups_settings.csv');

        $tsfeNotRestricted = GeneralUtility::makeInstance(Tsfe::class)->getTsfeByPageIdIgnoringLanguage(1);
        self::assertInstanceOf(
            TypoScriptFrontendController::class,
            $tsfeNotRestricted,
            'The TSFE can not be initialized at all, nor for public page either for access restricted(fe_group) page. ' .
                'Most probably nothing will work.'
        );

        $tsfeRestrictedForExistingFeGroup = GeneralUtility::makeInstance(Tsfe::class)->getTsfeByPageIdIgnoringLanguage(2);
        self::assertInstanceOf(
            TypoScriptFrontendController::class,
            $tsfeRestrictedForExistingFeGroup,
            'The TSFE can not be initialized for existing fe_group. ' .
                'This will lead to failures on editing the access restricted [sub]pages in BE.'
        );

        $tsfeForLoggedInUserOnly = GeneralUtility::makeInstance(Tsfe::class)->getTsfeByPageIdIgnoringLanguage(3);
        self::assertInstanceOf(
            TypoScriptFrontendController::class,
            $tsfeForLoggedInUserOnly,
            'The TSFE can not be initialized for page with fe_group="-2". ' .
                'This will lead to failures on editing the [sub]pages in BE for pages with fe_group="-2".'
        );
    }
}
