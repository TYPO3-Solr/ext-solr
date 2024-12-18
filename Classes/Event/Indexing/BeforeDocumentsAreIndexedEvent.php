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

namespace ApacheSolrForTypo3\Solr\Event\Indexing;

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\ContentObject\Exception\ContentRenderingException;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * An event to manipulate documents right before they get added to the Solr index.
 *
 * Previously used with
 * $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['preAddModifyDocuments']
 */
final class BeforeDocumentsAreIndexedEvent
{
    public function __construct(
        private readonly Document $document,
        private readonly Item $indexQueueItem,
        /**  @var Document[] */
        private array $documents,
        private readonly TypoScriptFrontendController $tsfe,
    ) {}

    /**
     * @throws ContentRenderingException
     */
    public function getSite(): Site
    {
        return clone $this->tsfe->cObj->getRequest()->getAttribute('site');
    }

    /**
     * @throws ContentRenderingException
     */
    public function getSiteLanguage(): SiteLanguage
    {
        return clone $this->tsfe->cObj->getRequest()->getAttribute('language');
    }

    public function getIndexQueueItem(): Item
    {
        return $this->indexQueueItem;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    /**
     * @param Document[] $documents
     */
    public function addDocuments(array $documents): void
    {
        $this->documents = array_merge($this->documents, $documents);
    }

    /**
     * @return Document[]
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    /**
     * @param Document[] $documents
     */
    public function setDocuments(array $documents): void
    {
        $this->documents = $documents;
    }

    public function getTsfe(): TypoScriptFrontendController
    {
        return clone $this->tsfe;
    }
}
