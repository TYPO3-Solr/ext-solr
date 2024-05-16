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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Records\Pages;

use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration tests for PagesRepository.
 */
class PagesRepositoryTest extends IntegrationTestBase
{
    /**
     * @var PagesRepository
     */
    protected $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = GeneralUtility::makeInstance(PagesRepository::class);
    }

    #[Test]
    public function canFindAllRootPages()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');

        $expectedResult = [
            0 => [
                'uid' => 1,
                'title' => 'Products',
            ],
            1 => [
                'uid' => 5,
                'title' => 'Support',
            ],
        ];
        $result = $this->repository->findAllRootPages();
        self::assertEquals($expectedResult, $result);
    }

    #[Test]
    public function canfindMountPointPagesByRootLineParentPageIdsIfMountedPagesIsOutsideOfTheSite()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_find_mount_pages_in_rootline.csv');

        $expectedResult = [
            [
                'uid' => 14,
                'mountPageDestination' => 14,
                'mountPageSource' => 24,
                'mountPageOverlayed' => 1,
            ],
            [
                'uid' => 34,
                'mountPageDestination' => 34,
                'mountPageSource' => 25,
                'mountPageOverlayed' => 1,
            ],
        ];

        $result = $this->repository->findMountPointPropertiesByPageIdOrByRootLineParentPageIds(24);
        self::assertSame([$expectedResult[0]], $result);

        $rootLine = [24, 20];

        // Page [14] has both pages [24] and [25], because mounting works recursive
        $result = $this->repository->findMountPointPropertiesByPageIdOrByRootLineParentPageIds(25, $rootLine);
        self::assertSame($expectedResult, $result);
    }
}
