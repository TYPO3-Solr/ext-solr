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

use ApacheSolrForTypo3\Solr\IndexQueue\IndexingInstructions;
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingResultCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware that scopes the user-group detection phase during findUserGroups sub-requests.
 *
 * Activates user-group detection on the IndexingResultCollector (singleton bridge),
 * which the UserGroupDetector event listeners check to decide whether to remove
 * fe_group constraints and collect groups during rendering.
 *
 * Must run AFTER PrepareTypoScriptFrontendRendering and BEFORE SolrIndexingMiddleware.
 */
readonly class UserGroupDetectionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private IndexingResultCollector $resultCollector,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $instructions = $request->getAttribute('solr.indexingInstructions');
        if (!$instructions instanceof IndexingInstructions || !$instructions->isFindUserGroups()) {
            return $handler->handle($request);
        }

        $this->resultCollector->setUserGroupDetectionActive(true);
        try {
            return $handler->handle($request);
        } finally {
            $this->resultCollector->setUserGroupDetectionActive(false);
        }
    }
}
