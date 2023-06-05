<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */
return [
    'frontend' => [
        'apache-solr-for-typo3/page-indexer-initialization' => [
            'target' => \ApacheSolrForTypo3\Solr\Middleware\PageIndexerInitialization::class,
            'before' => ['typo3/cms-frontend/tsfe'],
            'after' => ['typo3/cms-core/normalized-params-attribute'],
        ],
        'apache-solr-for-typo3/solr-route-enhancer' => [
            'target' => \ApacheSolrForTypo3\Solr\Middleware\SolrRoutingMiddleware::class,
            'before' => [
                'typo3/cms-frontend/site',
            ],
        ],
    ],
];
