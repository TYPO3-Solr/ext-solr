<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Validator;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017- Thomas Hohn <tho@systime.dk>
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

        $this->assertTrue($isValidPath);
    }

    /**
     * @test
     */
    public function canIsValidSolrPathEmptyString()
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidSolrPath('');

        $this->assertFalse($isValidPath);
    }

    /**
     * @test
     */
    public function canIsValidSolrPathisInvalidPathButAppears()
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidSolrPath('/sorl/#/core_da');

        $this->assertFalse($isValidPath);
    }

    /**
     * @test
     */
    public function canIsValidSolrPathisInvalidPath()
    {
        $path = GeneralUtility::makeInstance(Path::class);
        $isValidPath = $path->isValidSolrPath('/sorl/core_da?bogus');

        $this->assertFalse($isValidPath);
    }

}
