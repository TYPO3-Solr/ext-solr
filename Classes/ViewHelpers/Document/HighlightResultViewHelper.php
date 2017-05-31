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

use ApacheSolrForTypo3\Solr\Util;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractViewHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3\CMS\Fluid\Core\ViewHelper\Facets\CompilableInterface;

/**
 * Class HighlightResultViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\ViewHelpers\Document
 */
class HighlightResultViewHelper extends AbstractViewHelper implements CompilableInterface
{
    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * @param SearchResultSet $resultSet
     * @param \Apache_Solr_Document $document
     * @param string $fieldName
     * @return string
     */
    public function render(SearchResultSet $resultSet, \Apache_Solr_Document $document, $fieldName)
    {
        return self::renderStatic(
            [
                'resultSet' => $resultSet,
                'document' => $document,
                'fieldName' => $fieldName
            ],
            $this->buildRenderChildrenClosure(),
            $this->renderingContext
        );
    }

    /**
     * @param array $arguments
     * @param callable $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        /** @var $resultSet SearchResultSet */
        $resultSet = $arguments['resultSet'];
        $fieldName = $arguments['fieldName'];
        $document = $arguments['document'];

        $fragmentSeparator = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchResultsHighlightingFragmentSeparator();
        $content = call_user_func([$document, 'get' . $fieldName]);
        $highlightedContent = $resultSet->getUsedSearch()->getHighlightedContent();
        if (!empty($highlightedContent->{$document->getId()}->{$fieldName}[0])) {
            $content = implode(' ' . $fragmentSeparator . ' ', $highlightedContent->{$document->getId()}->{$fieldName});
        }
        return $content;
    }
}
