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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\EventListener\SolariumRequest;

use ApacheSolrForTypo3\Solr\EventListener\SolariumRequest\PostBigRequestListener;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use Solarium\Core\Client\Endpoint;
use Solarium\Core\Client\Request;
use Solarium\Core\Event\PreExecuteRequest;

final class PostBigRequestListenerTest extends SetUpUnitTestCase
{
    #[Test]
    public function shortGetRequestIsNotRewritten(): void
    {
        $listener = new PostBigRequestListener(maxQueryStringLength: 1024);
        $request = $this->makeRequest(Request::METHOD_GET, ['q' => '*:*', 'rows' => '0']);

        ($listener)(new PreExecuteRequest($request, new Endpoint()));

        self::assertSame(Request::METHOD_GET, $request->getMethod());
        self::assertSame('q=%2A%3A%2A&rows=0', $request->getQueryString());
        self::assertNull($request->getRawData());
    }

    #[Test]
    public function longGetRequestBecomesPostWithBodyAndClearedParams(): void
    {
        $listener = new PostBigRequestListener(maxQueryStringLength: 32);
        $params = [
            'q' => '*:*',
            'facet.field' => array_map(static fn(int $i): string => 'someFacetableField_' . $i, range(1, 50)),
        ];
        $request = $this->makeRequest(Request::METHOD_GET, $params);
        $expectedBody = $request->getQueryString();

        ($listener)(new PreExecuteRequest($request, new Endpoint()));

        self::assertSame(Request::METHOD_POST, $request->getMethod());
        self::assertSame($expectedBody, $request->getRawData());
        self::assertSame('', $request->getQueryString());
        self::assertSame(Request::CONTENT_TYPE_APPLICATION_X_WWW_FORM_URLENCODED, $request->getContentType());
        self::assertSame(['charset' => 'utf-8'], $request->getContentTypeParams());
    }

    #[Test]
    public function postRequestIsLeftUntouchedRegardlessOfBodyLength(): void
    {
        $listener = new PostBigRequestListener(maxQueryStringLength: 16);
        $request = $this->makeRequest(Request::METHOD_POST, ['q' => 'something_quite_long_indeed_xxxxxxxxxxxxxxxxxxxxxxxx']);

        ($listener)(new PreExecuteRequest($request, new Endpoint()));

        self::assertSame(Request::METHOD_POST, $request->getMethod());
    }

    /** @param array<string, scalar|list<string>> $params */
    private function makeRequest(string $method, array $params): Request
    {
        $request = new Request();
        $request->setMethod($method);
        $request->setParams($params);

        return $request;
    }
}
