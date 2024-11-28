<?php

/**
 * Definitions for modules provided by EXT:solr
 */

use ApacheSolrForTypo3\Solr\Controller\Backend\Search\CoreOptimizationModuleController;
use ApacheSolrForTypo3\Solr\Controller\Backend\Search\IndexAdministrationModuleController;
use ApacheSolrForTypo3\Solr\Controller\Backend\Search\IndexQueueModuleController;
use ApacheSolrForTypo3\Solr\Controller\Backend\Search\InfoModuleController;

return [
    'searchbackend' => [
        'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod.xlf',
        'iconIdentifier' => 'extensions-solr-module-main',
        'navigationComponent' => '@typo3/backend/tree/page-tree-element',
        'extensionName' => 'Solr',
    ],
    'searchbackend_info' => [
        'parent' => 'searchbackend',
        'access' => 'user',
        'path' => '/module/searchbackend/info',
        'iconIdentifier' => 'extensions-solr-module-info',
        'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod_info.xlf',
        'extensionName' => 'Solr',
        'controllerActions' => [
            InfoModuleController::class => [
                'index', 'switchSite', 'switchCore', 'documentsDetails',
            ],
        ],
    ],
    'searchbackend_coreoptimization' => [
        'parent' => 'searchbackend',
        'access' => 'user',
        'path' => '/module/searchbackend/core-optimization',
        'iconIdentifier' => 'extensions-solr-module-solr-core-optimization',
        'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod_coreoptimize.xlf',
        'extensionName' => 'Solr',
        'controllerActions' => [
            CoreOptimizationModuleController::class => [
                'index',
                'addSynonyms', 'importSynonymList', 'deleteAllSynonyms', 'exportSynonyms', 'deleteSynonyms',
                'saveStopWords', 'importStopWordList', 'exportStopWords',
                'switchSite', 'switchCore',
            ],
        ],
    ],
    'searchbackend_indexqueue' => [
        'parent' => 'searchbackend',
        'access' => 'user',
        'path' => '/module/searchbackend/index-queue',
        'iconIdentifier' => 'extensions-solr-module-index-queue',
        'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod_indexqueue.xlf',
        'extensionName' => 'Solr',
        'controllerActions' => [
            IndexQueueModuleController::class => [
                'index', 'initializeIndexQueue', 'clearIndexQueue', 'requeueDocument',
                'resetLogErrors', 'showError', 'doIndexingRun', 'switchSite',
            ],
        ],
    ],
    'searchbackend_indexadministration' => [
        'parent' => 'searchbackend',
        'access' => 'user',
        'path' => '/module/searchbackend/index-administration',
        'iconIdentifier' => 'extensions-solr-module-index-administration',
        'labels' => 'LLL:EXT:solr/Resources/Private/Language/locallang_mod_indexadmin.xlf',
        'extensionName' => 'Solr',
        'controllerActions' => [
            IndexAdministrationModuleController::class => [
                'index', 'emptyIndex', 'clearIndexQueue', 'reloadIndexConfiguration', 'switchSite',
            ],
        ],
    ],
];
