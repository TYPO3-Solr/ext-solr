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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\FieldProcessor;

use ApacheSolrForTypo3\Solr\FieldProcessor\PathToHierarchy;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * tests the path to hierarchy processing
 *
 * @author    Daniel Pötzinger <poetzinger@aoemedia.de>
 */
class PathToHierarchyTest extends UnitTest
{

    /**
     * @var PathToHierarchy
     */
    protected $processor;

    protected function setUp(): void
    {
        $this->processor = new PathToHierarchy();
        parent::setUp();
    }

    /**
     * @test
     */
    public function canBuildSolrHierarchyString()
    {
        self::assertEquals(
            $this->processor->process(['sport/cricket']),
            ['0-sport/', '1-sport/cricket/']
        );
        self::assertEquals(
            $this->processor->process(['sport/skateboarding']),
            ['0-sport/', '1-sport/skateboarding/']
        );

        self::assertEquals(
            $this->processor->process(['sport/skateboarding \/ snowboarding']),
            ['0-sport/', '1-sport/skateboarding \/ snowboarding/']
        );

        self::assertEquals(
            $this->processor->process(['sport/skateboarding/street']),
            [
                '0-sport/',
                '1-sport/skateboarding/',
                '2-sport/skateboarding/street/',
            ]
        );
        self::assertEquals(
            $this->processor->process(['/sport/skateboarding/street//']),
            [
                '0-sport/',
                '1-sport/skateboarding/',
                '2-sport/skateboarding/street/',
            ]
        );
    }
}
