<?php
namespace ApacheSolrForTypo3\Solr\System\Solr\Document;

use RuntimeException;
use Solarium\QueryType\Update\Query\Document\Document as SolariumDocument;

class Document extends SolariumDocument {

    /**
     * Magic call method used to emulate getters as used by the template engine.
     *
     * @param    string $name method name
     * @param    array $arguments method arguments
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