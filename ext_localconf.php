<?php

use ApacheSolrForTypo3\Solr\Controller\SearchController;
use ApacheSolrForTypo3\Solr\Controller\SuggestController;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\GroupedResultParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\ResultParserRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Eid\ApiEid;
use ApacheSolrForTypo3\Solr\GarbageCollector;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\AuthorizationService;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\Manager;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\UserGroupDetector;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use ApacheSolrForTypo3\Solr\Routing\Enhancer\SolrFacetMaskAndCombineEnhancer;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\Task\EventQueueWorkerTask;
use ApacheSolrForTypo3\Solr\Task\EventQueueWorkerTaskAdditionalFieldProvider;
use ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask;
use ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTaskAdditionalFieldProvider;
use ApacheSolrForTypo3\Solr\Task\OptimizeIndexTask;
use ApacheSolrForTypo3\Solr\Task\OptimizeIndexTaskAdditionalFieldProvider;
use ApacheSolrForTypo3\Solr\Task\ReIndexTask;
use ApacheSolrForTypo3\Solr\Task\ReIndexTaskAdditionalFieldProvider;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Scheduler\Task\TableGarbageCollectionTask;

defined('TYPO3') or die('Access denied.');

// ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

(static function() {
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
    Manager::registerFrontendHelper('findUserGroups', UserGroupDetector::class);

    Manager::registerFrontendHelper('indexPage', PageIndexer::class);

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // adding scheduler tasks

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][OptimizeIndexTask::class] = [
        'extension' => 'solr',
        'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:optimizeindex_title',
        'description' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:optimizeindex_description',
        'additionalFields' => OptimizeIndexTaskAdditionalFieldProvider::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][ReIndexTask::class] = [
        'extension' => 'solr',
        'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:reindex_title',
        'description' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:reindex_description',
        'additionalFields' => ReIndexTaskAdditionalFieldProvider::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][IndexQueueWorkerTask::class] = [
        'extension' => 'solr',
        'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:indexqueueworker_title',
        'description' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:indexqueueworker_description',
        'additionalFields' => IndexQueueWorkerTaskAdditionalFieldProvider::class,
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][EventQueueWorkerTask::class] = [
        'extension' => 'solr',
        'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang_be.xlf:task.eventQueueWorkerTask.title',
        'description' => 'LLL:EXT:solr/Resources/Private/Language/locallang_be.xlf:task.eventQueueWorkerTask.description',
        'additionalFields' => EventQueueWorkerTaskAdditionalFieldProvider::class,
    ];

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][TableGarbageCollectionTask::class]['options']['tables']['tx_solr_statistics'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][TableGarbageCollectionTask::class]['options']['tables']['tx_solr_statistics'] = [
            'dateField' => 'tstamp',
            'expirePeriod' => 180,
        ];
    }

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // registering the eID scripts
    // TODO move to suggest form modifier
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['tx_solr_api'] = ApiEid::class . '::main';

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
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['backend'] = Typo3DatabaseBackend::class;
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['options'])) {
        // default life-time is one day
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['options'] = ['defaultLifetime' => 60 * 60 * 24];
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['groups'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['groups'] = ['all'];
    }

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #
    /** @var ExtensionConfiguration $extensionConfiguration */
    $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);

    // cacheHash handling
    ArrayUtility::mergeRecursiveWithOverrule(
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
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '] = SearchResult::class;
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName '])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName '] = SearchResultSet::class;
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['LOG']['ApacheSolrForTypo3']['Solr']['writerConfiguration'])) {
        $context = Environment::getContext();
        if ($context->isProduction()) {
            $logLevel = LogLevel::ERROR;
        } elseif ($context->isDevelopment()) {
            $logLevel = LogLevel::DEBUG;
        } else {
            $logLevel = LogLevel::INFO;
        }
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['ApacheSolrForTypo3']['Solr']['writerConfiguration'] = [
            $logLevel => [
                FileWriter::class => [
                    'logFileInfix' => 'solr',
                ],
            ],
        ];
    }

    // ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    ExtensionUtility::configurePlugin(
        'Solr',
        'pi_results',
        [
            SearchController::class => 'results,form,detail',
        ],
        [
            SearchController::class => 'results',
        ]
    );

    ExtensionUtility::configurePlugin(
        'Solr',
        'pi_search',
        [
            SearchController::class => 'form',
        ]
    );

    ExtensionUtility::configurePlugin(
        'Solr',
        'pi_frequentlySearched',
        [
            SearchController::class => 'frequentlySearched',
        ],
        [
            SearchController::class => 'frequentlySearched',
        ]
    );

    ExtensionUtility::configurePlugin(
        'Solr',
        'pi_suggest',
        [
            SuggestController::class => 'suggest',
        ],
        [
            SuggestController::class => 'suggest',
        ]
    );

    // register the Fluid namespace 'solr' globally
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['solr'] = ['ApacheSolrForTypo3\\Solr\\ViewHelpers'];

    /*
     * Solr route enhancer configuration
     */
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['enhancers']['SolrFacetMaskAndCombineEnhancer'] = SolrFacetMaskAndCombineEnhancer::class;

    // add solr field to rootline fields
    if (($GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] ?? '') === '') {
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
    $dir = ExtensionManagementUtility::extPath('solr');
    require $dir . '/Resources/Private/Php/ComposerLibraries/vendor/autoload.php';
}
