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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidArgumentNameException;
use TYPO3\CMS\Extbase\Mvc\Web\RequestBuilder;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Class AbstractUriViewHelper
 */
abstract class AbstractUriViewHelper extends AbstractSolrFrontendViewHelper
{
    protected SearchUriBuilder $searchUriBuilder;

    protected RequestBuilder $requestBuilder;

    protected UriBuilder $uriBuilder;

    public function injectSearchUriBuilder(SearchUriBuilder $searchUriBuilder): void
    {
        $this->searchUriBuilder = $searchUriBuilder;
    }

    public function injectRequestBuilder(RequestBuilder $requestBuilder): void
    {
        $this->requestBuilder = $requestBuilder;
    }

    public function injectUriBuilder(UriBuilder $uriBuilder): void
    {
        $this->uriBuilder = $uriBuilder;
    }

    /**
     * @throws InvalidArgumentNameException
     */
    protected function getSearchUriBuilder(?RenderingContextInterface $renderingContext = null): SearchUriBuilder
    {
        if ($renderingContext !== null && isset($this->uriBuilder)) {
            $serverRequest = $renderingContext->getAttribute(ServerRequestInterface::class);
            if ($serverRequest instanceof ServerRequestInterface) {
                $request = $this->requestBuilder->build($serverRequest);
                $this->uriBuilder->reset()->setRequest($request);
                $this->searchUriBuilder->injectUriBuilder($this->uriBuilder);
            }
        }

        return $this->searchUriBuilder;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function getUsedSearchRequestFromRenderingContext(
        RenderingContextInterface $renderingContext,
    ): ?SearchRequest {
        $resultSet = $this->getUsedSearchResultSetFromRenderingContext($renderingContext);
        if (!$resultSet instanceof SearchResultSet) {
            throw new InvalidArgumentException('The variable resultSet need to be defined in the scope of ' . static::class, 1642765491);
        }

        return $resultSet->getUsedSearchRequest();
    }
}
