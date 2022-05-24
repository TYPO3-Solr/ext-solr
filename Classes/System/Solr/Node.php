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

namespace ApacheSolrForTypo3\Solr\System\Solr;

use Solarium\Core\Client\Endpoint;
use UnexpectedValueException;

/**
 * Represent a server node of solr, in the most setups you would only have one, but sometimes
 * multiple for reading and writing.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @copyright Copyright (c) 2009-2020 Timo Hund <timo.hund@dkd.de>
 *
 * @deprecated Class will be removed with Ext:solr 12.x. Use class \Solarium\Core\Client\Endpoint instead.
 */
class Node extends Endpoint
{
    /**
     * Node constructor.
     * @param string $scheme
     * @param string $host
     * @param int $port
     * @param string $path
     * @param ?string $username
     * @param ?string $password
     */
    public function __construct(
        string $scheme = 'http',
        string $host = 'localhost',
        int $port = 8983,
        string $path = '/solr/core_en/',
        ?string $username = null,
        ?string $password = null
    ) {
        $path = (string)$path;
        $elements = explode('/', trim($path, '/'));
        $coreName = (string)array_pop($elements);
        // Remove API version
        array_pop($elements);

        // The path should always have the same format!
        $path = trim(implode('/', $elements), '/');

        $options = [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'path' => '/' . $path,
            'collection' => null,
            'core' => $coreName,
            'leader' => false,
        ];

        parent::__construct($options);
        $this->setAuthentication($username, $password);
    }

    /**
     * @param array $configuration
     * @return Node
     */
    public static function fromArray(array $configuration): Node
    {
        static::checkIfRequiredKeyIsSet($configuration, 'scheme');
        static::checkIfRequiredKeyIsSet($configuration, 'host');
        static::checkIfRequiredKeyIsSet($configuration, 'port');
        static::checkIfRequiredKeyIsSet($configuration, 'path');

        $scheme = $configuration['scheme'];
        $host = $configuration['host'];
        $port = $configuration['port'];
        $path = $configuration['path'];

        $username = $configuration['username'] ?? '';
        $password = $configuration['password'] ?? '';
        return new Node($scheme, $host, $port, $path, $username, $password);
    }

    /**
     * Checks if the required configuration option is set.
     *
     * @param array  $configuration
     * @param string $name
     * @throws UnexpectedValueException
     */
    protected static function checkIfRequiredKeyIsSet(array $configuration, string $name)
    {
        if (empty($configuration[$name])) {
            throw new UnexpectedValueException('Required solr connection property ' . $name . ' is missing.');
        }
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return (string)$this->getOption('username');
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return (string)$this->getOption('password');
    }

    /**
     * Returns the path including api path.
     *
     * @return string
     */
    public function getCoreBasePath(): string
    {
        $pathWithoutLeadingAndTrailingSlashes = trim(trim($this->getPath()), '/');
        $pathWithoutLastSegment = substr($pathWithoutLeadingAndTrailingSlashes, 0, strrpos($pathWithoutLeadingAndTrailingSlashes, '/'));
        return ($pathWithoutLastSegment === '') ? '/' : '/' . $pathWithoutLastSegment . '/';
    }

    /**
     * Returns the core name from the configured path.
     *
     * @return string
     * @deprecated Will be removed with Ext:solr 12.x. Use method getCore() instead.
     */
    public function getCoreName(): string
    {
        return $this->getCore();
    }

    /**
     * @return array
     */
    public function getSolariumClientOptions(): array
    {
        return [
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'scheme' => $this->getScheme(),
            'path' => $this->getPath(),
            'core' => $this->getCore(),
        ];
    }

    /**
     * @return string
     * @deprecated Will be removed with Ext:solr 12.x. Use methods getCoreBaseUri() for API version 1 instead
     */
    public function __toString(): string
    {
        return $this->getCoreBaseUri();
    }
}
