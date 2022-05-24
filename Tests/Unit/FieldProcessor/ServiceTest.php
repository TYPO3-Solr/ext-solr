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
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * tests the processing Service class
 *
 * @author Daniel Poetzinger <poetzinger@aoemedia.de>
 */
class ServiceTest extends UnitTest
{

    /**
     * @var Document
     */
    protected $documentMock;

    /**
     * the service
     *
     * @var Service
     */
    protected $service;

    protected function setUp(): void
    {
        $this->documentMock = new Document();
        $this->service = new Service();
        parent::setUp();
    }

    /**
     * @test
     */
    public function transformsStringToUppercaseOnSingleValuedField()
    {
        $this->documentMock->setField('stringField', 'stringvalue');
        $configuration = ['stringField' => 'uppercase'];

        $this->service->processDocument($this->documentMock, $configuration);
        self::assertEquals(
            $this->documentMock['stringField'],
            'STRINGVALUE',
            'field was not processed with uppercase'
        );
    }

    /**
     * @test
     */
    public function transformsStringToUppercaseOnMultiValuedField()
    {
        $this->documentMock->addField('stringField', 'stringvalue_1');
        $this->documentMock->addField('stringField', 'stringvalue_2');
        $configuration = ['stringField' => 'uppercase'];

        $this->service->processDocument($this->documentMock, $configuration);
        self::assertEquals(
            $this->documentMock['stringField'],
            ['STRINGVALUE_1', 'STRINGVALUE_2'],
            'field was not processed with uppercase'
        );
    }

    /**
     * @test
     */
    public function transformsUnixTimestampToIsoDateOnSingleValuedField()
    {
        $this->documentMock->setField(
            'dateField',
            '1262343600'
        ); // 2010-01-01 12:00
        $configuration = ['dateField' => 'timestampToIsoDate'];

        $this->service->processDocument($this->documentMock, $configuration);
        self::assertEquals(
            $this->documentMock['dateField'],
            '2010-01-01T12:00:00Z',
            'field was not processed with timestampToIsoDate'
        );
    }

    /**
     * @test
     */
    public function transformsUnixTimestampToIsoDateOnMultiValuedField()
    {
        $this->documentMock->addField(
            'dateField',
            '1262343600'
        ); // 2010-01-01 12:00
        $this->documentMock->addField(
            'dateField',
            '1262343601'
        ); // 2010-01-01 12:01
        $configuration = ['dateField' => 'timestampToIsoDate'];

        $this->service->processDocument($this->documentMock, $configuration);
        self::assertEquals(
            $this->documentMock['dateField'],
            ['2010-01-01T12:00:00Z', '2010-01-01T12:00:01Z'],
            'field was not processed with timestampToIsoDate'
        );
    }
}
