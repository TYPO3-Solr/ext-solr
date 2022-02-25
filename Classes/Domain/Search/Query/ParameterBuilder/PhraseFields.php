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
 * The PhraseFields class
 */
class PhraseFields extends AbstractFieldList implements ParameterBuilderInterface
{
    /**
     * Parses the string representation of the fieldList (e.g. content^100, title^10) to the object representation.
     *
     * @param string $fieldListString
     * @param string $delimiter
     * @return PhraseFields
     */
    public static function fromString(string $fieldListString, string $delimiter = ','): PhraseFields
    {
        return self::initializeFromString($fieldListString, $delimiter);
    }

    /**
     * @param TypoScriptConfiguration $solrConfiguration
     * @return PhraseFields
     */
    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration): PhraseFields
    {
        $isEnabled = $solrConfiguration->getPhraseSearchIsEnabled();
        if (!$isEnabled) {
            return new PhraseFields(false);
        }

        return self::fromString($solrConfiguration->getSearchQueryPhraseFields());
    }

    /**
     * Parses the string representation of the fieldList (e.g. content^100, title^10) to the object representation.
     *
     * @param string $fieldListString
     * @param string $delimiter
     * @return PhraseFields
     */
    protected static function initializeFromString(string $fieldListString, string $delimiter = ','): PhraseFields
    {
        $fieldList = self::buildFieldList($fieldListString, $delimiter);
        return new PhraseFields(true, $fieldList);
    }

    /**
     * @param AbstractQueryBuilder $parentBuilder
     * @return AbstractQueryBuilder
     */
    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $phraseFieldString = $this->toString();
        if ($phraseFieldString === '' || !$this->getIsEnabled()) {
            return $parentBuilder;
        }

        $parentBuilder->getQuery()->getEDisMax()->setPhraseFields($phraseFieldString);
        return $parentBuilder;
    }
}
