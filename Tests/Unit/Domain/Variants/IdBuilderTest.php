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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Variants;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Variants\IdBuilder;
use ApacheSolrForTypo3\Solr\Event\Variants\AfterVariantIdWasBuiltEvent;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Tests\Unit\Fixtures\EventDispatcher\MockEventDispatcher;

/**
 * Testcase to check if the IdBuilder can be used to build proper variantIds.
 */
class IdBuilderTest extends SetUpUnitTestCase
{
    protected string $oldEncryptionKey;

    protected function setUp(): void
    {
        $this->oldEncryptionKey = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'testkey';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $this->oldEncryptionKey;
        parent::tearDown();
    }

    /**
     * @test
     */
    public function canBuildVariantId(): void
    {
        $build = new IdBuilder(new NoopEventDispatcher());
        $variantId = $build->buildFromTypeAndUid('pages', 4711, [], $this->createMock(Site::class), new Document());
        self::assertSame('e99b3552a0451f1a2e7aca4ac06ccaba063393de/pages/4711', $variantId);
    }

    /**
     * @test
     */
    public function canUseCustomEventListener(): void
    {
        $eventDispatcher = new MockEventDispatcher();
        $eventDispatcher->addListener(function (AfterVariantIdWasBuiltEvent $event) {
            $event->setVariantId('mycustomid');
        });
        $build = new IdBuilder($eventDispatcher);
        $variantId = $build->buildFromTypeAndUid('pages', 4711, [], $this->createMock(Site::class), new Document());

        // the variantId should be overwritten by the custom modifier
        self::assertSame('mycustomid', $variantId);
    }
}
