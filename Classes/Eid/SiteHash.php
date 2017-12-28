<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Stefan Sprenger <stefan.sprenger@dkd.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

/*

    Provides the sitehash for a given domain, valid for the current TYPO3
    installation.

    Example: http://www.my-typo3-solr-installation.com/index.php?eID=tx_solr_api&api=siteHash&apiKey=<API key>&domain=www.domain-to-index.com

*/

use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$domain = GeneralUtility::_GP('domain');
$returnData = '';

if (!empty($domain)) {
    /** @var $siteHashService SiteHashService */
    $siteHashService = GeneralUtility::makeInstance(SiteHashService::class);
    $siteHash = $siteHashService->getSiteHashForDomain($domain);
    $returnData = json_encode(['sitehash' => $siteHash]);
} else {
    header(HttpUtility::HTTP_STATUS_400);

    $errorMessage = 'You have to provide an existing domain, e.g. www.example.com.';

    $returnData = json_encode(['errorMessage' => $errorMessage]);
}

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Content-Type: application/json; charset=utf-8');
header('Content-Transfer-Encoding: 8bit');
header('Content-Length: ' . strlen($returnData));

echo $returnData;
