<?php

$domain     = t3lib_div::_GP('domain');
$returnData = '';

if (!empty($domain)) {
	$sitehash = md5(
		$domain .
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] .
		'tx_solr'
	);

	$returnData = json_encode(array('sitehash' => $sitehash));
} else {
	header(t3lib_utility_Http::HTTP_STATUS_400);

	$errorMessage = 'You have to provide an existing domain, e.g. www.example.com.';

	$returnData = json_encode(array('errorMessage' => $errorMessage));
}

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Content-Type: application/json; charset=utf-8');
header('Content-Transfer-Encoding: 8bit');
header('Content-Length: ' . strlen($returnData));

echo $returnData;

?>
