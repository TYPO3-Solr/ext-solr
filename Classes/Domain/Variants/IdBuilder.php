<?php

namespace ApacheSolrForTypo3\Solr\Domain\Variants;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017- Timo Hund <timo.hund@dkd.de>
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

/**
 * The variantId can be used to group documents by a variantId. This variantId is by default unique per system,
 * and has the following syntax:
 *
 * <SystemHash>/type/uid
 *
 * A file from one system will get the same variantId, which could be useful for de-duplication.
 * @author Timo Hund <timo.hund@dkd.de>
 */
class IdBuilder
{

    /**
     * This method is used to build a variantId.
     *
     * By default the variantId is used
     * @param string $type
     * @param integer $uid
     * @return string
     */
    public function buildFromTypeAndUid($type, $uid)
    {
        $systemHash = $this->getSystemHash();
        $variantId = $systemHash . '/' . $type . '/' . $uid;

        $variantId = $this->applyHook($variantId, $systemHash, $type, $uid);
        return $variantId;
    }

    /**
     * Applies configured postProcessing hooks to build a custom variantId.
     *
     * @param string $variantId
     * @param string $systemHash
     * @param string $type
     * @param integer $uid
     * @return string
     */
    protected function applyHook($variantId, $systemHash, $type, $uid)
    {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyVariantId'])) {
            return $variantId;
        }

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyVariantId'] as $classReference) {
            $variantIdModifier = GeneralUtility::makeInstance($classReference);
            if ($variantIdModifier instanceof IdModifier) {
                $variantId = $variantIdModifier->modifyVariantId($variantId, $systemHash, $type, $uid);
            }
        }

        return $variantId;
    }

    /**
     * Returns a system unique hash.
     *
     * @return string
     */
    protected function getSystemHash()
    {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'])) {
            throw new \InvalidArgumentException("No sitename set in TYPO3_CONF_VARS|SYS|sitename");
        }

        $siteName = $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'];
        $systemKey = 'tx_solr' . $siteName;
        return GeneralUtility::hmac($systemKey);
    }
}
