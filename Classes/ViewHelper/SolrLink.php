<?php
namespace ApacheSolrForTypo3\Solr\ViewHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Viewhelper class to create links containing solr parameters
 * Replaces viewhelpers ###SOLR_LINK:linkText|linkTarget|additionalParameters|useCache|urlOnly###
 *
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class SolrLink implements ViewHelper
{

    /**
     * Instance of ApacheSolrForTypo3\Solr\Search
     *
     * @var Search
     */
    protected $search = null;

    /**
     * Instance of ContentObjectRenderer
     *
     * @var ContentObjectRenderer
     */
    protected $contentObject = null;


    /**
     * Constructor
     *
     * @param array $arguments
     */
    public function __construct(array $arguments = array())
    {
        if (is_null($this->contentObject)) {
            $this->contentObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
        }

        if (is_null($this->search)) {
            $this->search = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Search');
        }
    }

    /**
     * Creates a link to a given page with a given link text with the current
     * tx_solr parameters appended to the URL
     *
     * @param array Array of arguments, [0] is the link text, [1] is the (optional) page Id to link to (otherwise TSFE->id), [2] are additional URL parameters, [3] use cache, defaults to FALSE
     * @return string complete anchor tag with URL and link text
     */
    public function execute(array $arguments = array())
    {
        $linkText = $arguments[0];
        $pageId = $this->determinePageId(trim($arguments[1]));
        $additionalUrlParameters = $arguments[2] ? $arguments[2] : '';
        $useCache = $arguments[3] ? true : false;
        $returnOnlyUrl = $arguments[4] ? true : false;

        // FIXME pass anything not prefixed with tx_solr in $additionalParameters as 4th parameter
        $additionalUrlParameters = GeneralUtility::explodeUrl2Array($additionalUrlParameters,
            true);
        $solrUrlParameters = array();
        if (!empty($additionalUrlParameters['tx_solr'])) {
            $solrUrlParameters = $additionalUrlParameters['tx_solr'];
        }

        $linkConfiguration = array('useCacheHash' => $useCache);

        if ($returnOnlyUrl) {
            $linkConfiguration['returnLast'] = 'url';
        }

        if ($this->search->hasSearched()) {
            $query = $this->search->getQuery();
        } else {
            $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query',
                '');
        }

        $linkBuilder = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query\\LinkBuilder',
            $query);
        $linkBuilder->setLinkTargetPageId($pageId);
        $queryLink = $linkBuilder->getQueryLink(
            $linkText,
            $solrUrlParameters,
            $linkConfiguration
        );

        return $queryLink;
    }

    /**
     * Take the link target ID viewhelper argument and try to find a page ID from it.
     *
     * @param string $linkArgument The viewhelper's link target argument
     * @return integer Page ID
     * @throws \InvalidArgumentException if an invalid TypoScript path was given
     */
    protected function determinePageId($linkArgument)
    {
        $pageId = $GLOBALS['TSFE']->id;

        if (is_numeric($linkArgument)) {
            // if the link target is a number, interpret it as a page ID
            $pageId = intval($linkArgument);
        } elseif (is_string($linkArgument) && !empty($linkArgument)) {
            // interpret a TypoScript path
            try {
                /** @var \ApacheSolrForTypo3\Solr\Configuration\TypoScriptConfiguration $configuration */
                $configuration = Util::getSolrConfiguration();
                $typoscript = $configuration->getObjectByPath($linkArgument);
                $pathExploded = explode('.', $linkArgument);
                $lastPathSegment = array_pop($pathExploded);

                $pageId = intval($typoscript[$lastPathSegment]);
            } catch (\InvalidArgumentException $e) {
                // ignore exceptions caused by markers, but accept the exception for wrong TS paths
                if (substr($linkArgument, 0, 3) != '###') {
                    throw $e;
                }
            }
        }

        return $pageId;
    }
}
