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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Queue\RecordMonitor\Helper;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\MountPagesUpdater;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Page;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;

final class MountPagesUpdaterTest extends SetUpUnitTestCase
{
    protected SiteRepository|MockObject $siteRepositoryMock;
    protected Page|MockObject $pageInitializerMock;
    protected MountPagesUpdater&AccessibleObjectInterface $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siteRepositoryMock = $this->createMock(SiteRepository::class);
        $this->pageInitializerMock = $this->createMock(Page::class);
        $this->subject = $this->getAccessibleMock(
            MountPagesUpdater::class,
            ['getSiteRepository', 'getPageInitializer'],
            [$this->createMock(PagesRepository::class)],
        );
        $this->subject->method('getSiteRepository')->willReturn($this->siteRepositoryMock);
        $this->subject->method('getPageInitializer')->willReturn($this->pageInitializerMock);
    }

    #[Test]
    public function updateMountPointInitializesMountPointForMountingSite(): void
    {
        $site = $this->createMock(Site::class);
        $this->siteRepositoryMock->method('getSiteByPageId')->with(42)->willReturn($site);
        $this->pageInitializerMock->expects(self::once())->method('setSite')->with($site);
        $this->pageInitializerMock->expects(self::once())->method('initializeMountPoint')->with(42);

        $this->subject->updateMountPoint(42);
    }

    #[Test]
    public function updateMountPointSkipsMountPointOfSiteThatCanNotBeBuilt(): void
    {
        // e.g. all languages of the site are disabled
        $this->siteRepositoryMock->method('getSiteByPageId')->with(42)->willReturn(null);
        $this->pageInitializerMock->expects(self::never())->method('setSite');
        $this->pageInitializerMock->expects(self::never())->method('initializeMountPoint');

        $this->subject->updateMountPoint(42);
    }

    #[Test]
    public function addPageToMountingSiteIndexQueueSkipsMountingSiteThatCanNotBeBuilt(): void
    {
        // e.g. all languages of the mounting site are disabled
        $this->siteRepositoryMock->method('getSiteByPageId')->with(42)->willReturn(null);
        $this->pageInitializerMock->expects(self::never())->method('setSite');
        $this->pageInitializerMock->expects(self::never())->method('initializeMountedPage');

        $this->subject->_call(
            'addPageToMountingSiteIndexQueue',
            7,
            ['mountPageSource' => 7, 'mountPageDestination' => 42, 'mountPageOverlayed' => 1],
        );
    }
}
