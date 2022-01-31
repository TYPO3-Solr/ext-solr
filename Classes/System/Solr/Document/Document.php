<?php
namespace ApacheSolrForTypo3\Solr\System\Solr\Document;

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

use RuntimeException;
use Solarium\QueryType\Update\Query\Document as SolariumDocument;

/**
 * Document representing the update query document
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class Document extends SolariumDocument
{
    /**
     * Magic call method used to emulate getters as used by the template engine.
     *
     * @param  string $name method name
     * @param  array $arguments method arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (substr($name, 0, 3) == 'get') {
            $field = substr($name, 3);
            $field = strtolower($field[0]) . substr($field, 1);
            return $this->fields[$field] ?? null;
        } else {
            throw new RuntimeException('Call to undefined method. Supports magic getters only.', 1311006605);
        }
    }

    /**
     * @return array
     */
    public function getFieldNames()
    {
        return array_keys($this->fields);
    }
}
