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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Service;

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Service\ConfigurationService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
            'escapedString' => ['id', 'foo"bar', 'id:"foo\"bar"'],
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
                            ['field' => ['field' => $filterField, 'value' => $filterValue]],
                        ],
                    ],
                ],
          ];
        $flexFormServiceMock = $this->getDumbMock(FlexFormService::class);
        $flexFormServiceMock->expects(self::once())->method('convertflexFormContentToArray')->willReturn($fakeFlexFormArrayData);

        $typoScriptConfiguration = new TypoScriptConfiguration(['plugin.' => ['tx_solr.' => []]]);
        $configurationService = new ConfigurationService();
        $configurationService->setFlexFormService($flexFormServiceMock);
        $configurationService->setTypoScriptService(GeneralUtility::makeInstance(TypoScriptService::class));

        self::assertEquals([], $typoScriptConfiguration->getSearchQueryFilterConfiguration());

        // the passed flexform data is empty because the convertflexFormContentToArray retrieves tha faked converted data
        $configurationService->overrideConfigurationWithFlexFormSettings('foobar', $typoScriptConfiguration);

        // the filter should be overwritten by the flexform
        self::assertEquals([$expectedFilterString], $typoScriptConfiguration->getSearchQueryFilterConfiguration());
    }
}
