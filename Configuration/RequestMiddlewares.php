<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */
return [
    'frontend' => [
        'apache-solr-for-typo3/page-indexer-fe-user-authenticator' => [
            'target' => \ApacheSolrForTypo3\Solr\Middleware\FrontendUserAuthenticator::class,
            'before' => ['apache-solr-for-typo3/page-indexer-initialization']
        ],
        'apache-solr-for-typo3/page-indexer-initialization' => [
            'target' => \ApacheSolrForTypo3\Solr\Middleware\PageIndexerInitialization::class,
            'before' => ['typo3/cms-frontend/tsfe'],
            'after' => ['typo3/cms-core/normalized-params-attribute']
        ],
        'apache-solr-for-typo3/page-indexer-finisher' => [
            'target' => \ApacheSolrForTypo3\Solr\Middleware\PageIndexerFinisher::class,
            'after' => ['typo3/cms-frontend/content-length-headers']
        ]
    ]
];
