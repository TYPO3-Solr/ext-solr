<?php

declare(strict_types=1);

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
     */
    public const SOLR_INDEXER_USERNAME = '__SolrIndexerUser__';

    /**
     * Gets a fake frontend user record to allow access to protected pages.
     *
     * @return ?array An array representing a frontend user if a authenticated solr request is available.
     */
    public function getUser(): ?array
    {
        if (!$this->authInfo['request']->getAttribute('solr.pageIndexingInstructions')) {
            return null;
        }
        return [
            'uid' => 0,
            'username' => self::SOLR_INDEXER_USERNAME,
            'authenticated' => true,
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
     * @param array $user Array of user data
     * @return int Returns 200 to grant access for the page indexer.
     * @see \TYPO3\CMS\Core\Authentication\AbstractUserAuthentication::checkAuthentication()
     */
    public function authUser(array $user): int
    {
        if (!$this->authInfo['request']->getAttribute('solr.pageIndexingInstructions')) {
            return 100;
        }
        // shouldn't happen, but in case we get a regular user we just
        // pass it on to another (regular) auth service
        $authenticationLevel = 100;

        if ($user['username'] == self::SOLR_INDEXER_USERNAME) {
            $authenticationLevel = 200;
        }

        return $authenticationLevel;
    }
}
