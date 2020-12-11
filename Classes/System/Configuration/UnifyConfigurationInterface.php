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

namespace ApacheSolrForTypo3\Solr\System\Configuration;

/**
 * This interface is used to allow a configuration to be merged into a unified configuration.
 *
 * Unified means that configuration placed inside of an equal structure.
 * A class implements this interface simple remaps configuration keys and path information
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
interface UnifyConfigurationInterface
{
    /**
     * Load and parse the configuration
     *
     * @return UnifyConfigurationInterface
     */
    public function load(): UnifyConfigurationInterface;

    /**
     * Returns a remapped configuration
     *
     * @return array
     */
    public function getUnifiedArray(): array;
}
