<?php
namespace ApacheSolrForTypo3\Solr\Plugin\Results;

/***************************************************************
*  Copyright notice
*
*  (c) 2008-2014 Dmitry Dulepov (dmitry@typo3.org)
*  (c) 2015 Ingo Renner (ingo@typo3.org)
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
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
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
 * The page browser used in search result listings
 *
 */
class PageBrowser {

	protected $numberOfPages;
	protected $pageParameterName = '[page]';
	protected $currentPage;
	protected $pagesBefore = 3;
	protected $pagesAfter = 3;

	protected $template;

	protected $configuration = array();
	protected $labels = array();
	protected $contentObject = null;


	/**
	 * PageBrowser constructor.
	 *
	 * @param array $configuration
	 * @param array $labels
	 */
	public function __construct(array $configuration, array $labels) {
		$this->configuration = $configuration;
		$this->labels        = $labels;

		$this->contentObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');

		$this->numberOfPages = $configuration['numberOfPages'];
		$this->currentPage   = $configuration['currentPage'];

		$this->pagesBefore = (int)$configuration['pagesBefore'];
		$this->pagesAfter  = (int)$configuration['pagesAfter'];

		$this->template = $this->contentObject->fileResource($configuration['templateFile']);
	}

	/**
	 * Produces the page browser HTML
	 *
	 * @return string Generated content
	 */
	public function render() {
		$pageBrowser = '';

		if ($this->numberOfPages > 1) {
			// Set up
			$markers = array(
				'###TEXT_FIRST###' => htmlspecialchars($this->labels['pagebrowser_first']),
				'###TEXT_NEXT###'  => htmlspecialchars($this->labels['pagebrowser_next']),
				'###TEXT_PREV###'  => htmlspecialchars($this->labels['pagebrowser_prev']),
				'###TEXT_LAST###'  => htmlspecialchars($this->labels['pagebrowser_last']),
			);
			$subPartMarkers = array();
			$subPart = $this->contentObject->getSubpart($this->template, '###PAGE_BROWSER###');

			// First page link
			if ($this->currentPage == 0) {
				$subPartMarkers['###ACTIVE_FIRST###'] = '';
			} else {
				$markers['###FIRST_LINK###'] = $this->getPageLink(0);
				$subPartMarkers['###INACTIVE_FIRST###'] = '';
			}

			// Prev page link
			if ($this->currentPage == 0) {
				$subPartMarkers['###ACTIVE_PREV###'] = '';
			} else {
				$markers['###PREV_LINK###'] = $this->getPageLink($this->currentPage - 1);
				$subPartMarkers['###INACTIVE_PREV###'] = '';
			}

			// Next link
			if ($this->currentPage >= $this->numberOfPages - 1) {
				$subPartMarkers['###ACTIVE_NEXT###'] = '';
			} else {
				$markers['###NEXT_LINK###'] = $this->getPageLink($this->currentPage + 1);
				$subPartMarkers['###INACTIVE_NEXT###'] = '';
			}

			// Last link
			if ($this->currentPage == $this->numberOfPages - 1) {
				$subPartMarkers['###ACTIVE_LAST###'] = '';
			} else {
				$markers['###LAST_LINK###'] = $this->getPageLink($this->numberOfPages - 1);
				$subPartMarkers['###INACTIVE_LAST###'] = '';
			}

			// Page links
			$actPageLinkSubPart      = trim($this->contentObject->getSubpart($subPart, '###CURRENT###'));
			$inactivePageLinkSubPart = trim($this->contentObject->getSubpart($subPart, '###PAGE###'));
			$pageLinks = '';
			$start = max($this->currentPage - $this->pagesBefore, 0);
			$end   = min($this->numberOfPages, $this->currentPage + $this->pagesAfter + 1);

			for ($i = $start; $i < $end; $i++) {
				$template = ($i == $this->currentPage ? $actPageLinkSubPart : $inactivePageLinkSubPart);
				$localMarkers = array(
					'###NUMBER###'         => $i,
					'###NUMBER_DISPLAY###' => $i + 1,
					'###LINK###'           => $this->getPageLink($i),
				);
				$pageLinks .= $this->contentObject->substituteMarkerArray($template, $localMarkers);
			}
			$subPartMarkers['###PAGE###'] = $pageLinks;
			$subPartMarkers['###CURRENT###'] = '';

			// Less pages part
			if ($start == 0 || !$this->configuration['enableLessPages']) {
				$subPartMarkers['###LESS_PAGES###'] = '';
			}

			// More pages part
			if ($end == $this->numberOfPages || !$this->configuration['enableMorePages']) {
				// We have all pages covered. Remove this part.
				$subPartMarkers['###MORE_PAGES###'] = '';
			}

			// Compile all together
			$pageBrowser = $this->contentObject->substituteMarkerArrayCached($subPart, $markers, $subPartMarkers);
			// Remove all comments
			$pageBrowser = preg_replace('/<!--\s*###.*?-->/', ' ', $pageBrowser);
		}

		return $pageBrowser;
	}

	/**
	 * Generates page link. Keeps all current URL parameters except for cHash and page parameter.
	 *
	 * @param int $page Page number starting from 1
	 * @return string Generated link
	 */
	protected function getPageLink($page) {
		// Prepare query string. We do both urlencoded and non-encoded version
		// because older TYPO3 versions use un-encoded parameter names
		$queryConf = array(
			'exclude' => $this->pageParameterName . ',' .
				rawurlencode($this->pageParameterName) .
				',cHash',
		);
		$additionalParams = urldecode($this->contentObject->getQueryArguments($queryConf));

		// Add page number
		if ($page > 0) {
			$additionalParams .= '&' . $this->pageParameterName . '=' . $page;
		}

		// Add extra query string from config
		$extraQueryString = trim($this->configuration['extraQueryString']);
		if (is_array($this->configuration['extraQueryString.'])) {
			$extraQueryString = $this->contentObject->stdWrap($extraQueryString, $this->configuration['extraQueryString.']);
		}

		if (strlen($extraQueryString) > 2 && $extraQueryString{0} == '&') {
			$additionalParams .= $extraQueryString;
		}

		// Assemble typolink configuration
		$conf = array(
			'parameter'        => $GLOBALS['TSFE']->id,
			'additionalParams' => $additionalParams,
			'useCacheHash'     => FALSE,
		);

		return htmlspecialchars($this->contentObject->typoLink_URL($conf));
	}

}
