<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequestHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Authentication\AbstractAuthenticationService;

/**
 * Authentication service to authorize the Index Queue page indexer to access
 * protected pages.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class AuthorizationService extends AbstractAuthenticationService
{

    /**
     * User used when authenticating the page indexer for protected pages,
     * to allow the indexer to access and protected content. May also allow to
     * identify requests by the page indexer.
     *
     * @var string
     */
    const SOLR_INDEXER_USERNAME = '__SolrIndexerUser__';

    /**
     * Gets a fake frontend user record to allow access to protected pages.
     *
     * @return array An array representing a frontend user.
     */
    public function getUser()
    {
        return [
            'uid' => 0,
            'username' => self::SOLR_INDEXER_USERNAME,
            'authenticated' => true
        ];
    }

    /**
     * Authenticates the page indexer frontend user to grant it access to
     * protected pages and page content.
     *
     * Returns 200 which automatically grants access for the current fake page
     * indexer user. A status of >= 200 also tells TYPO3 that it doesn't need to
     * conduct other services that might be registered for "their opinion"
     * whether a user is authenticated.
     *
     * @see \TYPO3\CMS\Core\Authentication\AbstractUserAuthentication::checkAuthentication()
     * @param array $user Array of user data
     * @return int Returns 200 to grant access for the page indexer.
     */
    public function authUser($user)
    {
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
     * @param array $user Data of user.
     * @param array $knownGroups Group data array of already known groups. This is handy if you want select other related groups. Keys in this array are unique IDs of those groups.
     * @return mixed Groups array, keys = uid which must be unique
     */
    public function getGroups(
        $user,
        /** @noinspection PhpUnusedParameterInspection */
        $knownGroups
    ) {
        $groupData = [];

            /** @var $requestHandler PageIndexerRequestHandler */
        $requestHandler = GeneralUtility::makeInstance(PageIndexerRequestHandler::class);
        $accessRootline = $requestHandler->getRequest()->getParameter('accessRootline');

        if ($user['username'] == self::SOLR_INDEXER_USERNAME && !empty($accessRootline)) {
            $accessRootline = GeneralUtility::makeInstance(Rootline::class, /** @scrutinizer ignore-type */ $accessRootline);
            $groups = $accessRootline->getGroups();

            foreach ($groups as $groupId) {
                // faking a user group record
                $groupData[] = [
                    'uid' => $groupId,
                    'pid' => 0,
                    'title' => '__SolrIndexerGroup__',
                    'TSconfig' => ''
                ];
            }
        }

        return $groupData;
    }
}
