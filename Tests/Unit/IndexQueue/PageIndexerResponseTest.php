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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

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

        self::assertEquals($result, $request->getActionResult($action));
    }

    /**
     * @test
     */
    public function getResultReturnsAllResults()
    {
        $request = GeneralUtility::makeInstance(PageIndexerResponse::class);
        $request->addActionResult('action1', 'result1');
        $request->addActionResult('action2', 'result2');

        self::assertEquals([
            'action1' => 'result1',
            'action2' => 'result2',
        ], $request->getActionResult());
    }
}
