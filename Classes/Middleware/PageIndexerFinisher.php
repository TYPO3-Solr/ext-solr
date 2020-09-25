<?php
namespace ApacheSolrForTypo3\Solr\Middleware;

/***************************************************************
 * Copyright notice
 *
 * (c) 2019 Achim Fritz <achim.fritz@b13.com>
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the textfile GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Http\Stream;

/**
 * Class PageIndexerFinisher
 * @package ApacheSolrForTypo3\Solr\Middleware
 */
class PageIndexerFinisher implements MiddlewareInterface
{

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($request->hasHeader(PageIndexerRequest::SOLR_INDEX_HEADER)) {
            /* @var PageIndexerRequestHandler $pageIndexerRequestHandler */
            $pageIndexerRequestHandler = GeneralUtility::makeInstance(PageIndexerRequestHandler::class);
            $pageIndexerRequestHandler->shutdown();
            $pageIndexResponse = $pageIndexerRequestHandler->getResponse();
            $response = new Response();

            $body = new Stream('php://temp', 'rw');
            $content = $pageIndexResponse->getContent();
            $body->write($content);
            $response = $response
                ->withBody($body)
                ->withHeader('Content-Length',  (string)strlen($content))
                ->withHeader('Content-Type',  'application/json');
        }
        return $response;
    }

}
