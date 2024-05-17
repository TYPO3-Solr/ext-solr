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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ConfigurationServiceTest extends SetUpUnitTestCase
{
    public static function escapeFilterDataProvider(): Traversable
    {
        yield ['id', 10, '10'];
        yield ['id', '10', '10'];
        yield ['price', '10.5', '10.5'];
        yield ['title', 'test', 'test'];
        yield ['title', '"test"', '"test"'];
        yield ['title', '"Do it right" AND right', '"Do it right" AND right'];
        yield ['title', 'Do it right', '"Do it right"'];
        yield ['title', 'Do "it" right', 'Do "it" right'];
        yield ['title', 'te?t', 'te?t'];
        yield ['title', 'test*', 'test*'];
        yield ['title', 'te*t', 'te*t'];
        yield ['title', 'te"st', 'te"st'];
        yield ['title', 'roam~', 'roam~'];
        yield ['title', 'roam~0.8', 'roam~0.8'];
        yield ['title', '"jakarta apache"~10', '"jakarta apache"~10'];
        yield ['mod_date', '[20020101 TO 20030101]', '[20020101 TO 20030101]'];
        yield ['title', '{Aida TO Carmen}', '{Aida TO Carmen}'];
        yield ['title', 'jakarta apache', '"jakarta apache"'];
        yield ['title', 'jakarta^4 apache', 'jakarta^4 apache'];
        yield ['title', '"jakarta apache"^4 "Apache Lucene"', '"jakarta apache"^4 "Apache Lucene"'];
        yield ['title', '"jakarta apache" jakarta', '"jakarta apache" jakarta'];
        yield ['title', '"jakarta apache" OR jakarta', '"jakarta apache" OR jakarta'];
        yield ['title', '"jakarta apache" AND "Apache Lucene"', '"jakarta apache" AND "Apache Lucene"'];
        yield ['title', '+jakarta lucene', '+jakarta lucene'];
        yield ['title', '"jakarta apache" NOT "Apache Lucene"', '"jakarta apache" NOT "Apache Lucene"'];
        yield ['title', 'NOT "jakarta apache"', 'NOT "jakarta apache"'];
        yield ['title', '"jakarta apache" -"Apache Lucene"', '"jakarta apache" -"Apache Lucene"'];
        yield ['title', '(jakarta OR apache) AND website', '(jakarta OR apache) AND website'];
        yield ['title', '(+return +"pink panther")', '(+return +"pink panther")'];
        yield ['title', '\(1\+1\)\:2', '\(1\+1\)\:2'];
    }

    #[DataProvider('escapeFilterDataProvider')]
    #[Test]
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

    public static function overrideFilterDataProvider(): Traversable
    {
        yield ['id', 4711, 'id:4711'];
        yield ['title', 'Do it right', 'title:"Do it right"'];
        yield ['title', 'test', 'title:test'];
    }

    #[DataProvider('overrideFilterDataProvider')]
    #[Test]
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
