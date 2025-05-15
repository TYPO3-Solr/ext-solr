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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Middleware;

use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\Middleware\SolrRoutingMiddleware;
use ApacheSolrForTypo3\Solr\Routing\RoutingService;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Routing\PageSlugCandidateProvider;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Test case to validate the behaviour of the middle ware
 */
#[CoversClass(SolrRoutingMiddleware::class)]
class SolrRoutingMiddlewareTest extends SetUpUnitTestCase
{
    protected RoutingService|MockObject $routingServiceMock;
    protected $responseOutputHandler;

    protected function setUp(): void
    {
        $this->routingServiceMock = $this->getMockBuilder(RoutingService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSiteMatcher', 'getSlugCandidateProvider', 'fetchEnhancerInSiteConfigurationByPageUid'])
            ->getMock();

        /* @see \TYPO3\CMS\Frontend\Tests\Unit\Middleware\PageResolverTest::setUp */
        $this->responseOutputHandler = new class () implements RequestHandlerInterface {
            protected ServerRequestInterface $request;
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;
                return new NullResponse();
            }

            /**
             * This method is required since we want to know how the URI changed inside
             */
            public function getRequest(): ServerRequestInterface
            {
                return $this->request;
            }
        };

        parent::setUp();
    }

    #[Test]
    public function missingEnhancerHasNoEffectTest(): void
    {
        $serverRequest = new ServerRequest(
            'GET',
            'https://domain.example/facet/bar,buz,foo',
        );
        $siteMatcherMock = $this->getMockBuilder(SiteMatcher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['matchRequest'])
            ->getMock();

        $siteMatcherMock->expects(self::any())
            ->method('matchRequest')
            ->willReturn(
                new SiteRouteResult(
                    new Uri('https://domain.example/facet/bar,buz,foo'),
                    new Site('website', 1, []),
                    new SiteLanguage(0, 'en_US', new Uri('https://domain.example/'), []),
                ),
            );
        $this->routingServiceMock->expects(self::exactly(1))
            ->method('getSiteMatcher')
            ->willReturn($siteMatcherMock);

        $pageSlugCandidateMock = $this->getMockBuilder(PageSlugCandidateProvider::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCandidatesForPath'])
            ->getMock();
        $pageSlugCandidateMock->expects(self::atLeastOnce())
            ->method('getCandidatesForPath')
            ->willReturn([['slug' => '/facet', 'uid' => '1']]);
        $this->routingServiceMock->expects(self::atLeastOnce())
            ->method('getSlugCandidateProvider')
            ->willReturn($pageSlugCandidateMock);
        $this->routingServiceMock->expects(self::atLeastOnce())
            ->method('fetchEnhancerInSiteConfigurationByPageUid')
            ->willReturn([]);

        $solrRoutingMiddleware = new SolrRoutingMiddleware();
        $solrRoutingMiddleware->setLogger(new NullLogger());
        $solrRoutingMiddleware->injectRoutingService($this->routingServiceMock);
        $solrRoutingMiddleware->process(
            $serverRequest,
            $this->responseOutputHandler,
        );
        $request = $this->responseOutputHandler->getRequest();
        $uri = $request->getUri();

        self::assertEquals(
            '/facet/bar,buz,foo',
            $uri->getPath(),
        );
    }

    #[Test]
    public function enhancerInactiveDuringIndexingTest(): void
    {
        $serverRequest = new ServerRequest(
            'GET',
            'https://domain.example/',
            [
                PageIndexerRequest::SOLR_INDEX_HEADER => '1',
            ],
        );

        $this->routingServiceMock->expects(self::never())->method('getSiteMatcher');
        $solrRoutingMiddleware = new SolrRoutingMiddleware();
        $solrRoutingMiddleware->setLogger(new NullLogger());
        $solrRoutingMiddleware->injectRoutingService($this->routingServiceMock);
        $solrRoutingMiddleware->process(
            $serverRequest,
            $this->responseOutputHandler,
        );
    }
}
