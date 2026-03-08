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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Classification;

class Classification
{
    public function __construct(
        protected array $matchPatterns = [],
        protected array $unMatchPatterns = [],
        protected string $mappedClass = '',
    ) {}

    public function getUnMatchPatterns(): array
    {
        return $this->unMatchPatterns;
    }

    public function getMatchPatterns(): array
    {
        return $this->matchPatterns;
    }

    public function getMappedClass(): string
    {
        return $this->mappedClass;
    }
}
