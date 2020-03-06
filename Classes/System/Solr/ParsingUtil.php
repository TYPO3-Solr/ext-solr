<?php
namespace ApacheSolrForTypo3\Solr\System\Solr;

/**
 * This class provides static helper functions that are helpful during the result parsing for solr.
 */
class ParsingUtil
{
    /**
     * This method is used to covert a array structure with json.nl=flat to have it as return with json.nl=map.
     *
     * @param $options
     * @return array
     */
    public static function getMapArrayFromFlatArray(array $options): array
    {
        $keyValueMap = [];
        $valueFromKeyNode = -1;
        foreach($options as $key => $value) {
            $isKeyNode = (($key % 2) == 0);
            if ($isKeyNode) {
                $valueFromKeyNode = $value;
            } else {
                if($valueFromKeyNode == -1) {
                    throw new \UnexpectedValueException('No optionValue before count value');
                }
                //we have a countNode
                $keyValueMap[$valueFromKeyNode] = $value;
            }
        }

        return $keyValueMap;
    }
}
