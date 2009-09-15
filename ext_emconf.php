<?php

########################################################################
# Extension Manager/Repository config file for ext: "solr"
#
# Auto generated 16-09-2009 00:26
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Apache Solr Search',
	'description' => 'Search with Solr',
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
	'author_company' => '',
	'version' => '1.0.1',
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
	'_md5_values_when_last_written' => 'a:73:{s:9:"ChangeLog";s:4:"d110";s:16:"ext_autoload.php";s:4:"47ea";s:12:"ext_icon.gif";s:4:"11e4";s:17:"ext_localconf.php";s:4:"1643";s:14:"ext_tables.php";s:4:"0f84";s:13:"locallang.xml";s:4:"0296";s:16:"locallang_db.xml";s:4:"487a";s:41:"classes/class.tx_solr_commandresolver.php";s:4:"7430";s:33:"classes/class.tx_solr_indexer.php";s:4:"856e";s:58:"classes/class.tx_solr_languagefileunavailableexception.php";s:4:"1e5a";s:31:"classes/class.tx_solr_query.php";s:4:"fa9d";s:32:"classes/class.tx_solr_search.php";s:4:"cca8";s:37:"classes/class.tx_solr_solrservice.php";s:4:"8100";s:34:"classes/class.tx_solr_template.php";s:4:"19c5";s:30:"classes/class.tx_solr_util.php";s:4:"4e44";s:62:"classes/querymodifier/class.tx_solr_querymodifier_faceting.php";s:4:"e477";s:52:"classes/viewhelper/class.tx_solr_viewhelper_crop.php";s:4:"003c";s:67:"classes/viewhelper/class.tx_solr_viewhelper_currentresultnumber.php";s:4:"3f27";s:52:"classes/viewhelper/class.tx_solr_viewhelper_date.php";s:4:"d3a4";s:52:"classes/viewhelper/class.tx_solr_viewhelper_link.php";s:4:"9d09";s:51:"classes/viewhelper/class.tx_solr_viewhelper_lll.php";s:4:"e66d";s:55:"classes/viewhelper/class.tx_solr_viewhelper_oddeven.php";s:4:"be8b";s:57:"classes/viewhelper/class.tx_solr_viewhelper_relevance.php";s:4:"f15e";s:60:"classes/viewhelper/class.tx_solr_viewhelper_relevancebar.php";s:4:"4cd2";s:56:"classes/viewhelper/class.tx_solr_viewhelper_solrlink.php";s:4:"a233";s:61:"classes/viewhelper/class.tx_solr_viewhelper_sortindicator.php";s:4:"e178";s:55:"classes/viewhelper/class.tx_solr_viewhelper_sorturl.php";s:4:"26ec";s:24:"flexforms/pi_results.xml";s:4:"cb2b";s:50:"interfaces/interface.tx_solr_additionalindexer.php";s:4:"2021";s:40:"interfaces/interface.tx_solr_command.php";s:4:"fa7c";s:45:"interfaces/interface.tx_solr_formmodifier.php";s:4:"50c6";s:46:"interfaces/interface.tx_solr_querymodifier.php";s:4:"64b4";s:54:"interfaces/interface.tx_solr_substitutepageindexer.php";s:4:"faed";s:49:"interfaces/interface.tx_solr_templatemodifier.php";s:4:"2e0c";s:43:"interfaces/interface.tx_solr_viewhelper.php";s:4:"867c";s:51:"interfaces/interface.tx_solr_viewhelperprovider.php";s:4:"f4a6";s:18:"lang/locallang.xml";s:4:"c25b";s:25:"lib/SolrPhpClient/COPYING";s:4:"7b1a";s:42:"lib/SolrPhpClient/Apache/Solr/Document.php";s:4:"661b";s:42:"lib/SolrPhpClient/Apache/Solr/Response.php";s:4:"2c37";s:41:"lib/SolrPhpClient/Apache/Solr/Service.php";s:4:"8332";s:50:"lib/SolrPhpClient/Apache/Solr/Service/Balancer.php";s:4:"d1a8";s:18:"mod_admin/conf.php";s:4:"de43";s:19:"mod_admin/index.php";s:4:"3dbd";s:23:"mod_admin/locallang.xml";s:4:"61c6";s:24:"mod_admin/mod_admin.html";s:4:"845b";s:24:"mod_admin/moduleicon.png";s:4:"7213";s:39:"pi_results/class.tx_solr_pi_results.php";s:4:"749f";s:55:"pi_results/class.tx_solr_pi_results_facetingcommand.php";s:4:"8e8b";s:51:"pi_results/class.tx_solr_pi_results_formcommand.php";s:4:"729c";s:56:"pi_results/class.tx_solr_pi_results_noresultscommand.php";s:4:"a508";s:54:"pi_results/class.tx_solr_pi_results_resultscommand.php";s:4:"a2f9";s:54:"pi_results/class.tx_solr_pi_results_sortingcommand.php";s:4:"a10d";s:62:"pi_results/class.tx_solr_pi_results_spellcheckformmodifier.php";s:4:"4090";s:24:"pi_results/locallang.xml";s:4:"5f07";s:42:"resources/css/eid_suggest/autocomplete.css";s:4:"6bca";s:35:"resources/images/indicator-down.png";s:4:"309b";s:33:"resources/images/indicator-up.png";s:4:"1522";s:36:"resources/javascript/jquery-1.3.2.js";s:4:"7b7e";s:40:"resources/javascript/jquery-1.3.2.min.js";s:4:"bb38";s:42:"resources/javascript/pi_results/results.js";s:4:"2f28";s:31:"resources/shell/install-solr.sh";s:4:"4440";s:25:"resources/solr/schema.xml";s:4:"da30";s:29:"resources/solr/solrconfig.xml";s:4:"fde7";s:46:"resources/templates/pi_results/pagebrowser.htm";s:4:"7b82";s:42:"resources/templates/pi_results/results.css";s:4:"d5c0";s:42:"resources/templates/pi_results/results.htm";s:4:"d683";s:25:"resources/tomcat/solr.xml";s:4:"ab91";s:24:"resources/tomcat/tomcat6";s:4:"f20b";s:50:"scheduler/class.tx_solr_scheduler_optimizetask.php";s:4:"0a93";s:65:"scheduler/class.tx_solr_scheduler_optimizetasksolrserverfield.php";s:4:"595f";s:25:"static/solr/constants.txt";s:4:"8e74";s:21:"static/solr/setup.txt";s:4:"797a";}',
	'suggests' => array(
	),
);

?>