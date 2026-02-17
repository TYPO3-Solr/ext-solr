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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Domain\Variants\IdBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\Typo3PageContentExtractor;
use ApacheSolrForTypo3\Solr\Util;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Builder class to build an ApacheSolrDocument
 *
 * Responsible to build \ApacheSolrForTypo3\Solr\System\Solr\Document\Document
 */
class Builder
{
    public function __construct(
        protected readonly IdBuilder $variantIdBuilder,
        protected ExtensionConfiguration $extensionConfiguration,
    ) {}

    /**
     * This method can be used to build a Document from a TYPO3 page.
     */
    public function fromPage(
        PageInformation $pageInformation,
        PageArguments $pageArguments,
        SiteLanguage $siteLanguage,
        string $pageContent,
        string $url,
        Rootline $pageAccessRootline,
        string $mountPointParameter = '',
    ): Document {
        $pageId = $pageInformation->getId();
        $pageRecord = $pageInformation->getPageRecord();

        $document = GeneralUtility::makeInstance(Document::class);
        $site = $this->getSiteByPageId($pageId);

        $accessGroups = $this->getDocumentIdGroups($pageAccessRootline);
        $documentId = $this->getPageDocumentId($pageInformation, $pageArguments, $siteLanguage, $accessGroups, $mountPointParameter);

        $document->setField('id', $documentId);
        $document->setField('site', $site->getSiteIdentifier());
        $document->setField('typo3Context_stringS', (string)Environment::getContext());
        if ($this->extensionConfiguration->getSiteHashStrategy() === 0) {
            $document->setField('site', $site->getDomain());
        }
        $document->setField('siteHash', $site->getSiteHash());
        $document->setField('domain_stringS', $site->getDomain());
        $document->setField('appKey', 'EXT:solr');
        $document->setField('type', 'pages');

        // system fields
        $document->setField('uid', $pageId);
        $document->setField('pid', $pageRecord['pid']);

        // variantId
        $variantId = $this->variantIdBuilder->buildFromTypeAndUid('pages', $pageId, $pageRecord, $site, $document);
        $document->setField('variantId', $variantId);

        $document->setField('typeNum', (int)$pageArguments->getPageType());
        $document->setField('created', $pageRecord['crdate']);
        $document->setField('changed', $pageRecord['SYS_LASTCHANGED']);

        $rootline = $this->getRootLineFieldValue($pageId, $mountPointParameter);
        $document->setField('rootline', $rootline);

        // access
        $this->addAccessField($document, $pageAccessRootline);
        $this->addEndtimeField($document, $pageRecord);

        // content
        $contentExtractor = $this->getExtractorForPageContent($pageContent);
        $document->setField('title', $contentExtractor->getPageTitle());
        $document->setField('subTitle', $pageRecord['subtitle']);
        $document->setField('navTitle', $pageRecord['nav_title']);
        $document->setField('author', $pageRecord['author']);
        $document->setField('description', $pageRecord['description']);
        $document->setField('abstract', $pageRecord['abstract']);
        $document->setField('content', $contentExtractor->getIndexableContent());
        $document->setField('url', $url);

        $this->addKeywordsField($document, $pageRecord);
        $this->addTagContentFields($document, $contentExtractor->getTagContent());

        return $document;
    }

    /**
     * Creates a Solr document with the basic / core fields set already.
     *
     * @throws DBALException
     */
    public function fromRecord(array $itemRecord, string $type, int $rootPageUid, string $accessRootLine): Document
    {
        /** @var Document $document */
        $document = GeneralUtility::makeInstance(Document::class);

        $site = $this->getSiteByPageId($rootPageUid);

        $documentId = $this->getDocumentId($type, $site->getRootPageId(), $itemRecord['uid']);

        // required fields
        $document->setField('id', $documentId);
        $document->setField('type', $type);
        $document->setField('appKey', 'EXT:solr');

        $document->setField('site', $site->getSiteIdentifier());
        $document->setField('typo3Context_stringS', (string)Environment::getContext());
        if ($this->extensionConfiguration->getSiteHashStrategy() === 0) {
            $document->setField('site', $site->getDomain());
        }
        $document->setField('siteHash', $site->getSiteHash());
        $document->setField('domain_stringS', $site->getDomain());

        // uid, pid
        $document->setField('uid', $itemRecord['uid']);
        $document->setField('pid', $itemRecord['pid']);

        // variantId
        $variantId = $this->variantIdBuilder->buildFromTypeAndUid($type, $itemRecord['uid'], $itemRecord, $site, $document);
        $document->setField('variantId', $variantId);

        // created, changed
        if (!empty($GLOBALS['TCA'][$type]['ctrl']['crdate'])) {
            $document->setField('created', $itemRecord[$GLOBALS['TCA'][$type]['ctrl']['crdate']]);
        }
        if (!empty($GLOBALS['TCA'][$type]['ctrl']['tstamp'])) {
            $document->setField('changed', $itemRecord[$GLOBALS['TCA'][$type]['ctrl']['tstamp']]);
        }

        // access, endtime
        $document->setField('access', $accessRootLine);
        if (!empty($GLOBALS['TCA'][$type]['ctrl']['enablecolumns']['endtime'])
            && $itemRecord[$GLOBALS['TCA'][$type]['ctrl']['enablecolumns']['endtime']] != 0
        ) {
            $document->setField('endtime', $itemRecord[$GLOBALS['TCA'][$type]['ctrl']['enablecolumns']['endtime']]);
        }

        return $document;
    }

    /**
     * @throws DBALException
     */
    protected function getPageDocumentId(PageInformation $pageInformation, PageArguments $pageArguments, SiteLanguage $siteLanguage, string $accessGroups, string $mountPointParameter): string
    {
        return Util::getPageDocumentId($pageInformation->getId(), (int)$pageArguments->getPageType(), $siteLanguage->getLanguageId(), $accessGroups, $mountPointParameter);
    }

    /**
     * @throws DBALException
     */
    protected function getDocumentId(string $type, int $rootPageId, int $recordUid): string
    {
        return Util::getDocumentId($type, $rootPageId, $recordUid);
    }

    /**
     * @throws DBALException
     */
    protected function getSiteByPageId(int $pageId): Site
    {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        return $siteRepository->getSiteByPageId($pageId);
    }

    /**
     * Returns extractor for given page content.
     */
    protected function getExtractorForPageContent(string $pageContent): Typo3PageContentExtractor
    {
        return GeneralUtility::makeInstance(Typo3PageContentExtractor::class, $pageContent);
    }

    /**
     * Builds the content for the rootline field.
     */
    protected function getRootLineFieldValue(int $pageId, string $mountPointParameter = ''): string
    {
        $rootline = (string)$pageId;
        if ($mountPointParameter !== '') {
            $rootline .= ',' . $mountPointParameter;
        }
        return $rootline;
    }

    /**
     * Returns a comma separated list of frontend user groups to use for the document ID.
     */
    protected function getDocumentIdGroups(Rootline $pageAccessRootline): string
    {
        $groups = $pageAccessRootline->getGroups();
        $groups = Rootline::cleanGroupArray($groups);

        if (empty($groups)) {
            $groups[] = 0;
        }

        return implode(',', $groups);
    }

    /**
     * Adds the access field to the document if needed.
     */
    protected function addAccessField(Document $document, Rootline $pageAccessRootline): void
    {
        $access = (string)$pageAccessRootline;
        if (trim($access) !== '') {
            $document->setField('access', $access);
        }
    }

    /**
     * Adds the endtime field value to the Document.
     */
    protected function addEndtimeField(Document $document, array $pageRecord): void
    {
        if (isset($pageRecord['endtime']) && $pageRecord['endtime'] > 0) {
            $document->setField('endtime', $pageRecord['endtime']);
        }
    }

    /**
     * Adds keywords, multi valued.
     */
    protected function addKeywordsField(Document $document, array $pageRecord): void
    {
        if (!isset($pageRecord['keywords'])) {
            return;
        }

        $keywords = array_unique(GeneralUtility::trimExplode(',', $pageRecord['keywords'], true));
        foreach ($keywords as $keyword) {
            $document->addField('keywords', $keyword);
        }
    }

    /**
     * Add content from several tags like headers, anchors, ...
     */
    protected function addTagContentFields(Document $document, array $tagContent = []): void
    {
        foreach ($tagContent as $fieldName => $fieldValue) {
            $document->setField($fieldName, $fieldValue);
        }
    }
}
