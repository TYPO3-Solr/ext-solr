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

namespace ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder;

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerDataUrlModifier;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Url\UrlHelper;
use RuntimeException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Implementations of this class are able to build an indexing url for solr page indexing.
 */
abstract class AbstractUriStrategy
{
    /**
     * @var SolrLogManager
     */
    protected SolrLogManager $logger;

    /**
     * AbstractUriStrategy constructor.
     * @param SolrLogManager|null $logger
     */
    public function __construct(
        SolrLogManager $logger = null
    ) {
        $this->logger = $logger ?? GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
    }

    /**
     * @param UrlHelper $urlHelper
     * @param array $overrideConfiguration
     * @return UrlHelper
     */
    protected function applyTypoScriptOverridesOnIndexingUrl(UrlHelper $urlHelper, array $overrideConfiguration = []): UrlHelper
    {
        // check whether we should use ssl / https
        if (!empty($overrideConfiguration['scheme'])) {
            $urlHelper->setScheme($overrideConfiguration['scheme']);
        }

        // overwriting the host
        if (!empty($overrideConfiguration['host'])) {
            $urlHelper->setHost($overrideConfiguration['host']);
        }

        // overwriting the port
        if (!empty($overrideConfiguration['port'])) {
            $urlHelper->setPort($overrideConfiguration['port']);
        }

        // setting a path if TYPO3 is installed in a subdirectory
        if (!empty($overrideConfiguration['path'])) {
            $urlHelper->setPath($overrideConfiguration['path']);
        }

        return $urlHelper;
    }

    /**
     * @param Item $item
     * @param int $language
     * @param string $mountPointParameter
     * @param array $options
     * @return string
     */
    public function getPageIndexingUriFromPageItemAndLanguageId(
        Item $item,
        int $language = 0,
        string $mountPointParameter = '',
        array $options = []
    ): string {
        $pageIndexUri = $this->buildPageIndexingUriFromPageItemAndLanguageId($item, $language, $mountPointParameter);
        $urlHelper = GeneralUtility::makeInstance(UrlHelper::class, $pageIndexUri);
        $overrideConfiguration = $options['frontendDataHelper.'] ?? [];
        $urlHelper = $this->applyTypoScriptOverridesOnIndexingUrl($urlHelper, $overrideConfiguration);
        $dataUrl = $urlHelper->getUrl();

        if (!GeneralUtility::isValidUrl($dataUrl)) {
            $this->logger->log(
                SolrLogManager::ERROR,
                'Could not create a valid URL to get frontend data while trying to index a page.',
                [
                    'item' => (array)$item,
                    'constructed URL' => $dataUrl,
                    'scheme' => $urlHelper->getScheme(),
                    'host' => $urlHelper->getHost(),
                    'path' => $urlHelper->getPath(),
                    'page ID' => $item->getRecordUid(),
                    'indexer options' => $options,
                ]
            );

            throw new RuntimeException(
                'Could not create a valid URL to get frontend data while trying to index a page. Created URL: ' . $dataUrl,
                1311080805
            );
        }

        return $this->applyDataUrlModifier($item, $language, $dataUrl, $urlHelper);
    }

    /**
     * @param Item $item
     * @param int $language
     * @param string $mountPointParameter
     * @return mixed
     */
    abstract protected function buildPageIndexingUriFromPageItemAndLanguageId(
        Item $item,
        int $language = 0,
        string $mountPointParameter = ''
    );

    /**
     * @param Item $item
     * @param int $language
     * @param string $dataUrl
     * @param UrlHelper $urlHelper
     * @return string
     */
    protected function applyDataUrlModifier(
        Item $item,
        int $language,
        string $dataUrl,
        UrlHelper $urlHelper
    ): string {
        if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueuePageIndexer']['dataUrlModifier'])) {
            return $dataUrl;
        }

        $dataUrlModifier = GeneralUtility::makeInstance($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueuePageIndexer']['dataUrlModifier']);
        if (!$dataUrlModifier instanceof PageIndexerDataUrlModifier) {
            throw new RuntimeException($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueuePageIndexer']['dataUrlModifier'] . ' is not an implementation of ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerDataUrlModifier', 1290523345);
        }

        return $dataUrlModifier->modifyDataUrl(
            $dataUrl,
            [
                'item' => $item, 'scheme' => $urlHelper->getScheme(), 'host' => $urlHelper->getHost(),
                'path' => $urlHelper->getPath(), 'pageId' => $item->getRecordUid(), 'language' => $language,
            ]
        );
    }
}
