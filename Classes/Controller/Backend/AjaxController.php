<?php
declare(strict_types=1);
namespace ApacheSolrForTypo3\Solr\Controller\Backend;

/***************************************************************
 *  Copyright notice
 *
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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Http\Response;

/**
 * Handling of Ajax requests
 */
class AjaxController
{
    /**
     * Update a single solr connection
     *
     * @deprecated Configuring solr connections with TypoScript is deprecated please use the site handling. Will be dropped with EXT:solr 11
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function updateConnection(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        trigger_error('solr:deprecation: Configuring solr connections with TypoScript is deprecated please use the site handling', E_USER_DEPRECATED);

        $queryParams = $request->getQueryParams();
        $pageId = 0;
        if (isset($queryParams['id'])) {
            $pageId = (int)$queryParams['id'];
        }

        // Currently no return value from connection manager
        $content = [
            'success' => true,
            'message' => 'Solr connection has been updated'
        ];
        if ($pageId) {
            $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
            $connectionManager->updateConnectionByRootPageId($pageId);
        }

        $response->getBody()->write(json_encode($content));
        return $response;
    }

    /**
     * Update all connections
     *
     * @deprecated Configuring solr connections with TypoScript is deprecated please use the site handling. Will be dropped with EXT:solr 11
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function updateConnections(ServerRequestInterface $request): ResponseInterface
    {
        trigger_error('solr:deprecation: Configuring solr connections with TypoScript is deprecated please use the site handling', E_USER_DEPRECATED);

        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        $connectionManager->updateConnections();
        // Currently no return value from connection manager
        return new Response();
    }

}
