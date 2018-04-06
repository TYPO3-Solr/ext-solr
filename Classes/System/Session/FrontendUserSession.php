<?php declare(strict_types = 1);

namespace ApacheSolrForTypo3\Solr\System\Session;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-eb-support@dkd.de>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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