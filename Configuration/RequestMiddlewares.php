<?php

return [
    'frontend' => [
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
