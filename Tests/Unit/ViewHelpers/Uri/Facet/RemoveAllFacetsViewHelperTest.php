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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelpers\Uri\Facet;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\Uri\SearchUriBuilder;
use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Facet\RemoveAllFacetsViewHelper;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RemoveAllFacetsViewHelperTest extends SetUpFacetItemViewHelper
{
    /**
     * @test
     */
    public function setFacetItemWillUseUriBuilderAsExpected(): void
    {
        $mockedPreviousFakedRequest = $this->getDumbMock(SearchRequest::class);
        $mockedControllerRequest = $this->getDumbMock(Request::class);

        $searchResultSetMock = $this->getDumbMock(SearchResultSet::class);
        $searchResultSetMock->expects(self::once())->method('getUsedSearchRequest')->willReturn($mockedPreviousFakedRequest);

        $variableProvideMock = $this->getDumbMock(StandardVariableProvider::class);
        $variableProvideMock->expects(self::once())->method('get')->with('resultSet')->willReturn($searchResultSetMock);

        /* @var MockObject|RenderingContext $renderContextMock */
        $renderContextMock = $this->getDumbMock(RenderingContext::class);
        $renderContextMock->expects(self::any())->method('getVariableProvider')->willReturn($variableProvideMock);
        $renderContextMock->expects(self::any())->method('getRequest')->willReturn($mockedControllerRequest);

        $viewHelper = new RemoveAllFacetsViewHelper();
        $viewHelper->setRenderingContext($renderContextMock);

        /* @var MockObject|SearchUriBuilder $searchUriBuilderMock */
        $searchUriBuilderMock = $this->getDumbMock(SearchUriBuilder::class);

        // we expected that the getRemoveAllFacetsUri will be called on the searchUriBuilder in the end.
        $searchUriBuilderMock->expects(self::once())->method('getRemoveAllFacetsUri')->with($mockedPreviousFakedRequest);
        $viewHelper->injectSearchUriBuilder($searchUriBuilderMock);

        $viewHelper->render();
    }
}
