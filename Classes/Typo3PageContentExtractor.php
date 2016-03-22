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
 *  the Free Software Foundation; either version 2 of the License, or
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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Content extraction class for TYPO3 pages.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Typo3PageContentExtractor extends HtmlContentExtractor
{

    /**
     * Shortcut method to retrieve the raw content marked for indexing.
     *
     * @return string Content marked for indexing.
     */
    public function getContentMarkedForIndexing()
    {
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
        $indexableContent = implode($indexableContents[0], '');

        $indexableContent = $this->excludeContentByClass($indexableContent);
        $configuration = Util::getSolrConfiguration();
        if (empty($indexableContent) && $configuration->getLoggingIndexingMissingTypo3SearchMarkers()) {
            GeneralUtility::devLog('No TYPO3SEARCH markers found.', 'solr', 2);
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
        if (empty($indexableContent) || empty($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['queue.']['pages.']['excludeContentByClass'])) {
            return $indexableContent;
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . $indexableContent);
        $xpath = new \DOMXPath($doc);
        $excludeParts = GeneralUtility::trimExplode(',', $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['queue.']['pages.']['excludeContentByClass'], true);
        foreach ($excludeParts as $excludePart) {
            $elements = $xpath->query("//*[contains(@class,'".$excludePart."')]");
            if (count($elements) > 0) {
                foreach ($elements as $element) {
                    $element->parentNode->removeChild($element);
                }
            }
        }
        $html = $doc->saveHTML($doc->documentElement->parentNode);
        // remove XML-Preamble, newlines and doctype
        $html = preg_replace('/(<\?xml[^>]+\?>|\r?\n|<!DOCTYPE.+?>)/imS', '', $html);
        $html = str_replace(array('<html>', '</html>', '<body>', '</body>'), array('', '', '', ''), $html);

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
        $content = $this->extractContentMarkedForIndexing($this->content);

        // clean content
        $content = self::cleanContent($content);
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $content = strip_tags($content); // after entity decoding we might have tags again
        $content = trim($content);

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
        $pageTitle = '';

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
        $pageContent = $this->content;

        return stristr($pageContent, '<body');
    }
}
