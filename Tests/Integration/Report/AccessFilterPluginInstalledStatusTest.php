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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Report;

use ApacheSolrForTypo3\Solr\Report\AccessFilterPluginInstalledStatus;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * Integration test for the Solr Access Filter status report
 */
class AccessFilterPluginInstalledStatusTest extends IntegrationTestBase
{
    protected bool $initializeDatabase = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    #[Test]
    public function canGetGreenAccessFilterStatus(): void
    {
        /** @var AccessFilterPluginInstalledStatus $accessFilterStatus */
        $accessFilterStatus = $this->get(AccessFilterPluginInstalledStatus::class);
        $results = $accessFilterStatus->getStatus();

        self::assertCount(1, $results);
        self::assertEquals(
            $results[0]->getSeverity(),
            ContextualFeedbackSeverity::OK,
            'We expect to get no violations against the test Solr server ',
        );
    }
}
