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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases;

/**
 * Brings the stored keywords of tx_solr_statistics into one form, so spellings
 * of the same search count as one term. The statistics store them HTML-encoded
 * and, before EXT:solr stored the raw search term, Lucene-escaped.
 */
final class SearchTermNormalizer
{
    /**
     * Characters EscapeService::escapeSpecialCharacters() escapes in either mode
     */
    private const ESCAPED_CHARACTERS = '/\\\\([(){}\[\]^"~:\\\\\/|&;])/';

    /**
     * The term as the visitor entered it, for display
     */
    public function display(string $keywords): string
    {
        $term = html_entity_decode($keywords, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $term = preg_replace(self::ESCAPED_CHARACTERS, '$1', $term) ?? $term;
        $term = str_replace("\u{00A0}", ' ', $term);
        $term = preg_replace('/\s+/u', ' ', $term) ?? $term;

        return trim($term);
    }

    /**
     * The key spellings of one term share
     */
    public function normalize(string $keywords): string
    {
        return mb_strtolower($this->display($keywords), 'UTF-8');
    }
}
