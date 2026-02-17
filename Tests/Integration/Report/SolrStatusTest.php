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

use ApacheSolrForTypo3\Solr\Report\SolrStatus;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * Integration test for the Solr status report
 */
class SolrStatusTest extends IntegrationTestBase
{
    #[Test]
    public function allStatusChecksShouldBeOkForValidSolrConnection(): void
    {
        $this->writeDefaultSolrTestSiteConfiguration();

        $solrStatus = $this->get(SolrStatus::class);
        $statusCollection = $solrStatus->getStatus();

        foreach ($statusCollection as $status) {
            self::assertSame(ContextualFeedbackSeverity::OK, $status->getSeverity(), 'Expected that all status objects should be ok');
        }
    }

    #[Test]
    public function allStatusChecksShouldFailForInvalidSolrConnection(): void
    {
        $this->writeDefaultSolrTestSiteConfigurationForHostAndPort(null, 'invalid', 4711);

        $solrStatus = $this->get(SolrStatus::class);
        $statusCollection = $solrStatus->getStatus();

        foreach ($statusCollection as $status) {
            self::assertSame(ContextualFeedbackSeverity::ERROR, $status->getSeverity(), 'Expected that all status objects should indicate an error');
        }
    }
}
