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

use TYPO3\CMS\Core\Http\Uri;

/**
 * Class UrlHelper
 */
class UrlHelper extends Uri
{
    /**
     * Remove a given parameter from the query and create a new instance.
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
     * Add a given parameter with value to the query and create a new instance.
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
}
