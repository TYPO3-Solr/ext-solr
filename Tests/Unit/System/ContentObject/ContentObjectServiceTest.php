<?php
namespace  ApacheSolrForTypo3\Solr\Tests\Unit\System\ContentObject;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\ContentObject\ContentObjectService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Testcase for ContentObjectService
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ContentObjectServiceTest extends UnitTest
{

    /**
     * @var ContentObjectRenderer
     */
    protected $contentObjectRendererMock;

    /**
     * @var ContentObjectService
     */
    protected $contentObjectService;

    public function setUp() {
        $this->contentObjectRendererMock = $this->getDumbMock(ContentObjectRenderer::class);
        $this->contentObjectService = new ContentObjectService($this->contentObjectRendererMock);
    }

    /**
     * @test
     */
    public function canRenderSingleContentObjectByArrayAndKey()
    {
        $fakeStdWrapConfiguration = [
            'field' => 'TEXT',
            'field.' => ['value' => 'test']
        ];

        $this->contentObjectRendererMock->expects($this->once())->method('cObjGetSingle')->with('TEXT',  ['value' => 'test']);
        $this->contentObjectService->renderSingleContentObjectByArrayAndKey($fakeStdWrapConfiguration, 'field');
    }

    /**
     * @test
     */
    public function renderSingleContentObjectByArrayAndKeyWillReturnNameWhenConfigIsNotAnArray()
    {
        $fakeStdWrapConfiguration = [
            'field' => 'fooo'
        ];

        $this->contentObjectRendererMock->expects($this->never())->method('cObjGetSingle');
        $result = $this->contentObjectService->renderSingleContentObjectByArrayAndKey($fakeStdWrapConfiguration, 'field');
        $this->assertSame('fooo', $result);
    }
}
