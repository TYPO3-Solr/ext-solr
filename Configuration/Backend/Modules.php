<?php

/**
 * Definitions for modules provided by EXT:solr
 */

use ApacheSolrForTypo3\Solr\Controller\Backend\Search\CoreOptimizationModuleController;
use ApacheSolrForTypo3\Solr\Controller\Backend\Search\IndexAdministrationModuleController;
use ApacheSolrForTypo3\Solr\Controller\Backend\Search\IndexQueueModuleController;
use ApacheSolrForTypo3\Solr\Controller\Backend\Search\InfoModuleController;
use ApacheSolrForTypo3\Solr\Controller\Backend\Search\LlmModuleController;

return [
    'searchbackend' => [
        'labels' => 'solr.modules.messages',
        'iconIdentifier' => 'extensions-solr-module-main',
        'navigationComponent' => '@typo3/backend/tree/page-tree-element',
        'extensionName' => 'Solr',
    ],
    'searchbackend_info' => [
        'parent' => 'searchbackend',
        'access' => 'user',
        'path' => '/module/searchbackend/info',
        'iconIdentifier' => 'extensions-solr-module-info',
        'labels' => 'solr.modules.info',
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
        'labels' => 'solr.modules.core_optimization',
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
        'labels' => 'solr.modules.index_queue',
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
        'labels' => 'solr.modules.index_admin',
        'extensionName' => 'Solr',
        'controllerActions' => [
            IndexAdministrationModuleController::class => [
                'index', 'emptyIndex', 'clearIndexQueue', 'reloadIndexConfiguration', 'switchSite',
            ],
        ],
    ],
    'searchbackend_llm' => [
        'parent' => 'searchbackend',
        'access' => 'user',
        'path' => '/module/searchbackend/llm',
        'iconIdentifier' => 'extensions-solr-module-llm',
        'labels' => 'solr.modules.llm',
        'extensionName' => 'Solr',
        'controllerActions' => [
            LlmModuleController::class => [
                'index', 'switchSite',
            ],
        ],
    ],
];
