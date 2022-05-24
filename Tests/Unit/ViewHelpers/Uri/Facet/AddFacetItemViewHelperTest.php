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

use ApacheSolrForTypo3\Solr\Domain\Search\Uri\SearchUriBuilder;
use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Facet\AddFacetItemViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class AddFacetItemViewHelperTest extends AbstractFacetItemViewHelperTest
{

    /**
     * @test
     */
    public function addFacetItemWillUseUriBuilderAsExpected()
    {
        $facet = $this->getTestColorFacet();

        $renderContextMock = $this->getDumbMock(RenderingContextInterface::class);
        $viewHelper = new AddFacetItemViewHelper();
        $viewHelper->setRenderingContext($renderContextMock);

        $searchUriBuilderMock = $this->getDumbMock(SearchUriBuilder::class);

        // we expected that the getAddFacetOptionUri will be called on the searchUriBuilder in the end.
        $searchUriBuilderMock->expects(self::once())->method('getAddFacetValueUri')->with($facet->getResultSet()->getUsedSearchRequest(), 'Color', 'red');
        $viewHelper->injectSearchUriBuilder($searchUriBuilderMock);
        // @extensionScannerIgnoreLine
        $viewHelper->setArguments(['facet' => $facet, 'facetItem' => $facet->getOptions()->getByPosition(0)]);
        $viewHelper->render();
    }
}
