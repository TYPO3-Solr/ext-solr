<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Document;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;
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

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Initializes the arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('resultSet', SearchResultSet::class, 'The context searchResultSet', true);
        $this->registerArgument('document', SearchResult::class, 'The document to highlight', true);
        $this->registerArgument('fieldName', 'string', 'The fieldName', true);
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        /** @var $resultSet SearchResultSet */
        $resultSet = $arguments['resultSet'];
        $fieldName = $arguments['fieldName'];
        $document = $arguments['document'];
        $content = self::getHighlightedContent($resultSet, $document, $fieldName);
        return self::escapeEverythingExceptAllowedTags($resultSet, $content);
    }

    /**
     * @param SearchResultSet $resultSet
     * @param $document
     * @param $fieldName
     * @return mixed|string
     */
    protected static function getHighlightedContent(SearchResultSet $resultSet, $document, $fieldName)
    {
        $fragmentSeparator = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchResultsHighlightingFragmentSeparator();

        $content = call_user_func([$document, 'get' . $fieldName]);
        $highlightedContent = $resultSet->getUsedSearch()->getHighlightedContent();
        if (!empty($highlightedContent->{$document->getId()}->{$fieldName}[0])) {
            $content = implode(' ' . $fragmentSeparator . ' ', $highlightedContent->{$document->getId()}->{$fieldName});
            return $content;
        }
        return $content;
    }

    /**
     * @param SearchResultSet $resultSet
     * @param $content
     * @return string
     */
    protected static function escapeEverythingExceptAllowedTags(SearchResultSet $resultSet, $content)
    {
        $wrap = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchResultsHighlightingWrap();
        if ($wrap === '') {
            return htmlspecialchars($content);
        }

        $wrapParts = GeneralUtility::trimExplode("|", $wrap);
        if (count($wrapParts) !== 2) {
            return htmlspecialchars($content);
        }

        $substitutedContent = str_replace($wrapParts[0], '___highlight_begin___', $content);
        $substitutedContent = str_replace($wrapParts[1], '___highlight_end___', $substitutedContent);
        $output = htmlspecialchars($substitutedContent);
        $output = str_replace('___highlight_begin___', $wrapParts[0], $output);
        $output = str_replace('___highlight_end___', $wrapParts[1], $output);

        return $output;
    }
}
