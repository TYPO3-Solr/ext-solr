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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets;

/**
 * Abstract item that represent a value of a facet. E.g. an option or a node
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractFacetItem
{
    /**
     * @param AbstractFacet $facet
     * @param string $label
     * @param int $documentCount
     * @param bool $selected
     * @param array $metrics
     */
    public function __construct(
        protected AbstractFacet $facet,
        protected string $label = '',
        protected int $documentCount = 0,
        protected bool $selected = false,
        protected array $metrics = []
    ) {
    }

    /**
     * @return int
     */
    public function getDocumentCount(): int
    {
        return $this->documentCount;
    }

    /**
     * @return AbstractFacet
     */
    public function getFacet(): AbstractFacet
    {
        return $this->facet;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return bool
     */
    public function getSelected(): bool
    {
        return $this->selected;
    }

    /**
     * @return array
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * @return string
     */
    abstract public function getUriValue(): string;

    /**
     * @return string
     */
    abstract public function getCollectionKey(): string;
}
