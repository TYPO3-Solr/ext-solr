<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Configuration;

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

use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\GlobalConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\SiteConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\UnifiedConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Testcase to test the functionality of the extension configuration that comes from
 *
 * ext_conf_template.txt
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class UnifiedConfigurationTest extends UnitTest
{
    /**
     * Configured base information
     */
    public function setUp()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = [
            'allowSelfSignedCertificates' => 0,
            'useConfigurationMonitorTables' => '',
            'useConfigurationTrackRecordsOutsideSiteroot' => 1,
            'useConfigurationFromClosestTemplate' => 0
        ];
    }

    /**
     * @test
     */
    public function testIfIsTrueReturnsExceptedValue()
    {
        $defaultConfiguration = new UnifiedConfiguration(1, 0);
        $defaultConfiguration->mergeConfigurationByObject(new GlobalConfiguration());

        $this->assertTrue($defaultConfiguration->get('connection.read.verify'));
    }

    /**
     * @test
     */
    public function testIfGetIntegerReturnsExceptedType()
    {
        $defaultConfiguration = new UnifiedConfiguration(1, 0);
        $defaultConfiguration->mergeConfigurationByObject(new GlobalConfiguration());

        $this->assertIsInt($defaultConfiguration->get('connection.read.connect_timeout'));
    }

    /**
     * @test
     */
    public function testIfGlobalConfigurationCanAccessByUnifiedPath()
    {
        $defaultConfiguration = new UnifiedConfiguration(1, 0);
        $defaultConfiguration->mergeConfigurationByObject(new GlobalConfiguration());

        $this->assertTrue($defaultConfiguration->has('connection.read.timeout'));
        $this->assertTrue($defaultConfiguration->has('connection.write.timeout'));
    }

    /**
     * @test
     */
    public function testIfExtensionConfigurationCanAccessByUnifiedPath()
    {
        $defaultConfiguration = new UnifiedConfiguration(1, 0);
        $defaultConfiguration->mergeConfigurationByObject(
            new ExtensionConfiguration($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'])
        );

        $this->assertTrue($defaultConfiguration->has('connection.read.allowSelfSignedCertificates'));
        $this->assertTrue($defaultConfiguration->has('connection.write.allowSelfSignedCertificates'));
    }

    /**
     * @test
     */
    public function testIfTypoScriptConfigurationCanAccessByUnifiedPath()
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['index.']['queue.']['tt_news.']['fields.']['content'] = 'SOLR_CONTENT';
        $fakeConfigurationArray['plugin.']['tx_solr.']['index.']['queue.']['tt_news.']['fields.']['content.']['field'] = 'bodytext';
        $configuration = new TypoScriptConfiguration($fakeConfigurationArray);
        $defaultConfiguration = new UnifiedConfiguration(1, 0);
        $defaultConfiguration->mergeConfigurationByObject($configuration);

        $this->assertTrue($defaultConfiguration->has('index.queue.tt_news.fields.content'));
        $this->assertEquals(
            'SOLR_CONTENT',
            $defaultConfiguration->get('index.queue.tt_news.fields.content._typoScriptNodeValue')
        );
        $this->assertEquals(
            'bodytext',
            $defaultConfiguration->get('index.queue.tt_news.fields.content.field')
        );
    }

    /**
     * @test
     */
    public function testIfSiteConfigurationCanAccessByUnifiedPath()
    {
        $site = new Site(
            'unit-test',
            1,
            [
                'solr_enabled_read' => true,
                'solr_host_read' => 'solr-site',
                'solr_path_read' => '/solr/',
                'solr_port_read' => 8983,
                'solr_scheme_read' => 'http',
                'solr_use_write_connection' => false,
            ]
        );
        $siteConfiguration = SiteConfiguration::newWithSite($site, 0);
        $defaultConfiguration = new UnifiedConfiguration(1, 0);
        $defaultConfiguration->mergeConfigurationByObject($siteConfiguration);

        $this->assertTrue($defaultConfiguration->has('connection.read.host'));
        $this->assertEquals(
            'solr-site',
            $defaultConfiguration->get('connection.read.host')
        );
    }
}
