<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Records\SystemTemplate;

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

use ApacheSolrForTypo3\Solr\System\Records\SystemTemplate\SystemTemplateRepository;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration test for the SystemTemplateRepository
 */
class SystemTemplateRepositoryTest extends IntegrationTest
{

    /**
     * @test
     */
    public function canFindOneClosestPageIdWithActiveTemplateByRootLine()
    {
        $this->importDataSetFromFixture('sys_template.xml');

        $fakeRootLine = [
            ['uid' => 100],
            ['uid' => 33],
            ['uid' => 8657]
        ];

        /* @var $repository SystemTemplateRepository */
        $repository = GeneralUtility::makeInstance(SystemTemplateRepository::class);
        $closestPageIdWithActiveTemplate = $repository->findOneClosestPageIdWithActiveTemplateByRootLine($fakeRootLine);
        $this->assertEquals(33, $closestPageIdWithActiveTemplate, 'Can not find closest page id with active template.');
    }
}
