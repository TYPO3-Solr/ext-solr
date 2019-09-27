<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Util;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Util\SiteUtility;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Testcase for the SiteUtilityTest helper class.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SiteUtilityTest extends UnitTest
{
    /**
     * @test
     */
    public function canFallbackToLanguageSpecificReadProperty()
    {
        $languageConfiguration = ['solr_core_read' => 'readcore'];
        $languageMock = $this->getDumbMock(SiteLanguage::class);
        $languageMock->expects($this->any())->method('toArray')->willReturn($languageConfiguration);

        $siteMock = $this->getDumbMock(Site::class);
        $siteMock->expects($this->any())->method('getLanguageById')->willReturn($languageMock);
        $property = SiteUtility::getConnectionProperty($siteMock, 'core', 2, 'write');

        $this->assertSame('readcore', $property, 'Can not fallback to read property when write property is undefined');
    }

    /**
     * @test
     */
    public function canFallbackToGlobalPropertyWhenLanguageSpecificPropertyIsNotSet()
    {
        $languageConfiguration = ['solr_core_read' => 'readcore'];
        $languageMock = $this->getDumbMock(SiteLanguage::class);
        $languageMock->expects($this->any())->method('toArray')->willReturn($languageConfiguration);

        $globalConfiguration = ['solr_host_read' => 'readhost'];
        $siteMock = $this->getDumbMock(Site::class);
        $siteMock->expects($this->any())->method('getLanguageById')->willReturn($languageMock);
        $siteMock->expects($this->any())->method('getConfiguration')->willReturn($globalConfiguration);
        $property = SiteUtility::getConnectionProperty($siteMock, 'host', 2, 'read');

        $this->assertSame('readhost', $property, 'Can not fallback to read property when write property is undefined');
    }

    /**
     * @return array
     */
    public function writeConnectionTestsDataProvider(): array
    {
        return [
            [ // enabling solr_use_write_connection, resolves to specified write host
                'expectedSolrHost' => 'writehost',
                'expectedSiteMockConfiguration' => [
                    'solr_host_read' => 'readhost',
                    'solr_use_write_connection' => true,
                    'solr_host_write' => 'writehost'
                ]
            ],
            [ // enabling solr_use_write_connection but not specifying write host, falls back to specified read host
                'expectedSolrHost' => 'readhost',
                'expectedSiteMockConfiguration' => [
                    'solr_host_read' => 'readhost',
                    'solr_use_write_connection' => true
                ]
            ],
            [ // disabling solr_use_write_connection and specifying write host, falls back to specified read host
                'expectedSolrHost' => 'readhost',
                'expectedSiteMockConfiguration' => [
                    'solr_host_read' => 'readhost',
                    'solr_use_write_connection' => false,
                    'solr_host_write' => 'writehost'
                ]
            ]
        ];
    }

    /**
     * solr_use_write_connection is functional
     *
     * @dataProvider writeConnectionTestsDataProvider
     * @test
     */
    public function solr_use_write_connectionSiteSettingInfluencesTheWriteConnection(string $expectedSolrHost, array $expectedSiteMockConfiguration)
    {
        $siteMock = $this->getDumbMock(Site::class);
        $siteMock->expects($this->any())->method('getConfiguration')->willReturn($expectedSiteMockConfiguration);
        $property = SiteUtility::getConnectionProperty($siteMock, 'host', 0, 'write');

        $this->assertEquals($expectedSolrHost, $property,
            'The setting "solr_use_write_connection" from sites config.yaml has no influence on system.' .
            'The setting "solr_use_write_connection=true/false" must enable or disable the write connection respectively.');
    }

    /**
     * @test
     */
    public function canLanguageSpecificConfigurationOverwriteGlobalConfiguration()
    {
        $languageConfiguration = ['solr_host_read' => 'readhost.local.de'];
        $languageMock = $this->getDumbMock(SiteLanguage::class);
        $languageMock->expects($this->any())->method('toArray')->willReturn($languageConfiguration);

        $globalConfiguration = ['solr_host_read' => 'readhost.global.de'];
        $siteMock = $this->getDumbMock(Site::class);
        $siteMock->expects($this->any())->method('getLanguageById')->willReturn($languageMock);
        $siteMock->expects($this->any())->method('getConfiguration')->willReturn($globalConfiguration);
        $property = SiteUtility::getConnectionProperty($siteMock, 'host', 2, 'read');

        $this->assertSame('readhost.local.de', $property, 'Can not fallback to read property when write property is undefined');
    }

    /**
     * @test
     */
    public function specifiedDefaultValueIsReturnedByGetConnectionPropertyIfPropertyIsNotDefinedInConfiguration()
    {
        $languageMock = $this->getDumbMock(SiteLanguage::class);
        $siteMock = $this->getDumbMock(Site::class);
        $siteMock->expects($this->any())->method('getLanguageById')->willReturn($languageMock);
        $property = SiteUtility::getConnectionProperty($siteMock, 'some_property', 2, 'read', 'value-of_some_property');

        $this->assertEquals('value-of_some_property', $property, 'Can not fall back to defaultValue.');
    }
}