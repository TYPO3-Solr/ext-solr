<?php
defined('TYPO3_MODE') or die();

$boot = function ($extensionKey) {

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // Windows compatibility

    if (!function_exists('strptime')) {
        require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('solr') . 'Resources/Private/Php/strptime/strptime.php');
    }

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // registering Index Queue page indexer helpers

    if (TYPO3_MODE == 'FE' && isset($_SERVER['HTTP_X_TX_SOLR_IQ'])) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/index_ts.php']['preprocessRequest']['ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequestHandler'] = '&' . \ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequestHandler::class . '->run';
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']['ApacheSolrForTypo3\Solr\AdditionalFieldsIndexer'] = \ApacheSolrForTypo3\Solr\AdditionalFieldsIndexer::class;

        ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\Manager::registerFrontendHelper(
            'findUserGroups',
            \ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\UserGroupDetector::class
        );

        ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\Manager::registerFrontendHelper(
            'indexPage',
            \ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageIndexer::class
        );
    }

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // page module plugin settings summary

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info'][$_EXTKEY . '_pi_results'][$_EXTKEY] = \ApacheSolrForTypo3\Solr\Controller\Backend\PageModuleSummary::class . '->getSummary';

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // register search components

    ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'access',
        \ApacheSolrForTypo3\Solr\Search\AccessComponent::class
    );

    ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'relevance',
        \ApacheSolrForTypo3\Solr\Search\RelevanceComponent::class
    );

    ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'sorting',
        \ApacheSolrForTypo3\Solr\Search\SortingComponent::class
    );

    ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'debug',
        \ApacheSolrForTypo3\Solr\Search\DebugComponent::class
    );

    ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'analysis',
        \ApacheSolrForTypo3\Solr\Search\AnalysisComponent::class
    );

    ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'highlighting',
        \ApacheSolrForTypo3\Solr\Search\HighlightingComponent::class
    );

    ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'spellchecking',
        \ApacheSolrForTypo3\Solr\Search\SpellcheckingComponent::class
    );

    ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'faceting',
        \ApacheSolrForTypo3\Solr\Search\FacetingComponent::class
    );

    ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'statistics',
        \ApacheSolrForTypo3\Solr\Search\StatisticsComponent::class
    );

    ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'lastSearches',
        \ApacheSolrForTypo3\Solr\Search\LastSearchesComponent::class
    );

    ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
        'elevation',
        \ApacheSolrForTypo3\Solr\Search\ElevationComponent::class
    );

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // adding scheduler tasks

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['ApacheSolrForTypo3\Solr\Task\ReIndexTask'] = [
        'extension' => $_EXTKEY,
        'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:reindex_title',
        'description' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:reindex_description',
        'additionalFields' => \ApacheSolrForTypo3\Solr\Task\ReIndexTaskAdditionalFieldProvider::class
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask'] = [
        'extension' => $_EXTKEY,
        'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:indexqueueworker_title',
        'description' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:indexqueueworker_description',
        'additionalFields' => \ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTaskAdditionalFieldProvider::class
    ];

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // registering the eID scripts
    // TODO move to suggest form modifier
    $TYPO3_CONF_VARS['FE']['eID_include']['tx_solr_suggest'] = 'EXT:solr/Classes/Eid/Suggest.php';
    $TYPO3_CONF_VARS['FE']['eID_include']['tx_solr_api'] = 'EXT:solr/Classes/Eid/Api.php';

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    // add custom Solr content objects

    $TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][ApacheSolrForTypo3\Solr\ContentObject\Multivalue::CONTENT_OBJECT_NAME] = [
        ApacheSolrForTypo3\Solr\ContentObject\Multivalue::CONTENT_OBJECT_NAME,
        \ApacheSolrForTypo3\Solr\ContentObject\Multivalue::class
    ];

    $TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][ApacheSolrForTypo3\Solr\ContentObject\Content::CONTENT_OBJECT_NAME] = [
        ApacheSolrForTypo3\Solr\ContentObject\Content::CONTENT_OBJECT_NAME,
        \ApacheSolrForTypo3\Solr\ContentObject\Content::class
    ];

    $TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][ApacheSolrForTypo3\Solr\ContentObject\Relation::CONTENT_OBJECT_NAME] = [
        ApacheSolrForTypo3\Solr\ContentObject\Relation::CONTENT_OBJECT_NAME,
        \ApacheSolrForTypo3\Solr\ContentObject\Relation::class
    ];

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

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = \ApacheSolrForTypo3\Solr\Command\SolrCommandController::class;

    # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '] = \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResult::class;
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
                'TYPO3\\CMS\\Core\\Log\\Writer\\FileWriter' => [
                    'logFile' => 'typo3temp/var/logs/solr.log'
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

    // add tsconfig
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:solr/Configuration/TSconfig/ContentElementWizard.typoscript">');
};

$boot($_EXTKEY);
unset($boot);
