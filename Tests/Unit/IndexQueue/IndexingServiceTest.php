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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingService;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionMethod;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Frontend\Http\Application as FrontendApplication;

/**
 * Testcase for the service that runs one indexing sub-request per queue item
 */
final class IndexingServiceTest extends SetUpUnitTestCase
{
    #[Test]
    public function subRequestForAPageIsBuiltForThatPageAndNotForItsRootPage(): void
    {
        $item = $this->createMock(Item::class);
        $item->method('getType')->willReturn('pages');
        $item->method('getRecordUid')->willReturn(12);
        $item->method('getRootPageUid')->willReturn(1);
        $item->method('hasIndexingProperty')->with('isMountedPage')->willReturn(false);

        self::assertSame(12, $this->resolvePageUid($item));
    }

    #[Test]
    public function subRequestForAMountedPageIsBuiltForTheMountDestination(): void
    {
        $item = $this->createMock(Item::class);
        $item->method('getType')->willReturn('pages');
        $item->method('getRecordUid')->willReturn(12);
        $item->method('hasIndexingProperty')->with('isMountedPage')->willReturn(true);
        $item->method('getIndexingProperty')->with('mountPageDestination')->willReturn('24');

        self::assertSame(24, $this->resolvePageUid($item));
    }

    protected function resolvePageUid(Item $item): int
    {
        $indexingService = new IndexingService(
            $this->createMock(FrontendApplication::class),
            $this->createMock(ConnectionManager::class),
            $this->createMock(PagesRepository::class),
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteFinder::class),
            $this->createMock(Context::class),
            $this->createMock(EventDispatcherInterface::class),
        );

        return (new ReflectionMethod($indexingService, 'resolvePageUid'))->invoke($indexingService, $item);
    }
}
