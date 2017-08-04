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

use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\ViewHelpers\Backend\IsStringViewHelper;

/**
 * Testcase for the IsStringViewHelper
 */
class IsStringViewHelperTest extends UnitTest
{
    /**
     * @var IsStringViewHelper
     */
    protected $viewHelper;

    protected function setUp()
    {
        parent::setUp();
        $this->viewHelper = $this->getAccessibleMock(IsStringViewHelper::class, ['renderThenChild', 'renderElseChild']);
    }

    /**
     * @test
     */
    public function viewHelperRendersThenChildIfStringIsGiven()
    {
        $this->viewHelper->setArguments([
            'value' => 'givenString'
        ]);
        $this->viewHelper->initializeArguments();

        $this->viewHelper->expects($this->at(0))->method('renderThenChild')->will($this->returnValue('then'));
        $actualResult = $this->viewHelper->render();
        $this->assertEquals('then', $actualResult);
    }

    /**
     * @test
     */
    public function viewHelperRendersElseChildIfNotStringTypeIsGiven()
    {
        $this->viewHelper->setArguments([
            'value' => ['givenStringInArray']
        ]);
        $this->viewHelper->initializeArguments();

        $this->viewHelper->expects($this->at(0))->method('renderElseChild')->will($this->returnValue('else'));
        $actualResult = $this->viewHelper->render();
        $this->assertEquals('else', $actualResult);
    }
}
