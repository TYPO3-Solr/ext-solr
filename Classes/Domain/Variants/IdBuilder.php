<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Domain\Variants;

use InvalidArgumentException;
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
     * By default, the variantId is used
     * @param string $type
     * @param int $uid
     * @return string
     */
    public function buildFromTypeAndUid(string $type, int $uid): string
    {
        $systemHash = $this->getSystemHash();
        $variantId = $systemHash . '/' . $type . '/' . $uid;

        return $this->applyHook($variantId, $systemHash, $type, $uid);
    }

    /**
     * Applies configured postProcessing hooks to build a custom variantId.
     *
     * @param string $variantId
     * @param string $systemHash
     * @param string $type
     * @param int $uid
     * @return string
     */
    protected function applyHook(
        string $variantId,
        string $systemHash,
        string $type,
        int $uid
    ): string {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyVariantId'] ?? null)) {
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
    protected function getSystemHash(): string
    {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'])) {
            throw new InvalidArgumentException('No sitename set in TYPO3_CONF_VARS|SYS|sitename');
        }

        $siteName = $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'];
        $systemKey = 'tx_solr' . $siteName;
        return GeneralUtility::hmac($systemKey);
    }
}
