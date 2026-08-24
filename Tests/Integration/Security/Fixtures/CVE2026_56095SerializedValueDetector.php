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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Security\Fixtures;

use ApacheSolrForTypo3\Solr\IndexQueue\SerializedValueDetector;

/**
 * Stands in for a third-party extension registering itself in
 * `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue']`.
 *
 * On 11.6.x this hook is the only way attacker-influenced cObj output reaches the decode step:
 * `AbstractIndexer::isSerializedValue()` otherwise accepts only the three own multi-value cObjs,
 * whose payload is EXT:solr's own array of strings. 12.1.x decodes unconditionally and needs no detector.
 */
final class CVE2026_56095SerializedValueDetector implements SerializedValueDetector
{
    /**
     * Solr field the indexing configuration maps to the attacker-influenced record column.
     */
    public const FLAGGED_SOLR_FIELD = 'sortSubTitle_stringS';

    public function isSerializedValue(array $indexingConfiguration, $solrFieldName): bool
    {
        return $solrFieldName === self::FLAGGED_SOLR_FIELD;
    }
}
