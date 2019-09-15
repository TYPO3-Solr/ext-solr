<?php

namespace ApacheSolrForTypo3\Solr\ContextMenu\ItemProviders;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017- Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Context menu item provider for the solr connection initialization
 *
 * @author Timo Hund <timo.hund@dkd.de>
 *
 * @deprecated Since EXT:solr 10 and will be removed in version 11
 */
class InitializeConnectionProvider extends AbstractProvider
{
    /**
     * @var array
     */
    protected $itemsConfiguration = [
        'solrconnection' => [
            'type' => 'item',
            'label' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:cache_initialize_solr_connections',
            'iconIdentifier' => 'extensions-solr-module-initsolrconnection',
            'callbackAction' => 'initializeSolrConnections'
        ]
    ];

    /**
     * This needs to be lower than priority of the RecordProvider
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 55;
    }

    /**
     * Whether this provider can handle given request (usually a check based on table, uid and context)
     *
     * @return bool
     */
    public function canHandle(): bool
    {
        trigger_error('solr:deprecation: Class InitializeConnectionProvider is deprecated since EXT:solr 10 and will be removed in v11, use sitehandling instead.', E_USER_DEPRECATED);
        if ($this->table !== 'pages') {
            return false;
        }

        if (!$this->backendUser->isAdmin()) {
            return false;
        }

        /** @var $rootPageResolver RootPageResolver */
        $rootPageResolver = GeneralUtility::makeInstance(RootPageResolver::class);
        return $rootPageResolver->getIsRootPageId((int)$this->identifier);
    }

    /**
     * Registers custom JS module with item onclick behaviour
     *
     * @param string $itemName
     * @return array
     */
    protected function getAdditionalAttributes(string $itemName): array
    {
        return ['data-callback-module' => 'TYPO3/CMS/Solr/ContextMenuActions'];
    }
}
