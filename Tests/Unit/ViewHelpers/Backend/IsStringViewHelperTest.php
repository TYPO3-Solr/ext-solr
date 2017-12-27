<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelpers\Backend;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Rafael Kähm <rafael.kaehm@dkd.de>
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
            '__thenClosure' => function() { return 'thenResult'; },
            '__elseClosures' => [function() { return 'elseResult'; }]
        ];

        $renderingContextMock = $this->getDumbMock(RenderingContextInterface::class);
        $result = IsStringViewHelper::renderStatic($arguments, function(){}, $renderingContextMock);
        $this->assertSame('thenResult', $result, 'thenClosure was not rendered');
    }

    /**
     * @test
     */
    public function viewHelperRendersElseChildIfNotStringTypeIsGiven()
    {
        $arguments = [
            'value' => ['givenStringInArray'],
            '__thenClosure' => function() { return 'thenResult'; },
            '__elseClosures' => [function() { return 'elseResult'; }]
        ];

        $renderingContextMock = $this->getDumbMock(RenderingContextInterface::class);
        $result = IsStringViewHelper::renderStatic($arguments, function(){}, $renderingContextMock);
        $this->assertSame('elseResult', $result, 'elseResult was not rendered');
    }
}
