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

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Index Queue Page Indexer request test.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class PageIndexerRequestTest extends UnitTest
{

    /**
     * @test
     */
    public function authenticatesValidRequest()
    {
        $jsonEncodedParameters = json_encode([
            'item' => 1,
            'page' => 1,
            'hash' => md5('1|1|' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'])
        ]);

        $request = $this->getPageIndexerRequest($jsonEncodedParameters);
        $this->assertTrue($request->isAuthenticated());
    }

    /**
     * @test
     */
    public function doesNotAuthenticateInvalidRequest()
    {
        $jsonEncodedParameters = json_encode([
            'item' => 1,
            'page' => 1,
            'hash' => md5('invalid|invalid|invalid')
        ]);

        $request = $this->getPageIndexerRequest($jsonEncodedParameters);
        $this->assertFalse($request->isAuthenticated());
    }

    /**
     * @test
     */
    public function usesUniqueIdFromHeader()
    {
        $id = uniqid();
        $jsonEncodedParameters = json_encode([
            'requestId' => $id
        ]);

        $request = $this->getPageIndexerRequest($jsonEncodedParameters);
        $this->assertEquals($id, $request->getRequestId());
    }

    /**
     * @test
     */
    public function sendCreatesExpectedResponse()
    {
        $testParameters = json_encode(['requestId' => '581f76be71f60']);

        $fakeResponse = $this->getFixtureContentByName('fakeResponse.json');
        $requestMock = $this->getMockedPageIndexerRequestWithUsedFakeResponse($testParameters, $fakeResponse);

        $queueItemMock = $this->getDumbMock(Item::class);
        $requestMock->setIndexQueueItem($queueItemMock);

        $response = $requestMock->send('http://7.6.local.typo3.org/about/typo3/');
        $indexPageResult = $response->getActionResult('indexPage');

        $this->assertTrue(is_array($indexPageResult));
        $this->assertSame(1, $indexPageResult['pageIndexed']);
        $this->assertSame($response->getRequestId(), '581f76be71f60', 'Response did not contain expected requestId');
    }

    /**
     * @test
     */
    public function sendThrowsExceptionOnIsMissmatch()
    {
        $testParameters = json_encode(['requestId' => 'wrongId']);
        $fakeResponse = $this->getFixtureContentByName('fakeResponse.json');

        $requestMock = $this->getMockedPageIndexerRequestWithUsedFakeResponse($testParameters, $fakeResponse);
        $queueItemMock = $this->getDumbMock(Item::class);

        $requestMock->setIndexQueueItem($queueItemMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Request ID mismatch');
        $requestMock->send('http://7.6.local.typo3.org/about/typo3/');
    }

    /**
     * @test
     */
    public function sendThrowsExceptionWhenInvalidJsonIsReturned()
    {
        $testParameters = json_encode(['requestId' => 'wrongId']);
        $fakeResponse = 'invalidJsonString!!';

        $requestMock = $this->getMockedPageIndexerRequestWithUsedFakeResponse($testParameters, $fakeResponse);
        $queueItemMock = $this->getDumbMock(Item::class);
        $requestMock->setIndexQueueItem($queueItemMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to execute Page Indexer Request');
        $requestMock->send('http://7.6.local.typo3.org/about/typo3/');
    }

    /**
     * @test
     */
    public function canSetTimeOutFromPHPConfiguration()
    {
        $initialTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout',122.5);

        $pageIndexerRequest = $this->getPageIndexerRequest();
        $this->assertSame(122.5, $pageIndexerRequest->getTimeout());
        ini_set('default_socket_timeout', $initialTimeout);
    }

    /**
     * @test
     */
    public function canSendRequestToSslSite()
    {
        $testParameters = json_encode(['requestId' => '581f76be71f60']);
        $fakeResponse = $this->getFixtureContentByName('fakeResponse.json');

        $requestMock = $this->getMockedPageIndexerRequestWithUsedFakeResponse($testParameters, $fakeResponse);
        $queueItemMock = $this->getDumbMock(Item::class);
        $requestMock->setIndexQueueItem($queueItemMock);

        $requestMock->send('https://7.6.local.typo3.org/about/typo3/');
    }

    /**
     * @test
     */
    public function authenticationHeaderIsSetWhenUsernameAndPasswordHaveBeenPassed()
    {
        $queueItemMock = $this->getDumbMock(Item::class);

        $pageIndexerRequest = $this->getPageIndexerRequest();
        $pageIndexerRequest->setIndexQueueItem($queueItemMock);
        $pageIndexerRequest->setAuthorizationCredentials('bob','topsecret');

        $headers = $pageIndexerRequest->getHeaders();

        $expetedHeader = 'Authorization: Basic ' . base64_encode('bob:topsecret');
        $this->assertContains($expetedHeader, $headers, 'Headers did not contain authentication details');
    }

    /**
     * @test
     */
    public function canSetParameter()
    {
        $pageIndexerRequest = $this->getPageIndexerRequest();
        $this->assertNull($pageIndexerRequest->getParameter('foo'), 'Parameter foo should be null when nothing was set');

        $pageIndexerRequest->setParameter('foo', 'bar');
        $this->assertSame('bar', $pageIndexerRequest->getParameter('foo'), 'Could not get parameter foo after setting it');

        $pageIndexerRequest->setParameter('test', 4711);
        $this->assertSame(4711, $pageIndexerRequest->getParameter('test'), 'Could not get parameter foo after setting it');

    }

    /**
     * @param string $jsonEncodedParameter
     * @return PageIndexerRequest
     */
    protected function getPageIndexerRequest($jsonEncodedParameter = null)
    {
        $solrLogManagerMock = $this->getDumbMock(SolrLogManager::class);
        $extensionConfigurationMock = $this->getDumbMock(ExtensionConfiguration::class);
        $request = new PageIndexerRequest($jsonEncodedParameter, $solrLogManagerMock, $extensionConfigurationMock);
        return $request;
    }

    /**
     * @param $testParameters
     * @param $fakeResponse
     * @return PageIndexerRequest
     */
    protected function getMockedPageIndexerRequestWithUsedFakeResponse($testParameters, $fakeResponse)
    {
        $solrLogManagerMock = $this->getDumbMock(SolrLogManager::class);
        $extensionConfigurationMock = $this->getDumbMock(ExtensionConfiguration::class);
        /** @var $requestMock PageIndexerRequest */
        $requestMock = $this->getMockBuilder(PageIndexerRequest::class)->setMethods(['getUrl'])->setConstructorArgs([$testParameters, $solrLogManagerMock, $extensionConfigurationMock])->getMock();

        // we fake the response from a captured response json file
        $requestMock->expects($this->once())->method('getUrl')->will($this->returnValue($fakeResponse));
        return $requestMock;
    }
}
