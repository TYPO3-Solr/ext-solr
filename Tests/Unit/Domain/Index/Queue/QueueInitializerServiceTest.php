<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueInitializationService;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

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
            /** @var QueueInitializationService $service */
        $service = $this->getMockBuilder(QueueInitializationService::class)->setMethods(['executeInitializer'])->setConstructorArgs([$queueMock])->getMock();

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
                                    'title' => 'title'
                                ]
                            ],
                            'my_news' => 1,
                            'my_news.' => [
                                'initialization' => 'MyNewsInitializer',
                                'table' => 'tx_news_domain_model_news',
                                'fields.' => [
                                    'title' => 'title'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $fakeConfiguration = new TypoScriptConfiguration($fakeTs);

        $siteMock = $this->getDumbMock(Site::class);
        $siteMock->expects($this->any())->method('getSolrConfiguration')->willReturn($fakeConfiguration);

        $service->expects($this->exactly(2))->method('executeInitializer')->withConsecutive(
            [$siteMock, 'my_pages', 'MyPagesInitializer', 'pages', $fakeTs['plugin.']['tx_solr.']['index.']['queue.']['my_pages.']],
            [$siteMock, 'my_news', 'MyNewsInitializer', 'tx_news_domain_model_news', $fakeTs['plugin.']['tx_solr.']['index.']['queue.']['my_news.']]
        );
        $service->initializeBySiteAndIndexConfiguration($siteMock, '*');
    }
}