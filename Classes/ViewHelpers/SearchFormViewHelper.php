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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;

/**
 * Class SearchFormViewHelper
 *
 *
 * @property RenderingContext $renderingContext
 */
class SearchFormViewHelper extends AbstractSolrFrontendTagBasedViewHelper
{
    protected $tagName = 'form';

    protected $escapeChildren = true;

    protected $escapeOutput = false;

    /**
     * Constructor
     */
    public function __construct(
        protected readonly UriBuilder $uriBuilder,
    ) {
        parent::__construct();
    }

    /**
     * Initialize arguments.
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerTagAttribute('enctype', 'string', 'MIME type with which the form is submitted');
        $this->registerTagAttribute('method', 'string', 'Transfer type (GET or POST)', false, 'get');
        $this->registerTagAttribute('name', 'string', 'Name of form');
        $this->registerTagAttribute('onreset', 'string', 'JavaScript: On reset of the form');
        $this->registerTagAttribute('onsubmit', 'string', 'JavaScript: On submit of the form');
        $this->registerUniversalTagAttributes();

        $this->registerArgument('pageUid', 'integer', 'When not set current page is used');
        $this->registerArgument('additionalFilters', 'array', 'Additional filters');
        $this->registerArgument('additionalParams', 'array', 'Query parameters to be attached to the resulting URI', false, []);
        $this->registerArgument('pageType', 'integer', 'Type of the target page. See typolink.parameter', false, 0);

        $this->registerArgument('noCache', 'boolean', 'Set this to disable caching for the target page. You should not need this.', false, false);
        $this->registerArgument('section', 'string', 'The anchor to be added to the action URI (only active if $actionUri is not set)', false, '');
        $this->registerArgument('absolute', 'boolean', 'If set, the URI of the rendered link is absolute', false, false);
        $this->registerArgument('addQueryString', 'boolean', 'If set, the current query parameters will be kept in the URI', false, false);
        $this->registerArgument('argumentsToBeExcludedFromQueryString', 'array', 'arguments to be removed from the URI. Only active if $addQueryString = TRUE', false, []);
        $this->registerArgument('addSuggestUrl', 'boolean', 'Indicates if suggestUrl should be rendered or not', false, true);
        $this->registerArgument('suggestHeader', 'string', 'The header for the top results', false, 'Top Results');
        $this->registerArgument('suggestPageType', 'integer', 'The page type that should be used for the suggest', false, 7384);
    }

    /**
     * Renders search form-tag
     *
     * @throws AspectNotFoundException
     */
    public function render(): string
    {
        /** @var RequestInterface $request */
        $request = $this->renderingContext->getAttribute(ServerRequestInterface::class);
        $this->uriBuilder->setRequest($request);
        $pageUid = $this->arguments['pageUid'] ?? null;
        if ($pageUid === null && !empty($this->getTypoScriptConfiguration()->getSearchTargetPage())) {
            $pageUid = $this->getTypoScriptConfiguration()->getSearchTargetPage();
        } elseif ($pageUid === null) {
            $pageUid = $this->renderingContext->getAttribute(ServerRequestInterface::class)->getAttribute('routing')?->getPageId();
        }
        $pageUid = (int)$pageUid;

        $uri = $this->buildUriFromPageUidAndArguments($pageUid);

        $this->tag->addAttribute('action', trim($uri));
        if (($this->arguments['addSuggestUrl'] ?? null)) {
            $this->tag->addAttribute('data-suggest', $this->getSuggestUrl($this->arguments['additionalFilters'], $pageUid));
        }
        $this->tag->addAttribute('data-suggest-header', htmlspecialchars($this->arguments['suggestHeader'] ?? ''));
        $this->tag->addAttribute('accept-charset', 'utf-8');

        // Get search term
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->add('q', $this->getQueryString());
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->add('pageUid', $pageUid);
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->add(
            'languageUid',
            (
                $this->renderingContext
                    ->getAttribute(ServerRequestInterface::class)
                    ->getAttribute('language')
                    ?->getLanguageId() ?? 0
            ),
        );
        // @extensionScannerIgnoreLine
        $this->getTemplateVariableContainer()->add('existingParameters', $this->getExistingSearchParameters());
        // @extensionScannerIgnoreLine
        // Added addPageAndLanguageId for compatibility
        $this->getTemplateVariableContainer()->add('addPageAndLanguageId', false);
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
     */
    protected function getExistingSearchParameters(): array
    {
        $searchParameters = [];
        if ($this->getTypoScriptConfiguration()->getSearchKeepExistingParametersForNewSearches()) {
            $request = $this->renderingContext->getAttribute(ServerRequestInterface::class);
            $pluginNamespace = $this->getTypoScriptConfiguration()->getSearchPluginNamespace();
            $arguments = $request->getQueryParams()[$pluginNamespace] ?? [];
            ArrayUtility::mergeRecursiveWithOverrule($arguments, $request->getParsedBody()[$pluginNamespace] ?? []);

            unset($arguments['q'], $arguments['id'], $arguments['L']);
            $searchParameters = $this->translateSearchParametersToInputTagAttributes($arguments);
        }
        return $searchParameters;
    }

    /**
     * Translate the multidimensional array of existing arguments into a flat array of name-value pairs for the input tags
     */
    protected function translateSearchParametersToInputTagAttributes(
        array $arguments,
        string $nameAttributePrefix = '',
    ): array {
        $attributes = [];
        foreach ($arguments as $key => $value) {
            $name = $nameAttributePrefix . '[' . $key . ']';
            if (is_array($value)) {
                $attributes = array_merge(
                    $attributes,
                    $this->translateSearchParametersToInputTagAttributes($value, $name),
                );
            } else {
                $attributes[$name] = $value;
            }
        }
        return $attributes;
    }

    protected function getTemplateVariableContainer(): ?VariableProviderInterface
    {
        return $this->templateVariableContainer;
    }

    protected function getQueryString(): string
    {
        $resultSet = $this->getSearchResultSet();
        if ($resultSet === null) {
            return '';
        }
        return trim($this->getSearchResultSet()->getUsedSearchRequest()->getRawUserQuery());
    }

    protected function getSuggestUrl(?array $additionalFilters, int $pageUid): string
    {
        $pluginNamespace = $this->getTypoScriptConfiguration()->getSearchPluginNamespace();
        $suggestUrl = $this->uriBuilder
            ->reset()
            ->setTargetPageUid($pageUid)
            ->setTargetPageType((int)$this->arguments['suggestPageType'])
            ->setArguments([$pluginNamespace => ['additionalFilters' => $additionalFilters]])
            ->build();

        /** @var UrlHelper $urlService */
        $urlService = GeneralUtility::makeInstance(UrlHelper::class, $suggestUrl);
        return $urlService->withoutQueryParameter('cHash')->__toString();
    }

    protected function buildUriFromPageUidAndArguments(int $pageUid): string
    {
        return $this->uriBuilder
            ->reset()
            ->setTargetPageUid($pageUid)
            ->setTargetPageType((int)($this->arguments['pageType'] ?? 0))
            ->setNoCache($this->arguments['noCache'] ?? false)
            ->setArguments($this->arguments['additionalParams'] ?? [])
            ->setCreateAbsoluteUri($this->arguments['absolute'] ?? false)
            ->setAddQueryString($this->arguments['addQueryString'] ?? false)
            ->setArgumentsToBeExcludedFromQueryString($this->arguments['argumentsToBeExcludedFromQueryString'] ?? [])
            ->setSection($this->arguments['section'] ?? '')
            ->build();
    }
}
