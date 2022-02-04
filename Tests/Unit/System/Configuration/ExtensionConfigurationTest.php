<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Configuration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017- Timo Hund <timo.hund@dkd.de>
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
