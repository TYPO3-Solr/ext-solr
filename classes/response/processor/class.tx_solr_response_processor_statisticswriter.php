<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Ingo Renner <ingo@typo3.org>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * Writes statistics after searches have been conducted.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @author	Dimitri Ebert <dimitri.ebert@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_response_processor_StatisticsWriter implements tx_solr_ResponseProcessor {

	/**
	 * Processes a query and its response after searching for that query.
	 *
	 * @param	tx_solr_Query	The query that has been searched for.
	 * @param	Apache_Solr_Response	The response for the last query.
	 */
	public function processResponse(tx_solr_Query $query, Apache_Solr_Response $response) {
		$urlParameters = t3lib_div::_GP('tx_solr');
		$keywords      = $query->getKeywords();
		$filters       = isset($urlParameters['filter']) ? $urlParameters['filter'] : array();

		if (empty($keywords)) {
				// do not track empty queries
			return;
		}

		$keywords = t3lib_div::removeXSS($keywords);
		$keywords = htmlentities($keywords, ENT_QUOTES, $GLOBALS['TSFE']->metaCharset);

		$insertFields = array(
			'pid'               => $GLOBALS['TSFE']->id,
			'root_pid'          => $GLOBALS['TSFE']->tmpl->rootLine[0]['uid'],
			'tstamp'            => $GLOBALS['EXEC_TIME'],
			'language'          => $GLOBALS['TSFE']->sys_language_uid,

			'num_found'         => $response->response->numFound,
			'suggestions_shown' => (int) get_object_vars($response->spellcheck->suggestions),
			'time_total'        => $response->debug->timing->time,
			'time_preparation'  => $response->debug->timing->prepare->time,
			'time_processing'   => $response->debug->timing->process->time,

			'feuser_id'         => (int) $GLOBALS['TSFE']->fe_user->user['uid'],
			'cookie'            => $GLOBALS['TSFE']->fe_user->id,
			'ip'                => t3lib_div::getIndpEnv('REMOTE_ADDR'),

			'page'              => (int) $urlParameters['page'],
			'keywords'          => $keywords,
			'filters'           => serialize($filters),
			'sorting'           => $urlParameters['sort'], // FIXME sanitize me!
			'parameters'        => serialize($response->responseHeader->params)
		);

		$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_solr_statistics', $insertFields);
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/response/processor/class.tx_solr_response_processor_statisticswriter.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/response/processor/class.tx_solr_response_processor_statisticswriter.php']);
}

?>