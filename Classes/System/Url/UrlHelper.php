<?php

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

namespace ApacheSolrForTypo3\Solr\System\Url;

use InvalidArgumentException;
use TYPO3\CMS\Core\Http\Uri;

/**
 * Class UrlHelper
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class UrlHelper extends Uri
{
    /**
     * @param string $host
     * @return UrlHelper
     * @deprecated Will be removed with v12. Use withHost instead.
     * @see Uri::withHost()
     */
    public function setHost(string $host): UrlHelper
    {
        $this->host = $host;
        return $this;
    }

    /**
     * @param int $port
     * @return UrlHelper
     * @deprecated Will be removed with v12. Use withPort instead.
     * @see Uri::withPort()
     */
    public function setPort(int $port): UrlHelper
    {
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Invalid port "' . $port . '" specified, must be a valid TCP/UDP port.', 1436717326);
        }

        $this->port = $port;
        return $this;
    }

    /**
     * @param string $scheme
     * @return UrlHelper
     * @deprecated Will be removed with v12. Use Uri::withScheme instead.
     * @see Uri::withScheme()
     */
    public function setScheme(string $scheme): UrlHelper
    {
        $this->scheme = $this->sanitizeScheme($scheme);
        return $this;
    }

    /**
     * @param string $path
     * @return UrlHelper
     * @deprecated Will be removed with v12. Use withPath instead.
     * @see Uri::withPath()
     */
    public function setPath(string $path): UrlHelper
    {
        if (str_contains($path, '?')) {
            throw new InvalidArgumentException('Invalid path provided. Must not contain a query string.', 1436717330);
        }

        if (str_contains($path, '#')) {
            throw new InvalidArgumentException('Invalid path provided; must not contain a URI fragment', 1436717332);
        }
        $this->path = $this->sanitizePath($path);
        return $this;
    }

    /**
     * Remove a given parameter from the query and create a new instance.
     *
     * @param string $parameterName
     * @return UrlHelper
     */
    public function withoutQueryParameter(string $parameterName): UrlHelper
    {
        parse_str($this->query, $parameters);
        if (isset($parameters[$parameterName])) {
            unset($parameters[$parameterName]);
        }
        $query = '';
        if (!empty($parameters)) {
            $query = http_build_query($parameters);
        }
        $query = $this->sanitizeQuery($query);
        $clonedObject = clone $this;
        $clonedObject->query = $query;
        return $clonedObject;
    }

    /**
     * @param string $parameterName
     * @throws InvalidArgumentException
     * @return UrlHelper
     * @deprecated Will be removed with v12. Use {@link withoutQueryParameter()} instead.
     */
    public function removeQueryParameter(string $parameterName): UrlHelper
    {
        parse_str($this->query, $parameters);
        if (isset($parameters[$parameterName])) {
            unset($parameters[$parameterName]);
        }
        $query = '';
        if (!empty($parameters)) {
            $query = http_build_query($parameters);
        }
        $this->query = $this->sanitizeQuery($query);

        return $this;
    }

    /**
     * Add a given parameter with value to the query and create a new instance.
     *
     * @param string $parameterName
     * @param mixed $value
     * @return UrlHelper
     */
    public function withQueryParameter(string $parameterName, $value): UrlHelper
    {
        parse_str($this->query, $parameters);
        $parameters[$parameterName] = $value;
        $query = '';
        if (!empty($parameters)) {
            $query = http_build_query($parameters);
        }
        $query = $this->sanitizeQuery($query);
        $clonedObject = clone $this;
        $clonedObject->query = $query;
        return $clonedObject;
    }

    /**
     * @param string $parameterName
     * @param mixed $value
     * @throws InvalidArgumentException
     * @return UrlHelper
     * @deprecated Will be removed with v12. Use {@link withQueryParameter()} instead.
     */
    public function addQueryParameter(string $parameterName, $value): UrlHelper
    {
        $parameters = $this->query;
        parse_str($this->query, $parameters);
        if (empty($parameters)) {
            $parameters = [];
        }
        $parameters[$parameterName] = $value;
        $query = '';
        if (!empty($parameters)) {
            $query = http_build_query($parameters);
        }
        $this->query = $this->sanitizeQuery($query);
        return $this;
    }

    /**
     * @return string
     * @deprecated Will be removed with v12. Use {@link __toString()} instead.
     */
    public function getUrl(): string
    {
        return $this->__toString();
    }
}
