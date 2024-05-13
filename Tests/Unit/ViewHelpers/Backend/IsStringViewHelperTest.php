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

use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use ApacheSolrForTypo3\Solr\ViewHelpers\Backend\IsStringViewHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Testcase for the IsStringViewHelper
 */
class IsStringViewHelperTest extends SetUpUnitTestCase
{
    #[Test]
    public function viewHelperRendersThenChildIfStringIsGiven(): void
    {
        $arguments = [
            'value' => 'givenString',
            '__then' => function() { return 'thenResult'; },
            '__else' => function() { return 'elseResult'; },
        ];

        $renderingContextMock = $this->createMock(RenderingContextInterface::class);
        $result = IsStringViewHelper::renderStatic($arguments, function() {}, $renderingContextMock);
        self::assertSame('thenResult', $result, 'thenClosure was not rendered');
    }

    #[Test]
    public function viewHelperRendersElseChildIfNotStringTypeIsGiven(): void
    {
        $arguments = [
            'value' => ['givenStringInArray'],
            '__then' => function() { return 'thenResult'; },
            '__else' => function() { return 'elseResult'; },
        ];

        $renderingContextMock = $this->createMock(RenderingContextInterface::class);
        $result = IsStringViewHelper::renderStatic($arguments, function() {}, $renderingContextMock);
        self::assertSame('elseResult', $result, 'elseResult was not rendered');
    }
}
