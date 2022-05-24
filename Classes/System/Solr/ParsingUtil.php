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

namespace ApacheSolrForTypo3\Solr\System\Solr;

use UnexpectedValueException;

/**
 * This class provides static helper functions that are helpful during the result parsing for solr.
 */
class ParsingUtil
{
    /**
     * This method is used to covert a array structure with json.nl=flat to have it as return with json.nl=map.
     *
     * @param array $options
     * @return array
     * @throws UnexpectedValueException
     */
    public static function getMapArrayFromFlatArray(array $options): array
    {
        $keyValueMap = [];
        $valueFromKeyNode = -1;
        foreach ($options as $key => $value) {
            $isKeyNode = (($key % 2) == 0);
            if ($isKeyNode) {
                $valueFromKeyNode = $value;
            } else {
                if ($valueFromKeyNode == -1) {
                    throw new UnexpectedValueException('No optionValue before count value');
                }
                //we have a countNode
                $keyValueMap[$valueFromKeyNode] = $value;
            }
        }

        return $keyValueMap;
    }
}
