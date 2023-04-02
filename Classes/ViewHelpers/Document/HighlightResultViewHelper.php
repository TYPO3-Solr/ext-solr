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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Document;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;
use Closure;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Class HighlightResultViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class HighlightResultViewHelper extends AbstractSolrFrontendViewHelper
{
    use CompileWithRenderStatic;

    protected $escapeOutput = false;

    /**
     * Initializes the arguments
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('resultSet', SearchResultSet::class, 'The context searchResultSet', true);
        $this->registerArgument('document', SearchResult::class, 'The document to highlight', true);
        $this->registerArgument('fieldName', 'string', 'The fieldName', true);
    }

    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ): string {
        /* @var SearchResultSet $resultSet */
        $resultSet = $arguments['resultSet'];
        $fieldName = $arguments['fieldName'];
        $document = $arguments['document'];
        $highlightedContent = self::getHighlightedContent($resultSet, $document, $fieldName);
        if (is_string($highlightedContent)) {
            return self::escapeEverythingExceptAllowedTags($resultSet, $highlightedContent);
        }
        return '';
    }

    protected static function getHighlightedContent(SearchResultSet $resultSet, SearchResult $document, string $fieldName)
    {
        $fragmentSeparator = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchResultsHighlightingFragmentSeparator();

        $content = call_user_func([$document, 'get' . $fieldName]);
        $highlightedContent = $resultSet->getUsedSearch()->getHighlightedContent();
        if (!empty($highlightedContent->{$document->getId()}->{$fieldName}[0])) {
            return implode(' ' . $fragmentSeparator . ' ', $highlightedContent->{$document->getId()}->{$fieldName});
        }
        return $content;
    }

    protected static function escapeEverythingExceptAllowedTags(SearchResultSet $resultSet, string $content): string
    {
        $wrap = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchResultsHighlightingWrap();
        if ($wrap === '') {
            return htmlspecialchars($content);
        }

        $wrapParts = GeneralUtility::trimExplode('|', $wrap);
        if (count($wrapParts) !== 2) {
            return htmlspecialchars($content);
        }

        $substitutedContent = str_replace($wrapParts[0], '___highlight_begin___', $content);
        $substitutedContent = str_replace($wrapParts[1], '___highlight_end___', $substitutedContent);
        $output = htmlspecialchars($substitutedContent);
        $output = str_replace('___highlight_begin___', $wrapParts[0], $output);
        return str_replace('___highlight_end___', $wrapParts[1], $output);
    }
}
