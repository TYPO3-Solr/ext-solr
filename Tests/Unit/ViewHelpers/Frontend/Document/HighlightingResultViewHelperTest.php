<?php
namespace ApacheSolrForTypo3\Solr\Test\ViewHelpers\Frontend\Document;

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
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\ViewHelpers\Frontend\Document\HighlightResultViewHelper;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class HighlightingResultViewHelperTest extends UnitTest
{

    /**
     * @test
     */
    public function canRenderCreateHighlightSnipped()
    {
        $renderingContextMock = $this->getDumbMock(RenderingContextInterface::class);

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getSearchResultsHighlightingFragmentSeparator')->will(
            $this->returnValue('###')
        );

        $searchRequestMock = $this->getDumbMock(SearchRequest::class);
        $searchRequestMock->expects($this->once())->method('getContextTypoScriptConfiguration')->will(
            $this->returnValue($configurationMock)
        );


        $fakeHighlightedContent = new \stdClass();
        $fakeHighlightedContent->foo = new \stdClass();
        $fakeHighlightedContent->foo->content = ['hello <em>world</em>','hi <em>world</em>'];

        $searchMock = $this->getDumbMock(Search::class);
        $searchMock->expects($this->once())->method('getHighlightedContent')->will(
            $this->returnValue($fakeHighlightedContent)
        );


        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $resultSetMock->expects($this->once())->method('getUsedSearchRequest')->will($this->returnValue(
           $searchRequestMock
        ));

        $resultSetMock->expects($this->once())->method('getUsedSearch')->will($this->returnValue(
           $searchMock
        ));

        $documentMock = $this->getDumbMock(SearchResult::class);
        $documentMock->expects($this->any())->method('getId')->will($this->returnValue("foo"));

        $viewHelper = new HighlightResultViewHelper();
        $viewHelper->setRenderingContext($renderingContextMock);
        $viewHelper->setArguments([
            'resultSet' => $resultSetMock,
            'document' => $documentMock,
            'fieldName' => 'content']
        );

        $output = $viewHelper->render();
        $this->assertSame('hello <em>world</em> ### hi <em>world</em>', $output);
    }
}
