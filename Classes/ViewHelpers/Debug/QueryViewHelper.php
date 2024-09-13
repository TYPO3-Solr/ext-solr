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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class QueryViewHelper
 *
 *
 * @noinspection PhpUnused used in Fluid templates <s:debug.query />
 */
class QueryViewHelper extends AbstractSolrFrontendViewHelper
{
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
    public function render()
    {
        $content = '';
        $resultSet = self::getUsedSearchResultSetFromRenderingContext($this->renderingContext);
        $backendUserIsLoggedIn = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('backend.user', 'isLoggedIn');
        if (
            $backendUserIsLoggedIn === true
            && $resultSet
            && $resultSet->getUsedSearch() !== null
            && $resultSet->getHasSearched() === true
            && $resultSet->getUsedSearch()->getDebugResponse() !== null
            && !empty($resultSet->getUsedSearch()->getDebugResponse()->parsedquery)
        ) {
            $this->renderingContext->getVariableProvider()->add('parsedQuery', $resultSet->getUsedSearch()->getDebugResponse()->parsedquery);
            $content = $this->renderChildren();
            $this->renderingContext->getVariableProvider()->remove('parsedQuery');

            if ($content === null) {
                $content = '<br><strong>Parsed Query:</strong><br>' . htmlspecialchars($resultSet->getUsedSearch()->getDebugResponse()->parsedquery);
            }
        }

        return $content;
    }
}
