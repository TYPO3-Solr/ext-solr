<?php

#--- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- ---

# TSFE initialization

tslib_eidtools::connectDB();
$pageId = (int) t3lib_div::_GET('id');

$TSFE = t3lib_div::makeInstance('tslib_fe', $GLOBALS['TYPO3_CONF_VARS'], $pageId, 0, true);

$TSFE->initFEuser();
$TSFE->initUserGroups();
$TSFE->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
$TSFE->rootLine = $TSFE->sys_page->getRootLine($pageId, '');
$TSFE->initTemplate();
$TSFE->getConfigArray();

$solrConfiguration = tx_solr_Util::getSolrConfiguration();

#--- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- ---

# Building Suggest Query

$q = trim(t3lib_div::_GET('q'));

$suggestQuery = t3lib_div::makeInstance('tx_solr_SuggestQuery', $q);
$suggestQuery->setUserAccessGroups(explode(',', $TSFE->gr_list));
$suggestQuery->setSiteHash(tx_solr_Util::getSiteHash());

$language = 0;
if ($TSFE->sys_language_uid) {
	$language = $TSFE->sys_language_uid;
}
$suggestQuery->addFilter('language:' . $language);
$suggestQuery->setOmitHeader();

#--- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- ---

# Search

$search = t3lib_div::makeInstance('tx_solr_Search');

if ($search->ping()) {
	if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery'])) {
		foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery'] as $classReference) {
			$queryModifier = t3lib_div::getUserObj($classReference);

			if ($queryModifier instanceof tx_solr_QueryModifier) {
				$suggestQuery = $queryModifier->modifyQuery($suggestQuery);
			}
		}
	}

	$results = json_decode($search->search($suggestQuery, 0, 0)->getRawResponse());
	$facetSuggestions = $results->facet_counts->facet_fields->{$solrConfiguration['suggest.']['suggestField']};
	$facetSuggestions = get_object_vars($facetSuggestions);

	$suggestions = array();
	foreach($facetSuggestions as $partialKeyword => $value){
		$suggestions[$suggestQuery->getKeywords() . ' ' . $partialKeyword] = $facetSuggestions[$partialKeyword];
	}

	$ajaxReturnData = json_encode($suggestions);
} else {
	$ajaxReturnData = json_encode(array('status' => false));
}

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Length: ' . strlen($ajaxReturnData));
header('Content-Type: application/json; charset=' . $TSFE->renderCharset);
header('Content-Transfer-Encoding: 8bit');
echo $ajaxReturnData;

?>