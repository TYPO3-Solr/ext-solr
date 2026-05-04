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

namespace ApacheSolrForTypo3\Solr\EventListener\SolariumRequest;

use Solarium\Core\Client\Request;
use Solarium\Core\Event\PreExecuteRequest;
use TYPO3\CMS\Core\Attribute\AsEventListener;

use function strlen;

/**
 * Bridges Solarium's PostBigRequest plugin onto TYPO3's PSR-14 event dispatcher.
 *
 * Solarium's PostBigRequest plugin only registers its listener when the dispatcher
 * is a Symfony EventDispatcherInterface. TYPO3 ships a PSR-14 dispatcher that does
 * not extend that Symfony interface, so the plugin call inside SolrConnection is a
 * silent no-op. This listener performs the same conversion (GET to POST when the
 * query string exceeds the threshold) and works directly with TYPO3's dispatcher.
 *
 * @noinspection PhpUnused Listener for {@link PreExecuteRequest}
 */
final readonly class PostBigRequestListener
{
    public const DEFAULT_MAX_QUERY_STRING_LENGTH = 1024;

    public function __construct(
        private int $maxQueryStringLength = self::DEFAULT_MAX_QUERY_STRING_LENGTH,
    ) {}

    #[AsEventListener(
        identifier: 'solr.solarium.post-big-request',
    )]
    public function __invoke(PreExecuteRequest $event): void
    {
        $request = $event->getRequest();

        if ($request->getMethod() !== Request::METHOD_GET) {
            return;
        }

        $queryString = $request->getQueryString();
        if (strlen($queryString) <= $this->maxQueryStringLength) {
            return;
        }

        $charset = $request->getParam('ie') ?? 'utf-8';

        $request->setMethod(Request::METHOD_POST);
        $request->setContentType(Request::CONTENT_TYPE_APPLICATION_X_WWW_FORM_URLENCODED, ['charset' => $charset]);
        $request->setRawData($queryString);
        $request->clearParams();
    }
}
