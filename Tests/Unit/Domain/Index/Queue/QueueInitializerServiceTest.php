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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueInitializationService;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class QueueInitializerServiceTest extends UnitTest
{

    /**
     * @test
     */
    public function allIndexConfigurationsAreUsedWhenWildcardIsPassed()
    {
        $queueMock = $this->getDumbMock(Queue::class);
        /* @var QueueInitializationService|MockObject $service */
        $service = $this->getMockBuilder(QueueInitializationService::class)->onlyMethods(['executeInitializer'])->setConstructorArgs([$queueMock])->getMock();

        $fakeTs = [
            'plugin.' => [
                'tx_solr.' => [
                    'index.' => [
                        'queue.' => [
                            'my_pages' => 1,
                            'my_pages.' => [
                                'initialization' => 'MyPagesInitializer',
                                'table' => 'pages',
                                'fields.' => [
                                    'title' => 'title',
                                ],
                            ],
                            'my_news' => 1,
                            'my_news.' => [
                                'initialization' => 'MyNewsInitializer',
                                'table' => 'tx_news_domain_model_news',
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

        $siteMock = $this->getDumbMock(Site::class);
        $siteMock->expects(self::any())->method('getSolrConfiguration')->willReturn($fakeConfiguration);

        $service
            ->expects(self::exactly(2))
            ->method('executeInitializer')
            ->withConsecutive(
                [$siteMock, 'my_pages', 'MyPagesInitializer', 'pages', $fakeTs['plugin.']['tx_solr.']['index.']['queue.']['my_pages.']],
                [$siteMock, 'my_news', 'MyNewsInitializer', 'tx_news_domain_model_news', $fakeTs['plugin.']['tx_solr.']['index.']['queue.']['my_news.']]
            );
        $service->initializeBySiteAndIndexConfiguration($siteMock, '*');
    }
}
