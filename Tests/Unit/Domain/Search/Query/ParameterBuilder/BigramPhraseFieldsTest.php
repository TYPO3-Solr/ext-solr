<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Query\ParameterBuilder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\BigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class BigramPhraseFieldsTest extends UnitTest
{
    /**
     * @test
     */
    public function buildFromEmptyStringCreatesEmptyArrayOnBuild()
    {
        $bigramPhraseFields = BigramPhraseFields::fromString('');

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $query = new Query('foo', $configurationMock);

        $bigramPhraseFields->build($query);

        $this->assertEmpty($query->getQueryParameters()['pf2'], 'Build on Phrase field does not create empty array when calling build');
    }
}