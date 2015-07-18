<?php

// TypoScript
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Solr/', 'Apache Solr - Default Configuration');

// OpenSearch
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/OpenSearch/', 'Apache Solr - OpenSearch');

// Extension Pre-Configuration
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/IndexQueueNews/', 'Apache Solr - Index Queue Configuration for news');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/IndexQueueNewsContentElements/', 'Apache Solr - Index Queue Configuration for news with content elements');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/IndexQueueTtNews/', 'Apache Solr - Index Queue Configuration for tt_news');

// Examples
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/BoostQueries/', 'Apache Solr Example - Boost more recent results');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/EverythingOn/', 'Apache Solr Example - Everything On');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/FilterPages/', 'Apache Solr Example - Filter to only show page results');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('solr', 'Configuration/TypoScript/Examples/IntroPackageSearchBox/', 'Apache Solr Example - Replace Introduction Package search box');

