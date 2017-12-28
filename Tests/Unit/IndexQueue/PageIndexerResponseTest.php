<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Index Queue Page Indexer response test.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class PageIndexerResponseTest extends UnitTest
{

    /**
     * @test
     */
    public function getResultReturnsSingleResult()
    {
        $action = 'testAction';
        $result = 'testResult';

        $request = GeneralUtility::makeInstance(PageIndexerResponse::class);
        $request->addActionResult($action, $result);

        $this->assertEquals($result, $request->getActionResult($action));
    }

    /**
     * @test
     */
    public function getResultReturnsAllResults()
    {
        $request = GeneralUtility::makeInstance(PageIndexerResponse::class);
        $request->addActionResult('action1', 'result1');
        $request->addActionResult('action2', 'result2');

        $this->assertEquals([
            'action1' => 'result1',
            'action2' => 'result2'
        ], $request->getActionResult());
    }
}
