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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Variants;

use ApacheSolrForTypo3\Solr\Domain\Variants\IdModifier;

class CustomIdModifier implements IdModifier
{
    /**
     * @param string $variantId
     * @param string $systemHash
     * @param string $type
     * @param int $uid
     * @return string
     */
    public function modifyVariantId(string $variantId, string $systemHash, string $type, int $uid): string
    {
        return 'mycustomid';
    }
}
