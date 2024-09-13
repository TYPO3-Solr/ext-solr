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

namespace ApacheSolrForTypo3\Solr\EventListener\PageIndexer;

use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use TYPO3\CMS\Core\Domain\Access\RecordAccessGrantedEvent;

/**
 * Class ExtendToSubpagesModifier is responsible to modify page records so that when checking for
 * access through fe groups no groups or extendToSubpages flag is found and thus access is granted.
 */
class ExtendToSubpagesModifier
{
    public function __invoke(RecordAccessGrantedEvent $event): void
    {
        if ($GLOBALS['TYPO3_REQUEST']->hasHeader(PageIndexerRequest::SOLR_INDEX_HEADER) && $event->getTable() === 'pages') {
            $header = $GLOBALS['TYPO3_REQUEST']->getHeader(PageIndexerRequest::SOLR_INDEX_HEADER);

            if (isset($header[0])) {
                $headerDecoded = json_decode($header[0], true);
                if (is_array($headerDecoded) && isset($headerDecoded['actions']) && $headerDecoded['actions'] === 'findUserGroups') {
                    $record = $event->getRecord();
                    $record['fe_group'] = '';
                    $record['extendToSubpages'] = 1;

                    $event->updateRecord($record);
                }
            }
        }
    }
}
