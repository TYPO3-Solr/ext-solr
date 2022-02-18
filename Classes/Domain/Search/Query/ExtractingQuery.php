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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query;

use Solarium\QueryType\Extract\Query as SolariumExtractQuery;

/**
 * Specialized query for content extraction using Solr Cell
 *
 */
class ExtractingQuery extends SolariumExtractQuery
{
    /**
     * Constructor
     *
     * @param string $file Absolute path to the file to extract content and meta data from.
     */
    public function __construct($file)
    {
        parent::__construct();
        $this->setFile($file);
        $this->addParam('extractFormat', 'text');
    }

}
