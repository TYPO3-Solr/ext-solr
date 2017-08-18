<?php

/**
 * Definitions for routes provided by EXT:solr
 */
return [
    'solr_updateConnections' => [
        'path' => '/solr/updateConnections',
        'target' => \ApacheSolrForTypo3\Solr\Controller\Backend\AjaxController::class . '::updateConnections'
    ],
    'solr_updateConnection' => [
        'path' => '/solr/updateConnection',
        'target' => \ApacheSolrForTypo3\Solr\Controller\Backend\AjaxController::class . '::updateConnection'
    ]
];
