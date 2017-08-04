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
class UrlHelper
{

    /**
     * @var string
     */
    protected $initialUrl;

    /**
     * @var array
     */
    protected $parts = [];

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
            throw new \InvalidArgumentException('Non parseable url passed to UrlHelper', 1498751529);
        }
        $this->parts = $parts;
        $this->wasParsed = true;
    }

    /**
     * @param string $parameterName
     * @throws \InvalidArgumentException
     * @return UrlHelper
     */
    public function removeQueryParameter(string $parameterName): UrlHelper
    {
        $this->parseInitialUrl();
        $queryParts = [];
        parse_str($this->parts['query'], $queryParts);
        unset($queryParts[$parameterName]);
        $this->parts['query'] = http_build_query($queryParts);

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
        $this->parseInitialUrl();
        $queryParts = [];
        parse_str($this->parts['query'], $queryParts);
        $queryParts[$parameterName] = $value;
        $this->parts['query'] = http_build_query($queryParts);

        return $this;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->unparse_url($this->parts);
    }

    /**
     * @param array $parsed_url
     * @return string
     */
    protected function unparse_url(array $parsed_url): string
    {
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass'] : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
    }
}
