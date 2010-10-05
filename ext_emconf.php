<?php

########################################################################
# Extension Manager/Repository config file for ext "solr".
#
# Auto generated 04-10-2010 13:33
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
	'module' => '',
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => 'd.k.d Internet Service GmbH',
	'version' => '1.2.2-dev',
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
	'_md5_values_when_last_written' => 'a:128:{s:9:"ChangeLog";s:4:"dd2a";s:16:"ext_autoload.php";s:4:"c472";s:12:"ext_icon.gif";s:4:"11e4";s:17:"ext_localconf.php";s:4:"6f86";s:14:"ext_tables.php";s:4:"fc7d";s:13:"locallang.xml";s:4:"754b";s:16:"locallang_db.xml";s:4:"e571";s:41:"classes/class.tx_solr_commandresolver.php";s:4:"dad3";s:43:"classes/class.tx_solr_connectionmanager.php";s:4:"fcdd";s:33:"classes/class.tx_solr_indexer.php";s:4:"824d";s:58:"classes/class.tx_solr_languagefileunavailableexception.php";s:4:"1e5a";s:56:"classes/class.tx_solr_nosolrconnectionfoundexception.php";s:4:"46d5";s:31:"classes/class.tx_solr_query.php";s:4:"a1e8";s:32:"classes/class.tx_solr_search.php";s:4:"1f40";s:37:"classes/class.tx_solr_solrservice.php";s:4:"4712";s:38:"classes/class.tx_solr_suggestquery.php";s:4:"7755";s:34:"classes/class.tx_solr_template.php";s:4:"f271";s:51:"classes/class.tx_solr_typo3pagecontentextractor.php";s:4:"f453";s:30:"classes/class.tx_solr_util.php";s:4:"817c";s:63:"classes/fieldprocessor/class.tx_solr_fieldprocessor_service.php";s:4:"ba63";s:74:"classes/fieldprocessor/class.tx_solr_fieldprocessor_timestamptoisodate.php";s:4:"8db6";s:65:"classes/pluginbase/class.tx_solr_pluginbase_commandpluginbase.php";s:4:"21c8";s:58:"classes/pluginbase/class.tx_solr_pluginbase_pluginbase.php";s:4:"6d7d";s:62:"classes/querymodifier/class.tx_solr_querymodifier_faceting.php";s:4:"976f";s:52:"classes/viewhelper/class.tx_solr_viewhelper_crop.php";s:4:"53d6";s:67:"classes/viewhelper/class.tx_solr_viewhelper_currentresultnumber.php";s:4:"5476";s:52:"classes/viewhelper/class.tx_solr_viewhelper_date.php";s:4:"d379";s:52:"classes/viewhelper/class.tx_solr_viewhelper_link.php";s:4:"be0a";s:51:"classes/viewhelper/class.tx_solr_viewhelper_lll.php";s:4:"c492";s:55:"classes/viewhelper/class.tx_solr_viewhelper_oddeven.php";s:4:"37e9";s:57:"classes/viewhelper/class.tx_solr_viewhelper_relevance.php";s:4:"d226";s:60:"classes/viewhelper/class.tx_solr_viewhelper_relevancebar.php";s:4:"9103";s:56:"classes/viewhelper/class.tx_solr_viewhelper_solrlink.php";s:4:"5c44";s:61:"classes/viewhelper/class.tx_solr_viewhelper_sortindicator.php";s:4:"8c06";s:55:"classes/viewhelper/class.tx_solr_viewhelper_sorturl.php";s:4:"40a8";s:50:"classes/viewhelper/class.tx_solr_viewhelper_ts.php";s:4:"f4c5";s:30:"compat/class.ux_tslib_cobj.php";s:4:"65d7";s:47:"compat/interface.tslib_content_postinithook.php";s:4:"e197";s:14:"doc/manual.sxw";s:4:"eb6d";s:23:"eid_suggest/suggest.php";s:4:"ffeb";s:24:"flexforms/pi_results.xml";s:4:"ca36";s:50:"interfaces/interface.tx_solr_additionalindexer.php";s:4:"2a36";s:40:"interfaces/interface.tx_solr_command.php";s:4:"6134";s:47:"interfaces/interface.tx_solr_facetsmodifier.php";s:4:"b7b0";s:47:"interfaces/interface.tx_solr_fieldprocessor.php";s:4:"b8e1";s:45:"interfaces/interface.tx_solr_formmodifier.php";s:4:"8568";s:46:"interfaces/interface.tx_solr_querymodifier.php";s:4:"2a98";s:50:"interfaces/interface.tx_solr_responseprocessor.php";s:4:"fd9c";s:55:"interfaces/interface.tx_solr_resultdocumentmodifier.php";s:4:"fa5a";s:50:"interfaces/interface.tx_solr_resultsetmodifier.php";s:4:"6e19";s:54:"interfaces/interface.tx_solr_substitutepageindexer.php";s:4:"469a";s:49:"interfaces/interface.tx_solr_templatemodifier.php";s:4:"452b";s:43:"interfaces/interface.tx_solr_viewhelper.php";s:4:"4c67";s:51:"interfaces/interface.tx_solr_viewhelperprovider.php";s:4:"38a5";s:18:"lang/locallang.xml";s:4:"1e1b";s:25:"lib/SolrPhpClient/COPYING";s:4:"7b1a";s:42:"lib/SolrPhpClient/Apache/Solr/Document.php";s:4:"c338";s:43:"lib/SolrPhpClient/Apache/Solr/Exception.php";s:4:"3b94";s:56:"lib/SolrPhpClient/Apache/Solr/HttpTransportException.php";s:4:"0876";s:58:"lib/SolrPhpClient/Apache/Solr/InvalidArgumentException.php";s:4:"0d44";s:61:"lib/SolrPhpClient/Apache/Solr/NoServiceAvailableException.php";s:4:"1f5f";s:49:"lib/SolrPhpClient/Apache/Solr/ParserException.php";s:4:"2d2e";s:42:"lib/SolrPhpClient/Apache/Solr/Response.php";s:4:"db7b";s:41:"lib/SolrPhpClient/Apache/Solr/Service.php";s:4:"002b";s:50:"lib/SolrPhpClient/Apache/Solr/Service/Balancer.php";s:4:"aee6";s:18:"mod_admin/conf.php";s:4:"de43";s:19:"mod_admin/index.php";s:4:"fe92";s:23:"mod_admin/locallang.xml";s:4:"61c6";s:24:"mod_admin/mod_admin.html";s:4:"845b";s:24:"mod_admin/moduleicon.png";s:4:"7213";s:39:"pi_results/class.tx_solr_pi_results.php";s:4:"2e6d";s:59:"pi_results/class.tx_solr_pi_results_advancedformcommand.php";s:4:"c2d5";s:55:"pi_results/class.tx_solr_pi_results_facetingcommand.php";s:4:"2a86";s:51:"pi_results/class.tx_solr_pi_results_formcommand.php";s:4:"c0f5";s:56:"pi_results/class.tx_solr_pi_results_noresultscommand.php";s:4:"68ab";s:54:"pi_results/class.tx_solr_pi_results_resultscommand.php";s:4:"97b5";s:54:"pi_results/class.tx_solr_pi_results_sortingcommand.php";s:4:"a8ed";s:62:"pi_results/class.tx_solr_pi_results_spellcheckformmodifier.php";s:4:"42ef";s:24:"pi_results/locallang.xml";s:4:"aca6";s:65:"report/class.tx_solr_report_accessfilterplugininstalledstatus.php";s:4:"b0d0";s:43:"report/class.tx_solr_report_indexreport.php";s:4:"765f";s:44:"report/class.tx_solr_report_schemastatus.php";s:4:"8cf0";s:48:"report/class.tx_solr_report_solrconfigstatus.php";s:4:"66c9";s:42:"report/class.tx_solr_report_solrstatus.php";s:4:"338b";s:25:"report/tx_solr_report.gif";s:4:"11e4";s:50:"resources/css/jquery-ui/jquery-ui-1.8.2.custom.css";s:4:"4f1d";s:30:"resources/css/report/index.css";s:4:"5af4";s:35:"resources/images/indicator-down.png";s:4:"309b";s:33:"resources/images/indicator-up.png";s:4:"1522";s:50:"resources/images/jquery-ui/ui-anim_basic_16x16.gif";s:4:"03ce";s:68:"resources/images/jquery-ui/ui-bg_diagonals-thick_18_b81900_40x40.png";s:4:"95f9";s:68:"resources/images/jquery-ui/ui-bg_diagonals-thick_20_666666_40x40.png";s:4:"f040";s:58:"resources/images/jquery-ui/ui-bg_flat_10_000000_40x100.png";s:4:"c18c";s:59:"resources/images/jquery-ui/ui-bg_glass_100_f6f6f6_1x400.png";s:4:"5f18";s:59:"resources/images/jquery-ui/ui-bg_glass_100_fdf5ce_1x400.png";s:4:"d26e";s:58:"resources/images/jquery-ui/ui-bg_glass_65_ffffff_1x400.png";s:4:"e5a8";s:65:"resources/images/jquery-ui/ui-bg_gloss-wave_35_f6a828_500x100.png";s:4:"58d2";s:68:"resources/images/jquery-ui/ui-bg_highlight-soft_100_eeeeee_1x100.png";s:4:"384c";s:67:"resources/images/jquery-ui/ui-bg_highlight-soft_75_ffe45c_1x100.png";s:4:"b806";s:54:"resources/images/jquery-ui/ui-icons_222222_256x240.png";s:4:"ebe6";s:54:"resources/images/jquery-ui/ui-icons_228ef1_256x240.png";s:4:"79f4";s:54:"resources/images/jquery-ui/ui-icons_ef8c08_256x240.png";s:4:"ef9a";s:54:"resources/images/jquery-ui/ui-icons_ffd27a_256x240.png";s:4:"ab8c";s:54:"resources/images/jquery-ui/ui-icons_ffffff_256x240.png";s:4:"342b";s:36:"resources/javascript/jquery-1.4.2.js";s:4:"c0ac";s:40:"resources/javascript/jquery-1.4.2.min.js";s:4:"1009";s:50:"resources/javascript/jquery-ui-1.8.2.custom.min.js";s:4:"472a";s:43:"resources/javascript/eid_suggest/suggest.js";s:4:"1485";s:42:"resources/javascript/pi_results/results.js";s:4:"e8c2";s:31:"resources/shell/install-solr.sh";s:4:"ea21";s:51:"resources/solr/plugins/typo3-accessfilter-1.1.0.jar";s:4:"7b07";s:53:"resources/solr/singlecore/mapping-ISOLatin1Accent.txt";s:4:"9f3c";s:39:"resources/solr/singlecore/protwords.txt";s:4:"89d5";s:36:"resources/solr/singlecore/schema.xml";s:4:"2ed1";s:40:"resources/solr/singlecore/solrconfig.xml";s:4:"d0e9";s:46:"resources/templates/pi_results/pagebrowser.htm";s:4:"7b82";s:42:"resources/templates/pi_results/results.css";s:4:"542a";s:42:"resources/templates/pi_results/results.htm";s:4:"6097";s:25:"resources/tomcat/solr.xml";s:4:"8c2a";s:24:"resources/tomcat/tomcat6";s:4:"5bd2";s:48:"scheduler/class.tx_solr_scheduler_committask.php";s:4:"c25b";s:63:"scheduler/class.tx_solr_scheduler_committasksolrserverfield.php";s:4:"05c4";s:50:"scheduler/class.tx_solr_scheduler_optimizetask.php";s:4:"0004";s:65:"scheduler/class.tx_solr_scheduler_optimizetasksolrserverfield.php";s:4:"7601";s:25:"static/solr/constants.txt";s:4:"e526";s:21:"static/solr/setup.txt";s:4:"e257";s:46:"tests/classes/class.tx_solr_query_testcase.php";s:4:"ef59";s:78:"tests/classes/fieldprocessor/class.tx_solr_fieldprocessor_service_testcase.php";s:4:"7bf7";}',
	'suggests' => array(
	),
);

?>