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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Security;

use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageFieldMappingIndexer;
use ApacheSolrForTypo3\Solr\Tests\Integration\Controller\AbstractFrontendControllerTest;
use ApacheSolrForTypo3\Solr\Tests\Integration\Security\Fixtures\CVE2026_56095SerializedValueDetector;
use ApacheSolrForTypo3\Solr\Tests\Integration\Security\Fixtures\Gadget\CVE2026_56095CanaryGadget;

/**
 * Regression guard for CVE-2026-56095 (SST #2026011610000017): the EXT:solr indexer must not run unserialize() on
 * attacker-controlled bytes returned by a cObj.
 * A record field is rendered through a TEXT cObj that carries serialized canary bytes;
 * the canary's magic method writing a marker file is the proof the sink fired.
 * The JSON fix makes json_decode() the only decode step, so no PHP object is ever reconstructed.
 * Fails on vulnerable code, passes after the fix — no assertion inversion.
 *
 * 11.6 specifics: the sink sits behind `AbstractIndexer::isSerializedValue()`, which accepts only the three
 * own multi-value cObjs — whose payload is EXT:solr's own array of strings — or a field flagged through the
 * `detectSerializedValue` hook. That hook is therefore the reachable path here and the test registers a
 * detector for it. 12.1.x decodes every cObj result unconditionally and needs no detector.
 *
 * @group frontend
 * @group security
 */
class InsecureDeserializationOfCObjOutputTest extends AbstractFrontendControllerTest
{
    /**
     * Marker path the canary writes to; randomized per test to avoid cross-run races.
     */
    private string $canaryPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->canaryPath = sys_get_temp_dir() . '/CVE-2026-56095-poc-marker-' . bin2hex(random_bytes(8));
        @unlink($this->canaryPath);

        // production wiring: PageIndexer::activate() registers this helper, which indexes
        // plugin.tx_solr.index.queue.pages.fields; indexPages() skips the frontend-helper activation
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'][PageFieldMappingIndexer::class] = PageFieldMappingIndexer::class;
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'][CVE2026_56095SerializedValueDetector::class] = CVE2026_56095SerializedValueDetector::class;
    }

    protected function tearDown(): void
    {
        @unlink($this->canaryPath);
        @unlink($this->canaryPath . '.wakeup');
        unset(
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'][PageFieldMappingIndexer::class],
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'][CVE2026_56095SerializedValueDetector::class]
        );
        parent::tearDown();
    }

    /**
     * Baseline: a benign field value must not create the canary marker — guards against false positives.
     *
     * @test
     */
    public function indexingAPageWithoutAnyDeserializationGadgetMustNotInvokeMagicMethods(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CVE2026_56095_deserialization.csv');

        // baseline runs with a benign value, not the CSV placeholder bytes
        $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->update(
                'pages',
                ['description' => 'plain benign baseline content for SST 235568'],
                ['uid' => 2]
            );

        $this->configureIndexingPipelineForDescription();
        $this->indexPages([2]);
        $this->assertSolrContainsDocumentCount(1);

        self::assertFileDoesNotExist(
            $this->canaryPath,
            'Baseline assumption broken: the canary file was created even without a gadget payload — the test infrastructure is not reliable.'
        );
        self::assertFileDoesNotExist(
            $this->canaryPath . '.wakeup',
            'Baseline assumption broken: the canary .wakeup file was created without a gadget payload.'
        );
    }

    /**
     * Primary guard: an attacker-controlled record field carrying serialized canary bytes is indexed via a TEXT cObj.
     * On vulnerable code unserialize() runs and the canary's __wakeup writes the marker; the JSON fix prevents it.
     *
     * @test
     */
    public function unserializeOfCObjOutputMustNotInvokeMagicMethodsOnAttackerControlledObject(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CVE2026_56095_deserialization.csv');

        $payload = $this->buildCanaryGadgetPayload();

        $this->getConnectionPool()
            ->getConnectionForTable('pages')
            ->update(
                'pages',
                ['description' => $payload],
                ['uid' => 2]
            );

        $this->configureIndexingPipelineForDescription();
        $this->indexPages([2]);

        // __wakeup fires synchronously during unserialize() inside the indexer run, so it is observable here;
        // __destruct may run only at shutdown, after the assertion.
        self::assertFileDoesNotExist(
            $this->canaryPath . '.wakeup',
            'SST 235568: EXT:solr indexer pipeline ran unserialize() on attacker-controlled bytes; '
            . 'the canary class\'s __wakeup magic method executed and wrote ' . $this->canaryPath . '.wakeup.'
        );
    }

    /**
     * Builds the serialized canary payload, clearing the local instance's path first so its destructor writes nothing here.
     */
    private function buildCanaryGadgetPayload(): string
    {
        $gadget = new CVE2026_56095CanaryGadget();
        $gadget->canaryPath = $this->canaryPath;
        $gadget->canaryMarker = 'CVE_2026_56095_GADGET_FIRED_FROM_INDEXER';
        $payload = serialize($gadget);

        // clear the local instance's path so its destructor writes nothing; the serialized string keeps the real path
        $gadget->canaryPath = '';
        unset($gadget);
        @unlink($this->canaryPath);

        return $payload;
    }

    /**
     * Maps the flagged Solr field to the `description` column via TEXT cObj, forcing the indexer's cObj decode path.
     */
    private function configureIndexingPipelineForDescription(): void
    {
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            config.index_enable = 1
            plugin.tx_solr.index.queue.pages.fields {
                ' . CVE2026_56095SerializedValueDetector::FLAGGED_SOLR_FIELD . ' = TEXT
                ' . CVE2026_56095SerializedValueDetector::FLAGGED_SOLR_FIELD . '.field = description
            }
            '
        );
    }
}
