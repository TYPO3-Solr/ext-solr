<?php
namespace ApacheSolrForTypo3\Solr\System\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2018 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Represent a server node of solr, in the most setups you would only have one, but sometimes
 * mulitple for reading and writing.
 */
class Node {

    /**
     * @var string
     */
    protected $scheme;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var int
     */
    protected $timeout;

    /**
     * Node constructor.
     * @param string $scheme
     * @param string $host
     * @param int $port
     * @param string $path
     * @param string $username
     * @param string $password
     * @param int $timeout
     */
    public function __construct(string $scheme = 'http', string $host = 'localhost', int $port = 8983, string $path = '/solr/core_en/', string $username, string $password, int $timeout = 0)
    {
        $this->scheme = $scheme;
        $this->host = $host;
        $this->port = $port;
        $this->path = $path;
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
    }

    /**
     * @return string
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @param $configuration
     * @return Node
     */
    public static function fromArray($configuration)
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
        $timeout = $configuration['timeout'] ?? 0;
        return new Node($scheme, $host, $port, $path, $username, $password, $timeout);
    }

    /**
     * Checks if the required configuration option is set.
     *
     * @param array  $configuration
     * @param string $name
     * @throws |UnexpectedValueException
     */
    protected static function checkIfRequiredKeyIsSet(array $configuration, $name)
    {
        if (empty($configuration[$name])) {
            throw new \UnexpectedValueException('Required solr connection property ' . $name. ' is missing.');
        }
    }

    /**
     * Returns the core name from the configured path without the core name.
     *
     * @return string
     */
    public function getCoreBasePath()
    {
        $pathWithoutLeadingAndTrailingSlashes = trim(trim($this->path), "/");
        $pathWithoutLastSegment = substr($pathWithoutLeadingAndTrailingSlashes, 0, strrpos($pathWithoutLeadingAndTrailingSlashes, "/"));
        return ($pathWithoutLastSegment === '') ? '/' : '/' . $pathWithoutLastSegment . '/';
    }

    /**
     * Returns the core name from the configured path.
     *
     * @return string
     */
    public function getCoreName()
    {
        $paths = explode('/', trim($this->path, '/'));
        return (string)array_pop($paths);
    }

    /**
     * @return array
     */
    public function getSolariumClientOptions()
    {
        return [
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'scheme' => $this->getScheme(),
            'path' => $this->getCoreBasePath(),
            'core' => $this->getCoreName(),
            'timeout' => $this->getTimeout()
        ];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getScheme() . '://' . $this->getHost() . ':' . $this->getPort() . $this->getCoreBasePath() . $this->getCoreName() . '/';
    }
}
