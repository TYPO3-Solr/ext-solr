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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Report;

use ApacheSolrForTypo3\Solr\Report\SolrStatus;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Integration test for the solr status report
 *
 * @author Timo Hund
 */
class SolrStatusTest extends IntegrationTest
{
    /**
     * @test
     */
    public function allStatusChecksShouldBeOkForValidSolrConnection()
    {
        $this->writeDefaultSolrTestSiteConfiguration();

        $solrStatus = GeneralUtility::makeInstance(SolrStatus::class);
        $statusCollection = $solrStatus->getStatus();

        foreach ($statusCollection as $status) {
            self::assertSame(ContextualFeedbackSeverity::OK, $status->getSeverity(), 'Expected that all status objects should be ok');
        }
    }

    /**
     * @test
     */
    public function allStatusChecksShouldFailForInvalidSolrConnection()
    {
        $this->writeDefaultSolrTestSiteConfigurationForHostAndPort(null, 'invalid', 4711);

        $solrStatus = GeneralUtility::makeInstance(SolrStatus::class);
        $statusCollection = $solrStatus->getStatus();

        foreach ($statusCollection as $status) {
            self::assertSame(ContextualFeedbackSeverity::ERROR, $status->getSeverity(), 'Expected that all status objects should indicate an error');
        }
    }
}
