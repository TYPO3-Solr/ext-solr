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

use ApacheSolrForTypo3\Solr\Api;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

$api = GeneralUtility::_GP('api');
$apiKey = trim(GeneralUtility::_GP('apiKey'));

if (!Api::isValidApiKey($apiKey)) {
    header(HttpUtility::HTTP_STATUS_403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['errorMessage' => 'Invalid API key']);
} else {
    switch ($api) {

        case 'siteHash':
            include('SiteHash.php');
            break;

        default:
            header(HttpUtility::HTTP_STATUS_400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['errorMessage' => 'You must provide an available API method, e.g. siteHash.']);
            break;
    }
}

exit();
