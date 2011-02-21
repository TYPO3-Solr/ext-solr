<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2011 Ingo Renner <ingo@typo3.org>
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


/**
 * Content extraction class for TYPO3 pages.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_Typo3PageContentExtractor {

	protected $pageHtml;

	protected $sourceCharset;

	/**
	 * mapping of HTML tags to Solr document fields
	 *
	 * @var	array
	 */
	protected $tagToFieldMapping = array(
		'h1'     => 'tagsH1',
		'h2'     => 'tagsH2H3',
		'h3'     => 'tagsH2H3',
		'h4'     => 'tagsH4H5H6',
		'h5'     => 'tagsH4H5H6',
		'h6'     => 'tagsH4H5H6',
		'u'      => 'tagsInline',
		'b'      => 'tagsInline',
		'strong' => 'tagsInline',
		'i'      => 'tagsInline',
		'em'     => 'tagsInline',
		'a'      => 'tagsA',
	);

	/**
	 * Constructor for class tx_solr_Typo3PageContentExtractor
	 *
	 * @param	string	The page's HTML markup
	 * @param	string	Optional charset of the given string, defaults to utf-8
	 */
	public function __construct($pageHtml, $sourceCharset = 'utf-8') {
		$this->sourceCharset = $sourceCharset;
		$this->pageHtml      = t3lib_div::makeInstance('t3lib_cs')->utf8_encode(
			$pageHtml,
			$this->sourceCharset
		);
	}

	/**
	 * Shortcut method to retrieve the raw content marked for indexing.
	 *
	 * @return	string	Content marked for indexing.
	 */
	public function getContentMarkedForIndexing() {
		return $this->extractContentMarkedForIndexing($this->pageHtml);
	}

	/**
	 * Returns the cleaned indexable content from the page's HTML markup.
	 *
	 * The content is cleaned from HTML tags and control chars Solr could
	 * stumble on.
	 *
	 * @return	string	Indexable, cleaned content ready for indexing.
	 */
	public function getIndexableContent() {
		$content = $this->extractContentMarkedForIndexing($this->pageHtml);

			// clean content
		$content = self::cleanContent($content);
		$content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
		$content = strip_tags($content); // after entity decoding we might have tags again
		$content = trim($content);

		return $content;
	}

	/**
	 * Extracts the markup wrapped with TYPO3SEARCH_begin and TYPO3SEARCH_end
	 * markers.
	 *
	 * @param	string	HTML markup with TYPO3SEARCH markers for content that should be indexed
	 * @return	string	HTML markup found between TYPO3SEARCH markers
	 */
	protected function extractContentMarkedForIndexing($html) {
		$explodedContent  = preg_split('/\<\!\-\-[\s]?TYPO3SEARCH_/', $html);
		$indexableContent = '';

		if(count($explodedContent) > 1) {

			foreach($explodedContent as $explodedContentPart) {
				$contentPart = explode('-->', $explodedContentPart, 2);

				if (trim($contentPart[0]) == 'begin') {
					$indexableContent .= $contentPart[1];
					$previousExplodedContentPart = '';
				} elseif (trim($contentPart[0]) == 'end') {
					$indexableContent .= $previousExplodedContentPart;
				} else {
					$previousExplodedContentPart = $explodedContentPart;
				}
			}
		} else {
			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['indexing.']['missingTypo3SearchMarkers']) {
				t3lib_div::devLog('No TYPO3SEARCH markers found.', 'tx_solr', 2);
			}
		}

		return $indexableContent;
	}

	/**
	 * Strips control characters that cause Jetty/Solr to fail.
	 *
	 * @param	string	the content to sanitize
	 * @return	string	the sanitized content
	 * @see	http://w3.org/International/questions/qa-forms-utf-8.html
	 */
	public static function stripControlCharacters($content) {
			// Printable utf-8 does not include any of these chars below x7F
		return preg_replace('@[\x00-\x08\x0B\x0C\x0E-\x1F]@', ' ', $content);
	}

	/**
	 * Strips html tags and certain whitespace characters.
	 *
	 * @param	string	String to clean
	 * @return	string	String cleaned from tags and special whitespace characters
	 */
	public static function cleanContent($content) {
		$content = self::stripControlCharacters($content);

			// remove Javascript
		$content = preg_replace('@<script[^>]*>.*?<\/script>@msi', '', $content);

			// prevents concatenated words when stripping tags afterwards
		$content = str_replace(array('<', '>'), array(' <', '> '), $content);
		$content = strip_tags($content);
		$content = str_replace(array("\t", "\n", "\r"), array(), $content);
		$content = trim($content);

		return $content;
	}

	/**
	 * Retrieves the page's title by checking the indexedDocTitle, altPageTitle,
	 * and regular page title - in that order.
	 *
	 * @return	string	the page's title
	 */
	public function getPageTitle() {
		$page      = $GLOBALS['TSFE'];
		$pageTitle = '';

		if ($page->indexedDocTitle) {
			$pageTitle = $page->indexedDocTitle;
		} elseif ($page->altPageTitle) {
			$pageTitle = $page->altPageTitle;
		} else {
			$pageTite = $page->page['title'];
		}

		return $pageTitle;
	}

	/**
	 * Retrieves the page's body
	 *
	 * @return	string	the page's body
	 */
	public function getPageBody() {
		$pageContent = $this->pageHtml;

		return stristr($pageContent, '<body');
	}

	/**
	 * Extracts HTML tag content from tags in the content marked for indexing.
	 *
	 * @return	array	A mapping of Solr document field names to content found in certain.
	 */
	public function getTagContent() {
		$result  = array();
		$matches = array();
		$content = $this->getContentMarkedForIndexing();

			// strip all ignored tags
		$content = strip_tags(
			$content,
			'<' . implode('><', array_keys($this->tagToFieldMapping)) . '>'
		);

		preg_match_all(
			'@<('. implode('|', array_keys($this->tagToFieldMapping)) .')[^>]*>(.*)</\1>@Ui',
			$content,
			$matches
		);
		foreach ($matches[1] as $key => $tag) {
				// We don't want to index links auto-generated by the url filter.
			if ($tag != 'a' || !preg_match('@(?:http://|https://|ftp://|mailto:|smb://|afp://|file://|gopher://|news://|ssl://|sslv2://|sslv3://|tls://|tcp://|udp://|www\.)[a-zA-Z0-9]+@', $matches[2][$key])) {
				$result[$this->tagToFieldMapping[$tag]] .= ' '. $matches[2][$key];
			}
		}

		return $result;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_typo3pagecontentextractor.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_typo3pagecontentextractor.php']);
}

?>