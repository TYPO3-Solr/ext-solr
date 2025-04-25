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
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Testcase for the IsStringViewHelper
 */
class IsStringViewHelperTest extends SetUpUnitTestCase
{
    protected IsStringViewHelper $isStringViewHelperTestable;

    protected MockObject|ViewHelperNode $viewHelperNodeMock;

    /**
     * @throws MockObjectException
     */
    protected function setUp(): void
    {
        $renderingContextMock = $this->createMock(RenderingContextInterface::class);
        $this->isStringViewHelperTestable = new IsStringViewHelper();
        $this->isStringViewHelperTestable->setRenderingContext($renderingContextMock);
        $this->viewHelperNodeMock = $this->createMock(ViewHelperNode::class);
        $this->isStringViewHelperTestable->setViewHelperNode($this->viewHelperNodeMock);
        parent::setUp();
    }

    #[Test]
    public function viewHelperRendersThenChildIfStringIsGiven(): void
    {
        $arguments = [
            'value' => 'givenString',
            'then' => 'thenResult',
            'else' => 'elseResult',
        ];

        $this->isStringViewHelperTestable->setArguments($arguments);
        $this->viewHelperNodeMock->expects(self::any())->method(
            'getArguments'
        )->willReturn($arguments);
        $result = $this->isStringViewHelperTestable->render();
        self::assertSame('thenResult', $result, 'thenClosure was not rendered');
    }

    #[Test]
    public function viewHelperRendersElseChildIfNotStringTypeIsGiven(): void
    {
        $arguments = [
            'value' => ['givenStringInArray'],
            'then' => 'thenResult',
            'else' => 'elseResult',
        ];

        $this->isStringViewHelperTestable->setArguments($arguments);
        $this->viewHelperNodeMock->expects(self::any())->method(
            'getArguments'
        )->willReturn($arguments);
        $result = $this->isStringViewHelperTestable->render();
        self::assertSame('elseResult', $result, 'elseResult was not rendered');
    }
}
