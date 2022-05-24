<?php

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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Configuration;

use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase to test the functionallity of the extension configuration that comes from
 *
 * ext_conf_template.txt
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ExtensionConfigurationTest extends UnitTest
{
    protected function setUp(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = [];
        parent::setUp();
    }

    /**
     * @test
     */
    public function testGetIsUseConfigurationFromClosestTemplateEnabled()
    {
        $defaultConfiguration = new ExtensionConfiguration();
        self::assertFalse($defaultConfiguration->getIsUseConfigurationFromClosestTemplateEnabled());
        $configurationWithClosestTemplateEnabled = new ExtensionConfiguration(
            ['useConfigurationFromClosestTemplate' => 1]
        );
        self::assertTrue($configurationWithClosestTemplateEnabled->getIsUseConfigurationFromClosestTemplateEnabled());
    }

    /**
     * @test
     */
    public function testIsGetUseConfigurationTrackRecordsOutsideSiterootEnabled()
    {
        $defaultConfiguration = new ExtensionConfiguration();
        self::assertTrue($defaultConfiguration->getIsUseConfigurationTrackRecordsOutsideSiteroot());
        $configurationUseConfigurationTrackRecordsOutsideSiteroot = new ExtensionConfiguration(
            ['useConfigurationTrackRecordsOutsideSiteroot' => 0]
        );
        self::assertFalse($configurationUseConfigurationTrackRecordsOutsideSiteroot->getIsUseConfigurationTrackRecordsOutsideSiteroot());
    }

    /**
     * @test
     */
    public function testIsGetIsUseConfigurationMonitorTablesConfiguredKnownTable()
    {
        $defaultConfiguration = new ExtensionConfiguration();
        self::assertEquals([], $defaultConfiguration->getIsUseConfigurationMonitorTables());
        $configurationUseConfigurationTrackRecordsOutsideSiteroot = new ExtensionConfiguration(
            ['useConfigurationMonitorTables' => 'pages, tt_content']
        );
        $tableList = $configurationUseConfigurationTrackRecordsOutsideSiteroot->getIsUseConfigurationMonitorTables();
        $result = in_array('pages', $tableList);
        self::assertTrue($result);
    }

    /**
     * @test
     */
    public function testIsGetIsUseConfigurationMonitorTablesConfiguredUnknownTable()
    {
        $defaultConfiguration = new ExtensionConfiguration();
        self::assertEquals([], $defaultConfiguration->getIsUseConfigurationMonitorTables());
        $configurationUseConfigurationTrackRecordsOutsideSiteroot = new ExtensionConfiguration(
            ['useConfigurationMonitorTables' => 'pages, tt_content']
        );
        $tableList = $configurationUseConfigurationTrackRecordsOutsideSiteroot->getIsUseConfigurationMonitorTables();
        $result = in_array('unknowntable', $tableList);
        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function testIsGetIsUseConfigurationMonitorTablesConfiguredEmptyList()
    {
        $defaultConfiguration = new ExtensionConfiguration();
        self::assertEquals([], $defaultConfiguration->getIsUseConfigurationMonitorTables());
        $configurationUseConfigurationTrackRecordsOutsideSiteroot = new ExtensionConfiguration(
            ['useConfigurationMonitorTables' => '']
        );
        $tableList = $configurationUseConfigurationTrackRecordsOutsideSiteroot->getIsUseConfigurationMonitorTables();
        self::assertSame([], $tableList);
    }

    /**
     * @test
     */
    public function testIsGetIsSelfSignedCertificatesEnabled()
    {
        $defaultConfiguration = new ExtensionConfiguration();
        self::assertFalse($defaultConfiguration->getIsSelfSignedCertificatesEnabled());
        $configurationUseConfigurationAllowSelfSignedCertificates = new ExtensionConfiguration(
            ['allowSelfSignedCertificates' => 1]
        );
        self::assertTrue($configurationUseConfigurationAllowSelfSignedCertificates->getIsSelfSignedCertificatesEnabled());
    }
}
