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
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Index Queue Page Indexer request test.
 */
class PageIndexerRequestTest extends SetUpUnitTestCase
{
    #[Test]
    public function authenticatesValidRequest(): void
    {
        $jsonEncodedParameters = json_encode([
            'item' => 1,
            'page' => 1,
            'hash' => hash('md5', '1|1|' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']),
        ]);

        $request = $this->getPageIndexerRequest($jsonEncodedParameters);
        self::assertTrue($request->isAuthenticated());
    }

    #[Test]
    public function doesNotAuthenticateInvalidRequest(): void
    {
        $jsonEncodedParameters = json_encode([
            'item' => 1,
            'page' => 1,
            'hash' => hash('md5', 'invalid|invalid|invalid'),
        ]);

        $request = $this->getPageIndexerRequest($jsonEncodedParameters);
        self::assertFalse($request->isAuthenticated());
    }

    #[Test]
    public function usesUniqueIdFromHeader(): void
    {
        $id = uniqid();
        $jsonEncodedParameters = json_encode([
            'requestId' => $id,
        ]);

        $request = $this->getPageIndexerRequest($jsonEncodedParameters);
        self::assertEquals($id, $request->getRequestId());
    }

    #[Test]
    public function sendCreatesExpectedResponse(): void
    {
        $testParameters = json_encode(['requestId' => '581f76be71f60']);

        $fakeResponse = self::getFixtureContentByName('fakeResponse.json');
        $requestMock = $this->getMockedPageIndexerRequestWithUsedFakeResponse($testParameters, $fakeResponse);

        /** @var MockObject|Item $queueItemMock */
        $queueItemMock = $this->createMock(Item::class);
        $requestMock->setIndexQueueItem($queueItemMock);

        $response = $requestMock->send('http://7.6.local.typo3.org/about/typo3/');
        $indexPageResult = $response->getActionResult('indexPage');

        self::assertTrue(is_array($indexPageResult));
        self::assertSame(1, $indexPageResult['pageIndexed']);
        self::assertSame($response->getRequestId(), '581f76be71f60', 'Response did not contain expected requestId');
    }

    #[Test]
    public function sendThrowsExceptionOnIsMismatch(): void
    {
        $testParameters = json_encode(['requestId' => 'wrongId']);
        $fakeResponse = self::getFixtureContentByName('fakeResponse.json');

        $requestMock = $this->getMockedPageIndexerRequestWithUsedFakeResponse($testParameters, $fakeResponse);
        /** @var MockObject|Item $queueItemMock */
        $queueItemMock = $this->createMock(Item::class);

        $requestMock->setIndexQueueItem($queueItemMock);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Request ID mismatch');
        $requestMock->send('http://7.6.local.typo3.org/about/typo3/');
    }

    #[Test]
    public function sendThrowsExceptionWhenInvalidJsonIsReturned(): void
    {
        $testParameters = json_encode(['requestId' => 'wrongId']);
        $fakeResponse = 'invalidJsonString!!';

        $requestMock = $this->getMockedPageIndexerRequestWithUsedFakeResponse($testParameters, $fakeResponse);
        /** @var MockObject|Item $queueItemMock */
        $queueItemMock = $this->createMock(Item::class);
        $requestMock->setIndexQueueItem($queueItemMock);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to execute Page Indexer Request');
        $requestMock->send('http://7.6.local.typo3.org/about/typo3/');
    }

    #[Test]
    public function canSetTimeOutFromPHPConfiguration(): void
    {
        $initialTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', 122);

        $pageIndexerRequest = $this->getPageIndexerRequest();
        self::assertSame(122.0, $pageIndexerRequest->getTimeout());
        ini_set('default_socket_timeout', $initialTimeout);
    }

    #[Test]
    public function canSendRequestToSslSite(): void
    {
        $testParameters = json_encode(['requestId' => '581f76be71f60']);
        $fakeResponse = self::getFixtureContentByName('fakeResponse.json');

        $requestMock = $this->getMockedPageIndexerRequestWithUsedFakeResponse($testParameters, $fakeResponse);
        /** @var MockObject|Item $queueItemMock */
        $queueItemMock = $this->createMock(Item::class);
        $requestMock->setIndexQueueItem($queueItemMock);

        $requestMock->send('https://7.6.local.typo3.org/about/typo3/');
    }

    #[Test]
    public function authenticationHeaderIsSetWhenUsernameAndPasswordHaveBeenPassed(): void
    {
        /** @var MockObject|RequestFactory $requestFactoryMock */
        $requestFactoryMock = $this->createMock(RequestFactory::class);
        $requestFactoryMock->expects(self::once())->method('request')->willReturnCallback(function ($url, $method, $options) {
            $this->assertSame(['bob', 'topsecret'], $options['auth'], 'Authentication options have not been set');
            $this->assertSame('GET', $method, 'Unexpected http method');

            return $this->getFakedGuzzleResponse(self::getFixtureContentByName('fakeResponse.json'));
        });

        /** @var MockObject|SolrLogManager $solrLogManagerMock */
        $solrLogManagerMock = $this->createMock(SolrLogManager::class);
        /** @var MockObject|ExtensionConfiguration $extensionConfigurationMock */
        $extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);

        $testParameters = json_encode(['requestId' => '581f76be71f60']);
        $pageIndexerRequest = new PageIndexerRequest($testParameters, $solrLogManagerMock, $extensionConfigurationMock, $requestFactoryMock);

        /** @var MockObject|Item $queueItemMock */
        $queueItemMock = $this->createMock(Item::class);
        $pageIndexerRequest->setIndexQueueItem($queueItemMock);
        $pageIndexerRequest->setAuthorizationCredentials('bob', 'topsecret');

        $pageIndexerRequest->send('https://7.6.local.typo3.org/about/typo3/');
    }

    #[Test]
    public function canSetParameter(): void
    {
        $pageIndexerRequest = $this->getPageIndexerRequest();
        self::assertNull($pageIndexerRequest->getParameter('foo'), 'Parameter foo should be null when nothing was set');

        $pageIndexerRequest->setParameter('foo', 'bar');
        self::assertSame('bar', $pageIndexerRequest->getParameter('foo'), 'Could not get parameter foo after setting it');

        $pageIndexerRequest->setParameter('test', 4711);
        self::assertSame(4711, $pageIndexerRequest->getParameter('test'), 'Could not get parameter foo after setting it');
    }

    #[Test]
    public function canSetUserAgent(): void
    {
        $pageIndexerRequest = $this->getPageIndexerRequest();

        /** @var MockObject|Item $itemMock */
        $itemMock = $this->createMock(Item::class);
        $pageIndexerRequest->setIndexQueueItem($itemMock);
        $headers = $pageIndexerRequest->getHeaders();
        self::assertContains('User-Agent: TYPO3', $headers, 'Header should contain a proper User-Agent');
    }

    /**
     * @param string|null $jsonEncodedParameter
     * @param RequestFactory|null $requestFactory
     * @return PageIndexerRequest
     */
    protected function getPageIndexerRequest(?string $jsonEncodedParameter = null, ?RequestFactory $requestFactory = null): PageIndexerRequest
    {
        /** @var MockObject|SolrLogManager $solrLogManagerMock */
        $solrLogManagerMock = $this->createMock(SolrLogManager::class);
        /** @var MockObject|ExtensionConfiguration $extensionConfigurationMock */
        $extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $requestFactory = $requestFactory ?? $this->createMock(RequestFactory::class);
        return new PageIndexerRequest($jsonEncodedParameter, $solrLogManagerMock, $extensionConfigurationMock, $requestFactory);
    }

    /**
     * @param $testParameters
     * @param $fakeResponse
     * @return MockObject|PageIndexerRequest
     */
    protected function getMockedPageIndexerRequestWithUsedFakeResponse($testParameters, $fakeResponse): PageIndexerRequest|MockObject
    {
        $solrLogManagerMock = $this->createMock(SolrLogManager::class);
        $extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $requestFactoryMock = $this->createMock(RequestFactory::class);
        /** @var MockObject|PageIndexerRequest $requestMock */
        $requestMock = $this->getMockBuilder(PageIndexerRequest::class)
            ->onlyMethods(['getUrl'])
            ->setConstructorArgs([
                $testParameters,
                $solrLogManagerMock,
                $extensionConfigurationMock,
                $requestFactoryMock,
            ])
            ->getMock();

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
        $bodyStream = $this->createMock(StreamInterface::class);
        $bodyStream->expects(self::any())->method('getContents')->willReturn($fakeResponse);

        /** @var MockObject|ResponseInterface $responseMock */
        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects(self::any())->method('getBody')->willReturn($bodyStream);
        return $responseMock;
    }
}
