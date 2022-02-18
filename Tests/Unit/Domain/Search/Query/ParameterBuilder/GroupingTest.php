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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Query\ParameterBuilder;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Grouping;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class GroupingTest extends UnitTest
{
    /**
     * @test
     */
    public function canBuildSortingFromConfiguration()
    {
        $typoScriptConfiguration = new TypoScriptConfiguration(
            [
                'plugin.' => [
                    'tx_solr.' => [
                        'search.' => [
                            'grouping' => 1,
                            'grouping.' => [
                                'sortBy' => 'title desc',
                            ],
                        ],
                    ],
                ],
            ]
        );
        $grouping = Grouping::fromTypoScriptConfiguration($typoScriptConfiguration);
        self::assertSame(['title desc'], $grouping->getSortings(), 'Could not set sortings from TypoScriptConfiguration');
    }
}
