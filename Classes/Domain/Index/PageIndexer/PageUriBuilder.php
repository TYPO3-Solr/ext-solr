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

namespace ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer;

use ApacheSolrForTypo3\Solr\Event\Indexing\AfterFrontendPageUriForIndexingHasBeenGeneratedEvent;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class is used to build the indexing url for a TYPO3 frontend.
 * These sites have the pageId and language information encoded in the speaking url.
 *
 * EXT:solr will then extend the URL with additional header information to actually
 * trigger a frontend request to index a page.
 */
class PageUriBuilder
{
    protected SiteFinder $siteFinder;
    protected SolrLogManager $logger;
    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(
        ?SolrLogManager $logger = null,
        ?SiteFinder $siteFinder = null,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->logger = $logger ?? GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    /**
     * Builds and returns URI for page indexing from index queue item
     * Handles "pages" type only.
     *
     * @throws SiteNotFoundException
     */
    protected function buildPageIndexingUriFromPageItemAndLanguageId(
        Item $item,
        int $language = 0,
        string $mountPointParameter = '',
    ): UriInterface {
        $site = $this->siteFinder->getSiteByPageId($item->getRecordUid());
        $parameters = [];

        if ($language > 0) {
            $parameters['_language'] = $language;
        }

        if ($mountPointParameter !== '') {
            $parameters['MP'] = $mountPointParameter;
        }

        return $site->getRouter()->generateUri($item->getRecord(), $parameters);
    }

    protected function applyTypoScriptOverridesOnIndexingUrl(UriInterface $urlHelper, array $overrideConfiguration): UriInterface
    {
        // check whether we should use ssl / https
        if (!empty($overrideConfiguration['scheme'])) {
            $urlHelper = $urlHelper->withScheme($overrideConfiguration['scheme']);
        }

        // overwriting the host
        if (!empty($overrideConfiguration['host'])) {
            $urlHelper = $urlHelper->withHost($overrideConfiguration['host']);
        }

        // overwriting the port
        if (!empty($overrideConfiguration['port'])) {
            $urlHelper = $urlHelper->withPort((int)$overrideConfiguration['port']);
        }

        // setting a path if TYPO3 is installed in a subdirectory
        if (!empty($overrideConfiguration['path'])) {
            $urlHelper = $urlHelper->withPath($overrideConfiguration['path']);
        }

        return $urlHelper;
    }

    public function getPageIndexingUriFromPageItemAndLanguageId(
        Item $item,
        int $language = 0,
        string $mountPointParameter = '',
        array $options = []
    ): string {
        $pageIndexUri = $this->buildPageIndexingUriFromPageItemAndLanguageId($item, $language, $mountPointParameter);
        $overrideConfiguration = $options['frontendDataHelper.'] ?? [];
        $pageIndexUri = $this->applyTypoScriptOverridesOnIndexingUrl($pageIndexUri, $overrideConfiguration);

        if (!GeneralUtility::isValidUrl((string)$pageIndexUri)) {
            $this->logger->error(
                'Could not create a valid URL to get frontend data while trying to index a page.',
                [
                    'item' => (array)$item,
                    'constructed URL' => (string)$pageIndexUri,
                    'scheme' => $pageIndexUri->getScheme(),
                    'host' => $pageIndexUri->getHost(),
                    'path' => $pageIndexUri->getPath(),
                    'page ID' => $item->getRecordUid(),
                    'indexer options' => $options,
                ]
            );

            throw new RuntimeException(
                'Could not create a valid URL to get frontend data while trying to index a page. Created URL: ' . (string)$pageIndexUri,
                1311080805
            );
        }

        $event = new AfterFrontendPageUriForIndexingHasBeenGeneratedEvent($item, $pageIndexUri, $language, $mountPointParameter, $options);
        $event = $this->eventDispatcher->dispatch($event);
        return (string)$event->getPageIndexUri();
    }
}
