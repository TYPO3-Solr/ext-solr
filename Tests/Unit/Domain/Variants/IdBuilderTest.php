<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Variants;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 Timo Hund <timo.hund@dkd.de>
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
     * @var string
     */
    protected $oldEncryptionKey;

    public function setUp() {
        $this->oldEncryptionKey = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'testkey';
        parent::setUp();
    }

    public function tearDown() {
        parent::tearDown();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] =  $this->oldEncryptionKey;
    }

    /**
     * @test
     */
    public function canBuildVariantId()
    {
        $build = new IdBuilder();
        $variantId = $build->buildFromTypeAndUid('pages', 4711);
        $this->assertSame('e99b3552a0451f1a2e7aca4ac06ccaba063393de/pages/4711', $variantId);
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
        $this->assertSame('mycustomid', $variantId);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyVariantId'] = [];
    }
}