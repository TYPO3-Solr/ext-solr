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
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the Path helper class.
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class PathTest extends SetUpUnitTestCase
{
    #[Test]
    public function canIsValidSolrPathisValidPath(): void
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidSolrPath('/sorl/core_da');

        self::assertTrue($isValidPath);
    }

    #[Test]
    public function canIsValidSolrPathEmptyString(): void
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidSolrPath('');

        self::assertFalse($isValidPath);
    }

    #[Test]
    public function canIsValidSolrPathisInvalidPathButAppears(): void
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidSolrPath('/sorl/#/core_da');

        self::assertFalse($isValidPath);
    }

    #[Test]
    public function canIsValidSolrPathisInvalidPath(): void
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidSolrPath('/sorl/core_da?bogus');

        self::assertFalse($isValidPath);
    }
}
