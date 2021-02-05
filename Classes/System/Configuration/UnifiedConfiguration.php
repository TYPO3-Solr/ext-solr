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

use ApacheSolrForTypo3\Solr\System\Configuration\Exception\ConfigurationAlreadyMergedException;
use ApacheSolrForTypo3\Solr\System\Util\ArrayAccessor;

use function Webmozart\Assert\Tests\StaticAnalysis\boolean;

/**
 * This class wraps all configuration information and offers one interface
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class UnifiedConfiguration extends ArrayAccessor
{
    /**
     * The root page ID
     *
     * @var int
     */
    protected $rootPageUid = 0;

    /**
     * The language uid
     *
     * @var int
     */
    protected $languageUid = 0;

    /**
     * Lists of sorted configurations
     *
     * @var string[]
     */
    protected $includedConfigurationClasses = [];

    /**
     * @var UnifyConfigurationInterface[]
     */
    protected $includedConfiguration = [];

    /**
     * This constructor contains all required parameters used for other
     *
     * @param int $pageUid
     * @param int $languageUid
     */
    public function __construct(int $pageUid = 0, int $languageUid = 0)
    {
        parent::__construct([], '.');
        $this->rootPageUid = $pageUid;
        $this->languageUid = $languageUid;
    }

    /**
     * Check if a class is included within this configuration
     *
     * @param string $className
     * @return bool
     */
    public function containsConfigurationClass(string $className): bool
    {
        return in_array($className, $this->includedConfigurationClasses);
    }

    /**
     * Merge another configuration into this one
     *
     * @param UnifyConfigurationInterface $configuration
     * @return $this
     * @throws ConfigurationAlreadyMergedException
     */
    public function mergeConfigurationByObject(UnifyConfigurationInterface $configuration): UnifiedConfiguration
    {
        $className = get_class($configuration);
        if (in_array($className, $this->includedConfigurationClasses)) {
            throw new ConfigurationAlreadyMergedException(
                'Configuration of type ' . $className . ' already merged. '.
                'Use method \'replaceConfigurationByObject()\' to replace this configuration.',
                409
            );
        }

        $data = $configuration->load()->getUnifiedArray();
        $this->includedConfiguration[$className] = $configuration;
        $this->includedConfigurationClasses[] = $className;
        $this->mergeArray($data);

        return $this;
    }

    /**
     * Merge another configuration into this one and execute a reloads
     *
     * @param UnifyConfigurationInterface $configuration
     * @return $this
     * @throws ConfigurationAlreadyMergedException
     */
    public function replaceConfigurationByObject(
        UnifyConfigurationInterface $configuration
    ): UnifiedConfiguration {
        $className = get_class($configuration);
        if (!$this->containsConfigurationClass($className)) {
            return $this->mergeConfigurationByObject($configuration);
        }
        $this->includedConfiguration[$className] = $configuration;
        $this->reload();
        return $this;
    }

    /**
     * @return $this
     */
    public function reload(): UnifiedConfiguration
    {
        $this->clear();
        foreach ($this->includedConfigurationClasses as $className) {
            if (!isset($this->includedConfiguration[$className])) {
                continue;
            }
            $data = $this->includedConfiguration[$className]->load()->getUnifiedArray();
            $this->mergeArray($data);
        }

        return $this;
    }

    /**
     * Returns a specific configuration from included configurations.
     *
     * @param string $className
     * @return UnifyConfigurationInterface|null
     */
    public function getConfigurationByClass(string $className): ?UnifyConfigurationInterface
    {
        if (isset($this->includedConfiguration[$className]) &&
            $this->includedConfiguration[$className] instanceof UnifyConfigurationInterface) {
            return $this->includedConfiguration[$className];
        }

        return null;
    }

    /**
     * Returns the current root page id
     *
     * @return int
     */
    public function getRootPageUid(): int
    {
        return $this->rootPageUid;
    }

    /**
     * Returns the current language uid
     *
     * @return int
     */
    public function getLanguageUid(): int
    {
        return $this->languageUid;
    }

    /**
     * Is Solr enabled?
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        $enabled = null;
        // @TODO: SiteConfiguration by language needs implementation

        // From SiteConfiguration
        if ($enabled === null) {
            $enabled = $this->get('connection.read.enabled', null);
        }
        // From TypoScript
        if ($enabled === null) {
            $enabled = (bool)$this->get('enabled', true);
        }

        return $enabled;
    }

    /**
     * Check if the given path contains a true value
     *
     * @param string $path
     * @param bool $fallback
     * @return bool
     */
    public function isTrue(string $path, bool $fallback = false): bool
    {
        $value = $this->get($path);
        if ($value === null) {
            return $fallback;
        }

        return (bool)$value;
    }

    /**
     * Returns an integer by given configuration path.
     *
     * @param string $path
     * @param int $fallback
     * @return int
     */
    public function getInteger(string $path, int $fallback = 0): int
    {
        $value = $this->get($path);
        if ($value === null) {
            return $fallback;
        }

        return (int)$value;
    }
}
