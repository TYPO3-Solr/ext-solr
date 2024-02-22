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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Variants;

use ApacheSolrForTypo3\Solr\Domain\Variants\IdBuilder;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase to check if the IdBuilder can be used to build proper variantIds.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class IdBuilderTest extends UnitTest
{
    /**
     * @test
     */
    public function canBuildVariantId()
    {
        $build = new IdBuilder();
        $variantId = $build->buildFromTypeAndUid('pages', 4711);
        self::assertSame('c523304ea47711019595d2bb352b623d1db40427/pages/4711', $variantId);
    }

    /**
     * @test
     */
    public function canRegisterCustomHook()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyVariantId']['test'] = CustomIdModifier::class;

        $build = new IdBuilder();
        $variantId = $build->buildFromTypeAndUid('pages', 4711);

        // the variantId should be overwritten by the custom modifier
        self::assertSame('mycustomid', $variantId);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyVariantId'] = [];
    }
}
