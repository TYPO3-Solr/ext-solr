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

namespace  ApacheSolrForTypo3\Solr\Tests\Unit\System\ContentObject;

use ApacheSolrForTypo3\Solr\System\ContentObject\ContentObjectService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Testcase for ContentObjectService
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ContentObjectServiceTest extends UnitTest
{

    /**
     * @var ContentObjectRenderer|MockObject
     */
    protected $contentObjectRendererMock;

    /**
     * @var ContentObjectService
     */
    protected ContentObjectService $contentObjectService;

    protected function setUp(): void
    {
        $this->contentObjectRendererMock = $this->getMockBuilder(ContentObjectRenderer::class)
            ->onlyMethods(['cObjGetSingle'])
            ->getMock();
        $this->contentObjectService = new ContentObjectService($this->contentObjectRendererMock);
        parent::setUp();
    }

    /**
     * @test
     */
    public function canRenderSingleContentObjectByArrayAndKey()
    {
        $fakeStdWrapConfiguration = [
            'field' => 'TEXT',
            'field.' => ['value' => 'test'],
        ];

        $this->contentObjectRendererMock
            ->expects(self::once())
            ->method('cObjGetSingle')
            ->with('TEXT', ['value' => 'test'])
            ->willReturn('test');
        $this->contentObjectService->renderSingleContentObjectByArrayAndKey($fakeStdWrapConfiguration, 'field');
    }

    /**
     * @test
     */
    public function renderSingleContentObjectByArrayAndKeyWillReturnNameWhenConfigIsNotAnArray()
    {
        $fakeStdWrapConfiguration = [
            'field' => 'fooo',
        ];

        $this->contentObjectRendererMock->expects(self::never())->method('cObjGetSingle');
        $result = $this->contentObjectService->renderSingleContentObjectByArrayAndKey($fakeStdWrapConfiguration, 'field');
        self::assertSame('fooo', $result);
    }
}
