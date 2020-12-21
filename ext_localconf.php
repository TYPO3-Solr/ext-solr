<?php
defined('TYPO3_MODE') || die();

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// Windows compatibility

if (!function_exists('strptime')) {
    require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('solr') . 'Resources/Private/Php/strptime/strptime.php');
}

(function () {

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #
    // registering Index Queue page indexer helpers
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'][\ApacheSolrForTypo3\Solr\AdditionalFieldsIndexer::class] = \ApacheSolrForTypo3\Solr\AdditionalFieldsIndexer::class;

    \ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\Manager::registerFrontendHelper(
        'findUserGroups',
        \ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\UserGroupDetector::class
    );

    \ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\Manager::registerFrontendHelper(
        'indexPage',
        \ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageIndexer::class
    );


    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // page module plugin settings summary

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info']['solr_pi_results']['solr'] = \ApacheSolrForTypo3\Solr\Controller\Backend\PageModuleSummary::class . '->getSummary';

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // register search components

    \ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'access',
        \ApacheSolrForTypo3\Solr\Search\AccessComponent::class
    );

    \ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'relevance',
        \ApacheSolrForTypo3\Solr\Search\RelevanceComponent::class
    );

    \ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'sorting',
        \ApacheSolrForTypo3\Solr\Search\SortingComponent::class
    );

    \ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'debug',
        \ApacheSolrForTypo3\Solr\Search\DebugComponent::class
    );

    \ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'analysis',
        \ApacheSolrForTypo3\Solr\Search\AnalysisComponent::class
    );

    \ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'highlighting',
        \ApacheSolrForTypo3\Solr\Search\HighlightingComponent::class
    );

    \ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'spellchecking',
        \ApacheSolrForTypo3\Solr\Search\SpellcheckingComponent::class
    );

    \ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'faceting',
        \ApacheSolrForTypo3\Solr\Search\FacetingComponent::class
    );

    \ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'statistics',
        \ApacheSolrForTypo3\Solr\Search\StatisticsComponent::class
    );

    \ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'lastSearches',
        \ApacheSolrForTypo3\Solr\Search\LastSearchesComponent::class
    );

    \ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'elevation',
        \ApacheSolrForTypo3\Solr\Search\ElevationComponent::class
    );

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // adding scheduler tasks

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\ApacheSolrForTypo3\Solr\Task\ReIndexTask::class] = [
        'extension' => 'solr',
        'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:reindex_title',
        'description' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:reindex_description',
        'additionalFields' => \ApacheSolrForTypo3\Solr\Task\ReIndexTaskAdditionalFieldProvider::class
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask::class] = [
        'extension' => 'solr',
        'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:indexqueueworker_title',
        'description' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:indexqueueworker_description',
        'additionalFields' => \ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTaskAdditionalFieldProvider::class
    ];

    if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\TYPO3\CMS\Scheduler\Task\TableGarbageCollectionTask::class]['options']['tables']['tx_solr_statistics'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\TYPO3\CMS\Scheduler\Task\TableGarbageCollectionTask::class]['options']['tables']['tx_solr_statistics'] = [
            'dateField' => 'tstamp',
            'expirePeriod' => 180
        ];
    }

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // registering the eID scripts
    // TODO move to suggest form modifier
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['tx_solr_api'] = 'EXT:solr/Classes/Eid/Api.php';

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // add custom Solr content objects

    $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'][\ApacheSolrForTypo3\Solr\ContentObject\Multivalue::CONTENT_OBJECT_NAME]
        = \ApacheSolrForTypo3\Solr\ContentObject\Multivalue::class;

    $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'][\ApacheSolrForTypo3\Solr\ContentObject\Content::CONTENT_OBJECT_NAME]
        = \ApacheSolrForTypo3\Solr\ContentObject\Content::class;

    $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'][\ApacheSolrForTypo3\Solr\ContentObject\Relation::CONTENT_OBJECT_NAME]
        = \ApacheSolrForTypo3\Solr\ContentObject\Relation::class;

    $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'][\ApacheSolrForTypo3\Solr\ContentObject\Classification::CONTENT_OBJECT_NAME]
        = \ApacheSolrForTypo3\Solr\ContentObject\Classification::class;


    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // Register cache for frequent searches

    if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr'] = [];
    }
    // Caching framework solr
    if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration'])) {
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

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #
    /* @var \ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration $extensionConfiguration */
    $extensionConfiguration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration::class
    );

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '] = \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult::class;
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName '])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName '] = \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet::class;
    }

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['LOG']['ApacheSolrForTypo3']['Solr']['writerConfiguration'])) {
        $context = \TYPO3\CMS\Core\Utility\GeneralUtility::getApplicationContext();
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
                    'logFileInfix' => 'solr'
                ]
            ],
        ];
    }

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'ApacheSolrForTypo3.solr',
        'pi_results',
        [
            'Search' => 'results,form,detail'
        ],
        [
            'Search' => 'results'
        ]
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'ApacheSolrForTypo3.solr',
        'pi_search',
        [
            'Search' => 'form'
        ]
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'ApacheSolrForTypo3.solr',
        'pi_frequentlySearched',
        [
            'Search' => 'frequentlySearched'
        ],
        [
            'Search' => 'frequentlySearched'
        ]
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'ApacheSolrForTypo3.solr',
        'pi_suggest',
        [
            'Suggest' => 'suggest'
        ],
        [
            'Suggest' => 'suggest'
        ]
    );

    // add tsconfig
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('@import \'EXT:solr/Configuration/TSconfig/Page/Mod/Wizards/NewContentElement.tsconfig\'');

    // register the Fluid namespace 'solr' globally
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['solr'] = ['ApacheSolrForTypo3\\Solr\\ViewHelpers'];
})();

$isComposerMode = defined('TYPO3_COMPOSER_MODE') && TYPO3_COMPOSER_MODE;
if (!$isComposerMode) {
    // we load the autoloader for our libraries
    $dir = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('solr');
    require $dir . '/Resources/Private/Php/ComposerLibraries/vendor/autoload.php';
}
