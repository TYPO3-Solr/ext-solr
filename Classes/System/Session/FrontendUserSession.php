<?php

declare(strict_types = 1);

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

namespace ApacheSolrForTypo3\Solr\System\Session;

use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Encapsulates the access to the session of the frontend user.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class FrontendUserSession
{

    /**
     * @var FrontendUserAuthentication
     */
    protected $feUser;

    /**
     * FrontendUserSession constructor.
     * @param FrontendUserAuthentication $feUser
     */
    public function __construct(FrontendUserAuthentication $feUser = null)
    {
        $this->feUser = $feUser ?? $GLOBALS['TSFE']->fe_user;
    }

    /**
     * @param int $requestedPerPage
     */
    public function setPerPage(int $requestedPerPage)
    {
        $this->feUser->setKey('ses', 'tx_solr_resultsPerPage', intval($requestedPerPage));
    }

    /**
     * @return int
     */
    public function getPerPage() : int
    {
        return (int)$this->feUser->getKey('ses', 'tx_solr_resultsPerPage');
    }

    /**
     * @return boolean
     */
    public function getHasPerPage()
    {
        return $this->feUser->getKey('ses', 'tx_solr_resultsPerPage') !== null;
    }

    /**
     * @return array
     */
    public function getLastSearches() : array
    {
        $result = $this->feUser->getKey('ses', 'tx_solr_lastSearches');
        return is_array($result) ? $result : [];
    }

    /**
     * @param array $lastSearches
     */
    public function setLastSearches(array $lastSearches)
    {
        return $this->feUser->setKey('ses', 'tx_solr_lastSearches', $lastSearches);
    }
}
