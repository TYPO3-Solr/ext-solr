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

use ApacheSolrForTypo3\Solr\Routing\RoutingService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Middleware to create beautiful URLs for Solr
 *
 * How to use:
 * Inside your extension create the following file
 * Configuration/RequestMiddlewares.php
 *
 * return [
 *   'frontend' => [
 *     'apache-solr-for-typo3/solr-route-enhancer' => [
 *       'target' => \ApacheSolrForTypo3\Solr\Middleware\SolrRoutingMiddleware::class,
 *       'before' => [
 *         'typo3/cms-frontend/site',
 *       ]
 *     ]
 *   ]
 * ];
 *
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/RequestHandling/Index.html
 */
final readonly class SolrRoutingMiddleware implements MiddlewareInterface
{
    private const DEFAULT_NAMESPACE = 'tx_solr';

    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $context = $this->resolveContext($request);
        if ($context === null) {
            return $handler->handle($request);
        }

        $routingService = $this->getRoutingService(
            $context->getEnhancerConfiguration()['solr'] ?? [],
            $context->getEnhancerConfiguration()['extensionKey'] ?? self::DEFAULT_NAMESPACE,
        );

        // Take slug path segments and argument from incoming URI
        $parameters = $this->extractParametersFromUriPath($request->getUri(), $context, $routingService);

        // Convert path arguments to query arguments
        if ($parameters !== []) {
            $request = $routingService->addPathArgumentsToQuery(
                $request,
                $context->getEnhancerConfiguration()['_arguments'],
                $parameters,
            );
        }

        /*
         * Replace internal URI with existing site taken from path information
         * We removed a possible path segment from the slug, that again needs to attach.
         *
         * NOTE: TypoScript is not available at this point!
         */
        if ($context->getPage()['slug'] !== '/') {
            $uri = $request->getUri()->withPath(
                $routingService->cleanupHeadingSlash(
                    $context->getSiteLanguage()->getBase()->getPath() . $context->getPage()['slug'],
                ),
            );
            $request = $request->withUri($uri);
        }

        $queryParams = $request->getQueryParams();
        $queryParams = $routingService->unmaskQueryParameters($queryParams);
        $queryParams = $routingService->inflateQueryParameter($queryParams);

        // @todo Drop cHash, but need to recalculate
        if (array_key_exists('cHash', $queryParams)) {
            unset($queryParams['cHash']);
        }

        $request = $request->withQueryParams($queryParams);

        return $handler->handle($request);
    }

    private function resolveContext(ServerRequestInterface $request): ?SolrRoutingContext
    {
        if ($request->getAttribute('solr.indexingInstructions') !== null) {
            return null;
        }

        $unconfiguredRoutingService = $this->getRoutingService([], self::DEFAULT_NAMESPACE);
        $siteRouteResult = $unconfiguredRoutingService
            ->getSiteMatcher()
            ->matchRequest($request);

        if (!$siteRouteResult instanceof SiteRouteResult) {
            return null;
        }

        if (!$siteRouteResult->getSite() instanceof Site) {
            return null;
        }

        $site = $siteRouteResult->getSite();
        $siteLanguage = $siteRouteResult->getLanguage();

        if (!$siteLanguage instanceof SiteLanguage) {
            return null;
        }

        $page = $this->retrievePageInformation(
            $request->getUri(),
            $site,
            $siteLanguage,
            $unconfiguredRoutingService,
        );

        if (empty($page['uid'])) {
            return null;
        }

        $enhancerConfiguration = $this->getEnhancerConfiguration(
            $siteLanguage->getLanguageId() === 0 ? (int)$page['uid'] : (int)$page['l10n_parent'],
            $site,
            $unconfiguredRoutingService,
        );

        if ($enhancerConfiguration === null) {
            return null;
        }

        return new SolrRoutingContext($site, $siteLanguage, $page, $enhancerConfiguration);
    }

    /**
     * Retrieve the enhancer configuration for a given site
     */
    private function getEnhancerConfiguration(
        int $pageUid,
        Site $site,
        RoutingService $unconfiguredRoutingService,
    ): ?array {
        $enhancers = $unconfiguredRoutingService->fetchEnhancerInSiteConfigurationByPageUid(
            $site,
            $pageUid,
        );

        if ($enhancers === []) {
            return null;
        }

        return $enhancers[0];
    }

    /**
     * Extract the slug and all arguments from the path
     */
    private function extractParametersFromUriPath(
        UriInterface $uri,
        SolrRoutingContext $context,
        RoutingService $routingService,
    ): array {
        $path = $context->getEnhancerConfiguration()['routePath'];
        $pageSlug = $context->getPage()['slug'];

        // URI get path returns the path with a given language parameter
        // The parameter pageSlug itself does not contain the language parameter.
        $uriPath = $routingService->stripLanguagePrefixFromPath(
            $context->getSiteLanguage(),
            $uri->getPath(),
        );

        if ($uriPath === $pageSlug) {
            return [
                $pageSlug,
                [],
            ];
        }

        // Remove slug from URI path to ensure only the arguments left
        if (mb_substr($uriPath, 0, mb_strlen($pageSlug) + 1) === $pageSlug . '/') {
            $length = mb_strlen($pageSlug) + 1;
            $uriPath = mb_substr($uriPath, $length, mb_strlen($uriPath) - $length);
        }

        // Take care of the format of configuration and given slug equals
        $uriPath = $routingService->removeHeadingSlash($uriPath);
        $path = $routingService->removeHeadingSlash($path);

        // Remove begin
        $uriElements = explode('/', $uriPath);
        $routeElements = explode('/', $path);
        $arguments = [];

        if (empty($routeElements[0])) {
            array_shift($routeElements);
        }
        if (empty($uriElements[0])) {
            array_shift($uriElements);
        }

        // Extract the values
        $uriElementsCount = count($uriElements);
        for ($i = 0; $i < $uriElementsCount; $i++) {
            // Skip empty elements
            if (empty($uriElements[$i])) {
                continue;
            }

            $key = substr($routeElements[$i], 1, strlen($routeElements[$i]) - 1);
            $key = substr($key, 0, strlen($key) - 1);

            $arguments[$key] = $uriElements[$i];
        }

        return $arguments;
    }

    /**
     * Retrieve the page uid to filter the route enhancer
     */
    private function retrievePageInformation(
        UriInterface $uri,
        Site $site,
        SiteLanguage $siteLanguage,
        RoutingService $unconfiguredRoutingService,
    ): array {
        $path = $unconfiguredRoutingService->stripLanguagePrefixFromPath(
            $siteLanguage,
            $uri->getPath(),
        );

        $slugProvider = $unconfiguredRoutingService->getSlugCandidateProvider($site);
        $scan = true;
        $page = [];
        do {
            $items = $slugProvider->getCandidatesForPath(
                $path,
                $siteLanguage,
            );

            if (empty($items)) {
                if (empty($path)) {
                    $message = 'Could not resolve page by path "%3$s" and language "%2$s".';
                } else {
                    $message = 'Could not determine page for slug "%1$s" and language "%2$s". Given path "%3$s"';
                }

                $this->logger->error(
                    sprintf(
                        $message,
                        $path,
                        $siteLanguage->getLocale()->getLanguageCode(),
                        $uri->getPath(),
                    ),
                );
                $scan = false;
            } elseif (empty($path) && count($items) === 1) {
                $page = $items[0];
                $this->logger->info(
                    sprintf(
                        'Path "%1$s" -> slug "%2$s"',
                        $uri->getPath(),
                        $page['slug'],
                    ),
                );
                $scan = false;
            } else {
                foreach ($items as $item) {
                    $this->logger->info(
                        sprintf(
                            'Path "%1$s" -> slug "%2$s"',
                            $path,
                            $item['slug'],
                        ),
                    );

                    if ($item['slug'] === $path) {
                        $page = $item;
                        $scan = false;
                        break;
                    }
                }

                if ($scan) {
                    $elements = explode('/', $path);
                    if (empty($elements) || $path === '') {
                        $scan = false;
                    } else {
                        array_pop($elements);
                        $path = implode('/', $elements);
                    }
                }
            }
        } while ($scan);

        return $page;
    }

    private function getRoutingService(array $settings, string $namespace): RoutingService
    {
        return GeneralUtility::makeInstance(RoutingService::class, $settings, $namespace);
    }
}
