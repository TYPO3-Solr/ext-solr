<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ExtractingQuery as NewExtractingQuery;

/**
 * Specialized query for content extraction using Solr Cell
 *
 * @deprecated since 8.0.0 will be removed in 9.0.0
 */
class ExtractingQuery extends NewExtractingQuery
{

    /**
     * Constructor
     *
     * @param string $file Absolute path to the file to extract content and meta data from.
     */
    public function __construct($file)
    {
        trigger_error('ApacheSolrForTypo3\Solr\ExtractingQuery is deprecated please create a ExtraingQuery using the QueryBuilder now. deprecated since 8.0.0 will be removed in 9.0.0', E_USER_DEPRECATED);
        parent::__construct('');
    }
}
