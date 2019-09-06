<?php
namespace ApacheSolrForTypo3\Solr\Test\System\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
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
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\System\Service\ConfigurationService;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Service\FlexFormService;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ConfigurationServiceTest extends UnitTest
{

    /**
     * @return array
     */
    public function overrideFilterDataProvider()
    {
        return [
            'simpleInteger' => ['id', 4711, 'id:4711'],
            'escapedString' => ['id', 'foo"bar', 'id:"foo\"bar"']
        ];
    }

    /**
     * @dataProvider overrideFilterDataProvider
     * @test
     */
    public function canOverrideConfigurationWithFlexFormSettings($filterField, $filterValue, $expectedFilterString)
    {
        $fakeFlexFormArrayData = [
            'search' =>
                [
                    'query' => [
                        'filter' => [
                            ['field' => ['field' => $filterField, 'value' => $filterValue]]
                        ]
                    ]
                ]
          ];
        $flexFormServiceMock = $this->getDumbMock(FlexFormService::class);
        $flexFormServiceMock->expects($this->once())->method('convertflexFormContentToArray')->will($this->returnValue($fakeFlexFormArrayData));

        $typoScriptConfiguration = new TypoScriptConfiguration(['plugin.' => ['tx_solr.' => []]]);
        $configurationService = new ConfigurationService();
        $configurationService->setFlexFormService($flexFormServiceMock);
        $configurationService->setTypoScriptService(GeneralUtility::makeInstance(TypoScriptService::class));

        $this->assertEquals([], $typoScriptConfiguration->getSearchQueryFilterConfiguration());

            // the passed flexform data is empty because the convertflexFormContentToArray retrieves tha faked converted data
        $configurationService->overrideConfigurationWithFlexFormSettings('foobar', $typoScriptConfiguration);

            // the filter should be overwritten by the flexform
        $this->assertEquals([$expectedFilterString], $typoScriptConfiguration->getSearchQueryFilterConfiguration());
    }
}
