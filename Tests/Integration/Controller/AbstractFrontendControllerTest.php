<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;

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

use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Typo3PageIndexer;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request as ExtbaseRequest;
use TYPO3\CMS\Extbase\Mvc\Web\Response;
use TYPO3\CMS\Frontend\Http\RequestHandler;
use TYPO3\CMS\Frontend\Page\PageGenerator;

abstract class AbstractFrontendControllerTest  extends IntegrationTest {

    /**
     * @return void
     */
    public function setUp()
    {
        $_SERVER['HTTP_HOST'] = 'testone.site';
        $_SERVER['REQUEST_URI'] = '/en/search/';


        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @param $importPageIds
     */
    protected function indexPages($importPageIds)
    {
        $existingAttributes = $GLOBALS['TYPO3_REQUEST'] ? $GLOBALS['TYPO3_REQUEST']->getAttributes() : [];
        foreach ($importPageIds as $importPageId) {
            $fakeTSFE = $this->getConfiguredTSFE($importPageId);
            $GLOBALS['TSFE'] = $fakeTSFE;
            $fakeTSFE->newCObj();

            /* @var ServerRequestFactory $serverRequestFactory */
            $serverRequestFactory = GeneralUtility::makeInstance(ServerRequestFactory::class);
            $request = $serverRequestFactory::fromGlobals();

            /* @var RequestHandler $requestHandler */
            $requestHandler = GeneralUtility::makeInstance(RequestHandler::class);
            $requestHandler->handle($request);

            /** @var $pageIndexer \ApacheSolrForTypo3\Solr\Typo3PageIndexer */
            $pageIndexer = GeneralUtility::makeInstance(Typo3PageIndexer::class, $fakeTSFE);
            $pageIndexer->indexPage();
        }

        /** @var $beUser  \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $beUser;
        if (!empty($existingAttributes)) {
            foreach ($existingAttributes as $attributeName => $attribute) {
                $GLOBALS['TYPO3_REQUEST'] = $GLOBALS['TYPO3_REQUEST']->withAttribute($attributeName, $attribute);
            }
        }
        $this->waitToBeVisibleInSolr();
    }

    /**
     * @param string $controllerName
     * @param string $actionName
     * @param string $plugin
     * @return ExtbaseRequest
     */
    protected function getPreparedRequest($controllerName = 'Search', $actionName = 'results', $plugin = 'pi_result')
    {
        /** @var ExtbaseRequest $request */
        $request = $this->objectManager->get(ExtbaseRequest::class);
        $request->setControllerName($controllerName);
        $request->setControllerActionName($actionName);

        $request->setPluginName($plugin);
        $request->setFormat('html');
        $request->setControllerExtensionName('Solr');

        return $request;
    }


    /**
     * @return Response
     */
    protected function getPreparedResponse()
    {
        /** @var $response Response */
        $response = $this->objectManager->get(Response::class);

        return $response;
    }
}
