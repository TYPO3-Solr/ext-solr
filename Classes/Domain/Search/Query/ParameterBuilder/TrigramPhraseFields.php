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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\AbstractQueryBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * The TrigramPhraseFields class
 */
class TrigramPhraseFields extends AbstractFieldList implements ParameterBuilder
{
    /**
     * Parameter key which should be used for Apache Solr URL query
     *
     * @var string
     */
    protected $parameterKey = 'pf3';

    /**
     * Parses the string representation of the fieldList (e.g. content^100, title^10) to the object representation.
     *
     * @param string $fieldListString
     * @param string $delimiter
     * @return TrigramPhraseFields
     */
    public static function fromString(string $fieldListString, string $delimiter = ',') : TrigramPhraseFields
    {
        return self::initializeFromString($fieldListString, $delimiter);
    }

    /**
     * @param TypoScriptConfiguration $solrConfiguration
     * @return TrigramPhraseFields
     */
    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration)
    {
        $isEnabled = $solrConfiguration->getTrigramPhraseSearchIsEnabled();
        if (!$isEnabled) {
            return new TrigramPhraseFields(false);
        }

        return self::fromString((string)$solrConfiguration->getSearchQueryTrigramPhraseFields());
    }

    /**
     * Parses the string representation of the fieldList (e.g. content^100, title^10) to the object representation.
     *
     * @param string $fieldListString
     * @param string $delimiter
     * @return TrigramPhraseFields
     */
    protected static function initializeFromString(string $fieldListString, string $delimiter = ',') : TrigramPhraseFields
    {
        $fieldList = self::buildFieldList($fieldListString, $delimiter);
        return new TrigramPhraseFields(true, $fieldList);
    }

    /**
     * @param AbstractQueryBuilder $parentBuilder
     * @return AbstractQueryBuilder
     */
    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $trigramPhraseFieldsString = $this->toString();
        if ($trigramPhraseFieldsString === '' || !$this->getIsEnabled()) {
            return $parentBuilder;
        }
        $parentBuilder->getQuery()->getEDisMax()->setPhraseTrigramFields($trigramPhraseFieldsString);
        return $parentBuilder;
    }
}
