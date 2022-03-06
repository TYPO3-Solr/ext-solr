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

/*

    Provides the sitehash for a given domain, valid for the current TYPO3
    installation.

    Example: http://www.my-typo3-solr-installation.com/index.php?eID=tx_solr_api&api=siteHash&apiKey=<API key>&domain=www.domain-to-index.com

*/

use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

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
