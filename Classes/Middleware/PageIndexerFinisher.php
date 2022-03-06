<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Middleware;

use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class PageIndexerFinisher
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
                ->withHeader('Content-Length', (string)strlen($content))
                ->withHeader('Content-Type', 'application/json');
        }
        return $response;
    }
}
