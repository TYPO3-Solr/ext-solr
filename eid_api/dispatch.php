<?php
$api = t3lib_div::_GP('api');

switch($api) {
	case 'siteHash':
		include('sitehash.php');

		break;
	default:
		header(t3lib_utility_Http::HTTP_STATUS_400);

		echo json_encode(array('errorMessage' => 'You must provide an available API method, e.g. siteHash.'));

		break;
}

exit();
?>
