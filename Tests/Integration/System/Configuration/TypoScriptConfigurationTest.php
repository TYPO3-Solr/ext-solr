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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Configuration;

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TypoScriptConfigurationTest extends IntegrationTestBase
{
    protected bool $initializeDatabase = false;

    #[Test]
    public function testCanUsePlainValuesFromConfiguration(): void
    {
        $configuration = [
            'plugin.' => [
                'tx_solr.' => [
                    'search.' => [
                        'sorting' => 1,
                    ],
                ],
            ],
        ];

        /** @var TypoScriptConfiguration $typoScriptConfiguration */
        $typoScriptConfiguration = GeneralUtility::makeInstance(TypoScriptConfiguration::class, $configuration, 0);
        $sorting = $typoScriptConfiguration->getSearchSorting();
        self::assertTrue($sorting, 'Can not get sorting configuration from typoscript');
    }
}
