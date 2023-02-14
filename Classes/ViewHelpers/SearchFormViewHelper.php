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

namespace ApacheSolrForTypo3\Solr\ViewHelpers;

use ApacheSolrForTypo3\Solr\System\Url\UrlHelper;
use ApacheSolrForTypo3\Solr\System\Util\SiteUtility;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;

/**
 * Class SearchFormViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
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
        $this->frontendController = $GLOBALS['TSFE'] ?? null;
    }

    /**
     * Initialize arguments.
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
        $this->registerArgument('section', 'string', 'The anchor to be added to the action URI (only active if $actionUri is not set)', false, '');
        $this->registerArgument('absolute', 'boolean', 'If set, the URI of the rendered link is absolute', false, false);
        $this->registerArgument('addQueryString', 'boolean', 'If set, the current query parameters will be kept in the URI', false, false);
        $this->registerArgument('argumentsToBeExcludedFromQueryString', 'array', 'arguments to be removed from the URI. Only active if $addQueryString = TRUE', false, []);
        $this->registerArgument('addQueryStringMethod', 'string', 'Set which parameters will be kept. Only active if $addQueryString = TRUE', false);
        $this->registerArgument('addSuggestUrl', 'boolean', 'Indicates if suggestUrl should be rendered or not', false, true);
        $this->registerArgument('suggestHeader', 'string', 'The header for the top results', false, 'Top Results');
        $this->registerArgument('suggestPageType', 'integer', 'The page type that should be used for the suggest', false, 7384);
    }

    /**
     * Render search form tag
     *
     * @return string
     * @throws AspectNotFoundException
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function render()
    {
        $pageUid = $this->arguments['pageUid'] ?? null;
        if ($pageUid === null && !empty($this->getTypoScriptConfiguration()->getSearchTargetPage())) {
            $pageUid = $this->getTypoScriptConfiguration()->getSearchTargetPage();
        }
        $pageUid = (int)$pageUid;

        $uri = $this->buildUriFromPageUidAndArguments($pageUid);

        $this->tag->addAttribute('action', trim($uri));
        if (($this->arguments['addSuggestUrl'] ?? null)) {
            $this->tag->addAttribute('data-suggest', $this->getSuggestUrl($this->arguments['additionalFilters'], $pageUid));
        }
        $this->tag->addAttribute('data-suggest-header', htmlspecialchars($this->arguments['suggestHeader'] ?? ''));
        $this->tag->addAttribute('accept-charset', $this->frontendController->metaCharset ?? null);

        // Get search term
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->add('q', $this->getQueryString());
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->add('pageUid', $pageUid);
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->add('languageUid', Util::getLanguageUid());
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->add('existingParameters', $this->getExistingSearchParameters());
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->add('addPageAndLanguageId', !$this->getIsSiteManagedSite($pageUid));
        $formContent = $this->renderChildren();
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->remove('addPageAndLanguageId');
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->remove('q');
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->remove('pageUid');
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->remove('languageUid');
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->remove('existingParameters');

        $this->tag->setContent($formContent);

        return $this->tag->render();
    }

    /**
     * Get the existing search parameters in an array
     * Returns an empty array if search.keepExistingParametersForNewSearches is not set
     *
     * @return array
     */
    protected function getExistingSearchParameters(): array
    {
        $searchParameters = [];
        if ($this->getTypoScriptConfiguration()->getSearchKeepExistingParametersForNewSearches()) {
            $arguments = GeneralUtility::_GPmerged($this->getTypoScriptConfiguration()->getSearchPluginNamespace());
            unset($arguments['q'], $arguments['id'], $arguments['L']);
            $searchParameters = $this->translateSearchParametersToInputTagAttributes($arguments);
        }
        return $searchParameters;
    }

    /**
     * Translate the multidimensional array of existing arguments into a flat array of name-value pairs for the input tags
     *
     * @param array $arguments
     * @param string $nameAttributePrefix
     * @return array
     */
    protected function translateSearchParametersToInputTagAttributes(
        array $arguments,
        string $nameAttributePrefix = ''
    ): array {
        $attributes = [];
        foreach ($arguments as $key => $value) {
            $name = $nameAttributePrefix . '[' . $key . ']';
            if (is_array($value)) {
                $attributes = array_merge(
                    $attributes,
                    $this->translateSearchParametersToInputTagAttributes($value, $name)
                );
            } else {
                $attributes[$name] = $value;
            }
        }
        return $attributes;
    }

    /**
     * When a site is managed with site management the language and the id are encoded in the path segment of the url.
     * When no speaking urls are active (e.g. with TYPO3 8 and no realurl) this information is passed as query parameter
     * and would get lost when it is only part of the query arguments in the action parameter of the form.
     *
     * @param int $pageId
     * @return bool
     */
    protected function getIsSiteManagedSite(int $pageId): bool
    {
        return SiteUtility::getIsSiteManagedSite($pageId);
    }

    /**
     * @return VariableProviderInterface|null
     */
    protected function getTemplateVariableContainer(): ?VariableProviderInterface
    {
        return $this->templateVariableContainer;
    }

    /**
     * @return string
     */
    protected function getQueryString(): string
    {
        $resultSet = $this->getSearchResultSet();
        if ($resultSet === null) {
            return '';
        }
        return trim($this->getSearchResultSet()->getUsedSearchRequest()->getRawUserQuery() ?? '');
    }

    /**
     * @param array|null $additionalFilters
     * @param int $pageUid
     * @return string
     */
    protected function getSuggestUrl(?array $additionalFilters, int $pageUid): string
    {
        $uriBuilder = $this->getControllerContext()->getUriBuilder();
        $pluginNamespace = $this->getTypoScriptConfiguration()->getSearchPluginNamespace();
        $suggestUrl = $uriBuilder
            ->reset()
            ->setTargetPageUid($pageUid)
            ->setTargetPageType($this->arguments['suggestPageType'])
            ->setArguments([$pluginNamespace => ['additionalFilters' => $additionalFilters]])
            ->build();

        /* @var UrlHelper $urlService */
        $urlService = GeneralUtility::makeInstance(UrlHelper::class, $suggestUrl);
        return $urlService->removeQueryParameter('cHash')->__toString();
    }

    /**
     * @param int|null $pageUid
     * @return string
     */
    protected function buildUriFromPageUidAndArguments($pageUid): string
    {
        $uriBuilder = $this->getControllerContext()->getUriBuilder();
        return $uriBuilder
            ->reset()
            ->setTargetPageUid((int)$pageUid)
            ->setTargetPageType($this->arguments['pageType'] ?? 0)
            ->setNoCache($this->arguments['noCache'] ?? false)
            ->setArguments($this->arguments['additionalParams'] ?? [])
            ->setCreateAbsoluteUri($this->arguments['absolute'] ?? false)
            ->setAddQueryString($this->arguments['addQueryString'] ?? false)
            ->setArgumentsToBeExcludedFromQueryString($this->arguments['argumentsToBeExcludedFromQueryString'] ?? [])
            ->setAddQueryStringMethod($this->arguments['addQueryStringMethod'] ?? '')
            ->setSection($this->arguments['section'] ?? '')
            ->build();
    }
}
