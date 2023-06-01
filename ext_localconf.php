<?php

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\Parser\GroupedResultParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\ResultParserRegistry;
use ApacheSolrForTypo3\Solr\GarbageCollector;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\AuthorizationService;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') or die('Access denied.');

// ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

(static function () {
    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #
    // Registering RecordMonitor and GarbageCollector hooks.

    // hooking into TCE Main to monitor record updates that may require deleting documents from the index
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['solr/garbagecollector'] = GarbageCollector::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['solr/garbagecollector'] = GarbageCollector::class;

    // hooking into TCE Main to monitor record updates that may require reindexing by the index queue
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['solr/recordmonitor'] = RecordMonitor::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['solr/recordmonitor'] = RecordMonitor::class;

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #
    // registering Index Queue page indexer helpers
    \ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\Manager::registerFrontendHelper(
        'findUserGroups',
        \ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\UserGroupDetector::class
    );

    \ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\Manager::registerFrontendHelper(
        'indexPage',
        \ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageIndexer::class
    );

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // adding scheduler tasks

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\ApacheSolrForTypo3\Solr\Task\OptimizeIndexTask::class] = [
        'extension' => 'solr',
        'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:optimizeindex_title',
        'description' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:optimizeindex_description',
        'additionalFields' => \ApacheSolrForTypo3\Solr\Task\OptimizeIndexTaskAdditionalFieldProvider::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\ApacheSolrForTypo3\Solr\Task\ReIndexTask::class] = [
        'extension' => 'solr',
        'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:reindex_title',
        'description' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:reindex_description',
        'additionalFields' => \ApacheSolrForTypo3\Solr\Task\ReIndexTaskAdditionalFieldProvider::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask::class] = [
        'extension' => 'solr',
        'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:indexqueueworker_title',
        'description' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:indexqueueworker_description',
        'additionalFields' => \ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTaskAdditionalFieldProvider::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\ApacheSolrForTypo3\Solr\Task\EventQueueWorkerTask::class] = [
        'extension' => 'solr',
        'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang_be.xlf:task.eventQueueWorkerTask.title',
        'description' => 'LLL:EXT:solr/Resources/Private/Language/locallang_be.xlf:task.eventQueueWorkerTask.description',
        'additionalFields' => \ApacheSolrForTypo3\Solr\Task\EventQueueWorkerTaskAdditionalFieldProvider::class,
    ];

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\TYPO3\CMS\Scheduler\Task\TableGarbageCollectionTask::class]['options']['tables']['tx_solr_statistics'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\TYPO3\CMS\Scheduler\Task\TableGarbageCollectionTask::class]['options']['tables']['tx_solr_statistics'] = [
            'dateField' => 'tstamp',
            'expirePeriod' => 180,
        ];
    }

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // registering the eID scripts
    // TODO move to suggest form modifier
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['tx_solr_api'] = \ApacheSolrForTypo3\Solr\Eid\ApiEid::class . '::main';

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // Register cache for frequent searches

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr'] = [];
    }
    // Caching framework solr
    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration'] = [];
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['backend'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['backend'] = \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class;
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['options'])) {
        // default life time one day
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['options'] = ['defaultLifetime' => 60 * 60 * 24];
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['groups'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['groups'] = ['all'];
    }

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #
    /** @var \ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration $extensionConfiguration */
    $extensionConfiguration = GeneralUtility::makeInstance(
        \ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration::class
    );

    // cacheHash handling
    \TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule(
        $GLOBALS['TYPO3_CONF_VARS'],
        [
            'FE' => [
                'cacheHash' => [
                    'excludedParameters' => $extensionConfiguration->getCacheHashExcludedParameters(),
                ],
            ],
        ]
    );

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '] = \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult::class;
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName '])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName '] = \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet::class;
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['LOG']['ApacheSolrForTypo3']['Solr']['writerConfiguration'])) {
        $context = \TYPO3\CMS\Core\Core\Environment::getContext();
        if ($context->isProduction()) {
            $logLevel = \TYPO3\CMS\Core\Log\LogLevel::ERROR;
        } elseif ($context->isDevelopment()) {
            $logLevel = \TYPO3\CMS\Core\Log\LogLevel::DEBUG;
        } else {
            $logLevel = \TYPO3\CMS\Core\Log\LogLevel::INFO;
        }
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['ApacheSolrForTypo3']['Solr']['writerConfiguration'] = [
            $logLevel => [
                \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                    'logFileInfix' => 'solr',
                ],
            ],
        ];
    }

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'Solr',
        'pi_results',
        [
            \ApacheSolrForTypo3\Solr\Controller\SearchController::class => 'results,form,detail',
        ],
        [
            \ApacheSolrForTypo3\Solr\Controller\SearchController::class => 'results',
        ]
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'Solr',
        'pi_search',
        [
            \ApacheSolrForTypo3\Solr\Controller\SearchController::class => 'form',
        ]
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'Solr',
        'pi_frequentlySearched',
        [
            \ApacheSolrForTypo3\Solr\Controller\SearchController::class => 'frequentlySearched',
        ],
        [
            \ApacheSolrForTypo3\Solr\Controller\SearchController::class => 'frequentlySearched',
        ]
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'Solr',
        'pi_suggest',
        [
            \ApacheSolrForTypo3\Solr\Controller\SuggestController::class => 'suggest',
        ],
        [
            \ApacheSolrForTypo3\Solr\Controller\SuggestController::class => 'suggest',
        ]
    );

    // register the Fluid namespace 'solr' globally
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['solr'] = ['ApacheSolrForTypo3\\Solr\\ViewHelpers'];

    /*
     * Solr route enhancer configuration
     */
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['enhancers']['SolrFacetMaskAndCombineEnhancer'] =
        \ApacheSolrForTypo3\Solr\Routing\Enhancer\SolrFacetMaskAndCombineEnhancer::class;

    // add solr field to rootline fields
    if ($GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] === '') {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] = 'no_search_sub_entries';
    } else {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] .= ',no_search_sub_entries';
    }

    /**
     * Registers an authentication service to authorize / grant the indexer to
     * access protected pages.
     */
    ExtensionManagementUtility::addService(
        'solr',
        'auth',
        AuthorizationService::class,
        [// service meta data
            'title' => 'Solr Indexer Authorization',
            'description' => 'Authorizes the Solr Index Queue indexer to access protected pages.',
            'subtype' => 'getUserFE,authUserFE',
            'available' => true,
            'priority' => 100,
            'quality' => 100,

            'os' => '',
            'exec' => '',
            'className' => AuthorizationService::class,
        ]
    );

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // Register Solr Grouping feature
    $parserRegistry = GeneralUtility::makeInstance(ResultParserRegistry::class);
    if (!$parserRegistry->hasParser(GroupedResultParser::class, 200)) {
        $parserRegistry->registerParser(GroupedResultParser::class, 200);
    }
})();

$isComposerMode = defined('TYPO3_COMPOSER_MODE') && TYPO3_COMPOSER_MODE;
if (!$isComposerMode) {
    // we load the autoloader for our libraries
    $dir = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('solr');
    require $dir . '/Resources/Private/Php/ComposerLibraries/vendor/autoload.php';
}
