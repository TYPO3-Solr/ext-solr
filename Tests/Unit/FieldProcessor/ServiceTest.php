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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\FieldProcessor;

use ApacheSolrForTypo3\Solr\FieldProcessor\Service;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * tests the processing Service class
 */
class ServiceTest extends SetUpUnitTestCase
{
    protected Document $documentMock;
    protected Service $service;

    protected function setUp(): void
    {
        $this->documentMock = new Document();
        $this->service = new Service();
        parent::setUp();
    }

    #[Test]
    public function transformsStringToUppercaseOnSingleValuedField(): void
    {
        $this->documentMock->setField('stringField', 'stringvalue');
        $configuration = ['stringField' => 'uppercase'];

        $this->service->processDocument($this->documentMock, $configuration);
        self::assertEquals(
            $this->documentMock['stringField'],
            'STRINGVALUE',
            'field was not processed with uppercase',
        );
    }

    #[Test]
    public function transformsStringToUppercaseOnMultiValuedField(): void
    {
        $this->documentMock->addField('stringField', 'stringvalue_1');
        $this->documentMock->addField('stringField', 'stringvalue_2');
        $configuration = ['stringField' => 'uppercase'];

        $this->service->processDocument($this->documentMock, $configuration);
        self::assertEquals(
            $this->documentMock['stringField'],
            ['STRINGVALUE_1', 'STRINGVALUE_2'],
            'field was not processed with uppercase',
        );
    }

    #[Test]
    public function transformsUnixTimestampToIsoDateOnSingleValuedField(): void
    {
        $this->documentMock->setField(
            'dateField',
            '1262343600',
        ); // 2010-01-01 12:00
        $configuration = ['dateField' => 'timestampToIsoDate'];

        $this->service->processDocument($this->documentMock, $configuration);
        self::assertEquals(
            $this->documentMock['dateField'],
            '2010-01-01T12:00:00Z',
            'field was not processed with timestampToIsoDate',
        );
    }

    #[Test]
    public function transformsUnixTimestampToIsoDateOnMultiValuedField(): void
    {
        $this->documentMock->addField(
            'dateField',
            '1262343600',
        ); // 2010-01-01 12:00
        $this->documentMock->addField(
            'dateField',
            '1262343601',
        ); // 2010-01-01 12:01
        $configuration = ['dateField' => 'timestampToIsoDate'];

        $this->service->processDocument($this->documentMock, $configuration);
        self::assertEquals(
            $this->documentMock['dateField'],
            ['2010-01-01T12:00:00Z', '2010-01-01T12:00:01Z'],
            'field was not processed with timestampToIsoDate',
        );
    }

    #[Test]
    public function customFieldProcessorTurnsFooIntoBar(): void
    {
        $this->documentMock->setField('stringField', 'foo');
        $configuration = ['stringField' => 'turnFooIntoBar'];

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['fieldProcessor']['turnFooIntoBar'] = TestFieldProcessor::class;

        $this->service->processDocument($this->documentMock, $configuration);
        self::assertEquals(
            $this->documentMock['stringField'],
            'bar',
            'field was not processed with TestFieldProcessor',
        );
    }
}
