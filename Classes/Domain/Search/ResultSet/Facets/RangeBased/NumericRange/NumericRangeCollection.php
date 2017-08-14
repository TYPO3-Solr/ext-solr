<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange;

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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetItemCollection;

/**
 * Collection for facet options.
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class NumericRangeCollection extends AbstractFacetItemCollection
{

    /**
     * @param NumericRange $numericRange
     * @return NumericRangeCollection
     */
    public function add($numericRange)
    {
        return parent::add($numericRange);
    }

    /**
     * @param int $position
     * @return NumericRange
     */
    public function getByPosition($position)
    {
        return parent::getByPosition($position);
    }
}
