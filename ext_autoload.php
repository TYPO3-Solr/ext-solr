<?php
$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('solr');
return array(

		// SolrPhpClient

	'apache_solr_httptransport_abstract' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/HttpTransport/Abstract.php',
	'apache_solr_httptransport_curl' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/HttpTransport/Curl.php',
	'apache_solr_httptransport_curlnoreuse' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/HttpTransport/CurlNoReuse.php',
	'apache_solr_httptransport_filegetcontents' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/HttpTransport/FileGetContents.php',
	'apache_solr_httptransport_interface' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/HttpTransport/Interface.php',
	'apache_solr_httptransport_response' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/HttpTransport/Response.php',
	'apache_solr_service_balancer' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/Service/Balancer.php',
	'apache_solr_document' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/Document.php',
	'apache_solr_exception' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/Exception.php',
	'apache_solr_httptransportexception' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/HttpTransportException.php',
	'apache_solr_invalidargumentexception' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/InvalidArgumentException.php',
	'apache_solr_noserviceavailableexception' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/NoServiceAvailableException.php',
	'apache_solr_parserexception' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/ParserException.php',
	'apache_solr_response' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/Response.php',
	'apache_solr_service' => $extensionPath . 'Lib/SolrPhpClient/Apache/Solr/Service.php',

		// EXT:solr

		// interfaces

	'tx_solr_additionalindexqueueitemindexer' => $extensionPath . 'Interfaces/AdditionalIndexQueueItemIndexer.php',
	'tx_solr_additionalpageindexer' => $extensionPath . 'Interfaces/AdditionalPageIndexer.php',
	'tx_solr_commandpluginaware' => $extensionPath . 'Interfaces/CommandPluginAware.php',
	'tx_solr_commandpostprocessor' => $extensionPath . 'Interfaces/CommandPostProcessor.php',
	'tx_solr_errordetector' => $extensionPath . 'Interfaces/ErrorDetector.php',
	'tx_solr_facetoptionsrenderer' => $extensionPath . 'Interfaces/FacetOptionsRenderer.php',
	'tx_solr_facetrenderer' => $extensionPath . 'Interfaces/FacetRenderer.php',
	'tx_solr_facetsmodifier' =>  $extensionPath . 'Interfaces/FacetsModifier.php',
	'tx_solr_formmodifier' => $extensionPath . 'Interfaces/FormModifier.php',
	'tx_solr_garbagecollectorpostprocessor' => $extensionPath . 'Interfaces/GarbageCollectorPostProcessor.php',
	'tx_solr_indexqueueinitializationpostprocessor' => $extensionPath . 'Interfaces/IndexQueueInitializationPostProcessor.php',
	'tx_solr_indexqueueinitializer' => $extensionPath . 'Interfaces/IndexQueueInitializer.php',
	'tx_solr_indexqueuepageindexerdataurlmodifier' => $extensionPath . 'Interfaces/IndexQueuePageIndexerDataUrlModifier.php',
	'tx_solr_indexqueuepageindexerdocumentsmodifier' => $extensionPath . 'Interfaces/IndexQueuePageIndexerDocumentsModifier.php',
	'tx_solr_indexqueuepageindexerfrontendhelper' => $extensionPath . 'Interfaces/IndexQueuePageIndexerFrontendHelper.php',
	'tx_solr_pagedocumentpostprocessor' => $extensionPath . 'Interfaces/PageDocumentPostProcessor.php',
	'tx_solr_pluginaware' => $extensionPath . 'Interfaces/PluginAware.php',
	'tx_solr_plugincommand' => $extensionPath . 'Interfaces/PluginCommand.php',
	'tx_solr_queryaware' => $extensionPath . 'Interfaces/QueryAware.php',
	'tx_solr_queryfacetbuilder' => $extensionPath . 'Interfaces/QueryFacetBuilder.php',
	'tx_solr_responsemodifier' => $extensionPath . 'Interfaces/ResponseModifier.php',
	'tx_solr_searchaware' => $extensionPath . 'Interfaces/SearchAware.php',
	'tx_solr_serializedvaluedetector' => $extensionPath . 'Interfaces/SerializedValueDetector.php',
	'tx_solr_subpartviewhelper' => $extensionPath . 'Interfaces/SubpartViewHelper.php',
	'tx_solr_substitutepageindexer' => $extensionPath . 'Interfaces/SubstitutePageIndexer.php',
	'tx_solr_templatemodifier' => $extensionPath . 'Interfaces/TemplateModifier.php',

);

