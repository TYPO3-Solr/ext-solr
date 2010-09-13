<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2010 Daniel Poetzinger <poetzinger@aoemedia.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
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

require_once($GLOBALS['PATH_solr'] . 'classes/fieldprocessor/class.tx_solr_fieldprocessor_service.php');
require_once($GLOBALS['PATH_solr'] . 'lib/SolrPhpClient/Apache/Solr/Document.php');


/**
 * tests the processing Service class
 *
 * @author Daniel Poetzinger <poetzinger@aoemedia.de>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_fieldprocessor_Service_testcase extends tx_phpunit_testcase {

	/**
	 * @var	Apache_Solr_Document
	 */
	private $documentMock;

	/**
	 * the service
	 *
	 * @var	tx_solr_fieldprocessor_Service
	 */
	private $service;

	public function setUp() {
		$this->documentMock = new Apache_Solr_Document();
		$this->service = new tx_solr_fieldprocessor_Service();
	}

	/**
	 * @test
	 */
	public function canProcessDocument() {
		$this->documentMock->addField('fieldname', 'fieldvalue');
		$configuration = array('fieldname' => 'uppercase');

		$this->service->processDocument($this->documentMock, $configuration);
		$value = $this->documentMock->getField('fieldname');
		$this->assertEquals(
			$value['value'][0],
			'FIELDVALUE',
			'field was not processed with uppercase'
		);
	}

}

?>