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
        //$data = Yaml::parse($this->getFixtureContentByName('siteConfiguration.yaml'));
        $this->routingServiceMock = $this->getDumbMock(RoutingService::class);
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