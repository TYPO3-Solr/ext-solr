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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Records\SystemTemplate;

use ApacheSolrForTypo3\Solr\System\Records\SystemTemplate\SystemTemplateRepository;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration test for the SystemTemplateRepository
 */
class SystemTemplateRepositoryTest extends IntegrationTestBase
{
    public static function rootLineDataProvider(): array
    {
        return [
            'Rootline with template on page 33 (2nd)' => [
                [
                    ['uid' => 4],  // No template
                    ['uid' => 3],  // No template
                    ['uid' => 2],  // No template
                    ['uid' => 33], // Template here
                    ['uid' => 1], // Current page
                ],
                33,
            ],
            'Rootline with template on page 44 (2nd)' => [
                [
                    ['uid' => 5],  // No template
                    ['uid' => 33], // Template here, but 44 is closer
                    ['uid' => 2],  // No template
                    ['uid' => 44], // Template here
                    ['uid' => 1], // Current page
                ],
                44,
            ],
            'Rootline with template on page 33 (3rd), page 44 (5th)' => [
                [
                    ['uid' => 44], // Template here, but 33 is closer
                    ['uid' => 30], // No template
                    ['uid' => 33], // Template here
                    ['uid' => 20], // No template
                    ['uid' => 10], // Current page
                ],
                33,
            ],
            'Rootline with template on page 44 (3rd), page 33 (5th)' => [
                [
                    ['uid' => 33], // Template here, but 44 is closer
                    ['uid' => 30], // No template
                    ['uid' => 44], // Template here
                    ['uid' => 20], // No template
                    ['uid' => 10], // Current page
                ],
                44,
            ],
        ];
    }

    #[DataProvider('rootLineDataProvider')]
    #[Test]
    public function canFindOneClosestPageIdWithActiveTemplateByRootLine(array $fakeRootLine, int $expectedPageId): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_template.csv');

        /** @var SystemTemplateRepository $repository */
        $repository = GeneralUtility::makeInstance(SystemTemplateRepository::class);
        $closestPageIdWithActiveTemplate = $repository->findOneClosestPageIdWithActiveTemplateByRootLine($fakeRootLine);
        self::assertEquals($expectedPageId, $closestPageIdWithActiveTemplate, 'Can not find closest page id with active template.');
    }
}
