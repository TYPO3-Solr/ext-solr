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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

/**
 * Interface defining required queue methods for mount point support
 */
interface MountPointAwareItemInterface
{
    /**
     * Returns mount point identifier
     */
    public function getMountPointIdentifier(): string;

    /**
     * Stores the indexing properties.
     */
    public function storeIndexingProperties(): void;

    /**
     * Indicatess if item has indexing properties
     *
     * @return bool
     */
    public function hasIndexingProperties(): bool;

    /**
     * Indicatess if item has given indexing properties
     *
     * @param string $key
     * @return bool
     */
    public function hasIndexingProperty(string $key): bool;

    /**
     * Sets an indexing property for the item.
     *
     * @param string $propertyName Indexing property name
     * @param string|int|float $value Indexing property value
     */
    public function setIndexingProperty(string $propertyName, string|int|float $value): void;

    /**
     * Gets a specific indexing property by its name/key.
     *
     * @param string $key Indexing property name/key.
     * @return string
     */
    public function getIndexingProperty(string $key): string;

    /**
     * Gets all indexing properties set for this item.
     *
     * @return array
     */
    public function getIndexingProperties(): array;

    /**
     * Gets the names of the item's indexing properties.
     *
     * @return array Array of indexing property names/keys
     */
    public function getIndexingPropertyNames(): array;
}
