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

namespace ApacheSolrForTypo3\Solr\Event\IndexQueue;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\AbstractInitializer;

/**
 * PSR-14 Event which is fired after a index queue has been (re-) initialized.
 *
 * Previously used via $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueInitialization']
 * and InitializationPostProcessor interface.
 */
final class AfterIndexQueueHasBeenInitializedEvent
{
    public function __construct(
        private readonly AbstractInitializer $initializer,
        private readonly Site $site,
        private readonly string $indexingConfigurationName,
        private readonly string $type,
        private readonly array $indexingConfiguration,
        private bool $isInitialized,
    ) {}

    public function getInitializer(): AbstractInitializer
    {
        return $this->initializer;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getIndexingConfigurationName(): string
    {
        return $this->indexingConfigurationName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getIndexingConfiguration(): array
    {
        return $this->indexingConfiguration;
    }

    public function isInitialized(): bool
    {
        return $this->isInitialized;
    }

    public function setIsInitialized(bool $isInitialized): void
    {
        $this->isInitialized = $isInitialized;
    }
}
