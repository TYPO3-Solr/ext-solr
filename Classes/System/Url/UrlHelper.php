<?php
namespace ApacheSolrForTypo3\Solr\System\Url;

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

/**
 * Class UrlHelper
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\System\Url
 */
class UrlHelper {

    /**
     * @var string
     */
    protected $initialUrl;

    /**
     * @var array
     */
    protected $urlParts = [];

    /**
     * @var array
     */
    protected $queryParts = [];

    /**
     * @var bool
     */
    protected $wasParsed = false;

    /**
     * UrlHelper constructor.
     * @param string $url
     */
    public function __construct($url)
    {
        $this->initialUrl = $url;
        $this->parseInitialUrl();
    }

    /**
     * @return void
     */
    protected function parseInitialUrl()
    {
        if ($this->wasParsed) {
            return;
        }
        $parts = parse_url($this->initialUrl);
        if (!is_array($parts)) {
            throw new \InvalidArgumentException("Non parseable url passed to UrlHelper", 1498751529);
        }
        $this->urlParts = $parts;

        parse_str($this->urlParts['query'], $this->queryParts);

        $this->wasParsed = true;
    }

    /**
     * @param string $part
     * @param mixed $value
     */
    protected function setUrlPart($part, $value)
    {
        $this->urlParts[$part] = $value;
    }

    /**
     * @param $path
     * @return mixed
     */
    protected function getUrlPart($path)
    {
        return $this->urlParts[$path];
    }

    /**
     * @param string $host
     * @return UrlHelper
     */
    public function setHost(string $host)
    {
        $this->setUrlPart('host', $host);
        return $this;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->getUrlPart('host');
    }

    /**
     * @param string $scheme
     * @return UrlHelper
     */
    public function setScheme(string $scheme)
    {
        $this->setUrlPart('scheme', $scheme);
        return $this;
    }

    /**
     * @return string
     */
    public function getScheme(): string
    {
        return $this->getUrlPart('scheme');
    }

    /**
     * @param string $path
     * @return UrlHelper
     */
    public function setPath($path)
    {
        $this->setUrlPart('path', $path);
        return $this;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->getUrlPart('path');
    }

    /**
     * @param string $parameterName
     * @throws \InvalidArgumentException
     * @return UrlHelper
     */
    public function removeQueryParameter(string $parameterName): UrlHelper
    {
        unset($this->queryParts[$parameterName]);
        return $this;
    }

    /**
     * @param string $parameterName
     * @param mixed $value
     * @throws \InvalidArgumentException
     * @return UrlHelper
     */
    public function addQueryParameter(string $parameterName, $value): UrlHelper
    {
        $this->queryParts[$parameterName] = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        $this->urlParts['query'] = http_build_query($this->queryParts);
        return $this->unparseUrl();
    }

    /**
     * @return string
     */
    protected function unparseUrl(): string
    {
        $scheme   = isset($this->urlParts['scheme']) ? $this->urlParts['scheme'] . '://' : '';
        $host     = $this->urlParts['host'] ?? '';
        $port     = $this->urlParts['port'] ? ':' . $this->urlParts['port'] : '';
        $user     = $this->urlParts['user'] ?? '';
        $pass     = $this->urlParts['pass'] ?? '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = $this->urlParts['path'] ?? '';
        $query    = isset($this->urlParts['query']) && !empty($this->urlParts['query']) ? '?' . $this->urlParts['query'] : '';
        $fragment = isset($this->urlParts['fragment']) ? '#' . $this->urlParts['fragment'] : '';
        return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
    }
}