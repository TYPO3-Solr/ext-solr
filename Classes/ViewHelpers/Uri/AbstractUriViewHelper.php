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
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\RequestBuilder;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
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

    protected static SearchUriBuilder $searchUriBuilder;

    protected static RequestBuilder $requestBuilder;

    public function injectSearchUriBuilder(SearchUriBuilder $searchUriBuilder): void
    {
        self::$searchUriBuilder = $searchUriBuilder;
    }

    public function injectRequestBuilder(RequestBuilder $requestBuilder): void
    {
        self::$requestBuilder = $requestBuilder;
    }

    protected static function getSearchUriBuilder(RenderingContextInterface $renderingContext = null): SearchUriBuilder
    {
        if (!isset(self::$searchUriBuilder)) {
            self::$searchUriBuilder = GeneralUtility::makeInstance(SearchUriBuilder::class);
        }
        if (!isset(self::$requestBuilder)) {
            self::$requestBuilder = GeneralUtility::makeInstance(RequestBuilder::class);
        }

        if ($renderingContext instanceof RenderingContext) {
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $request = self::$requestBuilder->build($renderingContext->getRequest());
            $uriBuilder->reset()->setRequest($request);
            self::$searchUriBuilder->injectUriBuilder($uriBuilder);
        }

        return self::$searchUriBuilder;
    }

    protected static function getUsedSearchRequestFromRenderingContext(RenderingContextInterface $renderingContext): ?SearchRequest
    {
        $resultSet = static::getUsedSearchResultSetFromRenderingContext($renderingContext);
        if (!$resultSet instanceof SearchResultSet) {
            throw new InvalidArgumentException('The variable resultSet need to be defined in the scope of ' . static::class, 1642765491);
        }

        return $resultSet->getUsedSearchRequest();
    }
}
