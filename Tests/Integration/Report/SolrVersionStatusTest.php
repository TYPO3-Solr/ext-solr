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

use ApacheSolrForTypo3\Solr\Report\SolrVersionStatus;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Reports\Status;

/**
 * Integration test for the Solr version test
 */
class SolrVersionStatusTest extends IntegrationTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    #[Test]
    public function canGetAGreenSolrConfigStatusAgainstTestServer(): void
    {
        /** @var SolrVersionStatus $solrVersionStatus */
        $solrVersionStatus = $this->get(SolrVersionStatus::class);
        $results = $solrVersionStatus->getStatus();
        self::assertCount(6, $results);
        self::assertEmpty(
            array_filter(
                $results,
                static fn(Status $status): bool => $status->getSeverity() !== ContextualFeedbackSeverity::OK,
            ),
            'We expect to get no violations against the test Solr server ',
        );
    }
}
