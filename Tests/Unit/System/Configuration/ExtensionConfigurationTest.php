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
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;

/**
 * Testcase to test the functionality of the extension configuration that comes from
 *
 * ext_conf_template.txt
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ExtensionConfigurationTest extends SetUpUnitTestCase
{
    protected function setUp(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = [];
        parent::setUp();
    }

    /**
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    #[Test]
    public function testGetIsUseConfigurationFromClosestTemplateEnabled(): void
    {
        $defaultConfiguration = new ExtensionConfiguration();
        self::assertFalse($defaultConfiguration->getIsUseConfigurationFromClosestTemplateEnabled());
        $configurationWithClosestTemplateEnabled = new ExtensionConfiguration(
            ['useConfigurationFromClosestTemplate' => 1]
        );
        self::assertTrue($configurationWithClosestTemplateEnabled->getIsUseConfigurationFromClosestTemplateEnabled());
    }

    /**
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    #[Test]
    public function testIsGetUseConfigurationTrackRecordsOutsideSiterootEnabled(): void
    {
        $defaultConfiguration = new ExtensionConfiguration();
        self::assertTrue($defaultConfiguration->getIsUseConfigurationTrackRecordsOutsideSiteroot());
        $configurationUseConfigurationTrackRecordsOutsideSiteroot = new ExtensionConfiguration(
            ['useConfigurationTrackRecordsOutsideSiteroot' => 0]
        );
        self::assertFalse($configurationUseConfigurationTrackRecordsOutsideSiteroot->getIsUseConfigurationTrackRecordsOutsideSiteroot());
    }

    /**
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    #[Test]
    public function testIsGetIsUseConfigurationMonitorTablesConfiguredKnownTable(): void
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
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    #[Test]
    public function testIsGetIsUseConfigurationMonitorTablesConfiguredUnknownTable(): void
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
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    #[Test]
    public function testIsGetIsUseConfigurationMonitorTablesConfiguredEmptyList(): void
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
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    #[Test]
    public function testIsGetIsSelfSignedCertificatesEnabled(): void
    {
        $defaultConfiguration = new ExtensionConfiguration();
        self::assertFalse($defaultConfiguration->getIsSelfSignedCertificatesEnabled());
        $configurationUseConfigurationAllowSelfSignedCertificates = new ExtensionConfiguration(
            ['allowSelfSignedCertificates' => 1]
        );
        self::assertTrue($configurationUseConfigurationAllowSelfSignedCertificates->getIsSelfSignedCertificatesEnabled());
    }
}
