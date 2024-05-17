<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\FrontendEnvironment;

use ApacheSolrForTypo3\Solr\FrontendEnvironment\Tsfe;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class TsfeTest extends IntegrationTestBase
{
    #[Test]
    public function canInitializeTsfeForPageWithDifferentFeGroupsSettings(): void
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
