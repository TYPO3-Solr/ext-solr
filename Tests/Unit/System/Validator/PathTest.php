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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Validator;

use ApacheSolrForTypo3\Solr\System\Validator\Path;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the Path helper class.
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class PathTest extends UnitTest
{
    /**
     * @test
     */
    public function canIsValidSolrPathisValidPath()
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidSolrPath('/sorl/core_da');

        self::assertTrue($isValidPath);
    }

    /**
     * @test
     */
    public function canIsValidSolrPathEmptyString()
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidSolrPath('');

        self::assertFalse($isValidPath);
    }

    /**
     * @test
     */
    public function canIsValidSolrPathisInvalidPathButAppears()
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidSolrPath('/sorl/#/core_da');

        self::assertFalse($isValidPath);
    }

    /**
     * @test
     */
    public function canIsValidSolrPathisInvalidPath()
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidSolrPath('/sorl/core_da?bogus');

        self::assertFalse($isValidPath);
    }
}
