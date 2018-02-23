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

        $this->registerArgument('pageUid', 'integer', 'When not set current page is used', false);
        $this->registerArgument('additionalFilters', 'array', 'Additional filters', false);
        $this->registerArgument('additionalParams', 'array', 'Query parameters to be attached to the resulting URI', false, []);
        $this->registerArgument('pageType', 'integer', 'Type of the target page. See typolink.parameter', false, 0);

        $this->registerArgument('noCache', 'boolean', 'Set this to disable caching for the target page. You should not need this.', false, false);
        $this->registerArgument('noCacheHash', 'boolean', 'Set this to supress the cHash query parameter created by TypoLink. You should not need this.', false, false);
        $this->registerArgument('section', 'string', 'The anchor to be added to the action URI (only active if $actionUri is not set)', false, '');
        $this->registerArgument('absolute', 'boolean', 'If set, the URI of the rendered link is absolute', false, false);
        $this->registerArgument('addQueryString', 'boolean', 'If set, the current query parameters will be kept in the URI', false, false);
        $this->registerArgument('argumentsToBeExcludedFromQueryString', 'array', 'arguments to be removed from the URI. Only active if $addQueryString = TRUE', false, []);
        $this->registerArgument('addQueryStringMethod', 'string', 'Set which parameters will be kept. Only active if $addQueryString = TRUE', false);
        $this->registerArgument('addSuggestUrl', 'boolean', 'Indicates if suggestUrl should be rendered or not', false, true);
        $this->registerArgument('suggestHeader', 'string', 'The header for the top results', false, 'Top Results');
    }

    /**
     * Render search form tag
     *
     * @return string
     */
    public function render()
    {
        $pageUid = $this->arguments['pageUid'];
        if ($pageUid === null && !empty($this->getTypoScriptConfiguration()->getSearchTargetPage())) {
            $pageUid = $this->getTypoScriptConfiguration()->getSearchTargetPage();
        }

        $uri = $this->buildUriFromPageUidAndArguments($pageUid);

        $this->tag->addAttribute('action', trim($uri));
        if ($this->arguments['addSuggestUrl']) {
            $this->tag->addAttribute('data-suggest', $this->getSuggestEidUrl($this->arguments['additionalFilters'], $pageUid));
        }
        $this->tag->addAttribute('data-suggest-header', htmlspecialchars($this->arguments['suggestHeader']));
        $this->tag->addAttribute('accept-charset', $this->frontendController->metaCharset);

        // Get search term
        $this->getTemplateVariableContainer()->add('q', $this->getQueryString());
        $this->getTemplateVariableContainer()->add('pageUid', $pageUid);
        $this->getTemplateVariableContainer()->add('languageUid', $this->frontendController->sys_language_uid);
        $formContent = $this->renderChildren();
        $this->getTemplateVariableContainer()->remove('q');
        $this->getTemplateVariableContainer()->remove('pageUid');
        $this->getTemplateVariableContainer()->remove('languageUid');

        $this->tag->setContent($formContent);

        return $this->tag->render();
    }

    /**
     * @return \TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface
     */
    protected function getTemplateVariableContainer()
    {
        return $this->templateVariableContainer;
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
     * @param NULL|array $additionalFilters
     * @param int $pageUid
     * @return string
     */
    protected function getSuggestEidUrl($additionalFilters, $pageUid)
    {
        $suggestUrl = $this->frontendController->absRefPrefix;

        $suggestUrl .= '?type=7384&id=' . $pageUid;

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

    /**
     * @param int|null $pageUid
     * @return string
     */
    protected function buildUriFromPageUidAndArguments($pageUid): string
    {
        $uriBuilder = $this->getControllerContext()->getUriBuilder();
        $uri = $uriBuilder->reset()->setTargetPageUid($pageUid)->setTargetPageType($this->arguments['pageType'])->setNoCache($this->arguments['noCache'])->setUseCacheHash(!$this->arguments['noCacheHash'])->setArguments($this->arguments['additionalParams'])->setCreateAbsoluteUri($this->arguments['absolute'])->setAddQueryString($this->arguments['addQueryString'])->setArgumentsToBeExcludedFromQueryString($this->arguments['argumentsToBeExcludedFromQueryString'])->setAddQueryStringMethod($this->arguments['addQueryStringMethod'])->setSection($this->arguments['section'])->build();
        return $uri;
    }
}
