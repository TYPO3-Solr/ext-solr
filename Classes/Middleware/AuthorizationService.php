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

namespace ApacheSolrForTypo3\Solr\Middleware;

use TYPO3\CMS\Core\Authentication\AbstractAuthenticationService;

/**
 * Authentication service to authorize the Index Queue page indexer to access
 * protected pages.
 */
class AuthorizationService extends AbstractAuthenticationService
{
    public const SOLR_INDEXER_USERNAME = '__SolrIndexerUser__';

    public function getUser(): ?array
    {
        if (!$this->authInfo['request']->getAttribute('solr.indexingInstructions')) {
            return null;
        }
        return [
            'uid' => 0,
            'username' => self::SOLR_INDEXER_USERNAME,
            'authenticated' => true,
        ];
    }

    /**
     * Called by TYPO3's auth service chain in {@see \TYPO3\CMS\Core\Authentication\AbstractUserAuthentication::checkAuthentication()}
     * after {@see getUser()} returned a user candidate.
     *
     * Return values (defined by TYPO3 Core):
     *   <= 0:  Authentication failed, stop checking.
     *   100:   Not responsible — pass to next auth service.
     *   >= 200: Authenticated, no further services needed.
     *
     * @noinspection PhpUnused Called implicitly by TYPO3 auth service chain, not by EXT:solr code directly.
     */
    public function authUser(array $user): int
    {
        if (!$this->authInfo['request']->getAttribute('solr.indexingInstructions')) {
            return 100;
        }

        if ($user['username'] == self::SOLR_INDEXER_USERNAME) {
            return 200;
        }

        return 100;
    }
}
