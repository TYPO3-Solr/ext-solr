<?php

namespace ApacheSolrForTypo3\Solr\System\Hooks\Backend\Toolbar;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Timo Hund <timo.hund@dkd.de>
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

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Toolbar\ClearCacheActionsHookInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ClearCacheActionsHook
 */
class ClearCacheActionsHook implements ClearCacheActionsHookInterface
{

    /**
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @var BackendUserAuthentication
     */
    protected $backendUser;

    /**
     * ClearCacheActionsHook constructor.
     * @param UriBuilder|null $uriBuilder
     * @param BackendUserAuthentication|null $backendUser
     */
    public function __construct(UriBuilder $uriBuilder = null, BackendUserAuthentication $backendUser = null)
    {
        $this->uriBuilder = $uriBuilder ??  GeneralUtility::makeInstance(UriBuilder::class);
        $this->backendUser = $backendUser ?? $GLOBALS['BE_USER'];
    }

    /**
     * Adds a menu entry to the clear cache menu to detect Solr connections.
     *
     * @param array $cacheActions Array of CacheMenuItems
     * @param array $optionValues Array of AccessConfigurations-identifiers (typically  used by userTS with options.clearCache.identifier)
     */
    public function manipulateCacheActions(&$cacheActions, &$optionValues)
    {
        if (!$this->backendUser->isAdmin()) {
            return;
        }

        $href = $this->uriBuilder->buildUriFromRoute('ajax_solr_updateConnections');
        $optionValues[] = 'clearSolrConnectionCache';
        $cacheActions[] = [
            'id' => 'clearSolrConnectionCache',
            'title' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:cache_initialize_solr_connections',
            'href' => $href,
            'iconIdentifier' => 'extensions-solr-module-initsolrconnections'
        ];
    }
}
