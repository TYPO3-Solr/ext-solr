<?php

use ApacheSolrForTypo3\Solr\Middleware\PageIndexerInitialization;
use ApacheSolrForTypo3\Solr\Middleware\SolrIndexingMiddleware;
use ApacheSolrForTypo3\Solr\Middleware\SolrRoutingMiddleware;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/** @noinspection PhpFullyQualifiedNameUsageInspection */
$requestMiddlewares = [
    // Legacy middleware - kept for now during transition
    'apache-solr-for-typo3/page-indexer-initialization' => [
        'target' => PageIndexerInitialization::class,
        'before' => ['typo3/cms-frontend/tsfe', 'typo3/cms-frontend/authentication'],
        'after' => ['typo3/cms-core/normalized-params-attribute'],
    ],
    // New unified indexing middleware - positioned after TypoScript loading
    'apache-solr-for-typo3/indexing' => [
        'target' => SolrIndexingMiddleware::class,
        'after' => ['typo3/cms-frontend/prepare-tsfe-rendering'],
        'before' => ['typo3/cms-frontend/content-length-headers'],
    ],
];

$extensionConfiguration = GeneralUtility::makeInstance(
    ExtensionConfiguration::class,
);
if ($extensionConfiguration->getIsRouteEnhancerEnabled()) {
    $requestMiddlewares['apache-solr-for-typo3/solr-route-enhancer'] = [
        'target' => SolrRoutingMiddleware::class,
        'before' => [
            'typo3/cms-frontend/site',
        ],
    ];
}

return [
    'frontend' => $requestMiddlewares,
];
