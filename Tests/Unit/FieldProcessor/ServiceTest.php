<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\FieldProcessor;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Daniel Poetzinger <poetzinger@aoemedia.de>
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

    public function setUp()
    {
        date_default_timezone_set('Europe/Berlin');
        $this->documentMock = new Document();
        $this->service = new Service();
    }

    /**
     * @test
     */
    public function transformsStringToUppercaseOnSingleValuedField()
    {
        $this->documentMock->setField('stringField', 'stringvalue');
        $configuration = ['stringField' => 'uppercase'];

        $this->service->processDocument($this->documentMock, $configuration);
        $this->assertEquals(
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
        $this->assertEquals(
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
        $this->documentMock->setField('dateField',
            '1262343600'); // 2010-01-01 12:00
        $configuration = ['dateField' => 'timestampToIsoDate'];

        $this->service->processDocument($this->documentMock, $configuration);
        $this->assertEquals(
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
        $this->documentMock->addField('dateField',
            '1262343600'); // 2010-01-01 12:00
        $this->documentMock->addField('dateField',
            '1262343601'); // 2010-01-01 12:01
        $configuration = ['dateField' => 'timestampToIsoDate'];

        $this->service->processDocument($this->documentMock, $configuration);
        $this->assertEquals(
            $this->documentMock['dateField'],
            ['2010-01-01T12:00:00Z', '2010-01-01T12:00:01Z'],
            'field was not processed with timestampToIsoDate'
        );
    }
}
