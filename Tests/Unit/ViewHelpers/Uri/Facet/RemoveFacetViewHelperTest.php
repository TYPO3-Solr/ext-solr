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
use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Facet\RemoveFacetViewHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

class RemoveFacetViewHelperTest extends SetUpFacetItemViewHelper
{
    #[Test]
    public function removeFacetWillUseUriBuilderAsExpected(): void
    {
        $facet = $this->getTestColorFacet();

        $renderContextMock = $this->createMock(RenderingContextInterface::class);
        $viewHelper = new RemoveFacetViewHelper();
        $viewHelper->setRenderingContext($renderContextMock);

        $searchUriBuilderMock = $this->createMock(SearchUriBuilder::class);

        // we expected that the getRemoveFacetValueUri will be called on the searchUriBuilder in the end.
        $searchUriBuilderMock->expects(self::once())->method('getRemoveFacetUri')->with($facet->getResultSet()->getUsedSearchRequest(), 'Color');
        $viewHelper->injectSearchUriBuilder($searchUriBuilderMock);
        // @extensionScannerIgnoreLine
        $viewHelper->setArguments(['facet' => $facet, 'facetItem' => $facet->getOptions()->getByPosition(0)]);
        $viewHelper->render();
    }
}
