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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

use ApacheSolrForTypo3\Solr\IndexQueue\AbstractIndexer;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use UnexpectedValueException;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class AbstractIndexerTest extends UnitTest
{
    protected function setUp(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'] = [];
        parent::setUp();
    }

    /**
     * @test
     */
    public function isSerializedValueCanHandleCustomContentElements()
    {
        $indexingConfiguration = [
            'topic_stringM' => 'SOLR_CLASSIFICATION',
            'categories_stringM' => 'SOLR_RELATION',
            'categories_stringM.' => [
                'multiValue' => true,
            ],
            'csv_stringM' => 'SOLR_MULTIVALUE',
            'category_stringM' => 'SOLR_RELATION',
        ];

        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'topic_stringM'), 'Response of SOLR_CLASSIFICATION is expected to be serialized');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'csv_stringM'), 'Response of SOLR_MULTIVALUE is expected to be serialized');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'categories_stringM'), 'Response of SOLR_MULTIVALUE is expected to be serialized');

        self::assertFalse(AbstractIndexer::isSerializedValue($indexingConfiguration, 'category_stringM'), 'Non configured fields should allways be unserialized');
        self::assertFalse(AbstractIndexer::isSerializedValue($indexingConfiguration, 'notConfigured_stringM'), 'Non configured fields should allways be unserialized');
    }

    /**
     * @test
     */
    public function isSerializedValueCanHandleCustomInvalidSerializedValueDetector()
    {
        // register invalid detector
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'][] = InvalidSerializedValueDetector::class;
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/.*InvalidSerializedValueDetector must implement interface.*/');

        $indexingConfiguration = [
            'topic_stringM' => 'SOLR_CLASSIFICATION',
        ];

        // when an invalid detector is registered we expect that an exception is thrown
        AbstractIndexer::isSerializedValue($indexingConfiguration, 'topic_stringM');
    }

    /**
     * @test
     */
    public function isSerializedValueCanHandleCustomValidSerializedValueDetector()
    {
        // register invalid detector
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'][] = ValidSerializedValueDetector::class;

        $indexingConfiguration = [
            'topic_stringM' => 'SOLR_CLASSIFICATION',
            'categories_stringM' => 'SOLR_RELATION',
            'categories_stringM.' => [
                'multiValue' => true,
            ],
            'csv_stringM' => 'SOLR_MULTIVALUE',
            'category_stringM' => 'SOLR_RELATION',
        ];
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'topic_stringM'), 'Every value should be treated as serialized by custom detector');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'csv_stringM'), 'Every value should be treated as serialized by custom detector');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'categories_stringM'), 'Every value should be treated as serialized by custom detector');
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'category_stringM', 'Every value should be treated as serialized by custom detector'));
        self::assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'notConfigured_stringM', 'Every value should be treated as serialized by custom detector'));
    }
}
