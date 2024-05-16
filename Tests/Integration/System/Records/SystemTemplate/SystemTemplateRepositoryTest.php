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
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration test for the SystemTemplateRepository
 */
class SystemTemplateRepositoryTest extends IntegrationTestBase
{
    #[Test]
    public function canFindOneClosestPageIdWithActiveTemplateByRootLine(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_template.csv');

        $fakeRootLine = [
            ['uid' => 100],
            ['uid' => 33],
            ['uid' => 8657],
        ];

        /** @var SystemTemplateRepository $repository */
        $repository = GeneralUtility::makeInstance(SystemTemplateRepository::class);
        $closestPageIdWithActiveTemplate = $repository->findOneClosestPageIdWithActiveTemplateByRootLine($fakeRootLine);
        self::assertEquals(33, $closestPageIdWithActiveTemplate, 'Can not find closest page id with active template.');
    }
}
