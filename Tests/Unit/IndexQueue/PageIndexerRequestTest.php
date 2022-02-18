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

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

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
            'hash' => md5('1|1|' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']),
        ]);

        $request = $this->getPageIndexerRequest($jsonEncodedParameters);
        self::assertTrue($request->isAuthenticated());
    }

    /**
     * @test
     */
    public function doesNotAuthenticateInvalidRequest()
    {
        $jsonEncodedParameters = json_encode([
            'item' => 1,
            'page' => 1,
            'hash' => md5('invalid|invalid|invalid'),
        ]);

        $request = $this->getPageIndexerRequest($jsonEncodedParameters);
        self::assertFalse($request->isAuthenticated());
    }

    /**
     * @test
     */
    public function usesUniqueIdFromHeader()
    {
        $id = uniqid();
        $jsonEncodedParameters = json_encode([
            'requestId' => $id,
        ]);

        $request = $this->getPageIndexerRequest($jsonEncodedParameters);
        self::assertEquals($id, $request->getRequestId());
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

        self::assertTrue(is_array($indexPageResult));
        self::assertSame(1, $indexPageResult['pageIndexed']);
        self::assertSame($response->getRequestId(), '581f76be71f60', 'Response did not contain expected requestId');
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
        ini_set('default_socket_timeout', 122.5);

        $pageIndexerRequest = $this->getPageIndexerRequest();
        self::assertSame(122.5, $pageIndexerRequest->getTimeout());
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
        $requestFactoryMock = $this->getDumbMock(RequestFactory::class);
        $requestFactoryMock->expects(self::once())->method('request')->willReturnCallback(function ($url, $method, $options) {
            $this->assertSame(['bob', 'topsecret'], $options['auth'], 'Authentication options have not been set');
            $this->assertSame('GET', $method, 'Unexpected http method');

            return $this->getFakedGuzzleResponse($this->getFixtureContentByName('fakeResponse.json'));
        });

        $solrLogManagerMock = $this->getDumbMock(SolrLogManager::class);
        $extensionConfigurationMock = $this->getDumbMock(ExtensionConfiguration::class);

        $testParameters = json_encode(['requestId' => '581f76be71f60']);
        $pageIndexerRequest = new PageIndexerRequest($testParameters, $solrLogManagerMock, $extensionConfigurationMock, $requestFactoryMock);

        $queueItemMock = $this->getDumbMock(Item::class);
        $pageIndexerRequest->setIndexQueueItem($queueItemMock);
        $pageIndexerRequest->setAuthorizationCredentials('bob', 'topsecret');

        $pageIndexerRequest->send('https://7.6.local.typo3.org/about/typo3/');
    }

    /**
     * @test
     */
    public function canSetParameter()
    {
        $pageIndexerRequest = $this->getPageIndexerRequest();
        self::assertNull($pageIndexerRequest->getParameter('foo'), 'Parameter foo should be null when nothing was set');

        $pageIndexerRequest->setParameter('foo', 'bar');
        self::assertSame('bar', $pageIndexerRequest->getParameter('foo'), 'Could not get parameter foo after setting it');

        $pageIndexerRequest->setParameter('test', 4711);
        self::assertSame(4711, $pageIndexerRequest->getParameter('test'), 'Could not get parameter foo after setting it');
    }

    /**
     * @test
     */
    public function canSetUserAgent()
    {
        $pageIndexerRequest = $this->getPageIndexerRequest();

        $itemMock = $this->getDumbMock(Item::class);
        $pageIndexerRequest->setIndexQueueItem($itemMock);
        $headers = $pageIndexerRequest->getHeaders();
        self::assertContains('User-Agent: TYPO3', $headers, 'Header should contain a proper User-Agent');
    }

    /**
     * @param string $jsonEncodedParameter
     * @param RequestFactory $requestFactory
     * @return PageIndexerRequest
     */
    protected function getPageIndexerRequest($jsonEncodedParameter = null, RequestFactory $requestFactory = null)
    {
        $solrLogManagerMock = $this->getDumbMock(SolrLogManager::class);
        $extensionConfigurationMock = $this->getDumbMock(ExtensionConfiguration::class);
        $request = new PageIndexerRequest($jsonEncodedParameter, $solrLogManagerMock, $extensionConfigurationMock, $requestFactory);
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
        $requestMock = $this->getMockBuilder(PageIndexerRequest::class)->onlyMethods(['getUrl'])->setConstructorArgs([$testParameters, $solrLogManagerMock, $extensionConfigurationMock])->getMock();

        $responseMock = $this->getFakedGuzzleResponse($fakeResponse);

        // we fake the response from a captured response json file
        $requestMock->expects(self::once())->method('getUrl')->willReturn($responseMock);
        return $requestMock;
    }

    /**
     * @param $fakeResponse
     * @return ResponseInterface
     */
    protected function getFakedGuzzleResponse($fakeResponse): ResponseInterface
    {
        $bodyStream = $this->getDumbMock(StreamInterface::class);
        $bodyStream->expects(self::any())->method('getContents')->willReturn($fakeResponse);

        /** @var $responseMock  ResponseInterface */
        $responseMock = $this->getDumbMock(ResponseInterface::class);
        $responseMock->expects(self::any())->method('getBody')->willReturn($bodyStream);
        return $responseMock;
    }
}
