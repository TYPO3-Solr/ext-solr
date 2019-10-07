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
use ApacheSolrForTypo3\Solr\System\Mvc\Frontend\Controller\OverriddenTypoScriptFrontendController;
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
        $tsfe = $this->getMockBuilder(OverriddenTypoScriptFrontendController::class)->setMethods([])->disableOriginalConstructor()->getMock();
        $tsfe->cObjectDepthCounter = 50;
        $GLOBALS['TSFE'] = $tsfe;
        parent::setUp();
    }

     /**
     * @test
     */
    public function testCanUsePlainValuesFromConfiguration() {
        $configuration = [
            'plugin.' => [
                'tx_solr.' => [
                    'search.' =>[
                        'sorting' => 1
                    ]
                ]
            ]
        ];

        /** @var $typoScriptConfiguration TypoScriptConfiguration */
        $typoScriptConfiguration = GeneralUtility::makeInstance(TypoScriptConfiguration::class, $configuration, 0);
        $sorting = $typoScriptConfiguration->getSearchSorting();
        $this->assertTrue($sorting, 'Can not get sorting configuration from typoscript');
    }
}