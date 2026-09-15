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
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Allows third party extensions to replace or modify the page document
 * created by the indexer.
 *
 * Previously used with
 *   $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']
 * with SubstitutePageIndexer and PageFieldMappingIndexer screens
 */
final class AfterPageDocumentIsCreatedForIndexingEvent
{
    public function __construct(
        private Document $document,
        private readonly Item $indexQueueItem,
        private readonly array $record,
        private readonly ServerRequestInterface $request,
        private readonly TypoScriptConfiguration $configuration,
    ) {}

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function overrideDocument(Document $document): void
    {
        $this->document = $document;
    }

    public function getIndexQueueItem(): Item
    {
        return $this->indexQueueItem;
    }

    public function getIndexingConfigurationName(): string
    {
        return $this->indexQueueItem->getIndexingConfigurationName();
    }

    public function getSite(): Site
    {
        return $this->request->getAttribute('site');
    }

    public function getSiteLanguage(): SiteLanguage
    {
        return $this->request->getAttribute('language');
    }

    public function getRecord(): array
    {
        return $this->record;
    }

    public function getConfiguration(): TypoScriptConfiguration
    {
        return $this->configuration;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
