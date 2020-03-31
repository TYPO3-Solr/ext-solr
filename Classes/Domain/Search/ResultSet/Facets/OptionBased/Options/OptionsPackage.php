<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetPackage;

/**
 * Class OptionsPackage
 */
class OptionsPackage extends AbstractFacetPackage {

    /**
     * @return string
     */
    public function getParserClassName() {
        return (string)OptionsFacetParser::class;
    }

    /**
     * @return string
     */
    public function getQueryBuilderClassName() {
        return (string)OptionsFacetQueryBuilder::class;
    }
}
