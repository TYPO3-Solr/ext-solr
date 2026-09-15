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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue;

use ApacheSolrForTypo3\Solr\IndexQueue\IndexingResultCollector;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;

class IndexingResultCollectorTest extends IntegrationTestBase
{
    #[Test]
    public function dropsCollectedGroupsWhenTheNextSubRequestIsPrepared(): void
    {
        $collector = $this->get(IndexingResultCollector::class);
        $collector->setUserGroupDetectionActive(true);
        $collector->addFrontendGroup(4711);

        $this->dispatchBeforeIndexingSubRequestIsPreparedEvent();

        self::assertFalse(
            $collector->isUserGroupDetectionActive(),
            'The collector still runs the user group detection of the previous sub-request.',
        );
        $collector->finalizeUserGroups();
        self::assertSame(
            [0],
            $collector->getUserGroups(),
            'The collector still carries the frontend groups of the previous sub-request.',
        );
    }
}
