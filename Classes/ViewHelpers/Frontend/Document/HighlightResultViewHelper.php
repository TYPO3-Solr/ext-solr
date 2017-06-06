<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Frontend\Document;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\ViewHelpers\Frontend\AbstractSolrFrontendViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Class HighlightResultViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\ViewHelpers\Document
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
        $this->registerArgument('document', \Apache_Solr_Document::class, 'The document to highlight', true);
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

        $fragmentSeparator = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchResultsHighlightingFragmentSeparator();
        $content = call_user_func([$document, 'get' . $fieldName]);
        $highlightedContent = $resultSet->getUsedSearch()->getHighlightedContent();
        if (!empty($highlightedContent->{$document->getId()}->{$fieldName}[0])) {
            $content = implode(' ' . $fragmentSeparator . ' ', $highlightedContent->{$document->getId()}->{$fieldName});
        }
        return $content;
    }
}
