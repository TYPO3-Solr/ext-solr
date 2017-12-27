<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Configuration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class TypoScriptConfigurationTest extends IntegrationTest
{

    /**
     * @return void
     */
    public function setUp() {
        $tsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->setMethods([])->disableOriginalConstructor()->getMock();
        $tsfe->cObjectDepthCounter = 50;
        $GLOBALS['TSFE'] = $tsfe;
        parent::setUp();
    }

    /**
     * @test
     */
    public function testCanRenderCObjectInConfiguration() {

        // we fake some deployment settings
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['host'] = 'mydeployhostname';
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['path'] = '/deploy/core_en/';

        $configuration = [
            'plugin.' => [
                'tx_solr.' => [
                    'solr.' => [
                        'host' => 'TEXT',
                        'host.' => [
                            'value' => 'mydefaulthostname',
                            'override.' => [
                                'data' => 'global:TYPO3_CONF_VARS|EXTCONF|solr|host'
                            ]
                        ],
                        'path' => 'TEXT',
                        'path.' => [
                            'value' => '/mydefaultpath/',
                            'override.' => [
                                'data' => 'global:TYPO3_CONF_VARS|EXTCONF|solr|path'
                            ]
                        ]

                    ]
                ]
            ]
        ];

            /** @var $typoScriptConfiguration TypoScriptConfiguration */
        $typoScriptConfiguration = GeneralUtility::makeInstance(TypoScriptConfiguration::class, $configuration, 0);

        $hostname = $typoScriptConfiguration->getSolrHost();
        $this->assertSame('mydeployhostname', $hostname, 'Could not apply cObject with configuration from TYPO3_CONF_VARS for host');

        $path = $typoScriptConfiguration->getSolrPath();
        $this->assertSame('/deploy/core_en/', $path, 'Could not apply cObject with configuration from TYPO3_CONF_VARS for path');

        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['host']);
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['path']);
    }


    /**
     * @test
     */
    public function testValueOfCObjectIsUsedWhenNoTYPO3ConfVarIsPresent() {
        // no configuration in TYPO3_CONF_VARS done we expect that the fallback configuration in value will be used

        $configuration = [
            'plugin.' => [
                'tx_solr.' => [
                    'solr.' => [
                        'host' => 'TEXT',
                        'host.' => [
                            'value' => 'mydefaulthostname',
                            'override.' => [
                                'data' => 'global:TYPO3_CONF_VARS|EXTCONF|solr|host'
                            ]
                        ],
                        'path' => 'TEXT',
                        'path.' => [
                            'value' => '/mydefaultpath/',
                            'override.' => [
                                'data' => 'global:TYPO3_CONF_VARS|EXTCONF|solr|path'
                            ]
                        ]

                    ]
                ]
            ]
        ];

        /** @var $typoScriptConfiguration TypoScriptConfiguration */
        $typoScriptConfiguration = GeneralUtility::makeInstance(TypoScriptConfiguration::class, $configuration, 0);

        $hostname = $typoScriptConfiguration->getSolrHost();
        $this->assertSame('mydefaulthostname', $hostname, 'cObject does not fallback to value when TYPO3_CONF_VARS value is missing');

        $path = $typoScriptConfiguration->getSolrPath();
        $this->assertSame('/mydefaultpath/', $path, 'cObject does not fallback to value when TYPO3_CONF_VARS value is missing');
    }

    /**
     * @test
     */
    public function testCanUsePlainValuesFromConfiguration() {
        $configuration = [
            'plugin.' => [
                'tx_solr.' => [
                    'solr.' => [
                        'host' => 'plainhost',
                        'path' => '/plainpath/',
                    ]
                ]
            ]
        ];

        /** @var $typoScriptConfiguration TypoScriptConfiguration */
        $typoScriptConfiguration = GeneralUtility::makeInstance(TypoScriptConfiguration::class, $configuration, 0);

        $hostname = $typoScriptConfiguration->getSolrHost();
        $this->assertSame('plainhost', $hostname, 'Can not use configured plain value as host');

        $path = $typoScriptConfiguration->getSolrPath();
        $this->assertSame('/plainpath/', $path, 'Can not use configured plain value as path');
    }
}