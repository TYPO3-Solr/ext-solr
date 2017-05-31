<?php
namespace ApacheSolrForTypo3\Solr\Test\ViewHelpers\Facet\Uri;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\Uri\SearchUriBuilder;
use ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext;
use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Facet\RemoveAllFacetsViewHelper;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RemoveAllFacetsViewHelperTest extends AbstractFacetItemViewHelperTest
{

    /**
     * @test
     */
    public function setFacetItemWillUseUriBuilderAsExpected()
    {
        $mockedPreviousFakedRequest = $this->getDumbMock(SearchRequest::class);
        $searchResultSetMock = $this->getDumbMock(SearchResultSet::class);
        $searchResultSetMock->expects($this->once())->method('getUsedSearchRequest')->will($this->returnValue($mockedPreviousFakedRequest));

        $controllerContextMock = $this->getDumbMock(SolrControllerContext::class);
        $controllerContextMock->expects($this->once())->method('getSearchResultSet')->will($this->returnValue($searchResultSetMock));
        $renderContextMock = $this->getDumbMock(RenderingContext::class);
        $renderContextMock->expects($this->any())->method('getControllerContext')->will($this->returnValue($controllerContextMock));

        $viewHelper = new RemoveAllFacetsViewHelper();
        $viewHelper->setRenderingContext($renderContextMock);

        $searchUriBuilderMock = $this->getDumbMock(SearchUriBuilder::class);

            // we expected that the getRemoveAllFacetsUri will be called on the searchUriBuilder in the end.
        $searchUriBuilderMock->expects($this->once())->method('getRemoveAllFacetsUri')->with($mockedPreviousFakedRequest);
        $viewHelper->injectSearchUriBuilder($searchUriBuilderMock);

        $viewHelper->render();
    }
}
