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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\DefaultUrlDecoder;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Class DefaultUrlEncoderTest
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DefaultUrlDecoderTest extends UnitTest
{

    /**
     * @test
     */
    public function canDecode()
    {
        $value = 'a + b';
        $encoder = new DefaultUrlDecoder();
        $encodedValue = $encoder->decode($value);
        self::assertSame('"a + b"', $encodedValue, 'Encode and decode does not produce initial value');
    }
}
