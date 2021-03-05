<?php
declare(strict_types=1);
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Middleware;

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

use ApacheSolrForTypo3\Solr\Middleware\SolrRoutingMiddleware;
use ApacheSolrForTypo3\Solr\Routing\RoutingService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
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
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class SolrRoutingMiddlewareTest extends UnitTest
{
    /**
     * @var RoutingService
     */
    protected $routingServiceMock;

    /**
     * @var RequestHandlerInterface
     */
    protected $responseOutputHandler;

    public function setUp()
    {
        $this->routingServiceMock = $this->getMockBuilder(RoutingService::class)
            ->disableOriginalConstructor()
            ->setMethods(['getSiteMatcher', 'getSlugCandidateProvider', 'fetchEnhancerInSiteConfigurationByPageUid'])
            ->getMock();

        /* @see \TYPO3\CMS\Frontend\Tests\Unit\Middleware\PageResolverTest::setUp */
        $this->responseOutputHandler = new class() implements RequestHandlerInterface {
            /**
             * @var ServerRequestInterface
             */
            protected $request;
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;
                return new NullResponse();
            }

            /**
             * This method is required since we wand to know how the URI changed inside
             *
             * @return ServerRequestInterface
             */
            public function getRequest(): ServerRequestInterface
            {
                return $this->request;
            }
        };
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Middleware\SolrRoutingMiddleware::process
     */
    public function missingEnhancerHasNoEffectTest()
    {
        $serverRequest = new ServerRequest(
            'GET',
            'https://domain.example/facet/bar,buz,foo'
        );
        $siteMatcherMock = $this->getMockBuilder(SiteMatcher::class)
            ->disableOriginalConstructor()
            ->setMethods(['matchRequest'])
            ->getMock();

        $siteMatcherMock->expects($this->any())
            ->method('matchRequest')
            ->willReturn(
                new SiteRouteResult(
                    new Uri('https://domain.example/facet/bar,buz,foo'),
                    new Site('website', 1, []),
                    new SiteLanguage(0, 'en_US', new Uri('https://domain.example/'), [])
                )
            );
        $this->routingServiceMock->expects($this->exactly(1))
            ->method('getSiteMatcher')
            ->willReturn($siteMatcherMock);

        $pageSlugCandidateMock = $this->getMockBuilder(PageSlugCandidateProvider::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCandidatesForPath'])
            ->getMock();
        $pageSlugCandidateMock->expects($this->atLeastOnce())
            ->method('getCandidatesForPath')
            ->willReturn([['slug' => '/facet', 'uid' => '1']]);
        $this->routingServiceMock->expects($this->atLeastOnce())
            ->method('getSlugCandidateProvider')
            ->willReturn($pageSlugCandidateMock);
        $this->routingServiceMock->expects($this->atLeastOnce())
            ->method('fetchEnhancerInSiteConfigurationByPageUid')
            ->willReturn([]);

        $solrRoutingMiddleware = new SolrRoutingMiddleware();
        $solrRoutingMiddleware->setLogger(New NullLogger());
        $solrRoutingMiddleware->injectRoutingService($this->routingServiceMock);
        $solrRoutingMiddleware->process(
            $serverRequest,
            $this->responseOutputHandler
        );
        $request = $this->responseOutputHandler->getRequest();
        /* @var Uri $uri */
        $uri = $request->getUri();

        $this->assertEquals(
            '/facet/bar,buz,foo',
            $uri->getPath()
        );
    }
}
