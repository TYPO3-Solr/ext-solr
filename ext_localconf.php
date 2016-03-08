<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

$GLOBALS['PATH_solr'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('solr');

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// Windows compatibility

if (!function_exists('strptime')) {
    require_once($GLOBALS['PATH_solr'] . 'Resources/Private/Php/strptime/strptime.php');
}

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// registering Index Queue page indexer helpers

if (TYPO3_MODE == 'FE' && isset($_SERVER['HTTP_X_TX_SOLR_IQ'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/index_ts.php']['preprocessRequest']['ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequestHandler'] = '&ApacheSolrForTypo3\\Solr\\IndexQueue\\PageIndexerRequestHandler->run';
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']['ApacheSolrForTypo3\Solr\AdditionalFieldsIndexer'] = 'ApacheSolrForTypo3\\Solr\\AdditionalFieldsIndexer';

    ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\Manager::registerFrontendHelper(
        'findUserGroups',
        'ApacheSolrForTypo3\\Solr\\IndexQueue\\FrontendHelper\\UserGroupDetector'
    );

    ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\Manager::registerFrontendHelper(
        'indexPage',
        'ApacheSolrForTypo3\\Solr\\IndexQueue\\FrontendHelper\\PageIndexer'
    );
}

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerExtDirectComponent(
    'TYPO3.tx_solr.IndexInspector.Remote',
    'ApacheSolrForTypo3\\Solr\\Backend\\IndexInspector\\IndexInspectorRemoteController',
    'web_info',
    'user,group'
);

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// page module plugin settings summary

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info'][$_EXTKEY . '_PiResults_Results'][$_EXTKEY] = 'ApacheSolrForTypo3\\Solr\\Plugin\\BackendSummary->getSummary';

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// register search components

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
    'access',
    'ApacheSolrForTypo3\\Solr\\Search\\AccessComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
    'relevance',
    'ApacheSolrForTypo3\\Solr\\Search\\RelevanceComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
    'sorting',
    'ApacheSolrForTypo3\\Solr\\Search\\SortingComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
    'debug',
    'ApacheSolrForTypo3\\Solr\\Search\\DebugComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
    'analysis',
    'ApacheSolrForTypo3\\Solr\\Search\\AnalysisComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
    'highlighting',
    'ApacheSolrForTypo3\\Solr\\Search\\HighlightingComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
    'spellchecking',
    'ApacheSolrForTypo3\\Solr\\Search\\SpellcheckingComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
    'faceting',
    'ApacheSolrForTypo3\\Solr\\Search\\FacetingComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
    'statistics',
    'ApacheSolrForTypo3\\Solr\\Search\\StatisticsComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
    'lastSearches',
    'ApacheSolrForTypo3\\Solr\\Search\\LastSearchesComponent'
);

ApacheSolrForTypo3\Solr\Search\SearchComponentManager::registerSearchComponent(
    'elevation',
    'ApacheSolrForTypo3\\Solr\\Search\\ElevationComponent'
);

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// register plugin commands

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
    'results, frequentsearches',
    'frequentSearches',
    'ApacheSolrForTypo3\\Solr\\Plugin\\Results\\FrequentSearchesCommand',
    ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_NONE
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
    'search, results',
    'form',
    'ApacheSolrForTypo3\\Solr\\Plugin\\Results\\FormCommand',
    ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_NONE
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
    'results',
    'resultsPerPageSwitch',
    'ApacheSolrForTypo3\\Solr\\Plugin\\Results\ResultsPerPageSwitchCommand',
    ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_HAS_SEARCHED
    + ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_HAS_RESULTS
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
    'search, results',
    'errors',
    'ApacheSolrForTypo3\\Solr\\Plugin\\Results\\ErrorsCommand',
    ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_NONE
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
    'results',
    'lastSearches',
    'ApacheSolrForTypo3\\Solr\\Plugin\\Results\\LastSearchesCommand',
    ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_NONE
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
    'results',
    'no_results',
    'ApacheSolrForTypo3\\Solr\\Plugin\\Results\\NoResultsCommand',
    ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_HAS_SEARCHED
    + ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_NO_RESULTS
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
    'results',
    'faceting',
    'ApacheSolrForTypo3\\Solr\\Plugin\\Results\\FacetingCommand',
    ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_HAS_SEARCHED
    + ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_HAS_RESULTS
    + ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_NO_RESULTS
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
    'results',
    'results',
    'ApacheSolrForTypo3\\Solr\\Plugin\\Results\\ResultsCommand',
    ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_HAS_SEARCHED
    + ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_HAS_RESULTS
);

ApacheSolrForTypo3\Solr\CommandResolver::registerPluginCommand(
    'results',
    'sorting',
    'ApacheSolrForTypo3\\Solr\\Plugin\\Results\\SortingCommand',
    ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_HAS_SEARCHED
    + ApacheSolrForTypo3\Solr\Plugin\PluginCommand::REQUIREMENT_HAS_RESULTS
);

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// registering facet types

ApacheSolrForTypo3\Solr\Facet\FacetRendererFactory::registerFacetType(
    'numericRange',
    'ApacheSolrForTypo3\\Solr\\Facet\\NumericRangeFacetRenderer',
    'ApacheSolrForTypo3\\Solr\\Query\\FilterEncoder\\Range',
    'ApacheSolrForTypo3\\Solr\\Query\\FilterEncoder\\Range'
);

ApacheSolrForTypo3\Solr\Facet\FacetRendererFactory::registerFacetType(
    'dateRange',
    'ApacheSolrForTypo3\\Solr\\Facet\\DateRangeFacetRenderer',
    'ApacheSolrForTypo3\\Solr\\Query\\FilterEncoder\\DateRange',
    'ApacheSolrForTypo3\\Solr\\Query\\FilterEncoder\\DateRange'
);

ApacheSolrForTypo3\Solr\Facet\FacetRendererFactory::registerFacetType(
    'hierarchy',
    'ApacheSolrForTypo3\\Solr\\Facet\\HierarchicalFacetRenderer',
    'ApacheSolrForTypo3\\Solr\\Query\\FilterEncoder\\Hierarchy'
);

ApacheSolrForTypo3\Solr\Facet\FacetRendererFactory::registerFacetType(
    'queryGroup',
    'ApacheSolrForTypo3\\Solr\\Facet\\QueryGroupFacetRenderer',
    'ApacheSolrForTypo3\\Solr\\Query\\FilterEncoder\\QueryGroup',
    'ApacheSolrForTypo3\\Solr\\Query\\FilterEncoder\\QueryGroup'
);

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// adding scheduler tasks

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['ApacheSolrForTypo3\Solr\Task\ReIndexTask'] = array(
    'extension' => $_EXTKEY,
    'title' => 'LLL:EXT:solr/Resources/Private/Language/ModuleScheduler/locallang.xlf:reindex_title',
    'description' => 'LLL:EXT:solr/Resources/Private/Language/ModuleScheduler/locallang.xlf:reindex_description',
    'additionalFields' => 'ApacheSolrForTypo3\\Solr\\Task\\ReIndexTaskAdditionalFieldProvider'
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask'] = array(
    'extension' => $_EXTKEY,
    'title' => 'LLL:EXT:solr/Resources/Private/Language/ModuleScheduler/locallang.xlf:indexqueueworker_title',
    'description' => 'LLL:EXT:solr/Resources/Private/Language/ModuleScheduler/locallang.xlf:indexqueueworker_description',
    'additionalFields' => 'ApacheSolrForTypo3\\Solr\\Task\\IndexQueueWorkerTaskAdditionalFieldProvider'
);

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// TODO move into pi_results, initializeSearch, add only when features are activated
$TYPO3_CONF_VARS['EXTCONF']['solr']['modifySearchForm']['keepParameters'] = 'ApacheSolrForTypo3\\Solr\\Plugin\\Results\\ParameterKeepingFormModifier';
$TYPO3_CONF_VARS['EXTCONF']['solr']['modifySearchForm']['spellcheck'] = 'ApacheSolrForTypo3\\Solr\\Plugin\\Results\\SpellCheckFormModifier';
$TYPO3_CONF_VARS['EXTCONF']['solr']['modifySearchForm']['suggest'] = 'ApacheSolrForTypo3\\Solr\\Plugin\\Results\\SuggestFormModifier';

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// registering the eID scripts
// TODO move to suggest form modifier
$TYPO3_CONF_VARS['FE']['eID_include']['tx_solr_suggest'] = 'EXT:solr/Classes/Eid/Suggest.php';
$TYPO3_CONF_VARS['FE']['eID_include']['tx_solr_api'] = 'EXT:solr/Classes/Eid/Api.php';

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

$hasCompatibilityLayer = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('compatibility6');
if ($hasCompatibilityLayer) {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
        'solr',
        'setup',
        'tt_content.search = COA
         tt_content.search {
           10 = < lib.stdheader
           20 >
           20 = < plugin.tx_solr_PiResults_Results
           30 >
        }',
        'defaultContentRendering'
    );
}

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// add custom Solr content objects

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][ApacheSolrForTypo3\Solr\ContentObject\Multivalue::CONTENT_OBJECT_NAME] = array(
    ApacheSolrForTypo3\Solr\ContentObject\Multivalue::CONTENT_OBJECT_NAME,
    'ApacheSolrForTypo3\\Solr\\ContentObject\\Multivalue'
);

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][ApacheSolrForTypo3\Solr\ContentObject\Content::CONTENT_OBJECT_NAME] = array(
    ApacheSolrForTypo3\Solr\ContentObject\Content::CONTENT_OBJECT_NAME,
    'ApacheSolrForTypo3\\Solr\\ContentObject\\Content'
);

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'][ApacheSolrForTypo3\Solr\ContentObject\Relation::CONTENT_OBJECT_NAME] = array(
    ApacheSolrForTypo3\Solr\ContentObject\Relation::CONTENT_OBJECT_NAME,
    'ApacheSolrForTypo3\\Solr\\ContentObject\\Relation'
);

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// Register cache for frequent searches

if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr'] = array();
}
// Caching framework solr
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration'] = array();
}

if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['backend'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['backend'] = 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend';
}

if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['options'])) {
    // default life time one day
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['options'] = array('defaultLifetime' => 60 * 60 * 24);
}


if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['groups'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tx_solr_configuration']['groups'] = array('all');
}


# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

if (TYPO3_MODE == 'BE') {
    $TYPO3_CONF_VARS['SC_OPTIONS']['GLOBAL']['cliKeys'][$_EXTKEY] = array(
        'EXT:' . $_EXTKEY . '/Classes/Cli/Api.php',
        '_CLI_solr'
    );
}

# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '])) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '] = 'ApacheSolrForTypo3\\Solr\\Domain\\Search\\ResultSet\\SearchResult';
}
