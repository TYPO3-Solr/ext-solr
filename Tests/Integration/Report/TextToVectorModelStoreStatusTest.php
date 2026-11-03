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

use ApacheSolrForTypo3\Solr\Report\TextToVectorModelStoreStatus;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

final class TextToVectorModelStoreStatusTest extends IntegrationTestBase
{
    // The report only accepts a model with this name
    private const MODEL_NAME = 'llm';

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->removeModelFromAllTestCores();
    }

    protected function tearDown(): void
    {
        $this->removeModelFromAllTestCores();
        parent::tearDown();
    }

    #[Test]
    public function pluginStatusIsGreenAndModelStatusWarnsWithoutModel(): void
    {
        $results = $this->get(TextToVectorModelStoreStatus::class)->getStatus();

        self::assertCount(2, $results);
        self::assertSame(
            ContextualFeedbackSeverity::OK,
            $results[0]->getSeverity(),
            'The knn_text_to_vector query parser of the shipped configset is not recognized',
        );
        self::assertSame(
            ContextualFeedbackSeverity::WARNING,
            $results[1]->getSeverity(),
            'Expected a warning about the missing model',
        );
    }

    #[Test]
    public function allStatusChecksAreGreenWithConfiguredModel(): void
    {
        foreach ($this->testSolrCores as $coreName) {
            $this->addTextToVectorModel(
                [
                    'class' => 'dev.langchain4j.model.openai.OpenAiEmbeddingModel',
                    'name' => self::MODEL_NAME,
                    'params' => [
                        'baseUrl' => 'http://localhost:1/v1',
                        'apiKey' => 'not-used-by-the-report',
                        'modelName' => 'text-embedding-3-small',
                    ],
                ],
                $coreName,
            );
        }

        $results = $this->get(TextToVectorModelStoreStatus::class)->getStatus();

        self::assertCount(2, $results);
        foreach ($results as $status) {
            self::assertSame(ContextualFeedbackSeverity::OK, $status->getSeverity(), $status->getTitle() . ' is not OK');
        }
    }

    private function removeModelFromAllTestCores(): void
    {
        foreach ($this->testSolrCores as $coreName) {
            $this->removeTextToVectorModel(self::MODEL_NAME, $coreName);
        }
    }
}
