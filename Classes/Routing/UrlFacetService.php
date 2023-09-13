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

namespace ApacheSolrForTypo3\Solr\Routing;

/**
 * Service class to wrap functions to handle facet values within the URL
 */
class UrlFacetService
{
    /**
     * Location within the URL.
     * Possible types are:
     *  - path
     *  - query
     *
     * These are connected to the configuration
     */
    protected string $uriLocation = 'query';

    /**
     * Mapping of characters
     */
    protected array $characterMap = [];

    /**
     * Character to separate multi values
     */
    protected string $multiValueSeparator = ',';

    /**
     * Character to replace multi value separator a value contains it.
     */
    protected string $multiValueEscapeCharacter = '°';

    public function __construct(string $uriLocation, array $settings = [])
    {
        $uriLocation = strtolower($uriLocation);
        if (in_array($uriLocation, ['query', 'path'])) {
            $this->uriLocation = $uriLocation;
        }

        $this->init($settings);
    }

    /**
     * Initialize settings
     */
    protected function init(array $settings): void
    {
        if (is_array($settings['facet-' . $this->uriLocation]['replaceCharacters'] ?? null)) {
            $this->characterMap = $settings['facet-' . $this->uriLocation]['replaceCharacters'];
        }
        if (is_array($settings['replaceCharacters'] ?? null)) {
            $this->characterMap = $settings['replaceCharacters'];
        }

        if (isset($settings['facet-' . $this->uriLocation]['multiValueSeparator'])) {
            $this->multiValueSeparator = (string)$settings['facet-' . $this->uriLocation]['multiValueSeparator'];
        }

        // Old configuration style
        if (isset($settings[$this->uriLocation]['multiValueSeparator'])) {
            $this->multiValueSeparator = (string)$settings[$this->uriLocation]['multiValueSeparator'];
        }

        if (isset($settings['facet-' . $this->uriLocation]['multiValueEscapeCharacter'])) {
            $this->multiValueEscapeCharacter = (string)$settings['facet-' . $this->uriLocation]['multiValueEscapeCharacter'];
        }

        // Old configuration style
        if (isset($settings[$this->uriLocation]['multiValueEscapeCharacter'])) {
            $this->multiValueEscapeCharacter = (string)$settings[$this->uriLocation]['multiValueEscapeCharacter'];
        }
    }

    public function getUriLocation(): string
    {
        return $this->uriLocation;
    }

    public function getCharacterMap(): array
    {
        return $this->characterMap;
    }

    public function getMultiValueSeparator(): string
    {
        return $this->multiValueSeparator;
    }

    public function getMultiValueEscapeCharacter(): string
    {
        return $this->multiValueEscapeCharacter;
    }

    /**
     * Encodes a single value in case it contains the multi value separator
     *
     * @see RoutingService::finalizePathQuery
     */
    public function encodeSingleValue(string $value): string
    {
        if (mb_strpos($value, $this->multiValueSeparator) !== false) {
            $value = str_replace($this->multiValueSeparator, $this->multiValueEscapeCharacter, $value);
        } elseif (mb_strpos($value, urlencode($this->multiValueSeparator)) !== false) {
            $value = str_replace(
                urlencode($this->multiValueSeparator),
                urlencode($this->multiValueEscapeCharacter),
                $value
            );
        }

        return (string)$value;
    }

    /**
     * Decodes a single value in case it contains the multi value separator
     *
     * @see RoutingService::inflateQueryParameter
     */
    public function decodeSingleValue(string $value): string
    {
        if (mb_strpos($value, $this->multiValueEscapeCharacter) !== false) {
            $value = str_replace($this->multiValueEscapeCharacter, $this->multiValueSeparator, $value);
        } elseif (mb_strpos($value, urlencode($this->multiValueEscapeCharacter)) !== false) {
            $value = str_replace(
                urlencode($this->multiValueEscapeCharacter),
                urlencode($this->multiValueSeparator),
                $value
            );
        }

        return (string)$value;
    }

    /**
     * Encode a string for path segment
     */
    public function applyCharacterMap(string $string): string
    {
        $string = urldecode($string);

        if (!empty($this->characterMap)) {
            foreach ($this->characterMap as $search => $replace) {
                $string = str_replace($replace, $search, $string);
            }
        }

        return $string;
    }
}
