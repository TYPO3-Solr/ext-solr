<?php
$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('solr');
return array(

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

);

