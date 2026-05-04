<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Queue;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueInitializationService;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class QueueInitializerServiceTest extends SetUpUnitTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function allIndexConfigurationsAreUsedWhenWildcardIsPassed(): void
    {
        // Register ContentObjectService mock to avoid ContentObjectRenderer instantiation
        $this->setUpTypoScriptConfigurationMocks();

        $queueMock = $this->createMock(Queue::class);
        GeneralUtility::addInstance(Queue::class, $queueMock);
        GeneralUtility::addInstance(Queue::class, $queueMock);
        $service = $this->getMockBuilder(QueueInitializationService::class)->onlyMethods(['executeInitializer'])->setConstructorArgs([new NoopEventDispatcher()])->getMock();

        $fakeTs = [
            'plugin.' => [
                'tx_solr.' => [
                    'index.' => [
                        'queue.' => [
                            'my_pages' => 1,
                            'my_pages.' => [
                                'initialization' => 'MyPagesInitializer',
                                'type' => 'pages',
                                'fields.' => [
                                    'title' => 'title',
                                ],
                            ],
                            'my_news' => 1,
                            'my_news.' => [
                                'initialization' => 'MyNewsInitializer',
                                'type' => 'tx_news_domain_model_news',
                                'fields.' => [
                                    'title' => 'title',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $fakeConfiguration = new TypoScriptConfiguration($fakeTs);

        $siteMock = $this->createMock(Site::class);
        $siteMock->expects(self::any())->method('getSolrConfiguration')->willReturn($fakeConfiguration);

        $matcher = self::exactly(2);
        $service
            ->expects($matcher)
            ->method('executeInitializer')
            ->willReturnCallback(static function () use ($siteMock, $fakeTs, $matcher): bool {
                match ($matcher->numberOfInvocations()) {
                    1 => self::assertEquals(
                        func_get_args(),
                        [
                            $siteMock,
                            'my_pages',
                            'MyPagesInitializer',
                            'pages',
                            $fakeTs['plugin.']['tx_solr.']['index.']['queue.']['my_pages.'],
                        ],
                    ),
                    2 => self::assertEquals(
                        func_get_args(),
                        [
                            $siteMock,
                            'my_news',
                            'MyNewsInitializer',
                            'tx_news_domain_model_news',
                            $fakeTs['plugin.']['tx_solr.']['index.']['queue.']['my_news.'],
                        ],
                    ),
                    default => self::fail('Unexpected number of invocations: ' . $matcher->numberOfInvocations()),
                };

                return true;
            });
        $service->initializeBySiteAndIndexConfiguration($siteMock, '*');
    }
}
