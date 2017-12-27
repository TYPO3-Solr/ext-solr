<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Records\Pages;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-eb-support@dkd.de>
 *
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

use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration tes for PagesRepository.
 */
class PagesRepositoryTest extends IntegrationTest
{

    /**
     * @var PagesRepository
     */
    protected $repository;

    public function setUp()
    {
        parent::setUp();
        $this->repository = GeneralUtility::makeInstance(PagesRepository::class);
    }

    /**
     * @test
     */
    public function canFindAllRootPages()
    {
        $this->importDataSetFromFixture('pages.xml');

        $expectedResult = [
            0 => [
                'uid' => 1,
                'title' => 'Products'
            ],
            1 => [
                'uid' => 5,
                'title' => 'Support'
            ]
        ];
        $result = $this->repository->findAllRootPages();
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @test
     *
     * There is following scenario:
     *
     * [0]
     * |
     * ——[ 1] Page (Root)
     * |   |
     * |   ——[14] Mount Point 1 (to [24] to show contents from) <—— Try to find this
     * |
     * ——[ 3] Page2 (Root)
     * |  |
     * |   ——[34] Mount Point 2 (to [25] to show contents from) <—— Try to find this
     * |
     * ——[20] Shared-Pages (Folder: Not root)
     * |   |
     * |   ——[24] FirstShared
     * |       |
     * |       ——[25] first sub page from FirstShared
     * |       |
     * |       ——[26] second sub page from FirstShared
     */
    public function canfindMountPointPagesByRootLineParentPageIdsIfMountedPagesIsOutsideOfTheSite()
    {
        $this->importDataSetFromFixture('can_find_mout_pages_in_rootline.xml');

        $expectedResult = [
            [
                'uid' => 14,
                'mountPageDestination' => 14,
                'mountPageSource' => 24,
                'mountPageOverlayed' => 1
            ],
            [
                'uid' => 34,
                'mountPageDestination' => 34,
                'mountPageSource' => 25,
                'mountPageOverlayed' => 1
            ]
        ];

        $result = $this->repository->findMountPointPropertiesByPageIdOrByRootLineParentPageIds(24);
        $this->assertSame([$expectedResult[0]], $result);

        $rootLine = [24,20];

        // Page [14] has both pages [24] and [25], because mounting works recursive
        $result = $this->repository->findMountPointPropertiesByPageIdOrByRootLineParentPageIds(25, $rootLine);
        $this->assertSame($expectedResult, $result);

    }
}
