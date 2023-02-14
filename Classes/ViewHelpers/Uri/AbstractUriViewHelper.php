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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Uri;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\Uri\SearchUriBuilder;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;
use InvalidArgumentException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Exception as ExtbaseObjectException;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Class AbstractUriViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractUriViewHelper extends AbstractSolrFrontendViewHelper
{
    use CompileWithRenderStatic;

    /**
     * @var SearchUriBuilder
     */
    protected static SearchUriBuilder $searchUriBuilder;

    /**
     * @param SearchUriBuilder $searchUriBuilder
     */
    public function injectSearchUriBuilder(SearchUriBuilder $searchUriBuilder)
    {
        self::$searchUriBuilder = $searchUriBuilder;
    }

    /**
     * @param RenderingContextInterface|null $renderingContext
     * @return SearchUriBuilder
     * @throws ExtbaseObjectException
     */
    protected static function getSearchUriBuilder(RenderingContextInterface $renderingContext = null): SearchUriBuilder
    {
        if (!isset(self::$searchUriBuilder)) {
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            self::$searchUriBuilder = $objectManager->get(SearchUriBuilder::class);
        }

        if ($renderingContext && method_exists($renderingContext, 'getControllerContext')) {
            self::$searchUriBuilder->injectUriBuilder($renderingContext->getControllerContext()->getUriBuilder());
        }

        return self::$searchUriBuilder;
    }

    /**
     * @param RenderingContextInterface $renderingContext
     * @return mixed
     */
    protected static function getUsedSearchRequestFromRenderingContext(RenderingContextInterface $renderingContext): ?SearchRequest
    {
        $resultSet = static::getUsedSearchResultSetFromRenderingContext($renderingContext);
        if (!$resultSet instanceof SearchResultSet) {
            throw new InvalidArgumentException('The variable resultSet need to be defined in the scope of ' . static::class, 1642765491);
        }

        return $resultSet->getUsedSearchRequest();
    }
}
