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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Report;

use ApacheSolrForTypo3\Solr\Report\SiteHandlingStatus;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;

/**
 * Integration test for the site handling status report
 */
class SiteHandlingStatusTest extends IntegrationTestBase
{
    #[Test]
    public function allStatusChecksShouldBeOkForFirstTestSite(): void
    {
        $this->writeDefaultSolrTestSiteConfiguration();

        /** @var SiteHandlingStatus $siteHandlingStatus */
        $siteHandlingStatus = $this->get(SiteHandlingStatus::class);
        $statusCollection = $siteHandlingStatus->getStatus();

        foreach ($statusCollection as $status) {
            /** @var Status $status */
            self::assertSame(ContextualFeedbackSeverity::OK, $status->getSeverity(), 'Expected that all status checks for site handling configuration of first test site should be ok');
        }
    }

    #[Test]
    public function statusCheckShouldFailIfSchemeIsNotDefined(): void
    {
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->mergeSiteConfiguration('integration_tree_one', [
            'base' => 'authorityOnly.example.com',
        ]);
        $this->mergeSiteConfiguration('integration_tree_two', [
            'base' => 'authorityOnly.two.example.com',
        ]);

        /** @var SiteHandlingStatus $siteHandlingStatus */
        $siteHandlingStatus = $this->get(SiteHandlingStatus::class);
        $statusCollection = $siteHandlingStatus->getStatus();

        foreach ($statusCollection as $status) {
            /** @var Status $status */
            self::assertSame(ContextualFeedbackSeverity::ERROR, $status->getSeverity(), 'Expected that status checks for site handling configuration should indicate an error if scheme in "Entry Point[base]" is not defined.');
            self::assertMatchesRegularExpression('~.*are empty or invalid: &quot;scheme&quot;~', $status->getMessage());
        }
    }

    #[Test]
    public function statusCheckShouldFailIfAuthorityIsNotDefined(): void
    {
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->mergeSiteConfiguration('integration_tree_one', [
            'base' => '/',
        ]);
        $this->mergeSiteConfiguration('integration_tree_two', [
            'base' => '/',
        ]);

        /** @var SiteHandlingStatus $siteHandlingStatus */
        $siteHandlingStatus = $this->get(SiteHandlingStatus::class);
        $statusCollection = $siteHandlingStatus->getStatus();

        foreach ($statusCollection as $status) {
            /** @var Status $status  */
            self::assertSame(ContextualFeedbackSeverity::ERROR, $status->getSeverity(), 'Expected that status checks for site handling configuration should indicate an error if authority in "Entry Point[base]" is not defined.');
            self::assertMatchesRegularExpression('~.*are empty or invalid: &quot;scheme, host&quot;~', $status->getMessage());
        }
    }

    #[Test]
    public function statusCheckShouldFailIfBaseIsSetWrongInLanguages(): void
    {
        $this->writeDefaultSolrTestSiteConfiguration();

        // mergeSiteConfiguration() do not work recursively
        putenv('TYPO3:configPath=' . $this->instancePath . '/typo3conf/');
        $siteConfiguration = GeneralUtility::makeInstance(SiteConfiguration::class);

        $configuration1 = $siteConfiguration->load('integration_tree_one');
        $configuration1['languages'][1]['base'] = 'authorityOnly.example.com';
        $this->mergeSiteConfiguration('integration_tree_one', $configuration1);

        $configuration2 = $siteConfiguration->load('integration_tree_two');
        $configuration2['languages'][1]['base'] = 'authorityOnly.two.example.com';

        $this->mergeSiteConfiguration('integration_tree_two', $configuration2);

        /** @var SiteHandlingStatus $siteHandlingStatus */
        $siteHandlingStatus = $this->get(SiteHandlingStatus::class);
        $statusCollection = $siteHandlingStatus->getStatus();

        foreach ($statusCollection as $status) {
            /** @var Status $status */
            self::assertSame(ContextualFeedbackSeverity::ERROR, $status->getSeverity(), 'Expected that status checks for site handling configuration should indicate an error if authority in "Entry Point[base]" is not defined.');
            self::assertMatchesRegularExpression('~.*is not valid URL\. Following parts of defined URL are empty or invalid: &quot;scheme&quot;~', $status->getMessage());
        }
    }
}
