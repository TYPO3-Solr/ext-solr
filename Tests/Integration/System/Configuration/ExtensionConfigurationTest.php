<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Configuration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2019 dkd Internet Services GmbH (info@dkd.de)
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

use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Core\Bootstrap;


class ExtensionConfigurationTest extends IntegrationTest
{

    /**
     * @test
     */
    public function testNotEnabledAllowLegacySiteModeHidesContextMenuInitSolrConnections()
    {
        $allowLegacySiteModeValue = (bool)$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr']['allowLegacySiteMode'];
        $this->assertFalse($allowLegacySiteModeValue);
        $this->assertFalse(isset($GLOBALS['TYPO3_CONF_VARS']['BE']['ContextMenu']['ItemProviders'][1487876780]));
    }

    /**
     * @test
     */
    public function testEnabledAllowLegacySiteModeFadesInContextMenuInitSolrConnections()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr']['allowLegacySiteMode'] = '1';
        $allowLegacySiteModeValue = (bool)$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr']['allowLegacySiteMode'];
        $this->assertTrue($allowLegacySiteModeValue);

        Bootstrap::loadExtTables(true);
        $this->assertTrue(isset($GLOBALS['TYPO3_CONF_VARS']['BE']['ContextMenu']['ItemProviders'][1487876780]));
    }

    /**
     * @test
     */
    public function testNotEnabledAllowLegacySiteModeHidesInitSolrConnectionsInClearCacheMenu()
    {
        $allowLegacySiteModeValue = (bool)$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr']['allowLegacySiteMode'];
        $this->assertFalse($allowLegacySiteModeValue);
        $this->assertFalse(isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['additionalBackendItems']['cacheActions']['clearSolrConnectionCache']));
    }

    /**
     * @test
     */
    public function testEnabledAllowLegacySiteModeFadesInInitSolrConnectionsInClearCacheMenu()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr']['allowLegacySiteMode'] = '1';
        $allowLegacySiteModeValue = (bool)$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr']['allowLegacySiteMode'];
        $this->assertTrue($allowLegacySiteModeValue);

        Bootstrap::loadExtTables(true);
        $this->assertTrue(isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['additionalBackendItems']['cacheActions']['clearSolrConnectionCache']));
    }
}