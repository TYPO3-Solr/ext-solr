<?php

/**
 * Definitions for routes provided by EXT:solr
 */
return [
    // Update Connections
    'solr_updateConnections' => [
        'path' => '/solr/updateConnections',
        'target' => \ApacheSolrForTypo3\Solr\ConnectionManager::class . '::updateConnectionsInCacheMenu'
    ]
];
