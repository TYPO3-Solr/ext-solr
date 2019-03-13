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
}