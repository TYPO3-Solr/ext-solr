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

namespace ApacheSolrForTypo3\Solr\System\Solr\ResponseStructure;

use stdClass;

/**
 * Shape-only description of the "spellcheck" section produced by Solr.
 *
 * At runtime, the value lives as a {@see stdClass} returned by `json_decode`. This
 * class exists exclusively so IDEs and static analyzers can autocomplete the well-known
 * sub-fields when accessed through the {@see \ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter}
 * magic getter.
 *
 * @internal
 */
class SpellcheckSection extends stdClass
{
    /**
     * Flat NamedList of misspelled-term strings alternating with suggestion objects
     * (when `json.nl=flat`, the EXT:solr default).
     *
     * @var array<int|string, mixed>|null
     */
    public ?array $suggestions = null;

    /**
     * Flat NamedList of collated full-query strings (the original query with all
     * misspelled tokens replaced) when `spellcheck.collate=true`.
     *
     * @var array<int|string, mixed>|null
     */
    public ?array $collations = null;
}
