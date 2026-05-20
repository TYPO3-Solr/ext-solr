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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Spellchecking;

/**
 * Value object that represent a spellchecking suggestion.
 */
class Suggestion
{
    protected string $suggestion = '';

    protected string $missSpelled = '';

    protected int $numFound = 1;

    protected int $startOffset = 0;

    protected int $endOffset = 0;

    protected string $fullQuery = '';

    public function __construct(
        string $suggestion = '',
        string $missSpelled = '',
        int $numFound = 1,
        int $startOffset = 0,
        int $endOffset = 0,
        string $fullQuery = '',
    ) {
        $this->suggestion = $suggestion;
        $this->missSpelled = $missSpelled;
        $this->numFound = $numFound;
        $this->startOffset = $startOffset;
        $this->endOffset = $endOffset;
        $this->fullQuery = $fullQuery !== '' ? $fullQuery : $suggestion;
    }

    public function getEndOffset(): int
    {
        return $this->endOffset;
    }

    public function getNumFound(): int
    {
        return $this->numFound;
    }

    public function getStartOffset(): int
    {
        return $this->startOffset;
    }

    public function getSuggestion(): string
    {
        return $this->suggestion;
    }

    public function getMissSpelled(): string
    {
        return $this->missSpelled;
    }

    /**
     * Returns the original user query with the misspelled term replaced by the suggestion,
     * so the other correctly-spelled terms are preserved when this suggestion is offered
     * as a follow-up search. Falls back to the bare suggestion when no original query is known.
     *
     * @noinspection PhpUnused Used in Fluid-Templates/Partials {suggestion.fullQuery}
     */
    public function getFullQuery(): string
    {
        return $this->fullQuery;
    }
}
