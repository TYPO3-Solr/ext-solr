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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\AbstractQueryBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * The BigramPhraseFields class
 */
class BigramPhraseFields extends AbstractFieldList implements ParameterBuilder
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

    /**
     * @param TypoScriptConfiguration $solrConfiguration
     * @return BigramPhraseFields
     */
    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration)
    {
        $isEnabled = $solrConfiguration->getBigramPhraseSearchIsEnabled();
        if (!$isEnabled) {
            return new BigramPhraseFields(false);
        }

        return self::fromString((string)$solrConfiguration->getSearchQueryBigramPhraseFields());
    }

    /**
     * Parses the string representation of the fieldList (e.g. content^100, title^10) to the object representation.
     *
     * @param string $fieldListString
     * @param string $delimiter
     * @return BigramPhraseFields
     */
    protected static function initializeFromString(string $fieldListString, string $delimiter = ',') : BigramPhraseFields
    {
        $fieldList = self::buildFieldList($fieldListString, $delimiter);
        return new BigramPhraseFields(true, $fieldList);
    }

    /**
     * @param AbstractQueryBuilder $parentBuilder
     * @return AbstractQueryBuilder
     */
    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $bigramPhraseFieldsString = $this->toString();
        if ($bigramPhraseFieldsString === '' || !$this->getIsEnabled()) {
            return $parentBuilder;
        }

        $parentBuilder->getQuery()->getEDisMax()->setPhraseBigramFields($bigramPhraseFieldsString);
        return $parentBuilder;
    }
}
