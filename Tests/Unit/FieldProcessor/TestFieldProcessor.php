<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\FieldProcessor;

use ApacheSolrForTypo3\Solr\FieldProcessor\FieldProcessor;

class TestFieldProcessor implements FieldProcessor
{
    public function process(array $values): array
    {
        foreach ($values as $no => $value) {
            if ($value === 'foo') {
                $values[$no] = 'bar';
            }
        }

        return $values;
    }
}
