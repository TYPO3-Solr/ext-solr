<?php
namespace ApacheSolrForTypo3\Solr\Query;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Link builder for queries.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class LinkBuilder
{

    /**
     * Content object.
     *
     * @var ContentObjectRenderer
     */
    protected $contentObject;

    /**
     * Solr configuration.
     *
     * @var array
     */
    protected $solrConfiguration;

    /**
     * Solr query. This query's parameters are used in URL parameters.
     *
     * @var Query
     */
    protected $query = null;

    /**
     * URL GET parameter prefix
     *
     * @var string
     */
    protected $prefix = 'tx_solr';

    /**
     * Link target page ID.
     *
     * @var integer
     */
    protected $linkTargetPageId;

    /**
     * Additional URL parameters applicable to all URLs
     *
     * @var string
     */
    protected $urlParameters = '';

    /**
     * Parameters we do not want to appear in the URL, usually for esthetic
     * reasons.
     *
     * @var array
     */
    protected $unwantedUrlParameters = array('resultsPerPage', 'page');


    /**
     * Constructor.
     *
     * @param Query $query Solr query
     */
    public function __construct(Query $query)
    {
        $this->solrConfiguration = Util::getSolrConfiguration();
        $this->contentObject = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
        $this->query = $query;

        $targetPageUid = $this->contentObject->stdWrap(
            $this->solrConfiguration->getSearchTargetPage(),
            $this->solrConfiguration->getSearchTargetPageConfiguration()
        );
        $this->linkTargetPageId = $targetPageUid;
    }

    /**
     * Gets the target page Id for links.
     *
     * @return integer Page Id links are going to point to.
     */
    public function getLinkTargetPageId()
    {
        return $this->linkTargetPageId;
    }

    /**
     * Sets the target page Id for links
     *
     * @param integer $pageId Page Id links shall point to.
     */
    public function setLinkTargetPageId($pageId)
    {
        $this->linkTargetPageId = intval($pageId);
    }

    /**
     * Adds general URL GET parameters.
     *
     * @param string $urlParameters URL GET parameters.
     */
    public function addUrlParameters($urlParameters)
    {
        if ($urlParameters[0] != '&') {
            $urlParameters = '&' . $urlParameters;
        }

        $this->urlParameters .= $urlParameters;
    }

    /**
     * Adds an unwanted URL parameter that should get removed if found when
     * building URLs.
     *
     * @param string $unwantedUrlParameter URL GET parameter
     */
    public function addUnwantedUrlParameter($unwantedUrlParameter)
    {
        $this->unwantedUrlParameters[] = $unwantedUrlParameter;
    }

    /**
     * Removes an unwanted URL parameter that should not get removed if found when
     * building URLs.
     *
     * @param string $unwantedUrlParameter URL GET parameter
     */
    public function removeUnwantedUrlParameter($unwantedUrlParameter)
    {
        $key = array_search($unwantedUrlParameter,
            $this->unwantedUrlParameters);
        if ($key !== false) {
            unset($this->unwantedUrlParameters[$key]);
        }
    }

    /**
     * Generates a URL.
     *
     * TODO currently everything in $additionalQueryParameters is prefixed with tx_solr,
     * allow arbitrary parameters, too (either filter them out or introduce a new 3rd parameter)
     *
     * @param array $additionalQueryParameters Additional query parameters
     * @param array $typolinkOptions Typolink Options
     * @return string A query URL
     */
    public function getQueryUrl(
        array $additionalQueryParameters = array(),
        array $typolinkOptions = array()
    ) {
        $linkConfigurationOverwrite = array('returnLast' => 'url');
        $link = $this->getQueryLink(
            '',
            $additionalQueryParameters,
            array_merge($typolinkOptions, $linkConfigurationOverwrite)
        );
        return htmlspecialchars($link);
    }

    /**
     * Generates a html link - an anchor tag.
     *
     * TODO currently everything in $additionalQueryParameters is prefixed with tx_solr,
     * allow arbitrary parameters, too (either filter them out or introduce a new 4th parameter)
     *
     * @param string $linkText Link Text
     * @param array $additionalQueryParameters Additional query parameters
     * @param array $typolinkOptions Typolink Options
     * @return string A html link
     */
    public function getQueryLink(
        $linkText,
        array $additionalQueryParameters = array(),
        array $typolinkOptions = array()
    ) {
        $queryParameters = array_merge(
            $this->getPluginParameters(),
            $additionalQueryParameters
        );
        $queryParameters = $this->removeUnwantedUrlParameters($queryParameters);

        $queryGetParameter = '';

        $keywords = $this->query->getKeywords();
        if (!empty($keywords)) {
            $queryGetParameter = '&q=' . $keywords;
        }


        $linkConfiguration = array(
            'useCacheHash' => false,
            'no_cache' => false,
            'parameter' => $this->linkTargetPageId,
            'additionalParams' => $queryGetParameter
                . \TYPO3\CMS\Core\Utility\GeneralUtility::implodeArrayForUrl('',
                    array($this->prefix => $queryParameters), '', true)
                . $this->getUrlParameters()
        );

        // merge linkConfiguration with typolinkOptions
        $linkConfiguration = array_merge($linkConfiguration, $typolinkOptions);

        return $this->contentObject->typoLink($linkText, $linkConfiguration);
    }

    /**
     * Gets the plugin parameters from GET and POST parameters.
     *
     * Usually known as piVars.
     *
     * @return array Array of GET and POST parameters for the extension.
     */
    protected function getPluginParameters()
    {
        $pluginParameters = \TYPO3\CMS\Core\Utility\GeneralUtility::_GPmerged($this->prefix);

        return $pluginParameters;
    }

    /**
     * Filters out unwanted parameters when building query URLs
     *
     * @param array $urlParameters An array of parameters that shall be used to build a URL.
     * @return array Array with wanted parameters only, ready to be used for URL building.
     */
    public function removeUnwantedUrlParameters(array $urlParameters)
    {
        foreach ($this->unwantedUrlParameters as $unwantedUrlParameter) {
            unset($urlParameters[$unwantedUrlParameter]);
        }

        return $urlParameters;
    }

    /**
     * Gets general URL GET parameters.
     *
     * @return string Additional URL GET parameters.
     */
    public function getUrlParameters()
    {
        $urlParameters = $this->urlParameters;

        if (!empty($urlParameters) && $urlParameters[0] != '&') {
            $urlParameters = '&' . $urlParameters;
        }

        return $urlParameters;
    }

    /**
     * Sets general URL GET parameters.
     *
     * @param string $urlParameters URL GET parameters.
     */
    public function setUrlParameters($urlParameters)
    {
        $this->urlParameters = $urlParameters;
    }
}
