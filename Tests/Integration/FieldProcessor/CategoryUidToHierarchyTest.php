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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\FieldProcessor;

use ApacheSolrForTypo3\Solr\FieldProcessor\CategoryUidToHierarchy;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration test for the CategoryUidToHierarchy
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class CategoryUidToHierarchyTest extends IntegrationTest
{
    /**
     * @test
     */
    public function canConvertToCategoryIdToHierarchy()
    {
        $this->importDataSetFromFixture('sys_category.xml');
        /** @var $processor CategoryUidToHierarchy */
        $processor = GeneralUtility::makeInstance(CategoryUidToHierarchy::class);
        $result = $processor->process([2]);
        $expectedResult = ['0-1/', '1-1/2/'];
        self::assertSame($result, $expectedResult, 'Hierarchy processor did not build expected hierarchy');
    }
}
