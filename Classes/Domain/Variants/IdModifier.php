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

namespace ApacheSolrForTypo3\Solr\Domain\Variants;

/**
 * An implementation of this class can be used to modify the variantId.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
interface IdModifier
{

    /**
     * @param string $variantId
     * @param string $systemHash
     * @param string $type
     * @param integer $uid
     * @return string
     */
    public function modifyVariantId($variantId, $systemHash, $type, $uid);
}
