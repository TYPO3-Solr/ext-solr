<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * The BigramPhraseFields class
 */
class BigramPhraseFields extends AbstractFieldList
{
    /**
     * Parameter key which should be used for Apache Solr URL query
     *
     * @var string
     */
    protected $parameterKey = 'pf2';

    /**
     * Parses the string representation of the fieldList (e.g. content^100, title^10) to the object representation.
     *
     * @param string $fieldListString
     * @param string $delimiter
     * @return BigramPhraseFields
     */
    public static function fromString(string $fieldListString, string $delimiter = ',') : BigramPhraseFields
    {
        return self::initializeFromString($fieldListString, $delimiter);
    }
}
