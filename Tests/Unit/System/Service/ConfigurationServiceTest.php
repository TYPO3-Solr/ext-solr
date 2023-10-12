<?php

declare(strict_types=1);

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
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ConfigurationServiceTest extends SetUpUnitTestCase
{
    public function escapeFilterDataProvider(): array
    {
        return [
            ['id', 10, '10'],
            ['id', '10', '10'],
            ['price', '10.5', '10.5'],
            ['title', 'test', 'test'],
            ['title', '"test"', '"test"'],
            ['title', '"Do it right" AND right', '"Do it right" AND right'],
            ['title', 'Do it right', '"Do it right"'],
            ['title', 'Do "it" right', 'Do "it" right'],
            ['title', 'te?t', 'te?t'],
            ['title', 'test*', 'test*'],
            ['title', 'te*t', 'te*t'],
            ['title', 'te"st', 'te"st'],
            ['title', 'roam~', 'roam~'],
            ['title', 'roam~0.8', 'roam~0.8'],
            ['title', '"jakarta apache"~10', '"jakarta apache"~10'],
            ['mod_date', '[20020101 TO 20030101]', '[20020101 TO 20030101]'],
            ['title', '{Aida TO Carmen}', '{Aida TO Carmen}'],
            ['title', 'jakarta apache', '"jakarta apache"'],
            ['title', 'jakarta^4 apache', 'jakarta^4 apache'],
            ['title', '"jakarta apache"^4 "Apache Lucene"', '"jakarta apache"^4 "Apache Lucene"'],
            ['title', '"jakarta apache" jakarta', '"jakarta apache" jakarta'],
            ['title', '"jakarta apache" OR jakarta', '"jakarta apache" OR jakarta'],
            ['title', '"jakarta apache" AND "Apache Lucene"', '"jakarta apache" AND "Apache Lucene"'],
            ['title', '+jakarta lucene', '+jakarta lucene'],
            ['title', '"jakarta apache" NOT "Apache Lucene"', '"jakarta apache" NOT "Apache Lucene"'],
            ['title', 'NOT "jakarta apache"', 'NOT "jakarta apache"'],
            ['title', '"jakarta apache" -"Apache Lucene"', '"jakarta apache" -"Apache Lucene"'],
            ['title', '(jakarta OR apache) AND website', '(jakarta OR apache) AND website'],
            ['title', '(+return +"pink panther")', '(+return +"pink panther")'],
            ['title', '\(1\+1\)\:2', '\(1\+1\)\:2'],
        ];
    }

    /**
     * @dataProvider escapeFilterDataProvider
     * @test
     */
    public function isFlexFormFilterEscaped(string $filterField, $filterValue, string $expectedFilterString): void
    {
        $fakeFlexFormArrayData = [
            'search' => [
                'query' => [
                    'filter' => [
                        [
                            'field' => [
                                'field' => $filterField,
                                'value' => $filterValue,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $filters = $this->callInaccessibleMethod(
            new ConfigurationService(),
            'getFilterFromFlexForm',
            $fakeFlexFormArrayData
        );

        self::assertEquals([$filterField . ':' . $expectedFilterString], $filters);
    }

    public function overrideFilterDataProvider(): array
    {
        return [
            ['id', 4711, 'id:4711'],
            ['title', 'Do it right', 'title:"Do it right"'],
            ['title', 'test', 'title:test'],
        ];
    }

    /**
     * @dataProvider overrideFilterDataProvider
     * @test
     */
    public function canOverrideConfigurationWithFlexFormSettings(
        string $filterField,
        $filterValue,
        string $expectedFilterString
    ): void {
        $fakeFlexFormArrayData = [
            'search' => [
                'query' => [
                    'filter' => [
                        [
                            'field' => [
                                'field' => $filterField,
                                'value' => $filterValue,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $flexFormServiceMock = $this->createMock(FlexFormService::class);
        $flexFormServiceMock->expects(self::once())->method('convertflexFormContentToArray')->willReturn($fakeFlexFormArrayData);

        $typoScriptConfiguration = new TypoScriptConfiguration(['plugin.' => ['tx_solr.' => []]]);

        $configurationService = new ConfigurationService();
        $configurationService->setFlexFormService($flexFormServiceMock);
        $configurationService->setTypoScriptService(GeneralUtility::makeInstance(TypoScriptService::class));

        self::assertEquals([], $typoScriptConfiguration->getSearchQueryFilterConfiguration());

        // the passed flexform data is empty because the convertflexFormContentToArray retrieves tha faked converted data
        $configurationService->overrideConfigurationWithFlexFormSettings('test', $typoScriptConfiguration);

        // the filter should be overwritten by the flexform
        self::assertEquals([$expectedFilterString], $typoScriptConfiguration->getSearchQueryFilterConfiguration());
    }
}
