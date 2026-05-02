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

namespace ApacheSolrForTypo3\Solr\Search;

use ApacheSolrForTypo3\Solr\Domain\Search\Llm\LlmQueryEnhancerService;
use ApacheSolrForTypo3\Solr\Event\Search\AfterSearchQueryHasBeenPreparedEvent;

final class LlmQueryEnhancerComponent
{
    public function __construct(
        private readonly LlmQueryEnhancerService $llmQueryEnhancerService,
    ) {}

    public function __invoke(AfterSearchQueryHasBeenPreparedEvent $event): void
    {
        $configuration = $event->getTypoScriptConfiguration();
        if ($configuration->isPureVectorSearchEnabled()) {
            return;
        }

        $enabled = (bool)$configuration->getValueByPathOrDefaultValue(
            'plugin.tx_solr.search.llmQueryEnhancer.enabled',
            false,
        );
        if (!$enabled) {
            return;
        }

        $rawQuery = $event->getSearchRequest()->getRawUserQuery();
        $configurationIdentifier = (string)$configuration->getValueByPathOrDefaultValue(
            'plugin.tx_solr.search.llmQueryEnhancer.configurationIdentifier',
            LlmQueryEnhancerService::DEFAULT_CONFIGURATION_IDENTIFIER,
        );
        $cacheLifetime = (int)$configuration->getValueByPathOrDefaultValue(
            'plugin.tx_solr.search.llmQueryEnhancer.cacheLifetime',
            86400,
        );

        $result = $this->llmQueryEnhancerService->enhanceQuery(
            $rawQuery,
            $configurationIdentifier,
            $event->getSearchRequest()->getContextSystemLanguageUid(),
            $cacheLifetime,
        );

        if (!$result->enhanced || $result->enhancedQuery === $rawQuery) {
            return;
        }

        $event->getQuery()->setQuery($result->enhancedQuery);
    }
}
