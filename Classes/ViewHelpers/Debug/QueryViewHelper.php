<?php

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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Debug;

use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;
use Closure;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Class QueryViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 *
 * @noinspection PhpUnused used in Fluid templates <s:debug.query />
 */
class QueryViewHelper extends AbstractSolrFrontendViewHelper
{
    use CompileWithRenderStatic;

    /**
     * @inheritdoc
     */
    protected $escapeOutput = false;

    /**
     * Renders the query.
     *
     * @throws AspectNotFoundException
     * @noinspection PhpUnused
     */
    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext,
    ) {
        $content = '';
        $resultSet = self::getUsedSearchResultSetFromRenderingContext($renderingContext);
        $backendUserIsLoggedIn = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('backend.user', 'isLoggedIn');
        if (
            $backendUserIsLoggedIn === true
            && $resultSet
            && $resultSet->getUsedSearch() !== null
            && $resultSet->getHasSearched() === true
            && $resultSet->getUsedSearch()->getDebugResponse() !== null
            && !empty($resultSet->getUsedSearch()->getDebugResponse()->parsedquery)
        ) {
            $renderingContext->getVariableProvider()->add('parsedQuery', $resultSet->getUsedSearch()->getDebugResponse()->parsedquery);
            $content = $renderChildrenClosure();
            $renderingContext->getVariableProvider()->remove('parsedQuery');

            if ($content === null) {
                $content = '<br><strong>Parsed Query:</strong><br>' . htmlspecialchars($resultSet->getUsedSearch()->getDebugResponse()->parsedquery);
            }
        }

        return $content;
    }
}
