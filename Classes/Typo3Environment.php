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
 *  the Free Software Foundation; either version 2 of the License, or
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

use TYPO3\CMS\Core\SingletonInterface;

/**
 * TYPO3 Environment Information
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 * @deprecated Class was used by EXT:solrfile only, which can't be used since EXT:solr 3.1.0, whole class will be removed in version 6.0
 */
class Typo3Environment implements SingletonInterface
{

    /**
     * Checks whether file indexing is enabled.
     *
     * @deprecated Setting plugin.tx_solr.index.files was used by EXT:solrfile only, which can't be used since EXT:solr 3.1.0, will be removed in version 6.0
     * @return boolean TRUE if file indexing is enabled, FALSE otherwise.
     */
    public function isFileIndexingEnabled()
    {
        GeneralUtility::logDeprecatedFunction();

        $configuration = Util::getSolrConfiguration();
        return (boolean) $configuration->getValueByPathOrDefaultValue('plugin.tx_solr.index.files', 0);
    }
}
