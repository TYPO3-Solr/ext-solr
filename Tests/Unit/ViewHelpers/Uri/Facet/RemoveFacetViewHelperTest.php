<?php
namespace ApacheSolrForTypo3\Solr\Test\ViewHelpers\Uri\Facet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Search\Uri\SearchUriBuilder;
use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Facet\RemoveFacetViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Frans Saris <frans@beech.it>
 */
class RemoveFacetViewHelperTest extends AbstractFacetItemViewHelperTest
{

    /**
     * @test
     */
    public function removeFacetWillUseUriBuilderAsExpected()
    {
        $facet = $this->getTestColorFacet();

        $renderContextMock = $this->getDumbMock(RenderingContextInterface::class);
        $viewHelper = new RemoveFacetViewHelper();
        $viewHelper->setRenderingContext($renderContextMock);

        $searchUriBuilderMock = $this->getDumbMock(SearchUriBuilder::class);

            // we expected that the getRemoveFacetValueUri will be called on the searchUriBuilder in the end.
        $searchUriBuilderMock->expects($this->once())->method('getRemoveFacetUri')->with($facet->getResultSet()->getUsedSearchRequest(), 'Color');
        $viewHelper->injectSearchUriBuilder($searchUriBuilderMock);
        // @extensionScannerIgnoreLine
        $viewHelper->setArguments(['facet' => $facet, 'facetItem' => $facet->getOptions()->getByPosition(0)]);
        $viewHelper->render();
    }
}
