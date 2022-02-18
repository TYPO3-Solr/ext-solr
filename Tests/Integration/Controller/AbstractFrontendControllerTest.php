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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;

use ApacheSolrForTypo3\Solr\FrontendEnvironment\Exception\Exception as SolrFrontendEnvironmentException;
use ApacheSolrForTypo3\Solr\FrontendEnvironment\Tsfe;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Typo3PageIndexer;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Exception as TestingFrameworkCoreException;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

abstract class AbstractFrontendControllerTest extends IntegrationTest
{
    /**
     * @throws NoSuchCacheException
     * @throws DBALException
     * @throws TestingFrameworkCoreException
     */
    protected function setUp(): void
    {
        $_SERVER['HTTP_HOST'] = 'testone.site';
        $_SERVER['REQUEST_URI'] = '/en/search/';

        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * Executed after each test. Empties solr and checks if the index is empty
     */
    protected function tearDown(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * @param $importPageIds
     * @throws SolrFrontendEnvironmentException
     * @throws DBALDriverException
     * @throws SiteNotFoundException
     */
    protected function indexPages($importPageIds)
    {
        /* @var Tsfe $tsfeFactory */
        $tsfeFactory = GeneralUtility::makeInstance(Tsfe::class);
        foreach ($importPageIds as $importPageId) {
            $fakeTSFE = $tsfeFactory->getTsfeByPageIdAndLanguageId($importPageId);
            $GLOBALS['TSFE'] = $fakeTSFE;
            $fakeTSFE->newCObj();

            $request = (new InternalRequest('http://testone.site/'))
                ->withPageId($importPageId);

            $response = $this->executeFrontendSubRequest($request);
            $fakeTSFE->content = (string)$response->getBody();

            /* @var $pageIndexer Typo3PageIndexer */
            $pageIndexer = GeneralUtility::makeInstance(Typo3PageIndexer::class, $fakeTSFE);
            $pageIndexer->indexPage();
        }
        $this->waitToBeVisibleInSolr();
        unset($GLOBALS['TSFE']);
    }

    /**
     * @param int $pageId
     * @return InternalRequest
     */
    protected function getPreparedRequest(int $pageId = 2022): InternalRequest
    {
        return (new InternalRequest('http://testone.site/'))->withPageId($pageId);
    }

    /**
     * @return Response
     */
    protected function getPreparedResponse()
    {
        return $this->objectManager->get(Response::class);
    }
}
