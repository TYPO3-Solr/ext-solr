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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\Access\RootlineElement;
use ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder\AbstractUriStrategy;
use ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriStrategyFactory;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use Exception;
use RuntimeException;
use TYPO3\CMS\Core\Type\Bitmask\PageTranslationVisibility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A special purpose indexer to index pages.
 *
 * In the case of pages we can't directly index the page records, we need to
 * retrieve the content that belongs to a page from tt_content, too.
 * Also, plugins may be included on a page and thus may need to be executed.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class PageIndexer extends Indexer
{
    /**
     * Indexes an item from the indexing queue.
     *
     * @param Item $item An index queue item
     * @return bool Whether indexing was successful
     * @throws DBALDriverException
     * @throws DBALException
     * @throws NoSolrConnectionFoundException
     */
    public function index(Item $item): bool
    {
        $this->setLogging($item);

        // check whether we should move on at all
        if (!$this->isPageIndexable($item)) {
            return false;
        }

        $solrConnections = $this->getSolrConnectionsByItem($item);
        foreach ($solrConnections as $systemLanguageUid => $solrConnection) {
            $contentAccessGroups = $this->getAccessGroupsFromContent($item, $systemLanguageUid);

            if (empty($contentAccessGroups)) {
                // might be an empty page w/no content elements or some TYPO3 error / bug
                // FIXME logging needed
                continue;
            }

            foreach ($contentAccessGroups as $userGroup) {
                $this->indexPage($item, $systemLanguageUid, (int)$userGroup);
            }
        }

        return true;
    }

    /**
     * Checks whether we can index this page.
     *
     * @param Item $item The page we want to index encapsulated in an index queue item
     * @return bool True if we can index this page, FALSE otherwise
     */
    protected function isPageIndexable(Item $item): bool
    {
        // TODO do we still need this?
        // shouldn't those be sorted out by the record monitor / garbage collector already?

        $isIndexable = true;
        $record = $item->getRecord();

        if (isset($GLOBALS['TCA']['pages']['ctrl']['enablecolumns']['disabled'])
            && $record[$GLOBALS['TCA']['pages']['ctrl']['enablecolumns']['disabled']]
        ) {
            $isIndexable = false;
        }

        return $isIndexable;
    }

    /**
     * Gets the Solr connections applicable for a page.
     *
     * The connections include the default connection and connections to be used
     * for translations of a page.
     *
     * @param Item $item An index queue item
     * @return array An array of ApacheSolrForTypo3\Solr\System\Solr\SolrConnection connections, the array's keys are the sys_language_uid of the language of the connection
     * @throws DBALDriverException
     * @throws NoSolrConnectionFoundException
     */
    protected function getSolrConnectionsByItem(Item $item): array
    {
        $solrConnections = parent::getSolrConnectionsByItem($item);

        $page = $item->getRecord();
        if ((new PageTranslationVisibility((int)($page['l18n_cfg'] ?? 0)))->shouldBeHiddenInDefaultLanguage()) {
            // page is configured to hide the default translation -> remove Solr connection for default language
            unset($solrConnections[0]);
        }

        if ((new PageTranslationVisibility((int)($page['l18n_cfg'] ?? 0)))->shouldHideTranslationIfNoTranslatedRecordExists()) {
            $accessibleSolrConnections = [];
            if (isset($solrConnections[0])) {
                $accessibleSolrConnections[0] = $solrConnections[0];
            }

            $translationOverlays = $this->pagesRepository->findTranslationOverlaysByPageId((int)$page['uid']);

            foreach ($translationOverlays as $overlay) {
                $languageId = $overlay['sys_language_uid'];
                if (array_key_exists($languageId, $solrConnections)) {
                    $accessibleSolrConnections[$languageId] = $solrConnections[$languageId];
                }
            }

            $solrConnections = $accessibleSolrConnections;
        }

        return $solrConnections;
    }

    /**
     * Finds the FE user groups used on a page including all groups of content
     * elements and groups of records of extensions that have correctly been
     * pushed through ContentObjectRenderer during rendering.
     *
     * @param Item $item Index queue item representing the current page to get the user groups from
     * @param int $language The sys_language_uid language ID
     * @return array Array of user group IDs
     * @throws DBALDriverException
     * @throws DBALException
     */
    protected function getAccessGroupsFromContent(Item $item, int $language = 0): array
    {
        static $accessGroupsCache;

        $accessGroupsCacheEntryId = $item->getRecordUid() . '|' . $language;
        if (!isset($accessGroupsCache[$accessGroupsCacheEntryId])) {
            $request = $this->buildBasePageIndexerRequest();
            $request->setIndexQueueItem($item);
            $request->addAction('findUserGroups');

            $indexRequestUrl = $this->getDataUrl($item, $language);
            $response = $request->send($indexRequestUrl);

            $groups = $response->getActionResult('findUserGroups');
            if (is_array($groups)) {
                $accessGroupsCache[$accessGroupsCacheEntryId] = $groups;
            }

            if ($this->loggingEnabled) {
                $this->logger->log(
                    SolrLogManager::INFO,
                    'Page Access Groups',
                    [
                        'item' => (array)$item,
                        'language' => $language,
                        'index request url' => $indexRequestUrl,
                        'request' => (array)$request,
                        'response' => (array)$response,
                        'groups' => $groups,
                    ]
                );
            }
        }

        return $accessGroupsCache[$accessGroupsCacheEntryId];
    }

    // Utility methods

    /**
     * Builds a base page indexer request with configured headers and other
     * parameters.
     *
     * @return PageIndexerRequest Base page indexer request
     */
    protected function buildBasePageIndexerRequest(): PageIndexerRequest
    {
        $request = $this->getPageIndexerRequest();
        $request->setParameter('loggingEnabled', $this->loggingEnabled);

        if (!empty($this->options['authorization.'])) {
            $request->setAuthorizationCredentials(
                $this->options['authorization.']['username'],
                $this->options['authorization.']['password']
            );
        }

        if (!empty($this->options['frontendDataHelper.']['headers.'])) {
            foreach ($this->options['frontendDataHelper.']['headers.'] as $headerValue) {
                $request->addHeader($headerValue);
            }
        }

        if (!empty($this->options['frontendDataHelper.']['requestTimeout'])) {
            $request->setTimeout((float)$this->options['frontendDataHelper.']['requestTimeout']);
        }

        return $request;
    }

    /**
     * @return PageIndexerRequest
     */
    protected function getPageIndexerRequest(): PageIndexerRequest
    {
        return GeneralUtility::makeInstance(PageIndexerRequest::class);
    }

    /**
     * Determines a page ID's URL.
     *
     * Tries to find a domain record to use to build a URL for a given page ID
     * and then actually build and return the page URL.
     *
     * @param Item $item Item to index
     * @param int $language The language id
     * @return string URL to send the index request to
     * @throws DBALDriverException
     * @throws DBALException
     * @throws Exception
     */
    protected function getDataUrl(Item $item, int $language = 0): string
    {
        $pageId = $item->getRecordUid();
        $strategy = $this->getUriStrategy($pageId);
        $mountPointParameter = $this->getMountPageDataUrlParameter($item);
        return $strategy->getPageIndexingUriFromPageItemAndLanguageId($item, $language, $mountPointParameter, $this->options);
    }

    /**
     * @param int $pageId
     * @return AbstractUriStrategy
     * @throws Exception
     */
    protected function getUriStrategy(int $pageId): AbstractUriStrategy
    {
        return GeneralUtility::makeInstance(UriStrategyFactory::class)->getForPageId($pageId);
    }

    /**
     * Generates the MP URL parameter needed to access mount pages. If the item
     * is identified as being a mounted page, the &MP parameter is generated.
     *
     * @param Item $item Item to get an &MP URL parameter for
     * @return string 'MP' URL parameter if $item is a mounted page
     * @throws DBALDriverException
     * @throws DBALException
     */
    protected function getMountPageDataUrlParameter(Item $item): string
    {
        if (!$item->hasIndexingProperty('isMountedPage')) {
            return '';
        }

        return $item->getIndexingProperty('mountPageSource') . '-' . $item->getIndexingProperty('mountPageDestination');
    }

    //
    // Frontend User Groups Access
    //

    /**
     * Creates a single Solr Document for a page in a specific language and for
     * a specific frontend user group.
     *
     * @param Item $item The index queue item representing the page.
     * @param ?int $language The language to use.
     * @param ?int $userGroup The frontend user group to use.
     * @return PageIndexerResponse Page indexer response
     * @throws DBALDriverException
     * @throws DBALException
     */
    protected function indexPage(Item $item, ?int $language = 0, ?int $userGroup = 0): PageIndexerResponse
    {
        $accessRootline = $this->getAccessRootline($item, $language, $userGroup);
        $request = $this->buildBasePageIndexerRequest();
        $request->setIndexQueueItem($item);
        $request->addAction('indexPage');
        $request->setParameter('accessRootline', (string)$accessRootline);

        $indexRequestUrl = $this->getDataUrl($item, $language);
        $response = $request->send($indexRequestUrl);
        $indexActionResult = $response->getActionResult('indexPage');

        if ($this->loggingEnabled) {
            $logSeverity = SolrLogManager::INFO;
            $logStatus = 'Info';
            if (!empty($indexActionResult['pageIndexed'])) {
                $logSeverity = SolrLogManager::NOTICE;
                $logStatus = 'Success';
            }

            $this->logger->log(
                $logSeverity,
                'Page Indexer: ' . $logStatus,
                [
                    'item' => (array)$item,
                    'language' => $language,
                    'user group' => $userGroup,
                    'index request url' => $indexRequestUrl,
                    'request' => (array)$request,
                    'request headers' => $request->getHeaders(),
                    'response' => (array)$response,
                ]
            );
        }

        if (empty($indexActionResult['pageIndexed'])) {
            $message = 'Failed indexing page Index Queue item: ' . $item->getIndexQueueUid() . ' url: ' . $indexRequestUrl;

            throw new RuntimeException($message, 1331837081);
        }

        return $response;
    }

    /**
     * Generates a page document's "Access Rootline".
     *
     * The Access Rootline collects frontend user group access restrictions set
     * for pages up in a page's rootline extended to sub-pages.
     *
     * The format is like this:
     * pageId1:group1,group2|groupId2:group3|c:group1,group4,groupN
     *
     * The single elements of the access rootline are separated by a pipe
     * character. All but the last elements represent pages, the last element
     * defines the access restrictions applied to the page's content elements
     * and records shown on the page.
     * Each page element is composed by the page ID of the page setting frontend
     * user access restrictions, a colon, and a comma separated list of frontend
     * user group IDs restricting access to the page.
     * The content access element does not have a page ID, instead it replaces
     * the ID by a lower case C.
     *
     * @param Item $item Index queue item representing the current page
     * @param int $language The sys_language_uid language ID
     * @param int|null $contentAccessGroup The user group to use for the content access rootline element. Optional, will be determined automatically if not set.
     * @return mixed|Rootline An Access Rootline.
     * @throws DBALDriverException
     * @throws DBALException
     */
    protected function getAccessRootline(Item $item, int $language = 0, int $contentAccessGroup = null)
    {
        static $accessRootlineCache;

        $mountPointParameter = $this->getMountPageDataUrlParameter($item);

        $accessRootlineCacheEntryId = $item->getRecordUid() . '|' . $language;
        if ($mountPointParameter !== '') {
            $accessRootlineCacheEntryId .= '|' . $mountPointParameter;
        }
        if (!is_null($contentAccessGroup)) {
            $accessRootlineCacheEntryId .= '|' . $contentAccessGroup;
        }

        if (!isset($accessRootlineCache[$accessRootlineCacheEntryId])) {
            $accessRootline = $this->getAccessRootlineByPageId($item->getRecordUid(), $mountPointParameter);

            // current page's content access groups
            $contentAccessGroups = [$contentAccessGroup];
            if (is_null($contentAccessGroup)) {
                $contentAccessGroups = $this->getAccessGroupsFromContent($item, $language);
            }
            $element = GeneralUtility::makeInstance(RootlineElement::class, /** @scrutinizer ignore-type */ 'c:' . implode(',', $contentAccessGroups));
            $accessRootline->push($element);

            $accessRootlineCache[$accessRootlineCacheEntryId] = $accessRootline;
        }

        return $accessRootlineCache[$accessRootlineCacheEntryId];
    }

    /**
     * Returns the access rootLine for a certain pageId.
     *
     * @param int $pageId
     * @param string $mountPointParameter
     * @return Rootline
     */
    protected function getAccessRootlineByPageId(int $pageId, string $mountPointParameter): Rootline
    {
        return Rootline::getAccessRootlineByPageId($pageId, $mountPointParameter);
    }
}
