<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Content extraction class for TYPO3 pages.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Typo3PageContentExtractor extends HtmlContentExtractor
{

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager
     */
    protected $logger = null;

    /**
     * Shortcut method to retrieve the raw content marked for indexing.
     *
     * @return string Content marked for indexing.
     */
    public function getContentMarkedForIndexing()
    {
        // @extensionScannerIgnoreLine
        return $this->extractContentMarkedForIndexing($this->content);
    }

    /**
     * Extracts the markup wrapped with TYPO3SEARCH_begin and TYPO3SEARCH_end
     * markers.
     *
     * @param string $html HTML markup with TYPO3SEARCH markers for content that should be indexed
     * @return string HTML markup found between TYPO3SEARCH markers
     */
    protected function extractContentMarkedForIndexing($html)
    {
        preg_match_all('/<!--\s*?TYPO3SEARCH_begin\s*?-->.*?<!--\s*?TYPO3SEARCH_end\s*?-->/mis',
            $html, $indexableContents);
        $indexableContent = implode('', $indexableContents[0]);

        $indexableContent = $this->excludeContentByClass($indexableContent);
        if (empty($indexableContent) && $this->getConfiguration()->getLoggingIndexingMissingTypo3SearchMarkers()) {
            $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
            $this->logger->log(SolrLogManager::WARNING, 'No TYPO3SEARCH markers found.');
        }

        return $indexableContent;
    }

    /**
     * Exclude some html parts by class inside content wrapped with TYPO3SEARCH_begin and TYPO3SEARCH_end
     * markers.
     *
     * @param string $indexableContent HTML markup
     * @return string HTML
     */
    public function excludeContentByClass($indexableContent)
    {
        if (empty(trim($indexableContent))) {
            return $indexableContent;
        }

        $excludeClasses = $this->getConfiguration()->getIndexQueuePagesExcludeContentByClassArray();
        if (count($excludeClasses) === 0) {
            return $indexableContent;
        }

        $isInContent = Util::containsOneOfTheStrings($indexableContent, $excludeClasses);
        if (!$isInContent) {
            return $indexableContent;
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . $indexableContent);
        $xpath = new \DOMXPath($doc);
        foreach ($excludeClasses as $excludePart) {
            $elements = $xpath->query("//*[contains(@class,'" . $excludePart . "')]");
            if (count($elements) == 0) {
                continue;
            }

            foreach ($elements as $element) {
                $element->parentNode->removeChild($element);
            }
        }
        $html = $doc->saveHTML($doc->documentElement->parentNode);
        // remove XML-Preamble, newlines and doctype
        $html = preg_replace('/(<\?xml[^>]+\?>|\r?\n|<!DOCTYPE.+?>)/imS', '', $html);
        $html = str_replace(['<html>', '</html>', '<body>', '</body>'], ['', '', '', ''], $html);

        return $html;
    }

    /**
     * Returns the cleaned indexable content from the page's HTML markup.
     *
     * The content is cleaned from HTML tags and control chars Solr could
     * stumble on.
     *
     * @return string Indexable, cleaned content ready for indexing.
     */
    public function getIndexableContent()
    {
        // @extensionScannerIgnoreLine
        $content = $this->extractContentMarkedForIndexing($this->content);

        // clean content
        $content = self::cleanContent($content);
        $content = trim($content);
        $content = preg_replace('!\s+!u', ' ', $content); // reduce multiple spaces to one space

        return $content;
    }

    /**
     * Retrieves the page's title by checking the indexedDocTitle, altPageTitle,
     * and regular page title - in that order.
     *
     * @return string the page's title
     */
    public function getPageTitle()
    {
        $page = $GLOBALS['TSFE'];

        if ($page->indexedDocTitle) {
            $pageTitle = $page->indexedDocTitle;
        } elseif ($page->altPageTitle) {
            $pageTitle = $page->altPageTitle;
        } else {
            $pageTitle = $page->page['title'];
        }

        return $pageTitle;
    }

    /**
     * Retrieves the page's body
     *
     * @return string the page's body
     */
    public function getPageBody()
    {
        // @extensionScannerIgnoreLine
        $pageContent = $this->content;

        return stristr($pageContent, '<body');
    }
}
