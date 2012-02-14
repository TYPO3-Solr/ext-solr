<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2012 Ingo Renner <ingo@typo3.org>
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


/**
 * Index Queue Page Indexer request test.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_indexqueue_PageIndexerRequestTestCase extends tx_phpunit_testcase {

	/**
	 * @test
	 */
	public function authenticatesValidRequest() {
		$header = json_encode(array(
			'item' => 1,
			'page' => 1,
			'hash' => md5('1|1|' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'])
		));

		$request = t3lib_div::makeInstance('tx_solr_indexqueue_PageIndexerRequest', $header);
		$this->assertTrue($request->isAuthenticated());
	}

	/**
	 * @test
	 */
	public function doesNotAuthenticateInvalidRequest() {
		$header = json_encode(array(
			'item' => 1,
			'page' => 1,
			'hash' => md5('invalid|invalid|invalid')
		));

		$request = t3lib_div::makeInstance('tx_solr_indexqueue_PageIndexerRequest', $header);
		$this->assertFalse($request->isAuthenticated());
	}

	/**
	 * @test
	 */
	public function usesUniqueIdFromHeader() {
		$id     = uniqid();
		$header = json_encode(array(
			'requestId' => $id
		));

		$request = t3lib_div::makeInstance('tx_solr_indexqueue_PageIndexerRequest', $header);
		$this->assertEquals($id, $request->getRequestId());
	}
}


?>