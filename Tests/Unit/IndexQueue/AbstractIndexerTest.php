<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017
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

use ApacheSolrForTypo3\Solr\IndexQueue\AbstractIndexer;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class AbstractIndexerTest extends UnitTest
{

    public function setUp()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'] = [];
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
                'multiValue' => true
            ],
            'csv_stringM' => 'SOLR_MULTIVALUE',
            'category_stringM' => 'SOLR_RELATION'
        ];

        $this->assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'topic_stringM'), 'Response of SOLR_CLASSIFICATION is expected to be serialized');
        $this->assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'csv_stringM'), 'Response of SOLR_MULTIVALUE is expected to be serialized');
        $this->assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'categories_stringM'), 'Response of SOLR_MULTIVALUE is expected to be serialized');


        $this->assertFalse(AbstractIndexer::isSerializedValue($indexingConfiguration, 'category_stringM', 'Non configured fields should allways be unserialized'));
        $this->assertFalse(AbstractIndexer::isSerializedValue($indexingConfiguration, 'notConfigured_stringM', 'Non configured fields should allways be unserialized'));
    }

    /**
     * @test
     */
    public function isSerializedValueCanHandleCustomInvalidSerializedValueDetector()
    {
        // register invalid detector
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'][] = InvalidSerializedValueDetector::class;
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageRegExp('/.*InvalidSerializedValueDetector must implement interface.*/');

        $indexingConfiguration = [
            'topic_stringM' => 'SOLR_CLASSIFICATION'
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
                'multiValue' => true
            ],
            'csv_stringM' => 'SOLR_MULTIVALUE',
            'category_stringM' => 'SOLR_RELATION'
        ];
        $this->assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'topic_stringM'), 'Every value should be treated as serialized by custom detector');
        $this->assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'csv_stringM'), 'Every value should be treated as serialized by custom detector');
        $this->assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'categories_stringM'), 'Every value should be treated as serialized by custom detector');
        $this->assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'category_stringM', 'Every value should be treated as serialized by custom detector'));
        $this->assertTrue(AbstractIndexer::isSerializedValue($indexingConfiguration, 'notConfigured_stringM', 'Every value should be treated as serialized by custom detector'));
    }
}