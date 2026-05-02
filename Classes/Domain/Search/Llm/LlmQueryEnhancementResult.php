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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Llm;

final class LlmQueryEnhancementResult
{
    public function __construct(
        public readonly string $originalQuery,
        public readonly string $enhancedQuery,
        public readonly bool $enhanced,
        public readonly string $configurationIdentifier,
        public readonly string $status,
        public readonly ?string $message = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly bool $fromCache = false,
    ) {}
}
