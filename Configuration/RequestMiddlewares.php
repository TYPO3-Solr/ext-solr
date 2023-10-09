<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */
$requestMiddlewares = [
    'apache-solr-for-typo3/page-indexer-initialization' => [
        'target' => \ApacheSolrForTypo3\Solr\Middleware\PageIndexerInitialization::class,
        'before' => ['typo3/cms-frontend/tsfe'],
        'after' => ['typo3/cms-core/normalized-params-attribute'],
    ],
];

$extensionConfiguration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
    \ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration::class
);
if ($extensionConfiguration->getIsRouteEnhancerEnabled()) {
    $requestMiddlewares['apache-solr-for-typo3/solr-route-enhancer'] = [
        'target' => \ApacheSolrForTypo3\Solr\Middleware\SolrRoutingMiddleware::class,
        'before' => [
            'typo3/cms-frontend/site',
        ],
    ];
}

return [
    'frontend' => $requestMiddlewares,
];
