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

use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationPageResolver;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ConfigurationPageResolverTest extends IntegrationTest
{

    /**
     * @test
     */
    public function canGetClosestPageIdWithActiveTemplate()
    {
        $this->importDataSetFromFixture('can_get_closest_template_page_id.xml');

        /* @var ConfigurationPageResolver $configurationPageIdResolver */
        $configurationPageIdResolver = GeneralUtility::makeInstance(ConfigurationPageResolver::class);

        $pageIdWithActiveTypoScriptConfiguration = $configurationPageIdResolver->getClosestPageIdWithActiveTemplate(4);
        self::assertSame(2, $pageIdWithActiveTypoScriptConfiguration, 'Could not resolve expected page id with active typoscript configuration');
    }
}
