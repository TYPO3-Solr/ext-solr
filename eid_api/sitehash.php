<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Stefan Sprenger <stefan.sprenger@dkd.de>
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

/*

	Provides the sitehash for a given domain, valid for the current TYPO3
	installation.

	Example: http://www.my-typo3-solr-installation.com/index.php?eID=tx_solr_api&api=siteHash&apiKey=<API key>&domain=www.domain-to-index.com

*/


$domain     = t3lib_div::_GP('domain');
$returnData = '';

if (!empty($domain)) {
	$siteHash   = tx_solr_Util::getSiteHashForDomain($domain);
	$returnData = json_encode(array('sitehash' => $siteHash));
} else {
	header(t3lib_utility_Http::HTTP_STATUS_400);

	$errorMessage = 'You have to provide an existing domain, e.g. www.example.com.';

	$returnData = json_encode(array('errorMessage' => $errorMessage));
}

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Content-Type: application/json; charset=utf-8');
header('Content-Transfer-Encoding: 8bit');
header('Content-Length: ' . strlen($returnData));

echo $returnData;

?>