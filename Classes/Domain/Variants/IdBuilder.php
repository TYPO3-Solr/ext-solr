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

namespace ApacheSolrForTypo3\Solr\Domain\Variants;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Event\Variants\AfterVariantIdWasBuiltEvent;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The variantId can be used to group documents by a variantId. This variantId is by default unique per system,
 * and has the following syntax:
 *
 * <SystemHash>/type/uid
 *
 * A file from one system will get the same variantId, which could be useful for de-duplication.
 */
class IdBuilder
{
    public function __construct(
        protected readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * This method is used to build a variantId.
     */
    public function buildFromTypeAndUid(string $type, int $uid, array $itemRecord, Site $site, Document $document): string
    {
        $systemHash = $this->getSystemHash();
        $variantId = $systemHash . '/' . $type . '/' . $uid;
        $event = new AfterVariantIdWasBuiltEvent($variantId, $systemHash, $type, $uid, $itemRecord, $site, $document);
        $event = $this->eventDispatcher->dispatch($event);
        return $event->getVariantId();
    }

    /**
     * Returns a system unique hash.
     */
    protected function getSystemHash(): string
    {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'])) {
            throw new InvalidArgumentException(
                'No sitename set in TYPO3_CONF_VARS|SYS|sitename',
                8666989008,
            );
        }

        $siteName = $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'];
        $systemKey = 'tx_solr' . $siteName;
        return GeneralUtility::makeInstance(HashService::class)->hmac($systemKey, $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '');
    }
}
