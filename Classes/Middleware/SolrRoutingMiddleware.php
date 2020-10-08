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
use TYPO3\CMS\Core\Site\SiteFinder;
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
     * List of query parameters to ignore
     *
     * @var array
     */
    protected $ignoreQueryParameters = [];

    /**
     * Masque alle parameters with the given Solr key
     *
     * @var bool
     */
    protected $masqueParameter = true;

    /**
     * @var SiteLanguage
     */
    protected $language = null;

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
        [$slug, $parameters] = $this->getSlugAndParameters(
            $request->getUri(),
            $enhancerConfiguration['routePath'],
            (string)$page['slug']
        );

        // No parameter exists -> Skip
        if (count($parameters) === 0) {
            return $handler->handle($request);
        }

        /*
         * Map arguments against the argument configuration
         */
        $request = $this->enrichUriByPathArguments(
            $request,
            $enhancerConfiguration['_arguments'],
            $parameters
        );
        $uri = $request->getUri();

        // TODO: Explode query parameters if they are joined!

        /*
         * Replace internal URI with existing site taken from path information
         * We removed a possible path segment from the slug, that again needs to attach.
         *
         * NOTE: TypoScript is not available at this point!
         */
        $uri = $uri->withPath(
            $this->language->getBase()->getPath() .
            (string)$page['slug']
        );
        $request = $request->withUri($uri);

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
    protected function getSlugAndParameters(UriInterface $uri, string $path, string $pageSlug): array
    {
        if ($uri->getPath() === $pageSlug) {
            return [
                $pageSlug,
                []
            ];
        }

        $uriElements = explode('/', $uri->getPath());
        $routeElements = explode('/', $path);
        $slugElements = [];
        $arguments = [];
        $process = true;
        do {
            if (count($uriElements) >= count($routeElements)) {
                $slugElements[] = array_shift($uriElements);
            } else {
                $process = false;
            }
        } while ($process);

        if (empty($routeElements[0])) {
            array_shift($routeElements);
        }

        // Extract the values
        for ($i = 0; $i < count($uriElements); $i++) {
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
     * Enrich the current query Params with data from path information
     *
     * @param ServerRequestInterface $request
     * @param array $arguments
     * @param array $parameters
     * @return ServerRequestInterface
     */
    protected function enrichUriByPathArguments(
        ServerRequestInterface $request,
        array $arguments,
        array $parameters
    ): ServerRequestInterface {
        $queryParams = $request->getQueryParams();
        foreach ($arguments as $fieldName => $queryPath) {
            // Skip if there is no parameter
            if (!isset($parameters[$fieldName])) {
                continue;
            }
            $pathElements = explode('/', $queryPath);

            if (!empty($this->namespace)) {
                array_unshift($pathElements, $this->namespace);
            }
            $queryParams = $this->processUriPathArgument(
                $queryParams,
                $fieldName,
                $parameters,
                $pathElements
            );
        }

        return $request->withQueryParams($queryParams);
    }

    /**
     * Converts path segment information into query parameters
     *
     * Example:
     * /products/household
     *
     * tx_solr:
     *      filter:
     *          - type:household
     *
     * @param array $queryParams
     * @param string $fieldName
     * @param array $parameters
     * @param array $pathElements
     * @return array
     */
    protected function processUriPathArgument(
        array $queryParams,
        string $fieldName,
        array $parameters,
        array $pathElements
    ): array {
        $queryKey = array_shift($pathElements);

        if (!isset($queryParams[$queryKey]) || $queryParams[$queryKey] === null) {
            $queryParams[$queryKey] = [];
        }

        if (strpos($queryKey, '-') !== false) {
            [$queryKey, $filterName] = explode('-', $queryKey, 2);

            // explode multiple values
            $values = explode(
                RoutingUtility::getFacetValueSeparator($this->settings),
                $parameters[$fieldName]
            );
            // @TODO: Support URL data bag
            foreach ($values as $value) {
                $queryParams[$queryKey][] = $filterName . ':' . $value;
            }
        } else {
            $queryParams[$queryKey] = $this->processUriPathArgument(
                $queryParams[$queryKey],
                $fieldName,
                $parameters,
                $pathElements
            );
        }

        return $queryParams;
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
        $path = $this->stripLanguagePrefixFromPath($uri->getPath());
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
     * In order to search for a path, a possible language prefix need to remove
     *
     * @param string $path
     * @return string
     */
    protected function stripLanguagePrefixFromPath(string $path): string
    {
        if ($this->language->getBase()->getPath() === '/') {
            return $path;
        }

        $pathLength = mb_strlen($this->language->getBase()->getPath());

        $path = mb_substr($path, $pathLength, mb_strlen($path) - $pathLength);
        if (mb_substr($path, 0, 1) !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    /**
     * Returns the current language
     * @TODO Improvement: Currently we expect that the longest length for base is at the end of the language array
     *       This may be incorrect and lead to wrong results.
     *
     * @param Site $site
     * @param UriInterface $uri
     */
    protected function determineSiteLanguage(Site $site, UriInterface $uri)
    {
        if ($this->language instanceof SiteLanguage) {
            return;
        }
        $configuration = $site->getConfiguration();
        if (empty($configuration) || empty($configuration['languages']) || !is_array($configuration['languages'])) {
            $this->logger
                ->info('No language configuration available! Return default language');
            $this->language = $site->getDefaultLanguage();
            return;
        }
        $this->language = $site->getDefaultLanguage();
        $languageId = -1;
        $languages = array_reverse($configuration['languages']);

        foreach ($languages as $language) {
            if (empty($language['base'])) {
                continue;
            }

            // Base could be a path segment or a URL
            if (mb_substr($language['base'], 0, 1) === '/') {
                /*
                 * Only the path segment need to be checked
                 */
                if (mb_substr($uri->getPath(), 0, mb_strlen($language['base'])) === $language['base']) {
                    $languageId = (int)$language['languageId'];
                    break;
                }
            } else {
                /*
                 * There different versions of a domain are possible
                 * - http://domain.example
                 * - https://domain.example
                 * - ://domain.example
                 *
                 * It is possible that the base contains a path too.
                 * In order to keep it simple as possible, we convert the base into an URI object
                 */

                try {
                    $baseUri = new Uri($language['base']);

                    // Host not match ... base is not what we are looking for
                    if ($baseUri->getHost() !== $uri->getHost()) {
                        continue;
                    }
                    // Path is configured but does not match ... base is not what we are looking for
                    if (!empty($baseUri->getPath()) &&
                        mb_substr($uri->getPath(), 0, mb_strlen($baseUri->getPath())) !== $baseUri->getPath()) {
                        continue;
                    }

                    $languageId = (int)$language['languageId'];
                } catch (\Exception $exception) {
                    // Base could not be parsed as a URI
                    $this->logger
                        ->error(vsprintf('Could not parse language base "%1$s" as URI', [$language['base']]));
                }
            }
        }

        if ($languageId > 0) {
            try {
                $this->language = $site->getLanguageById($languageId);
            } catch (\InvalidArgumentException $invalidArgumentException) {
                $this->logger
                    ->error(vsprintf('Could not find language by ID "%1$s"', [$languageId]));
            }
        }
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
        return GeneralUtility::makeInstance(RoutingService::class);
    }
}