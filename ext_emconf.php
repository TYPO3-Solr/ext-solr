<?php

########################################################################
# Extension Manager/Repository config file for ext "solr".
#
# Auto generated 23-06-2010 17:36
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Apache Solr for TYPO3',
	'description' => 'Apache Solr for TYPO3 is the enterprise search engine you were looking for with special features such as Facetted Search or Synonym Support and incredibly fast response times of results within milliseconds.',
	'category' => 'plugin',
	'author' => 'Ingo Renner',
	'author_email' => 'ingo@typo3.org',
	'shy' => '',
	'dependencies' => 'pagebrowse',
	'conflicts' => '',
	'priority' => '',
	'module' => 'mod_admin',
	'state' => 'beta',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => 'd.k.d Internet Service GmbH',
	'version' => '1.1.0',
	'constraints' => array(
		'depends' => array(
			'pagebrowse' => '',
			'php' => '5.2.0-0.0.0',
			'typo3' => '4.3.0-0.0.0',
		),
		'conflicts' => array(
		),
		'suggests' => array(
			'devlog' => '',
		),
	),
	'_md5_values_when_last_written' => 'a:97:{s:9:"ChangeLog";s:4:"9f45";s:16:"ext_autoload.php";s:4:"965d";s:12:"ext_icon.gif";s:4:"11e4";s:17:"ext_localconf.php";s:4:"7564";s:14:"ext_tables.php";s:4:"01c0";s:13:"locallang.xml";s:4:"754b";s:16:"locallang_db.xml";s:4:"487a";s:41:"classes/class.tx_solr_commandresolver.php";s:4:"235d";s:43:"classes/class.tx_solr_connectionmanager.php";s:4:"2dbc";s:33:"classes/class.tx_solr_indexer.php";s:4:"a88b";s:58:"classes/class.tx_solr_languagefileunavailableexception.php";s:4:"1e5a";s:31:"classes/class.tx_solr_query.php";s:4:"836d";s:32:"classes/class.tx_solr_search.php";s:4:"fd07";s:37:"classes/class.tx_solr_solrservice.php";s:4:"9be5";s:38:"classes/class.tx_solr_suggestquery.php";s:4:"7755";s:34:"classes/class.tx_solr_template.php";s:4:"10b8";s:30:"classes/class.tx_solr_util.php";s:4:"41ce";s:62:"classes/querymodifier/class.tx_solr_querymodifier_faceting.php";s:4:"f68a";s:52:"classes/viewhelper/class.tx_solr_viewhelper_crop.php";s:4:"53d6";s:67:"classes/viewhelper/class.tx_solr_viewhelper_currentresultnumber.php";s:4:"e6f5";s:52:"classes/viewhelper/class.tx_solr_viewhelper_date.php";s:4:"d379";s:52:"classes/viewhelper/class.tx_solr_viewhelper_link.php";s:4:"436d";s:51:"classes/viewhelper/class.tx_solr_viewhelper_lll.php";s:4:"e66d";s:55:"classes/viewhelper/class.tx_solr_viewhelper_oddeven.php";s:4:"37e9";s:57:"classes/viewhelper/class.tx_solr_viewhelper_relevance.php";s:4:"d226";s:60:"classes/viewhelper/class.tx_solr_viewhelper_relevancebar.php";s:4:"9103";s:56:"classes/viewhelper/class.tx_solr_viewhelper_solrlink.php";s:4:"5c44";s:61:"classes/viewhelper/class.tx_solr_viewhelper_sortindicator.php";s:4:"8c06";s:55:"classes/viewhelper/class.tx_solr_viewhelper_sorturl.php";s:4:"40a8";s:50:"classes/viewhelper/class.tx_solr_viewhelper_ts.php";s:4:"0848";s:14:"doc/manual.sxw";s:4:"eb6d";s:23:"eid_suggest/suggest.php";s:4:"46e9";s:24:"flexforms/pi_results.xml";s:4:"cb2b";s:50:"interfaces/interface.tx_solr_additionalindexer.php";s:4:"2021";s:40:"interfaces/interface.tx_solr_command.php";s:4:"fa7c";s:45:"interfaces/interface.tx_solr_formmodifier.php";s:4:"8568";s:46:"interfaces/interface.tx_solr_querymodifier.php";s:4:"2a98";s:54:"interfaces/interface.tx_solr_substitutepageindexer.php";s:4:"d0cb";s:49:"interfaces/interface.tx_solr_templatemodifier.php";s:4:"452b";s:43:"interfaces/interface.tx_solr_viewhelper.php";s:4:"4c67";s:51:"interfaces/interface.tx_solr_viewhelperprovider.php";s:4:"38a5";s:18:"lang/locallang.xml";s:4:"e233";s:25:"lib/SolrPhpClient/COPYING";s:4:"7b1a";s:42:"lib/SolrPhpClient/Apache/Solr/Document.php";s:4:"661b";s:42:"lib/SolrPhpClient/Apache/Solr/Response.php";s:4:"2c37";s:41:"lib/SolrPhpClient/Apache/Solr/Service.php";s:4:"b86c";s:50:"lib/SolrPhpClient/Apache/Solr/Service/Balancer.php";s:4:"d1a8";s:18:"mod_admin/conf.php";s:4:"de43";s:19:"mod_admin/index.php";s:4:"fe92";s:23:"mod_admin/locallang.xml";s:4:"61c6";s:24:"mod_admin/mod_admin.html";s:4:"845b";s:24:"mod_admin/moduleicon.png";s:4:"7213";s:39:"pi_results/class.tx_solr_pi_results.php";s:4:"bb2f";s:59:"pi_results/class.tx_solr_pi_results_advancedformcommand.php";s:4:"c2d5";s:55:"pi_results/class.tx_solr_pi_results_facetingcommand.php";s:4:"da86";s:51:"pi_results/class.tx_solr_pi_results_formcommand.php";s:4:"5b38";s:56:"pi_results/class.tx_solr_pi_results_noresultscommand.php";s:4:"4df7";s:54:"pi_results/class.tx_solr_pi_results_resultscommand.php";s:4:"ca27";s:54:"pi_results/class.tx_solr_pi_results_sortingcommand.php";s:4:"5492";s:62:"pi_results/class.tx_solr_pi_results_spellcheckformmodifier.php";s:4:"df7e";s:24:"pi_results/locallang.xml";s:4:"b495";s:43:"report/class.tx_solr_report_indexreport.php";s:4:"51b5";s:44:"report/class.tx_solr_report_schemastatus.php";s:4:"04de";s:42:"report/class.tx_solr_report_solrstatus.php";s:4:"b71e";s:42:"resources/css/eid_suggest/autocomplete.css";s:4:"6bca";s:30:"resources/css/report/index.css";s:4:"5af4";s:35:"resources/images/indicator-down.png";s:4:"309b";s:33:"resources/images/indicator-up.png";s:4:"1522";s:36:"resources/javascript/jquery-1.3.2.js";s:4:"7b7e";s:40:"resources/javascript/jquery-1.3.2.min.js";s:4:"bb38";s:43:"resources/javascript/eid_suggest/suggest.js";s:4:"ec89";s:66:"resources/javascript/eid_suggest/jquery-autocomplete/changelog.txt";s:4:"d69d";s:76:"resources/javascript/eid_suggest/jquery-autocomplete/jquery.autocomplete.css";s:4:"719c";s:75:"resources/javascript/eid_suggest/jquery-autocomplete/jquery.autocomplete.js";s:4:"b6b2";s:79:"resources/javascript/eid_suggest/jquery-autocomplete/jquery.autocomplete.min.js";s:4:"93c1";s:80:"resources/javascript/eid_suggest/jquery-autocomplete/jquery.autocomplete.pack.js";s:4:"b84d";s:57:"resources/javascript/eid_suggest/jquery-autocomplete/todo";s:4:"0cd2";s:76:"resources/javascript/eid_suggest/jquery-autocomplete/lib/jquery.ajaxQueue.js";s:4:"bf41";s:79:"resources/javascript/eid_suggest/jquery-autocomplete/lib/jquery.bgiframe.min.js";s:4:"54ca";s:66:"resources/javascript/eid_suggest/jquery-autocomplete/lib/jquery.js";s:4:"b89b";s:79:"resources/javascript/eid_suggest/jquery-autocomplete/lib/thickbox-compressed.js";s:4:"62ec";s:69:"resources/javascript/eid_suggest/jquery-autocomplete/lib/thickbox.css";s:4:"9b29";s:42:"resources/javascript/pi_results/results.js";s:4:"2bd2";s:31:"resources/shell/install-solr.sh";s:4:"562e";s:25:"resources/solr/schema.xml";s:4:"a2e6";s:29:"resources/solr/solrconfig.xml";s:4:"6cdb";s:46:"resources/templates/pi_results/pagebrowser.htm";s:4:"7b82";s:42:"resources/templates/pi_results/results.css";s:4:"d5c0";s:42:"resources/templates/pi_results/results.htm";s:4:"ad75";s:25:"resources/tomcat/solr.xml";s:4:"ab91";s:24:"resources/tomcat/tomcat6";s:4:"f20b";s:48:"scheduler/class.tx_solr_scheduler_committask.php";s:4:"c25b";s:63:"scheduler/class.tx_solr_scheduler_committasksolrserverfield.php";s:4:"05c4";s:50:"scheduler/class.tx_solr_scheduler_optimizetask.php";s:4:"0004";s:65:"scheduler/class.tx_solr_scheduler_optimizetasksolrserverfield.php";s:4:"7601";s:25:"static/solr/constants.txt";s:4:"8e74";s:21:"static/solr/setup.txt";s:4:"aca6";}',
	'suggests' => array(
	),
);

?>