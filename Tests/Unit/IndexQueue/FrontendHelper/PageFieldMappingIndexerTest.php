<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\IndexQueue\AbstractIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageFieldMappingIndexer;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\AbstractIndexerTest;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectFactory;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\PageInformation;

class PageFieldMappingIndexerTest extends SetUpUnitTestCase
{
    protected ContentObjectFactory|MockObject $contentObjectFactoryMock;
    protected ContentObjectRenderer $contentObjectRenderer;
    protected MockObject|AbstractContentObject $contentObjectMock;

    protected function setUp(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'] = [];
        parent::setUp();

        $container = new Container();

        $request = $GLOBALS['TYPO3_REQUEST'] = new ServerRequest();
        $this->contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $this->contentObjectRenderer->setRequest($request);
        $this->contentObjectFactoryMock = $this->getMockBuilder(ContentObjectFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->contentObjectMock = $this->createMock(AbstractContentObject::class);

        $this->contentObjectFactoryMock
            ->expects(self::any())
            ->method('getContentObject')
            ->willReturn($this->contentObjectMock);

        $container->set(ContentObjectFactory::class, $this->contentObjectFactoryMock);
        $container->set(EventDispatcherInterface::class, new NoopEventDispatcher());
        GeneralUtility::setContainer($container);
    }

    /**
     * Test that field values can be resolved
     */
    #[DataProviderExternal(AbstractIndexerTest::class, 'indexingDataProvider')]
    #[Test]
    public function resolveFieldValue(
        array $indexingConfiguration,
        string $solrFieldName,
        array $data,
        array $mockSettings,
        mixed $expectedValue,
    ): void {

        $subject = $this->getAccessibleMock(
            PageFieldMappingIndexer::class,
            array_merge(
                [
                ],
                $mockSettings[AbstractIndexer::class]['methods'] ?? [],
            ),
        );
        $typoScriptConfiguration = new TypoScriptConfiguration(
            [
                'plugin.' => [
                    'tx_solr.' => [
                        'index.' => [
                            'queue.' => [
                                'pages.' => [
                                    'fields.' => $indexingConfiguration,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        );
        $this->inject(
            $subject,
            'configuration',
            $typoScriptConfiguration,
        );

        $pageInformation = new PageInformation();
        $pageInformation->setId(0);
        $request = (new ServerRequest())
            ->withAttribute('frontend.page.information', $pageInformation);
        $GLOBALS['TYPO3_REQUEST'] = $request;

        if (is_callable($mockSettings['modsCallable'] ?? null)) {
            $mockSettings['modsCallable']();
        }
        $document = new Document();
        self::assertSame(
            $expectedValue,
            $this->callInaccessibleMethod(
                $subject,
                'resolveFieldValue',
                $solrFieldName,
                $document,
                $data,
            ),
        );
    }
}
