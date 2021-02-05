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
 * This class is used to map TYPO3 global settings like proxy etc. into the unified configuration
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class GlobalConfiguration implements UnifyConfigurationInterface
{
    /**
     * This array is used in case the configuration need to be customized
     *
     * @var array|null
     */
    protected $globals = null;

    /**
     * Allows place own global configuration information.
     *
     * @param array $globals
     */
    public function __construct(array $globals = [])
    {
        if (!empty($globals)) {
            $this->globals = $globals;
        }
    }

    /**
     * Nothing to load
     *
     * @return UnifyConfigurationInterface
     */
    public function load(): UnifyConfigurationInterface
    {
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getUnifiedArray(): array
    {
        $globals = $this->globals ?? $GLOBALS;
        return [
            'connection' => [
                'read' => $globals['TYPO3_CONF_VARS']['HTTP'],
                'write' => $globals['TYPO3_CONF_VARS']['HTTP']
            ]
        ];
    }
}
