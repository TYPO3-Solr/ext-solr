<?php

// TypoScript
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Solr/', 'Search - Base Configuration');

// OpenSearch
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/OpenSearch/', 'Search - OpenSearch');

// Extension Pre-Configuration
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/IndexQueueNews/', 'Search - Index Queue Configuration for news');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/IndexQueueNewsContentElements/', 'Search - Index Queue Configuration for news with content elements');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/IndexQueueTtNews/', 'Search - Index Queue Configuration for tt_news');

// Examples
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/BoostQueries/', 'Search - (Example) Boost more recent results');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/EverythingOn/', 'Search - (Example) Everything On');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/FilterPages/', 'Search - (Example) Filter to only show page results');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/IntroPackageSearchBox/', 'Search - (Example) Replace Introduction Package search box');

