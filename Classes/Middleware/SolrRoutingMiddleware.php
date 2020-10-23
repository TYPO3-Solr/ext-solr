<?php
namespace ApacheSolrForTypo3\Solr\Middleware;

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

use ApacheSolrForTypo3\Solr\Routing\RoutingService;
use ApacheSolrForTypo3\Solr\Utility\RoutingUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\PageSlugCandidateProvider;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Middleware to create beautiful URLs for Solr
 *
 * How to use:
 * Inside of your extension create following file
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
 * @author Lars Tode <lars.tode@dkd.de>
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ApiOverview/RequestHandling/Index.html
 */
class SolrRoutingMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Solr parameter key
     *
     * @var string
     */
    protected $namespace = 'tx_solr';

    /**
     * Settings from enhancer configuration
     *
     * @var array
     */
    protected $settings = [];

    /**
     * @var SiteLanguage
     */
    protected $language = null;

    /**
     * @var RoutingService
     */
    protected $routingService;

    /**
     * Inject the routing service.
     * Used in unit tests too
     *
     * @param RoutingService $routingService
     */
    public function injectRoutingService(RoutingService $routingService)
    {
        $this->routingService = $routingService;
    }

    /**
     * Process the request
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $this->getRoutingService()->findSiteByUri($request->getUri());
        if (!($site instanceof Site)) {
            return $handler->handle($request);
        }

        $this->determineSiteLanguage(
            $site,
            $request->getUri()
        );

        $page = $this->retrievePageInformation(
            $request->getUri(),
            $site
        );
        if ((int)$page['uid'] === 0) {
            return $handler->handle($request);
        }
        $enhancerConfiguration = $this->getEnhancerConfiguration(
            $site,
            $this->language->getLanguageId() === 0 ? (int)$page['uid'] : (int)$page['l10n_parent']
        );

        if ($enhancerConfiguration === null) {
            return $handler->handle($request);
        }

        $this->configure($enhancerConfiguration);

        /*
         * Take slug path segments and argument from incoming URI
         */
        [$slug, $parameters] = $this->extractParametersFromUriPath(
            $request->getUri(),
            $enhancerConfiguration['routePath'],
            (string)$page['slug']
        );

        /*
         * Convert path arguments to query arguments
         */
        if (!empty($parameters)) {
            $request = $this->getRoutingService()->addPathArgumentsToQuery(
                $request,
                $enhancerConfiguration['_arguments'],
                $parameters
            );
        }

        /*
         * Replace internal URI with existing site taken from path information
         * We removed a possible path segment from the slug, that again needs to attach.
         *
         * NOTE: TypoScript is not available at this point!
         */
        $uri = $request->getUri()->withPath(
            $this->getRoutingService()->cleanupHeadingSlash(
                $this->language->getBase()->getPath() .
                (string)$page['slug']
            )
        );
        $request = $request->withUri($uri);
        $queryParams = $request->getQueryParams();

        $queryParams = $this->getRoutingService()->unmaskQueryParameters($queryParams);
        $queryParams = $this->getRoutingService()->inflateQueryParameter($queryParams);

        // @todo Drop cHash, but need to recalculate
        if (array_key_exists('cHash', $queryParams)) {
            unset($queryParams['cHash']);
        }

        $request = $request->withQueryParams($queryParams);

        return $handler->handle($request);
    }

    /**
     * Configures the middleware by enhancer configuration
     *
     * @param array $enhancerConfiguration
     */
    protected function configure(array $enhancerConfiguration): void
    {
        $this->settings = $enhancerConfiguration['solr'];
        $this->namespace = isset($enhancerConfiguration['extensionKey']) ?
            $enhancerConfiguration['extensionKey'] :
            $this->namespace;
        $this->routingService = null;
    }

    /**
     * Retrieve the enhancer configuration for given site
     *
     * @param Site $site
     * @param int $pageUid
     * @return array|null
     */
    protected function getEnhancerConfiguration(Site $site, int $pageUid): ?array
    {
        $enhancers = $this->getRoutingService()->fetchEnhancerInSiteConfigurationByPageUid(
            $site,
            $pageUid
        );

        if (empty($enhancers)) {
            return null;
        }

        return $enhancers[0];
    }

    /**
     * Extract the slug and all arguments from path
     *
     * @param UriInterface $uri
     * @param string $path
     * @param string $pageSlug
     * @return array
     */
    protected function extractParametersFromUriPath(UriInterface $uri, string $path, string $pageSlug): array
    {
        // URI get path returns the path with given language parameter
        // The parameter pageSlug itself does not contains the language parameter.
        $uriPath = $this->getRoutingService()->stripLanguagePrefixFromPath(
            $this->language,
            $uri->getPath()
        );

        if ($uriPath === $pageSlug) {
            return [
                $pageSlug,
                []
            ];
        }

        // Remove slug from URI path in order the ensure only the arguments left
        if (mb_substr($uriPath, 0, mb_strlen($pageSlug) + 1) === $pageSlug . '/') {
            $length = mb_strlen($pageSlug) + 1;
            $uriPath = mb_substr($uriPath, $length, mb_strlen($uriPath) - $length);
        }

        // Take care the format of configuration and given slug equals
        $uriPath = $this->getRoutingService()->removeHeadingSlash($uriPath);
        $path = $this->getRoutingService()->removeHeadingSlash($path);

        // Remove begin
        $uriElements = explode('/', $uriPath);
        $routeElements = explode('/', $path);
        $slugElements = [];
        $arguments = [];
        $process = true;
        /*
         * Extract the slug elements, until the the amount of route elements reached
         */
        do {
            if (count($uriElements) > count($routeElements)) {
                $slugElements[] = array_shift($uriElements);
            } else {
                $process = false;
            }
        } while ($process);

        if (empty($routeElements[0])) {
            array_shift($routeElements);
        }
        if (empty($uriElements[0])) {
            array_shift($uriElements);
        }

        // Extract the values
        for ($i = 0; $i < count($uriElements); $i++) {
            // Skip empty elements
            if (empty($uriElements[$i])) {
                continue;
            }

            $key = substr($routeElements[$i], 1, strlen($routeElements[$i]) - 1);
            $key = substr($key, 0, strlen($key) - 1);

            $arguments[$key] = $uriElements[$i];
        }

        return [
            implode('/', $slugElements),
            $arguments
        ];
    }

    /**
     * Retrieve the page uid to filter the route enhancer
     *
     * @param UriInterface $uri
     * @param Site $site
     * @return array
     */
    protected function retrievePageInformation(UriInterface $uri, Site $site): array
    {
        $path = $this->getRoutingService()->stripLanguagePrefixFromPath(
            $this->language,
            $uri->getPath()
        );
        $slugProvider = $this->getSlugCandidateProvider($site);
        $scan = true;
        $page = [];
        do {
            $items = $slugProvider->getCandidatesForPath(
                $path,
                $this->language
            );
            if (empty($items)) {
                $this->logger
                    ->error(
                        vsprintf(
                            'Could not determine page for slug "%1$s" and language "%2$s". Given path "%3$s"',
                            [
                                $path,
                                $this->language->getTwoLetterIsoCode(),
                                $uri->getPath()
                            ]
                        )
                    );
                $scan = false;
            } elseif (empty($path)) {
                $this->logger
                    ->error(
                        vsprintf(
                            'Could resolve page by path "%1$s" and language "%2$s".',
                            [
                                $uri->getPath(),
                                $this->language->getTwoLetterIsoCode()
                            ]
                        )
                    );
                $scan = false;
            } else {
                foreach ($items as $item) {
                    $this->logger
                        ->info(
                            vsprintf(
                                'Path "%1$s" -> slug "%2$s"',
                                [
                                    $path,
                                    $item['slug']
                                ]
                            )
                        );
                    if ($item['slug'] === $path) {
                        $page = $item;
                        $scan = false;
                        break;
                    }
                }

                if ($scan) {
                    $elements = explode('/', $path);
                    if (empty($elements)) {
                        $scan = false;
                    } else {
                        array_pop($elements);
                        $path = implode('/', $elements);
                    }
                }
            }
        } while($scan);
        return $page;
    }

    /**
     * Determine the current language by given site and URI
     *
     * @param Site $site
     * @param UriInterface $uri
     */
    protected function determineSiteLanguage(Site $site, UriInterface $uri)
    {
        if ($this->language instanceof SiteLanguage) {
            return;
        }

        $this->language = $this->getRoutingService()
            ->determineSiteLanguage($site, $uri);
    }

    /**
     * @param Site $site
     * @return PageSlugCandidateProvider
     */
    protected function getSlugCandidateProvider(Site $site): PageSlugCandidateProvider
    {
        $context = GeneralUtility::makeInstance(Context::class);
        return GeneralUtility::makeInstance(
            PageSlugCandidateProvider::class,
            $context,
            $site,
            null
        );
    }

    /**
     * @return RoutingService
     */
    protected function getRoutingService(): RoutingService
    {
        if (!($this->routingService instanceof RoutingService)) {
            $this->routingService = GeneralUtility::makeInstance(
                RoutingService::class,
                $this->settings,
                $this->namespace
            );
        } else {
            $this->routingService = $this->routingService->withSettings($this->settings);
        }
        return $this->routingService;
    }
}
