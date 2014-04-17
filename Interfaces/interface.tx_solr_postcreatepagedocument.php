<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2014 Steffen Ritter <steffen.ritter@typo3.org>
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
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * Subsitute page indexer interface, describes the method an indexer must
 * implement to provide a substitute page document
 *
 * @author	Steffen Ritter <steffen.ritter@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
interface Tx_Solr_PostCreatePageDocument {

	/**
	 * Allows Modification of the PageDocument
	 * Can be used to trigger actions when all contextual variables of the pageDocument to be indexed are known
	 *
	 * @param \Apache_Solr_Document $pageDocument the generated page document
	 * @param \tslib_fe $page the page object with information about page id or language
	 * @param string $pageUrl the URL of the page
	 * @param \Tx_Solr_Access_Rootline $pageAccessRootline the access-rootline of the current indexed page
	 *
	 * @return void
	 */
	public function pageDocumentCreated(\Apache_Solr_Document $pageDocument, \tslib_fe $page, $pageUrl, \Tx_Solr_Access_Rootline $pageAccessRootline);

}
