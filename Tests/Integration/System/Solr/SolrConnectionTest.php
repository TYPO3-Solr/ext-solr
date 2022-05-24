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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Solr;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SolrConnectionTest
 */
class SolrConnectionTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @param ?int $pageUid defaults to 1
     * @return SolrConnection|object
     */
    protected function canFindSolrConnectionByPageAndReturn(?int $pageUid = 1)
    {
        $this->importDataSetFromFixture('SolrConnectionTest_slim_basic_sites.xml');

        /* @var $connectionManager ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);

        $messageOnNoSolrConnectionFoundException = vsprintf(
            'The SolrConnection for page with uid "%s" could not be found. Can\'t proceed with dependent tests.',
            [$pageUid]
        );
        try {
            $solrConnection = $connectionManager->getConnectionByPageId($pageUid, 0);
            self::assertInstanceOf(SolrConnection::class, $solrConnection, $messageOnNoSolrConnectionFoundException);
            return $solrConnection;
        } catch (NoSolrConnectionFoundException $exception) {
            self::fail($messageOnNoSolrConnectionFoundException);
        }
    }

    /**
     * @test
     */
    public function typo3sHttpSettingsAreRecognizedByClient()
    {
        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['connect_timeout'] = 0.0001;
        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['timeout'] = 0.0001;
        $solrConnection = $this->canFindSolrConnectionByPageAndReturn();

        $httpClientAdapter = $solrConnection->getReadService()->getClient()->getAdapter();
        $httpClientObject = $this->getInaccessiblePropertyFromObject(
            $httpClientAdapter,
            'httpClient'
        );

        $guzzleConfig = $this->getInaccessiblePropertyFromObject($httpClientObject, 'config');

        $httpSettingsIgnoredMessage = 'The client for solarium does not get TYPO3 system configuration for HTTP. ' . PHP_EOL .
            'Please check why "%s" does not taken into account or are overridden.';

        self::assertEquals(
            $GLOBALS['TYPO3_CONF_VARS']['HTTP']['connect_timeout'],
            $guzzleConfig['connect_timeout'],
            vsprintf(
                $httpSettingsIgnoredMessage,
                [
                    '$GLOBALS[\'TYPO3_CONF_VARS\'][\'HTTP\'][\'connect_timeout\']',
                ]
            )
        );
        self::assertEquals(
            $GLOBALS['TYPO3_CONF_VARS']['HTTP']['timeout'],
            $guzzleConfig['timeout'],
            vsprintf(
                $httpSettingsIgnoredMessage,
                [
                    '$GLOBALS[\'TYPO3_CONF_VARS\'][\'HTTP\'][\'timeout\']',
                ]
            )
        );
        self::assertEquals(
            $GLOBALS['TYPO3_CONF_VARS']['HTTP']['headers']['User-Agent'],
            $guzzleConfig['headers']['User-Agent'],
            vsprintf(
                $httpSettingsIgnoredMessage,
                [
                    '$GLOBALS[\'TYPO3_CONF_VARS\'][\'HTTP\'][\'headers\'][\'User-Agent\']',
                ]
            )
        );
    }
}
