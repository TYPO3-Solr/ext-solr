<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Ingo Renner <ingo@typo3.org>
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
 * Authentication service to authorize the Index Queue page indexer to access
 * protected pages.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_indexqueue_frontendhelper_AuthorizationService extends tx_sv_authbase {

	/**
	 * User used when authenticating the page indexer for protected pages,
	 * to allow the indexer to access and protected content. May also allow to
	 * identify requests by the page indexer.
	 *
	 * @var	string
	 */
	const SOLR_INDEXER_USERNAME = '__SolrIndexerUser__';

	/**
	 * Gets a fake frontend user record to allow access to protected pages.
	 *
	 * @return	array	An array representing a frontend user.
	 */
	public function getUser() {
		return array(
			'username'      => self::SOLR_INDEXER_USERNAME,
			'authenticated' => TRUE
		);
	}

	/**
	 * Authenticates the page indexer frontend user to gratn it access to
	 * protected pages and page content.
	 *
	 * Returns 200 which automatically grants access for the current fake page
	 * indexer user. A status of >= 200 also tells TYPO3 that it doesn't need to
	 * conduct other services that might be registered for "their opinion"
	 * whether a user is authenticated.
	 *
	 * @see	t3lib_userAuth::checkAuthentication()
	 * @param	array	Array of user data
	 * @return	integer	Returns 200 to grant access for the page indexer.
	 */
	public function authUser($user) {
			// shouldn't happen, but in case we get a regular user we just
			// pass it on to another (regular) auth service
		$authenticationLevel = 100;

		if ($user['username'] == self::SOLR_INDEXER_USERNAME) {
			$authenticationLevel = 200;
		}

		return $authenticationLevel;
	}

	/**
	 * Creates user group records so that the page indexer is granted access to
	 * protected pages.
	 *
	 * @param	array		$user Data of user.
	 * @param	array		$knownGroups Group data array of already known groups. This is handy if you want select other related groups. Keys in this array are unique IDs of those groups.
	 * @return	mixed		Groups array, keys = uid which must be unique
	 */
	public function getGroups($user, $knownGroups)	{
		$groupData = array();

		$requestHandler = t3lib_div::makeInstance('tx_solr_indexqueue_PageIndexerRequestHandler');
		$accessRootline = $requestHandler->getRequest()->getParameter('accessRootline');

		if ($user['username'] == self::SOLR_INDEXER_USERNAME && !empty($accessRootline)) {

			$accessRootline = t3lib_div::makeInstance(
				'tx_solr_access_Rootline',
				$accessRootline
			);

			$groups = $accessRootline->getGroups();

			foreach ($groups as $groupId) {
					// faking a user group record
				$groupData[] = array(
					'uid'      => $groupId,
					'pid'      => 0,
					'title'    => '__SolrIndexerGroup__',
					'TSconfig' => ''
				);
			}
		}

		return $groupData;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/frontendhelper/class.tx_solr_indexqueue_frontendhelper_authorizationservice.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/frontendhelper/class.tx_solr_indexqueue_frontendhelper_authorizationservice.php']);
}

?>