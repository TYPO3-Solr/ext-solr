<?php

use ApacheSolrForTypo3\Solr\Middleware\SolrIndexingMiddleware;
use ApacheSolrForTypo3\Solr\Middleware\SolrRoutingMiddleware;
use ApacheSolrForTypo3\Solr\Middleware\UserGroupDetectionMiddleware;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/** @noinspection PhpFullyQualifiedNameUsageInspection */
$requestMiddlewares = [
    // Scopes user-group detection: sets request attribute for findUserGroups sub-requests
    'apache-solr-for-typo3/user-group-detection' => [
        'target' => UserGroupDetectionMiddleware::class,
        'after' => ['typo3/cms-frontend/prepare-tsfe-rendering'],
        'before' => ['apache-solr-for-typo3/indexing'],
    ],
    // Unified indexing middleware - positioned after TypoScript loading
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
