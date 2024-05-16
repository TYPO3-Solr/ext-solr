<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die('Access denied.');

// TypoScript
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Solr/',
    'Search - Base Configuration'
);

// StyleSheets
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/BootstrapCss/',
    'Search - Bootstrap CSS Framework'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/StyleSheets/',
    'Search - Default Stylesheets'
);

// OpenSearch
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/OpenSearch/',
    'Search - OpenSearch'
);

// Extension Pre-Configuration
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/IndexQueueNews/',
    'Search - Index Queue Configuration for news'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/IndexQueueNewsContentElements/',
    'Search - Index Queue Configuration for news with content elements'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/IndexQueueTtNews/',
    'Search - Index Queue Configuration for tt_news'
);

// Examples
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/BoostQueries/',
    'Search - (Example) Boost more recent results'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/EverythingOn/',
    'Search - (Example) Everything On'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/FilterPages/',
    'Search - (Example) Filter to only show page results'
);

ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/Suggest/',
    'Search - (Example) Suggest/autocomplete with jquery'
);

ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/Facets/Options/',
    'Search - (Example) Options facet on author field'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/Facets/OptionsToggle/',
    'Search - (Example) Options with on/off toggle'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/Facets/OptionsPrefixGrouped/',
    'Search - (Example) Options grouped by prefix'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/Facets/OptionsSinglemode/',
    'Search - (Example) Options with singlemode (only one option at a time)'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/Facets/OptionsFiltered/',
    'Search - (Example) Options filterable by option value'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/Facets/QueryGroup/',
    'Search - (Example) QueryGroup facet on the created field'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/Facets/Hierarchy/',
    'Search - (Example) Hierarchy facet on the rootline field'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/Facets/DateRange/',
    'Search - (Example) DateRange facet with jquery ui datepicker on created field'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/Facets/NumericRange/',
    'Search - (Example) NumericRange facet with jquery ui slider on pid field'
);
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/Ajaxify/',
    'Search - Ajaxify the search results with jQuery'
);

// Solr Fluid Grouping Examples
ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/TypeFieldGroup/',
    'Search - (Example) Fieldgroup on type field'
);

ExtensionManagementUtility::addStaticFile(
    'solr',
    'Configuration/TypoScript/Examples/PidQueryGroup/',
    'Search - (Example) Querygroup on pid field'
);
