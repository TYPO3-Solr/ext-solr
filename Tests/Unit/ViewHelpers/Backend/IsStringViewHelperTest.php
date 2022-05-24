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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelpers\Backend;

use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\ViewHelpers\Backend\IsStringViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Testcase for the IsStringViewHelper
 */
class IsStringViewHelperTest extends UnitTest
{

    /**
     * @test
     */
    public function viewHelperRendersThenChildIfStringIsGiven()
    {
        $arguments = [
            'value' => 'givenString',
            '__thenClosure' => function () { return 'thenResult'; },
            '__elseClosures' => [function () { return 'elseResult'; }],
        ];

        $renderingContextMock = $this->getDumbMock(RenderingContextInterface::class);
        $result = IsStringViewHelper::renderStatic($arguments, function () {}, $renderingContextMock);
        self::assertSame('thenResult', $result, 'thenClosure was not rendered');
    }

    /**
     * @test
     */
    public function viewHelperRendersElseChildIfNotStringTypeIsGiven()
    {
        $arguments = [
            'value' => ['givenStringInArray'],
            '__thenClosure' => function () { return 'thenResult'; },
            '__elseClosures' => [function () { return 'elseResult'; }],
        ];

        $renderingContextMock = $this->getDumbMock(RenderingContextInterface::class);
        $result = IsStringViewHelper::renderStatic($arguments, function () {}, $renderingContextMock);
        self::assertSame('elseResult', $result, 'elseResult was not rendered');
    }
}
