<?php
namespace ApacheSolrForTypo3\Solr\ContentObject;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;

/**
 * A content object (cObj) to turn comma separated strings into an array to be
 * used in a multi value field in a Solr document.
 *
 * Example usage:
 *
 * keywords = SOLR_MULTIVALUE # supports stdWrap
 * keywords {
 *   field = tags # a comma separated field. instead of field you can also use "value"
 *   separator = , # comma is the default value
 *   removeEmptyValues = 1 # a flag to remove empty strings from the list, on by default.
 *   removeDuplicateValues = 1 # a flag to remove duplicate strings from the list, off by default.
 * }
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Multivalue extends AbstractContentObject
{
    const CONTENT_OBJECT_NAME = 'SOLR_MULTIVALUE';

    /**
     * Executes the SOLR_MULTIVALUE content object.
     *
     * Turns a list of values into an array that can then be used to fill
     * multivalued fields in a Solr document. The array is returned in
     * serialized form as content objects are expected to return strings.
     *
     * @inheritDoc
     */
    public function render($conf = [])
    {
        $data = '';
        if (isset($conf['value'])) {
            $data = $conf['value'];
            unset($conf['value']);
        }

        if (!empty($conf)) {
            $data = $this->cObj->stdWrap($data, $conf);
        }

        if (!array_key_exists('separator', $conf)) {
            $conf['separator'] = ',';
        }

        $removeEmptyValues = true;
        if (isset($conf['removeEmptyValues']) && $conf['removeEmptyValues'] == 0) {
            $removeEmptyValues = false;
        }

        $listAsArray = GeneralUtility::trimExplode(
            $conf['separator'],
            $data,
            $removeEmptyValues
        );

        if (!empty($conf['removeDuplicateValues'])) {
            $listAsArray = array_unique($listAsArray);
        }

        return serialize($listAsArray);
    }
}
