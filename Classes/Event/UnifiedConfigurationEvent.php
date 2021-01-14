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

namespace ApacheSolrForTypo3\Solr\Event;

use ApacheSolrForTypo3\Solr\System\Configuration\UnifiedConfiguration;

/**
 * This event is is used to initialize the unified configurations.
 *
 * This is required since there are many places a configuration could be available threw all
 * available extensions.
 *
 * @author Lars Tode <lars.tode@dkd.de>
 * @copyright (c) 2020-2021 Lars Tode <lars.tode@dkd.de>
 */
class UnifiedConfigurationEvent
{
    /**
     * @var UnifiedConfiguration
     */
    protected $unifiedConfiguration;

    /**
     * Default constructor for this event
     *
     * @param UnifiedConfiguration $unifiedConfiguration
     */
    public function __construct(UnifiedConfiguration $unifiedConfiguration)
    {
        $this->unifiedConfiguration = $unifiedConfiguration;
    }

    /**
     * @return UnifiedConfiguration
     */
    public function getUnifiedConfiguration(): UnifiedConfiguration
    {
        return $this->unifiedConfiguration;
    }
}
