<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers;

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

use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;


/**
 * Class SearchFormViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\ViewHelpers
 */
class SearchFormViewHelper extends AbstractSolrFrontendTagBasedViewHelper
{

    /**
     * @var string
     */
    protected $tagName = 'form';

    /**
     * @var TypoScriptFrontendController
     */
    protected $frontendController;

    /**
     * @var bool
     */
    protected $escapeChildren = true;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->frontendController = $GLOBALS['TSFE'];
    }

    /**
     * Initialize arguments.
     *
     * @return void
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerTagAttribute('enctype', 'string', 'MIME type with which the form is submitted');
        $this->registerTagAttribute('method', 'string', 'Transfer type (GET or POST)', false, 'get');
        $this->registerTagAttribute('name', 'string', 'Name of form');
        $this->registerTagAttribute('onreset', 'string', 'JavaScript: On reset of the form');
        $this->registerTagAttribute('onsubmit', 'string', 'JavaScript: On submit of the form');
        $this->registerUniversalTagAttributes();
    }

    /**
     * Render search form tag
     *
     * @param int|NULL $pageUid When not set current page is used
     * @param array|NULL $additionalFilters Additional filters
     * @param array $additionalParams query parameters to be attached to the resulting URI
     * @param integer $pageType type of the target page. See typolink.parameter
     * @param boolean $noCache set this to disable caching for the target page. You should not need this.
     * @param boolean $noCacheHash set this to supress the cHash query parameter created by TypoLink. You should not need this.
     * @param string $section The anchor to be added to the action URI (only active if $actionUri is not set)
     * @param boolean $absolute If set, the URI of the rendered link is absolute
     * @param boolean $addQueryString If set, the current query parameters will be kept in the URI
     * @param array $argumentsToBeExcludedFromQueryString arguments to be removed from the URI. Only active if $addQueryString = TRUE
     * @param string $addQueryStringMethod Set which parameters will be kept. Only active if $addQueryString = TRUE
     * @param bool $addSuggestUrl
     * @return string
     */
    public function render($pageUid = null, $additionalFilters = null, array $additionalParams = [], $noCache = false, $pageType = 0, $noCacheHash = false, $section = '', $absolute = false, $addQueryString = false, array $argumentsToBeExcludedFromQueryString = [], $addQueryStringMethod = null, $addSuggestUrl = true)
    {
        if ($pageUid === null && !empty($this->getTypoScriptConfiguration()->getSearchTargetPage())) {
            $pageUid = $this->getTypoScriptConfiguration()->getSearchTargetPage();
        }

        $uriBuilder = $this->controllerContext->getUriBuilder();
        $uri = $uriBuilder->reset()->setTargetPageUid($pageUid)->setTargetPageType($pageType)->setNoCache($noCache)->setUseCacheHash(!$noCacheHash)->setArguments($additionalParams)->setCreateAbsoluteUri($absolute)->setAddQueryString($addQueryString)->setArgumentsToBeExcludedFromQueryString($argumentsToBeExcludedFromQueryString)->setAddQueryStringMethod($addQueryStringMethod)->setSection($section)->build();

        $this->tag->addAttribute('action', trim($uri));
        if ($addSuggestUrl) {
            $this->tag->addAttribute('data-suggest', $this->getSuggestEidUrl($additionalFilters, $pageUid));
        }
        $this->tag->addAttribute('accept-charset', $this->frontendController->metaCharset);

        // Get search term
        $this->templateVariableContainer->add('q', $this->getQueryString());
        $this->templateVariableContainer->add('pageUid', $pageUid);
        $this->templateVariableContainer->add('languageUid', $this->frontendController->sys_language_uid);
        $formContent = $this->renderChildren();
        $this->templateVariableContainer->remove('q');
        $this->templateVariableContainer->remove('pageUid');
        $this->templateVariableContainer->remove('languageUid');

        $this->tag->setContent($formContent);

        return $this->tag->render();
    }

    /**
     * @return string
     */
    protected function getQueryString()
    {
        $resultSet = $this->getSearchResultSet();
        if ($resultSet === null) {
            return '';
        }
        return trim($this->getSearchResultSet()->getUsedSearchRequest()->getRawUserQuery());
    }

    /**
     * Returns the eID URL for the AJAX suggestion request
     *
     * This link should be touched by realurl etc
     *
     * @return string the full URL to the eID script including the needed parameters
     */
    /**
     * @param NULL|array $additionalFilters
     * @param int $pageUid
     * @return string
     */
    protected function getSuggestEidUrl($additionalFilters, $pageUid)
    {
        $suggestUrl = $this->frontendController->absRefPrefix;
        $suggestUrl .= '?eID=tx_solr_suggest&id=' . $pageUid;

        // add filters
        if (!empty($additionalFilters)) {
            $additionalFilters = json_encode($additionalFilters);
            $additionalFilters = rawurlencode($additionalFilters);

            $suggestUrl .= '&filters=' . $additionalFilters;
        }

        // adds the language parameter to the suggest URL
        if ($this->frontendController->sys_language_uid > 0) {
            $suggestUrl .= '&L=' . $this->frontendController->sys_language_uid;
        }

        return $suggestUrl;
    }
}
